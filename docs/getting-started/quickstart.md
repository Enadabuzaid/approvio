# Quickstart — your first workflow in 5 minutes

You have the package installed and migrations run. This guide takes you from zero to a working approval flow — an `Expense` that requires manager sign-off before it can be processed.

---

## What we're building

A single-step sequential flow:

```
Expense submitted → Manager reviews → Approved or Rejected
```

One model, one workflow class, one approver, and the `SoftApproval` strategy. The model is visible immediately; the `approval_status` column reflects the current state.

---

## 1. Create the expenses table

The strategy we're using (`SoftApproval`) mirrors the approval state onto your model. This means your expenses table needs an `approval_status` column that the strategy can read and write. We'll cover the tradeoff vs. `DraftApproval` (which leaves your model untouched until the request is approved) in [Concepts](./concepts.md) — for the quickstart, add the column.

```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('manager_id')->constrained('users');
    $table->string('title');
    $table->decimal('amount', 10, 2);
    $table->string('approval_status')->default('draft');
    $table->timestamps();
});
```

---

## 2. Set up the Expense model

Add the `Approvable` trait and declare which workflow handles the `'default'` approval path.

```php
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

    protected $fillable = ['user_id', 'manager_id', 'title', 'amount', 'approval_status'];

    protected array $approvalWorkflows = [
        'default' => ExpenseApprovalWorkflow::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
```

The `$approvalWorkflows` array maps workflow names to workflow classes. `'default'` is what `requestApproval()` targets when called without an explicit workflow name.

---

## 3. Add HasApprovalActions to User

Whoever approves or rejects a request needs the `HasApprovalActions` trait. Add it to whichever model represents your actors — typically `App\Models\User`.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Enadstack\Approvio\Concerns\HasApprovalActions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApprovalActions;

    // ...
}
```

---

## 4. Write the workflow class

Create `app/Workflows/ExpenseApprovalWorkflow.php`. Extend `Workflow` and implement `define()`.

```php
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
            ->approvers(fn (Expense $expense) => [$expense->manager]);
    }
}
```

The closure receives the approvable model and returns an array or `Collection` of approver models. When a request is submitted, the engine evaluates the closure and creates one row in `approval_step_assignees` per returned model.

`$approvableType` is the only required property. The slug stored in `approval_requests.workflow_slug` defaults to the kebab-cased class basename — `expense-approval-workflow` in this case.

---

## 5. Submit the expense for approval

Call `requestApproval()` on the model. This creates an `ApprovalRequest`, persists it inside a database transaction, and activates the first step.

```php
// In a controller or action:
$expense = Expense::create([
    'user_id'    => auth()->id(),
    'manager_id' => $managerId,
    'title'      => 'Laptop stand',
    'amount'     => 89.00,
]);

$approvalRequest = $expense->requestApproval();
```

After this call:

- `$approvalRequest->status` is `RequestStatus::Pending`
- `$expense->refresh()->approval_status` is `'pending'`

`requestApproval()` uses the authenticated user as the requester and the `'default'` workflow by name. Named arguments let you override either:

```php
// Use a named workflow:
$approvalRequest = $expense->requestApproval(workflow: 'finance-review');

// Supply an explicit requester — useful in queued jobs without an auth context:
$approvalRequest = $expense->requestApproval(requester: $submitter);

// Both:
$approvalRequest = $expense->requestApproval(
    workflow:  'finance-review',
    requester: $submitter,
    context:   ['source' => 'api'],
);
```

---

## 6. Find pending approvals

The manager queries for requests waiting on their action:

```php
$pending = $manager->pendingApprovals();
// Returns a Collection of ApprovalRequest models assigned to this manager.

foreach ($pending as $approvalRequest) {
    $expense = $approvalRequest->approvable; // the Expense model
    $step    = $approvalRequest->currentStep();
}
```

---

## 7. Approve or reject

```php
// Approve:
$manager->approve($approvalRequest);

// Approve with a comment:
$manager->approve($approvalRequest, comment: 'Good to go — receipt attached.');

// Reject:
$manager->reject($approvalRequest, comment: 'Missing receipt. Please resubmit.');
```

After `approve()`:

- `$approvalRequest->status` → `RequestStatus::Approved`
- `$expense->refresh()->approval_status` → `'approved'`
- One new row appended to `approval_actions`

After `reject()`:

- `$approvalRequest->status` → `RequestStatus::Rejected`
- `$expense->refresh()->approval_status` → `'rejected'`

---

## What the database contains

After a full submit → approve cycle, four tables hold data:

**`approval_requests`** — one row per lifecycle

| id | workflow_slug             | status   | current_step_index | submitted_at        |
|----|---------------------------|----------|--------------------|---------------------|
| 1  | expense-approval-workflow | approved | 0                  | 2026-05-07 10:00:00 |

**`approval_request_steps`** — one row per step

| id | request_id | name           | step_index | status   | type       |
|----|------------|----------------|------------|----------|------------|
| 1  | 1          | manager-review | 0          | approved | sequential |

**`approval_step_assignees`** — one row per approver slot

| id | step_id | assignee_type   | assignee_id | status   | assigned_via |
|----|---------|-----------------|-------------|----------|--------------|
| 1  | 1       | App\Models\User | 42          | approved | direct       |

**`approval_actions`** — append-only audit log (submit + approve = two rows)

| id | request_id | type      | actor_type      | actor_id | created_at          |
|----|------------|-----------|-----------------|----------|---------------------|
| 1  | 1          | submitted | App\Models\User | 7        | 2026-05-07 10:00:00 |
| 2  | 1          | approved  | App\Models\User | 42       | 2026-05-07 10:05:00 |

`approval_actions` is never updated or deleted — every state change appends a new row.

---

## Checking state

The `ApprovalRequest` model has helper methods for the common checks:

```php
$approvalRequest->isPending();   // bool
$approvalRequest->isApproved();  // bool
$approvalRequest->isRejected();  // bool
```

The `Approvable` trait exposes shortcuts on the model:

```php
$expense->hasPendingApproval();          // bool
$expense->pendingApprovalRequest();      // ?ApprovalRequest
$expense->latestApprovalRequest();       // ?ApprovalRequest
```

---

## What's next

- **[Concepts](./concepts.md)** — the mental model behind requests, steps, assignees, and strategies. Read this before building anything non-trivial.
- **[Parallel steps](../workflows/parallel-steps.md)** — require finance and legal to both approve simultaneously, with quorum rules (`any`, `all`, `n_of_m`).
- **[Conditional steps](../workflows/conditional-steps.md)** — add a CFO step only when the expense exceeds a threshold.

---

**Previous:** [Installation](./installation.md) | **Next:** [Concepts](./concepts.md)
