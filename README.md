# Approvio

> Flexible approval workflows for Laravel. Make any model approvable with a trait.

[![Tests](https://github.com/enadstack/approvio/actions/workflows/tests.yml/badge.svg)](https://github.com/enadstack/approvio/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/enadstack/approvio/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/enadstack/approvio/actions/workflows/static-analysis.yml)
[![Latest Version](https://img.shields.io/packagist/v/enadstack/approvio)](https://packagist.org/packages/enadstack/approvio)
[![Total Downloads](https://img.shields.io/packagist/dt/enadstack/approvio)](https://packagist.org/packages/enadstack/approvio)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/packagist/php-v/enadstack/approvio)](https://packagist.org/packages/enadstack/approvio)

Approvio is a Laravel package that adds full approval workflows to any Eloquent
model. Submit, approve, reject, audit. Multi-step. Multi-tenant. Strategy-based.
Headless.

> **Status:** v0.1 alpha. Public API may shift slightly before 1.0. Pin a version.

---

## Why Approvio

Every non-trivial Laravel app eventually needs approvals — leave requests,
expense submissions, document edits, content publishing, user invitations,
contract changes. Most apps reinvent the wheel each time: a `status` enum here,
an `approved_by` column there, hard-coded approver logic somewhere else.

Approvio replaces all of that with one trait and a workflow class. You get:

- **Any-model approvable** via the `Approvable` trait.
- **Multi-step workflows** declared in plain PHP (DB-defined coming in 0.3).
- **Two strategies** out of the box: keep the model visible (`SoftApproval`)
  or buffer the changes (`DraftApproval`).
- **Tenant-aware** — works in single-DB, multi-DB, or non-tenant apps.
- **Append-only audit log** of every action with actor, comment, IP, and timestamps.
- **Events** for everything — wire up your own notifications, webhooks, or analytics.
- **State machine** guards: no more "we approved an already-rejected request" bugs.

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Installation

```bash
composer require enadstack/approvio
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="approvio-migrations"
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="approvio-config"
```

## Quickstart

A 60-second walkthrough using a classic example: an expense that needs manager
approval before reimbursement.

### 1. Make the model approvable

```php
use Enadstack\Approvio\Concerns\Approvable;
use Enadstack\Approvio\Strategies\SoftApproval;

class Expense extends Model
{
    use Approvable;

    protected string $approvalStrategy = SoftApproval::class;

    protected array $approvalWorkflows = [
        'submission' => \App\Approvals\ExpenseSubmissionWorkflow::class,
    ];
}
```

If you use `SoftApproval`, add an `approval_status` column to the model's table:

```php
$table->string('approval_status')->default('draft');
```

### 2. Add the actor trait to your User

```php
use Enadstack\Approvio\Concerns\HasApprovalActions;

class User extends Authenticatable
{
    use HasApprovalActions;
}
```

### 3. Define a workflow

```php
namespace App\Approvals;

use App\Models\Expense;
use App\Models\User;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseSubmissionWorkflow extends Workflow
{
    protected string $approvableType = Expense::class;
    protected ?string $slug = 'submission';

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('manager-review')
            ->approvers(fn (Expense $expense) => [$expense->user->manager]);

        $flow->step('finance-review')
            ->approvers(fn () => User::where('role', 'finance')->get());
    }
}
```

### 4. Use it

```php
// Submit
$expense = Expense::create([...]);
$request = $expense->requestApproval('submission');

// The current approver acts on it
$request = $manager->approve($request, 'Looks good.');
// or
$request = $manager->reject($request, 'Need receipts.');

// Find what's waiting on me
$user->pendingApprovals();

// Inspect history
$expense->approvalRequests;
$request->actions; // full audit trail
```

That's it. The state machine handles the transitions, the audit log records
everything, and events fire for every step so you can wire up notifications.

---

## Parallel steps

By default every step is sequential — one approver acts, the step advances.
Declare `.parallel()` to activate **all assigned approvers simultaneously**
and configure how many must act before the step completes.

```php
public function define(WorkflowBuilder $flow): void
{
    // All three must approve (quorum: all).
    $flow->step('joint-review')
        ->approvers(fn () => User::whereIn('role', ['manager', 'finance', 'legal'])->get())
        ->parallel()
        ->quorum('all');

    // Any single approval is enough (quorum: any).
    $flow->step('peer-review')
        ->approvers(fn () => User::where('role', 'peer')->get())
        ->parallel()
        ->quorum('any');

    // 2 of 3 must approve (quorum: n_of_m).
    $flow->step('committee-vote')
        ->approvers(fn () => User::where('role', 'committee')->get())
        ->parallel()
        ->quorum('n_of_m', 2);
}
```

**Quorum rules**

| Rule | Description |
|------|-------------|
| `any` | First approval satisfies the step (default for sequential steps). |
| `all` | Every assigned approver must approve. |
| `n_of_m` | A specific number N must approve (pass N as second argument). |

**Rejection policy**: any single rejection on a parallel step terminates the
entire request immediately, regardless of how many approvals have already been
received. There is no partial-approval recovery — cancel the request and
resubmit if the rejection was in error.

---

## Conditional steps

Skip a step entirely when it does not apply to a given request by adding
`.when(Closure)`. The closure is evaluated **at activation time against the
live model** — not against the snapshot taken at submit time.

```php
public function define(WorkflowBuilder $flow): void
{
    $flow->step('manager-review')
        ->approvers(fn (Expense $expense) => [$expense->user->manager]);

    // CFO review is only required for large expenses.
    $flow->step('cfo-review')
        ->approvers(fn () => User::where('role', 'cfo')->get())
        ->when(fn (Expense $expense) => $expense->amount > 10000);

    $flow->step('final-sign-off')
        ->approvers(fn () => User::where('role', 'director')->get());
}
```

When the condition returns `false`:
- The step's status is set to `skipped`.
- A `skipped` audit action is logged.
- `StepSkipped` event fires.
- The engine immediately advances to the next step.
- If all remaining steps are skipped, the request is marked `approved`.

**Live model vs snapshot**: the closure receives the model as it exists at
activation time. If the expense amount was changed between submit and the
step's activation, the condition sees the new value. To opt into snapshot
semantics, read `$request->snapshot` inside the closure:

```php
->when(fn (Expense $expense, ApprovalRequest $request) =>
    ($request->snapshot['amount'] ?? 0) > 10000
)
```

`.when()` composes with `.parallel()` and `.quorum()` — a parallel step
is skipped as a whole if its condition is false.

---

## Relationship-based approvers

Use `.relation()` to resolve approvers by walking a dot-notation chain of
Eloquent relations on the approvable model. The chain is evaluated against
the **live model** at the moment the step activates.

```php
$flow->step('owner-review')
    ->relation('user');              // $expense->user  (BelongsTo)

$flow->step('head-of-department')
    ->relation('user.department.head');  // multi-hop chain

$flow->step('team-members')
    ->relation('team.members');     // chain ending in a Collection
```

If any segment in the chain returns `null`, or a non-Model/non-Collection
value, the step is activated with zero assignees (no exception thrown).
Combine with `.when()` to skip such steps explicitly when the chain is known
to be optional.

The `approval_step_assignees.assigned_via` column is set to `'relationship'`
for relation-resolved assignees.

---

## Spatie Permission integration

Use `.role()` on a step to resolve all users holding a given Spatie role at
activation time. The package is an opt-in — it is not a hard dependency.

```bash
composer require spatie/laravel-permission
```

```php
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseWorkflow extends Workflow
{
    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('finance-review')
            ->role('finance');            // all users with role 'finance'

        $flow->step('cfo-approval')
            ->role('cfo', 'web');         // role scoped to the 'web' guard
    }
}
```

The `approval_step_assignees.assigned_via` column is set to `'role'` for
role-resolved assignees so you can distinguish them from directly-assigned
users in queries and audit views.

Configure which Eloquent model is queried for role resolution:

```php
// config/approvio.php
'user_model' => env('APPROVIO_USER_MODEL', App\Models\User::class),
```

If `spatie/laravel-permission` is not installed and `.role()` is called, a
`MissingDependencyException` is thrown with the exact `composer require` command.

---

## Delegation

An assignee can hand their slot to another user with `delegate()`. Delegation is
**one level only** — a delegate cannot delegate further.

```php
// Actor delegates to a deputy with an optional comment.
$manager->delegate($request, $deputy, 'OOO this week');
```

After delegating:

- The original assignee's status becomes `Delegated` and they can no longer approve or reject.
- A new assignee row is created for the deputy with `assigned_via = 'delegation'`.
- For `All` quorum steps, `Delegated` rows are excluded from the denominator — only the active (non-delegated) slots count.

Delegation is **not revocable**. If a request needs to be restarted after an erroneous delegation, cancel it and resubmit.

---

## Escalation and deadlines

Steps can declare a time limit and an escalation target. When a step's deadline passes,
`approvio:escalate` either promotes a new assignee or expires the request.

```php
$flow->step('manager-review')
    ->approvers(fn ($expense) => [$expense->user->manager])
    ->deadline(48)                           // hours
    ->escalateTo(fn ($expense) => [$expense->user->manager->manager]);
```

Run the command on a schedule (e.g. every minute in `App\Console\Kernel`):

```bash
php artisan approvio:escalate
```

When a step's deadline passes:

- **If `escalateTo` resolves non-empty users**: original Pending assignees are marked `Escalated`
  (they can no longer act), and the escalation target(s) are added as new `Pending` assignees.
  Quorum re-evaluates excluding `Escalated` rows.
- **If no escalation target** (or it resolves empty): the step and the entire request are
  transitioned to `expired`.

The command also scans `approval_requests.expires_at`: any non-terminal request whose
`expires_at` has passed is expired directly.

Trying to `approve()` or `reject()` an expired request throws `InvalidStateTransitionException`.

---

## Resubmit

When a request is rejected, the submitter can open a new request tied to the original
via `resubmit()`. The new request goes through the full workflow again and the original
is linked via `parent_request_id`.

```php
// On the approvable model — targets the most recent rejected request
$newRequest = $expense->resubmit($submitter);

// Carry forward the rejection comment / make corrections
$newRequest = $expense->resubmit($submitter, context: ['note' => 'Reduced cost']);

// DraftApproval: override the proposed changes on resubmit
$newRequest = $doc->resubmit(changes: ['title' => 'Corrected title', 'body' => 'Fixed body']);

// Or target a specific rejected request directly
$newRequest = Approvio::resubmit($rejectedRequest, $submitter);
```

Rules:

- Only `Rejected` requests can be resubmitted.
- If the original already has an active child request, a second resubmit throws
  `InvalidStateTransitionException`.
- The `parent_request_id` on the new request points to the original.
- `DraftApproval` workflows: `pending_changes` from the parent are carried forward
  automatically unless you pass explicit `$changes`.
- A `Resubmitted` audit action is appended to the **original** request's log.
- `RequestResubmitted` event fires with both request instances.

---

## Strategies

Approvio ships two strategies. Pick per model based on risk tolerance.

### `SoftApproval` — model exists, flagged

The model row is created and visible from day one. Approvio just flips an
`approval_status` column between `pending`, `approved`, and `rejected`.

Best for: blog posts, profile updates, user-generated content where pending
visibility is acceptable.

### `DraftApproval` — buffered changes

The proposed changes are buffered on the approval request and only applied to
the model on approval. The live row stays untouched.

```php
$document->requestApprovalFor(
    ['title' => 'New title', 'body' => 'New body'],
    'edit'
);
// $document->title is unchanged until an approver approves the request.
```

Best for: contracts, financial records, medical data, anything where the
pending version must not be confused for current truth.

You can switch strategies per model (`protected string $approvalStrategy = ...`)
or globally in `config/approvio.php`.

## Multi-tenancy

Approvio's tenant logic is pluggable. Pick the resolver that matches your stack:

```php
// config/approvio.php

'tenant_resolver' => \Enadstack\Approvio\Resolvers\Tenants\NullTenantResolver::class,
//                or ColumnTenantResolver::class for shared-DB-with-tenant_id apps
```

Every approval request, step, assignee, and action is scoped to the resolved
tenant. Adapters for `stancl/tenancy` and `spatie/laravel-multitenancy` are
planned for v0.3 — implement `TenantResolver` to ship your own today.

## Audit log

Every action writes an immutable `ApprovalAction` row:

```php
$request->actions->each(function ($action) {
    // $action->actor, $action->action, $action->comment,
    // $action->ip_address, $action->user_agent, $action->created_at
});
```

The table has no `updated_at`. Rows are never updated and never deleted
(except by cascade when the parent request is hard-deleted). This is what
makes the package safe for compliance contexts.

## Events

| Event                 | When it fires                                    |
| --------------------- | ------------------------------------------------ |
| `ApprovalRequested`   | A new request is submitted.                      |
| `StepActivated`       | A step becomes active, awaiting approvers.       |
| `StepApproved`        | A step is approved.                              |
| `StepRejected`        | A step is rejected.                              |
| `ApprovalCompleted`   | All steps approved — request is fully approved.  |
| `ApprovalRejected`    | The request is rejected at any step.             |
| `ApprovalCancelled`   | The request is cancelled.                        |
| `RequestDelegated`    | An assignee delegates to another user.           |
| `StepEscalated`       | An overdue step escalates to a new assignee.     |
| `ApprovalExpired`     | A request expires due to a missed deadline.      |
| `StepSkipped`         | A conditional step is skipped.                   |
| `RequestResubmitted`  | A rejected request is resubmitted.               |

Listen to these in your `EventServiceProvider` to send notifications, sync to
external systems, fire webhooks, etc.

## Configuration reference

Everything in `config/approvio.php` is documented inline. The key knobs:

```php
'default_strategy' => SoftApproval::class,   // or DraftApproval::class
'tenant_resolver'  => NullTenantResolver::class,
'tenant.column'    => 'tenant_id',
'workflow_sources' => ['code'],              // 'database' coming in v0.3
'audit.capture_ip' => true,
```

## Upgrading from v0.1 to v0.2

### Breaking change — custom `ApproverResolver` implementations

If you implemented `ApproverResolver` directly in your application before v0.2, you must add one new method:

```php
public function assignedVia(): string
{
    return 'my-custom-resolver'; // any string label
}
```

Applications that only **use** the built-in resolvers (`DirectUserResolver`, `RoleResolver`,
`RelationshipResolver`) require no changes.

### Everything else is opt-in

All v0.2 features are additive. Existing v0.1 code compiles and runs without modification:

- **Parallel steps** — add `.parallel()` to new steps only. Existing sequential steps are unaffected.
- **Quorum rules** — default quorum is `any` on parallel steps, unchanged for sequential.
- **Conditional steps** — add `.when(fn($model) => bool)` to new steps only.
- **Role-based approvers** — opt-in via `.role('role-name')`. Requires `spatie/laravel-permission`.
- **Relationship-based approvers** — opt-in via `.relation('user.manager')`.
- **Delegation** — `HasApprovalActions::delegate()` is new; no existing method changed.
- **Escalation/deadlines** — opt-in via `.deadline(hours: N)` and `.escalateTo(...)`.
- **Resubmit** — `Approvable::resubmit()` is new; no existing method changed.
- **New migration** — `parent_request_id` nullable FK on `approval_requests`. Run `php artisan migrate` after upgrading.

## Roadmap

- **0.2** — Parallel steps, quorum rules (any/all/N-of-M), conditional steps
  (`when()`), Spatie Permission integration, relationship-based approvers,
  delegation, escalation, deadlines, resubmit.
- **0.3** — DB-defined workflows, Stancl & Spatie Multitenancy adapters,
  per-tenant workflow overrides.
- **0.4** — `approvio-filament` companion package, `approvio-inertia-vue`
  companion package, default notification classes.
- **1.0** — Stable API, full docs site, example apps repo.

## Contributing

PRs welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
