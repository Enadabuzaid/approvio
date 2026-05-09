# Expense approval recipe

A complete walkthrough of building a multi-tier expense approval workflow. The reader follows along in a fresh Laravel app and ends with working code. Every code block is runnable in sequence.

---

## The scenario

A SaaS company processes employee expenses. Approval authority scales with the amount:

- **Under $1,000** — the submitter's direct manager approves. One step, one approver, done.
- **$1,000–$10,000** — manager first, then any member of the finance team. Finance approval runs in parallel across the whole team; the first approval clears the step.
- **Over $10,000** — all three tiers: manager, then finance, then CFO and CEO must both sign off simultaneously.

Rejections at any tier terminate the request immediately. A manager's rejection on a $50,000 expense does not pass to finance — the request is over.

The finance team is managed as a Spatie role. As people join or leave the team, future requests automatically reflect the current membership without workflow code changes. Manager relationships are structural — stored as a `manager_id` foreign key on the `users` table. The CFO and CEO are specific users associated with the `Company` model.

---

## Domain models and migrations

Three models: `Company`, `User`, and `Expense`.

```php
// database/migrations/..._create_companies_table.php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('cfo_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('ceo_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});

// database/migrations/..._create_expenses_table.php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->string('description');
    $table->string('approval_status')->default('draft');
    $table->timestamps();
});
```

Add `company_id`, `manager_id`, and `department` to your existing `users` migration:

```php
$table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
$table->string('department')->nullable();
```

```php
// app/Models/Company.php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $fillable = ['name', 'cfo_id', 'ceo_id'];

    public function cfo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cfo_id');
    }

    public function ceo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_id');
    }
}
```

```php
// app/Models/User.php
<?php

declare(strict_types=1);

namespace App\Models;

use Enadstack\Approvio\Concerns\HasApprovalActions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApprovalActions;
    use HasRoles;

    protected $fillable = ['name', 'email', 'password', 'company_id', 'manager_id', 'department'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
```

```php
// app/Models/Expense.php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Workflows\ExpenseApprovalWorkflow;
use Enadstack\Approvio\Concerns\Approvable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Approvable;

    protected $fillable = ['user_id', 'amount', 'currency', 'description', 'approval_status'];

    protected $casts = ['amount' => 'decimal:2'];

    protected array $approvalWorkflows = [
        'default' => ExpenseApprovalWorkflow::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## Spatie setup

Full installation is covered in [Role-based approvers](../approvers/roles-via-spatie.md). For this recipe, create the `finance` role and assign your finance team:

```php
// database/seeders/RoleSeeder.php
use Spatie\Permission\Models\Role;

$finance = Role::findOrCreate('finance', 'web');

User::where('department', 'finance')
    ->each(fn (User $user) => $user->assignRole($finance));
```

The `finance` role must exist in the `roles` table before any request is submitted against a workflow that references it.

---

## The workflow class

```php
// app/Workflows/ExpenseApprovalWorkflow.php
<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Models\Expense;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseApprovalWorkflow extends Workflow
{
    protected string $approvableType = Expense::class;

    protected function define(WorkflowBuilder $flow): void
    {
        $flow->step('manager-review')
            ->relation('user.manager')
            ->deadline(hours: 24)
            ->escalateTo(fn (Expense $expense) => array_filter([
                $expense->user?->company?->cfo,
            ]));

        $flow->step('finance-review')
            ->role('finance')
            ->parallel()
            ->quorum('any')
            ->deadline(hours: 48)
            ->escalateTo(fn (Expense $expense) => array_filter([
                $expense->user?->company?->cfo,
            ]))
            ->when(fn (Expense $expense) => $expense->amount > 1_000);

        $flow->step('executive-signoff')
            ->approvers(fn (Expense $expense) => array_filter([
                $expense->user?->company?->cfo,
                $expense->user?->company?->ceo,
            ]))
            ->parallel()
            ->quorum('all')
            ->deadline(hours: 72)
            ->escalateTo(fn (Expense $expense) => array_filter([
                $expense->user?->company?->cfo,
            ]))
            ->when(fn (Expense $expense) => $expense->amount > 10_000);
    }
}
```

**Step 0 — manager-review.**
`->relation('user.manager')` walks `$expense->user` then `->manager` at activation time via `RelationshipResolver`. If either segment is null — the user has no manager set — the resolver returns an empty collection without throwing. The step activates with zero assignees and stalls. The 24-hour deadline fires and escalates to the CFO, keeping the request moving. See [Zero assignees resolved](../workflows/parallel-steps.md#zero-assignees-resolved) for the stall behavior.

**Step 1 — finance-review.**
`->role('finance')` queries every user currently holding the Spatie `finance` role at the moment this step activates — not at submission time. `->quorum('any')` means the first finance team member to approve clears the step. The `->when()` condition is evaluated at activation time against the live `Expense` model: if the amount was reduced below $1,000 between submission and manager approval, this step is skipped and the resolver never runs.

**Step 2 — executive-signoff.**
The closure returns CFO and CEO in an array. `array_filter()` is required here: `DirectUserResolver` does not filter null values before passing elements to the engine, and calling `->getMorphClass()` on `null` throws a fatal error. With `array_filter()`, a missing CFO or CEO is removed; if both are null, the step gets zero assignees and stalls (the 72-hour deadline applies). `->quorum('all')` requires every resolved approver to sign off — if both are present, both must approve.

`array_filter()` is also applied in every `escalateTo` closure for the same reason — the escalation target can be null if the company has no CFO set.

---

## The controller

```php
// app/Http/Controllers/ExpenseController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'currency'    => ['required', 'string', 'size:3'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $expense = Expense::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        $approvalRequest = $expense->requestApproval(
            context: ['ip' => $request->ip()],
        );

        return response()->json([
            'expense'            => $expense,
            'approval_request'   => $approvalRequest->id,
        ], 201);
    }
}
```

```php
// app/Http/Controllers/ApprovalController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(): JsonResponse
    {
        $pending = auth()->user()->pendingApprovals();

        return response()->json($pending->map(fn (ApprovalRequest $req) => [
            'id'      => $req->id,
            'step'    => $req->currentStep()?->step_name,
            'expense' => $req->approvable,
        ]));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $user    = auth()->user();
        $pending = $user->pendingApprovals();

        if (! $pending->contains($approvalRequest)) {
            return response()->json(['error' => 'Not assigned to this request.'], 403);
        }

        $result = $user->approve($approvalRequest, $request->input('comment'));

        return response()->json(['status' => $result->status->value]);
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $user    = auth()->user();
        $pending = $user->pendingApprovals();

        if (! $pending->contains($approvalRequest)) {
            return response()->json(['error' => 'Not assigned to this request.'], 403);
        }

        $result = $user->reject($approvalRequest, $request->input('comment'));

        return response()->json(['status' => $result->status->value]);
    }
}
```

The `pendingApprovals()` check before calling `approve()` or `reject()` is a first-layer guard that returns a 403 with a controlled JSON body. The engine also validates assignment internally and throws `UnauthorizedActionException` for unauthorized actors — even without the check, unauthorized calls are blocked. The explicit check here prevents an unhandled exception from reaching your error handler.

---

## End-to-end test

```php
// tests/Feature/ExpenseApprovalTest.php
<?php

use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Enadstack\Approvio\Enums\StepStatus;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('finance', 'web');
});

it('routes a small expense through manager only', function () {
    $company  = Company::create(['name' => 'Acme']);
    $manager  = User::factory()->create(['company_id' => $company->id]);
    $employee = User::factory()->create([
        'company_id' => $company->id,
        'manager_id' => $manager->id,
    ]);

    $expense = Expense::create([
        'user_id'     => $employee->id,
        'amount'      => 50.00,
        'currency'    => 'USD',
        'description' => 'Coffee supplies',
    ]);

    $request = $expense->requestApproval();
    expect($request->isPending())->toBeTrue();

    $manager->approve($request);
    $request->refresh();

    expect($request->isApproved())->toBeTrue();

    $steps = $request->steps()->get();
    expect($steps[0]->status)->toBe(StepStatus::Approved); // manager-review
    expect($steps[1]->status)->toBe(StepStatus::Skipped);  // finance-review  (amount <= 1000)
    expect($steps[2]->status)->toBe(StepStatus::Skipped);  // executive-signoff (amount <= 10000)
});

it('routes a medium expense through manager then any finance member', function () {
    $company  = Company::create(['name' => 'Acme']);
    $manager  = User::factory()->create(['company_id' => $company->id]);
    $finance1 = User::factory()->create(['company_id' => $company->id]);
    $employee = User::factory()->create([
        'company_id' => $company->id,
        'manager_id' => $manager->id,
    ]);

    $finance1->assignRole('finance');

    $expense = Expense::create([
        'user_id'     => $employee->id,
        'amount'      => 5_000.00,
        'currency'    => 'USD',
        'description' => 'Conference tickets',
    ]);

    $request = $expense->requestApproval();

    $manager->approve($request);
    $request->refresh();
    expect($request->isPending())->toBeTrue(); // finance step now active

    $finance1->approve($request);
    $request->refresh();

    expect($request->isApproved())->toBeTrue();

    $steps = $request->steps()->get();
    expect($steps[0]->status)->toBe(StepStatus::Approved); // manager-review
    expect($steps[1]->status)->toBe(StepStatus::Approved); // finance-review
    expect($steps[2]->status)->toBe(StepStatus::Skipped);  // executive-signoff (amount <= 10000)
});

it('routes a large expense through all three tiers', function () {
    $company  = Company::create(['name' => 'Acme']);
    $cfo      = User::factory()->create(['company_id' => $company->id]);
    $ceo      = User::factory()->create(['company_id' => $company->id]);
    $manager  = User::factory()->create(['company_id' => $company->id]);
    $finance1 = User::factory()->create(['company_id' => $company->id]);
    $employee = User::factory()->create([
        'company_id' => $company->id,
        'manager_id' => $manager->id,
    ]);

    $finance1->assignRole('finance');
    $company->update(['cfo_id' => $cfo->id, 'ceo_id' => $ceo->id]);

    $expense = Expense::create([
        'user_id'     => $employee->id,
        'amount'      => 50_000.00,
        'currency'    => 'USD',
        'description' => 'Server infrastructure upgrade',
    ]);

    $request = $expense->requestApproval();

    $manager->approve($request);
    $request->refresh();
    expect($request->isPending())->toBeTrue();

    $finance1->approve($request);
    $request->refresh();
    expect($request->isPending())->toBeTrue(); // executive step now active

    // CFO approves — quorum('all') still waiting for CEO.
    $cfo->approve($request);
    $request->refresh();
    expect($request->isPending())->toBeTrue();

    // CEO approves — quorum met, request completes.
    $ceo->approve($request);
    $request->refresh();

    expect($request->isApproved())->toBeTrue();

    $steps = $request->steps()->get();
    expect($steps[0]->status)->toBe(StepStatus::Approved); // manager-review
    expect($steps[1]->status)->toBe(StepStatus::Approved); // finance-review
    expect($steps[2]->status)->toBe(StepStatus::Approved); // executive-signoff
});
```

Each test calls `$request->refresh()` after every approval because the engine writes all state changes inside its own database transaction. The local PHP variable holds the pre-approval snapshot until refreshed.

The third test creates the company first with null `cfo_id` and `ceo_id`, creates all users with `company_id` set, then updates the company to assign CFO and CEO — a standard top-down seeding order without patching records created later.

---

## What you have now

A three-tier approval workflow built with four v0.2 features working in combination:

- **Relationship-based resolver** (`->relation('user.manager')`) — manager is determined structurally at activation time, not hardcoded
- **Role-based resolver** (`->role('finance')`) — finance team membership drives step assignment; no code changes when the team changes
- **Conditional steps** (`->when()`) — tiers activate automatically based on amount; the workflow definition handles all cases
- **Parallel steps with quorum** (`->parallel()->quorum('all')`) — CFO and CEO sign off simultaneously, in any order

Every step has a 24–72 hour deadline and an escalation path to the CFO. A request cannot stall indefinitely unless the CFO position itself is unset on the company.

---

## Where to go from here

- **[Delegation](../advanced/delegation.md)** — let a CFO delegate to a deputy while they are out of office
- **[Multi-tenancy](../advanced/multi-tenancy.md)** — scope approval records per company when a single app instance serves multiple tenants
- **[Public API reference](../reference/public-api.md)** — complete method signatures for `Approvable`, `HasApprovalActions`, and `ApprovalRequest`

---

**Previous:** [Role-based approvers](../approvers/roles-via-spatie.md) | **Next:** [Public API reference](../reference/public-api.md)

---

**Verification summary**

| Behavioral claim | Verified at |
|---|---|
| `requestApproval(string $workflow, ?Model $requester, array $context, array $changes)` signature | `Approvable.php:43-48` |
| `approve(ApprovalRequest $request, ?string $comment)` signature | `HasApprovalActions.php:64-67` |
| `reject(ApprovalRequest $request, ?string $comment)` signature | `HasApprovalActions.php:69-72` |
| `pendingApprovals(): Collection<int, ApprovalRequest>` | `HasApprovalActions.php:49-62` |
| `currentStep(): ?ApprovalRequestStep` | `ApprovalRequest.php:91-96` |
| `isPending(): bool` calls `status->isActive()` — true for both `Pending` and `InReview` | `ApprovalRequest.php:98-101` |
| `isApproved(): bool`, `isRejected(): bool` | `ApprovalRequest.php:103-111` |
| `->relation(string $chain)` delegates to `RelationshipResolver`; returns empty collection when any segment is null | `WorkflowBuilder.php:122-125`; `RelationshipResolver.php:50-53` |
| `->deadline(int $hours)` — parameter name is `$hours`; `deadline(hours: N)` is valid PHP 8 named argument | `WorkflowBuilder.php:136-141` |
| `->escalateTo(Closure $resolver)` — accepts only `Closure` | `WorkflowBuilder.php:143-148` |
| `DirectUserResolver::resolve()` wraps iterable in `collect()` without filtering nulls; `getMorphClass()` on null throws at engine line 404 | `DirectUserResolver.php:33-39`; `ApprovalEngine.php:401-409` |
| `StepStatus` cases: `Approved = 'approved'`, `Skipped = 'skipped'`, `Rejected = 'rejected'`, `Active = 'active'`, `Pending = 'pending'` | `StepStatus.php:9-14` |
| `steps()` — `HasMany<ApprovalRequestStep>` ordered by `step_index` ascending | `ApprovalRequest.php:67-71` |
