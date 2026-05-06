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

## Roadmap

- **0.2** — Parallel steps, quorum rules (any/all/N-of-M), conditional steps
  (`when()`), Spatie Permission integration, relationship-based approvers,
  delegation, escalation, deadlines.
- **0.3** — DB-defined workflows, Stancl & Spatie Multitenancy adapters,
  per-tenant workflow overrides.
- **0.4** — `approvio-filament` companion package, `approvio-inertia-vue`
  companion package, default notification classes.
- **1.0** — Stable API, full docs site, example apps repo.

## Contributing

PRs welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
