# Role-based approvers via Spatie Permission

A closure-based approver names specific users: `fn ($expense) => [$expense->manager]`. This works when the approver is determined structurally — a relationship on the model. It breaks down when the approver pool is membership-based: "any user who currently holds the finance role." As the team changes, the workflow should reflect it without code changes.

`RoleResolver` bridges Approvio's resolver contract and `spatie/laravel-permission`'s role system. It runs the Spatie role query at step activation time, so whoever holds the role when the step activates is who gets assigned.

---

## Prerequisites

`spatie/laravel-permission` is a soft dependency — not required to install Approvio, but required to use `->role()`. Install it:

```bash
composer require spatie/laravel-permission
```

Publish and run Spatie's migration, add the `HasRoles` trait to your user model, and create the roles your workflow will reference. Spatie's own [installation guide](https://spatie.be/docs/laravel-permission/v6/installation-laravel) covers these steps. Approvio only needs two things from that setup:

1. The user model configured in `config/approvio.php` (or `APPROVIO_USER_MODEL`) must use the `HasRoles` trait
2. The roles referenced in `->role()` must exist in the `roles` table before any request is submitted against a workflow that uses them

---

## The `.role()` builder method

```php
protected function define(WorkflowBuilder $flow): void
{
    $flow->step('finance-review')
        ->role('finance');
}
```

Signature (`WorkflowBuilder.php:131-134`):

```php
public function role(string $roleName, ?string $guardName = null): self
```

- `$roleName` — the name of the Spatie role, exactly as stored in the `roles.name` column
- `$guardName` — optional; defaults to null, which uses Spatie's default guard resolution

`->role('finance')` is equivalent to:

```php
->approvers(new RoleResolver('finance'))
```

The guard parameter matters when your application has multiple guards (e.g. `web` and `api`) and users in each guard can hold roles with the same name. Pass the guard name to scope the query:

```php
$flow->step('finance-review')
    ->role('finance', 'web');
```

---

## How RoleResolver resolves users

At step activation time, `RoleResolver::resolve()` runs:

```php
$userModel = config('approvio.user_model', 'App\Models\User');
return $userModel::role($this->roleName, $this->guardName)->get();
```

This is Spatie's `role()` model scope, which queries `model_has_roles` to find every user currently assigned that role. The returned `Collection` flows into the engine the same way any other resolver's output does — one `approval_step_assignees` row per user.

The resolver runs at activation time, not at submission time. This means:
- Users added to the role between submission and activation are included
- Users removed from the role between submission and activation are excluded
- The workflow requires no change when your finance team changes

The `assigned_via` column for these assignees contains `'role'`.

---

## MissingDependencyException

`RoleResolver`'s constructor throws `MissingDependencyException` if `spatie/laravel-permission` is not installed:

```
Feature [RoleResolver] requires [spatie/laravel-permission].
Install it with: composer require spatie/laravel-permission
```

This exception fires when the workflow class is first resolved — which happens inside `requestApproval()`, before any database rows are written. The submit transaction rolls back and the caller receives the exception.

The check is in the constructor, not in `resolve()`. This means the error is raised at the point when the `RoleResolver` object is created (during workflow definition building), not at the moment approvers are queried. In practice: call `$expense->requestApproval()` on a workflow using `->role()` without Spatie installed and you get a clear exception immediately.

---

## Edge cases

### Role has no users assigned

If the role exists but no users hold it, `->get()` returns an empty `Collection`. The engine creates no assignee rows, the step activates with `status = active`, and the request stalls in `InReview` indefinitely — identical to the zero-approver stall described in [Parallel steps — zero assignees resolved](../workflows/parallel-steps.md#zero-assignees-resolved).

Mitigation: set a deadline and escalation target on any role-based step where the role could be empty:

```php
$flow->step('finance-review')
    ->role('finance')
    ->deadline(hours: 48)
    ->escalateTo(fn (Expense $expense) => [$expense->cfo]);
```

### Role does not exist in the database

If the role name does not exist in the `roles` table, Spatie throws its own `RoleDoesNotExist` exception from inside `resolve()`. `RoleResolver` does not catch or rewrap it — the raw Spatie exception propagates through the engine and rolls back the activation transaction.

This will surface as a `Spatie\Permission\Exceptions\RoleDoesNotExist` exception, not an Approvio exception. If you use `->role()`, ensure referenced roles are seeded before any workflow that uses them runs. Consider a guard in your seeder or a smoke-test assertion:

```php
// In a seeder or startup check:
Role::findByName('finance', 'web'); // throws if missing — surfaces the problem early
```

> **v0.3 note:** Wrapping Spatie's `RoleDoesNotExist` in an Approvio-specific exception with a clearer message and resolution hint is a v0.3 improvement candidate.

### User loses the role between submission and activation

Because resolution is activation-time, a user who loses the `finance` role between submission and step activation will not be assigned. If the entire role empties out, the stall behavior above applies. This is by design — the resolver reflects current team membership.

### Same user assigned to the role multiple times

`RoleResolver::resolve()` calls `->get()` directly with no deduplication pass. If Spatie's underlying query returns the same user model multiple times (e.g. under the Spatie team feature where a user holds the same role in multiple teams and the query is unscoped), the engine creates multiple `approval_step_assignees` rows for that user. When they act, `markAssigneeStatus()` updates all their rows simultaneously (mass update keyed on `assignee_type`/`assignee_id`). For `any` and `all` quorum this is harmless in practice; for `n_of_m` the duplicate counts as two approvals. If you use the Spatie team feature, scope the role query explicitly or deduplicate in a wrapping closure:

```php
->approvers(function (Expense $expense) {
    $userModel = config('approvio.user_model');
    return $userModel::role('finance', 'web')->get()->unique('id')->values();
})
```

---

## Combining with quorum rules

Role-based resolvers and quorum rules compose naturally. These three patterns cover the most common multi-approver scenarios.

### Any finance team member

```php
$flow->step('finance-review')
    ->role('finance')
    ->parallel()
    ->quorum('any');   // default — first approval completes
```

Useful when approval authority is shared across a team and speed matters.

### All current finance leads

```php
$flow->step('finance-leads-signoff')
    ->role('finance-lead')
    ->parallel()
    ->quorum('all');
```

Every user who holds `finance-lead` at activation time must approve. As leads are added or removed between submission and activation, the required approver set changes accordingly.

### N of M legal counsels

```php
$flow->step('legal-review')
    ->role('legal-counsel')
    ->parallel()
    ->quorum('n_of_m', 2);
```

At least 2 of however many legal counsels hold the role at activation time. Guard against the `N > M` stall (see [Parallel steps — n_of_m edge case](../workflows/parallel-steps.md#n_of_m----n-of-m-assignees-must-approve)) with a deadline and escalation target.

---

## Combining with conditions

A role-based step can be conditional. The condition is evaluated first; if it returns false, the step is skipped and the role query never runs:

```php
$flow->step('senior-finance-review')
    ->role('finance-lead')
    ->parallel()
    ->quorum('all')
    ->when(fn (Expense $expense) => $expense->amount > 50_000);
```

Expenses under $50,000 skip the `finance-lead` step entirely. See [Conditional steps](../workflows/conditional-steps.md).

---

## Migrating from direct user queries

If your existing workflow queries users by a role-like string column — `User::where('department', 'finance')->get()` — and you want to switch to Spatie roles, the migration is a two-step:

**Before:**

```php
$flow->step('finance-review')
    ->approvers(fn (Expense $expense) => User::where('department', 'finance')->get());
```

**After:**

1. Seed the Spatie role and assign existing users to it:

```php
$role = Role::findOrCreate('finance');
User::where('department', 'finance')->each(fn ($user) => $user->assignRole($role));
```

2. Switch the step:

```php
$flow->step('finance-review')
    ->role('finance');
```

The engine behavior is identical — same activation-time resolution, same `Collection` output, same assignee rows created. The `assigned_via` column changes from `'direct'` to `'role'`. In-flight requests submitted before the switch still have their original assignee rows from the direct resolver and are unaffected by the change.

---

**Previous:** [Conditional steps](../workflows/conditional-steps.md) | **Next:** [Expense approval recipe](../recipes/expense-approval.md)

---

**Verification summary**

| Behavioral claim | Verified at |
|---|---|
| `WorkflowBuilder::role()` signature: `(string $roleName, ?string $guardName = null)` | `WorkflowBuilder.php:131-134` |
| `->role()` delegates to `new RoleResolver($roleName, $guardName)` | `WorkflowBuilder.php:133` |
| `RoleResolver::resolve()` reads `approvio.user_model` config, calls `$model::role($name, $guard)->get()` | `RoleResolver.php:41-44` |
| No try-catch in `resolve()` — Spatie exceptions propagate (e.g. `RoleDoesNotExist`) | `RoleResolver.php:38-45` |
| Zero-member role: `->get()` returns empty Collection; step stalls (same zero-approver path) | `RoleResolver.php:44`; `ApprovalEngine.php:399-420` |
| `MissingDependencyException::forPackage('spatie/laravel-permission', 'RoleResolver')` thrown in constructor if Spatie absent | `RoleResolver.php:25-30` |
| Exception message format: `"Feature [RoleResolver] requires [spatie/laravel-permission]. Install it with: composer require spatie/laravel-permission"` | `MissingDependencyException.php:11-14` |
| Exception fires at workflow-resolution time (constructor call inside `define()` building), not at `resolve()` time | `RoleResolver.php:21-31`; `WorkflowBuilder.php:133`; `CodeWorkflowSource.php:40` |
| `assignedVia()` returns `'role'` | `RoleResolver.php:33-36` |
| Guard parameter passed directly to Spatie's `role()` scope; guard filtering handled by Spatie | `RoleResolver.php:44` |
| No deduplication in `resolve()` — duplicate users from Spatie query create multiple assignee rows | `RoleResolver.php:44`; `ApprovalEngine.php:399-409` |
