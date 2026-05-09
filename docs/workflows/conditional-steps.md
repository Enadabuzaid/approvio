# Conditional steps

A conditional step is a step that may be skipped based on the state of the approvable model at the time the step would activate. The workflow definition stays the same for all requests; the condition determines whether each individual request needs that step.

---

## The simplest conditional step

```php
protected function define(WorkflowBuilder $flow): void
{
    $flow->step('manager-review')
        ->approvers(fn (Expense $expense) => [$expense->manager]);

    $flow->step('cfo-review')
        ->approvers(fn (Expense $expense) => [$expense->cfo])
        ->when(fn (Expense $expense) => $expense->amount > 10_000);
}
```

Every expense goes through manager review. The CFO step only activates if the amount exceeds $10,000. If it does not, the step is recorded as `Skipped` and the request completes after manager approval.

---

## The condition closure

### Signature

The closure receives two arguments:

```php
->when(function (Expense $expense, ApprovalRequest $request): bool {
    return $expense->amount > 10_000;
})
```

1. The approvable model — the same instance the request was submitted against, live from the database at activation time
2. The `ApprovalRequest` — useful for reading `$request->context`, `$request->snapshot`, or `$request->pending_changes`

Both arguments are always passed. You can ignore the second if you only need model state.

### Return value

The engine evaluates the closure with PHP's truthy check — `if (! $condition($approvable, $request))`. A falsy return value (`false`, `null`, `0`, `''`, `[]`) skips the step; any truthy value activates it. In practice, return an explicit `bool` for readability:

```php
->when(fn (Expense $expense) => $expense->amount > 10_000)
```

### Exceptions

If the closure throws an exception, it propagates up through the engine with no catch. Since step activation runs inside `DB::transaction()`, the transaction rolls back entirely — for the first step this means the entire `submit()` is rolled back; for subsequent steps the `approve()` that triggered activation is rolled back. Guard against external dependencies inside conditions:

```php
->when(function (Expense $expense) {
    try {
        return ExchangeRateService::toUsd($expense->amount, $expense->currency) > 10_000;
    } catch (\Throwable) {
        return true; // fail open: let the step activate if the service is down
    }
})
```

---

## Evaluation timing: live model vs snapshot

**The condition is evaluated against the live model at step activation time, not at submission time.**

For the first step, activation happens immediately inside `submit()`. For later steps, activation happens when the prior step completes — which may be hours or days after submission.

If model state that the condition depends on can change between submission and activation, the condition sees the changed state. This is intentional: a manager who lowers an expense amount after submission should not trigger a CFO review that was no longer warranted.

If you need the condition to reflect the state at submission time, read from `$request->snapshot`:

```php
->when(function (Expense $expense, ApprovalRequest $request): bool {
    // Use the amount at submission time, not the current amount:
    return ($request->snapshot['amount'] ?? 0) > 10_000;
})
```

`$request->snapshot` contains `$approvable->toArray()` captured at submission. The keys match your model's column names. The values are uncast — dates come as strings, not Carbon instances.

---

## What the database looks like when a step is skipped

When a condition returns false, `skipStep()` writes the `ApprovalRequestStep` row and appends an audit action. No `ApprovalStepAssignee` rows are created.

**`approval_request_steps`** after `cfo-review` is skipped:

| id | step_name    | step_index | status  | activated_at | completed_at        |
|----|--------------|------------|---------|--------------|---------------------|
| 1  | manager-review | 0        | approved | ...          | 2026-05-07 10:05:00 |
| 2  | cfo-review   | 1          | skipped | null         | 2026-05-07 10:05:00 |

`activated_at` is null for skipped steps — the step was never set to `Active`, so no activation timestamp is recorded.

**`approval_actions`** for the skip event:

| id | request_id | action  | actor_type | actor_id | created_at          |
|----|------------|---------|------------|----------|---------------------|
| 3  | 1          | skipped | null       | null     | 2026-05-07 10:05:00 |

The `actor` is null — skipping is an engine decision, not a user action.

**`StepSkipped` event** fires with `(ApprovalRequest $request, ApprovalRequestStep $step)`.

---

## When all steps are skipped

If every step in a workflow has a condition, and all conditions return false for a given request, the engine finalizes the request as approved. A skipped-only request goes directly from `Pending` to `Approved` — it never enters `InReview` because the `InReview` status is set only when a step is actually activated, which never happens.

```php
protected function define(WorkflowBuilder $flow): void
{
    $flow->step('manager-review')
        ->approvers(fn (Expense $expense) => [$expense->manager])
        ->when(fn (Expense $expense) => $expense->amount > 100);

    $flow->step('cfo-review')
        ->approvers(fn (Expense $expense) => [$expense->cfo])
        ->when(fn (Expense $expense) => $expense->amount > 10_000);
}
```

An expense of $50 skips both steps. The `ApprovalRequest.status` goes `pending → approved` in the same `submit()` transaction. `ApprovalCompleted` fires. The strategy's `onApprove()` runs.

This is valid and intentional. If it surprises you — a submit that immediately approves — add a guard step at the top with an unconditional approver, or use a condition that can never be false for your lowest-tier case.

---

## Composing with parallel steps

`.when()` and `.parallel()` are independent modifiers on the same step. They compose without restriction:

```php
$flow->step('board-approval')
    ->approvers(fn (Expense $expense) => $expense->boardMembers)
    ->parallel()
    ->quorum('n_of_m', 3)
    ->when(fn (Expense $expense) => $expense->amount > 100_000);
```

If the condition returns false, the entire parallel step is skipped wholesale — no assignees are resolved, no assignee rows are created. If it returns true, the parallel step activates normally with its quorum rule. See [Parallel steps](./parallel-steps.md) for quorum behavior.

---

## ConditionEvaluator: extractable conditions

For conditions you want to test in isolation or reuse across workflows, implement the `ConditionEvaluator` contract:

```php
<?php

declare(strict_types=1);

namespace App\Approvals\Conditions;

use Enadstack\Approvio\Contracts\ConditionEvaluator;
use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

class ExceedsThreshold implements ConditionEvaluator
{
    public function __construct(private readonly int $threshold) {}

    public function evaluate(Model $approvable, ApprovalRequest $request): bool
    {
        return $approvable->amount > $this->threshold;
    }
}
```

**The `when()` method accepts only a `Closure`, not a `ConditionEvaluator` directly.** To use the class, instantiate it and pass a closure that delegates to it:

```php
$threshold = new ExceedsThreshold(10_000);

$flow->step('cfo-review')
    ->approvers(fn (Expense $expense) => [$expense->cfo])
    ->when(fn ($model, $request) => $threshold->evaluate($model, $request));
```

The interface is useful as a naming convention and for unit testing conditions without exercising the full engine:

```php
it('triggers cfo review above threshold', function () {
    $condition = new ExceedsThreshold(10_000);
    $expense = Expense::factory()->make(['amount' => 15_000]);

    expect($condition->evaluate($expense, new ApprovalRequest))->toBeTrue();
});
```

---

## Edge cases and gotchas

**Snapshot keys are column names, not cast types**

`$request->snapshot` is `$approvable->toArray()` at submission time. Dates are strings, not Carbon instances. Enums are their raw values. Cast your snapshot values before comparing:

```php
->when(function (Expense $expense, ApprovalRequest $request): bool {
    $amount = (float) ($request->snapshot['amount'] ?? 0);
    return $amount > 10_000;
})
```

**Conditions on the first step run inside `submit()`'s transaction**

If the first step's condition returns false, the step is skipped and the engine immediately tries to activate the second step — all within the same database transaction that created the request. If a subsequent condition throws, the entire request creation rolls back. Treat condition closures on early steps as code paths inside `submit()` — they need to handle their own failure cases without throwing, or the entire submission attempt rolls back and the user sees a 500 error.

**A step with `when()` and no prior steps**

The condition on the first step is evaluated immediately when the request is submitted. If it returns false, the step is skipped and the second step's condition is evaluated in the same call, and so on. A request that skips all steps completes as approved within the submit call, before `submit()` returns.

**Changing the workflow class affects in-flight requests**

Condition closures, approver closures, and escalation closures are all stored in the PHP class — none are serialized to the database. When any later step activates, the engine re-reads the current PHP class and uses whatever is defined there. Changing a condition, swapping an approver resolver, or removing an escalation target between submission and activation will affect all requests that have not yet reached that step. See [Concepts — workflow definition lifecycle](../getting-started/concepts.md#workflow-definition-lifecycle) for full coverage.

---

**Previous:** [Parallel steps](./parallel-steps.md) | **Next:** [Role-based approvers](../approvers/roles-via-spatie.md)

---

**Verification summary**

| Behavioral claim | Verified at |
|---|---|
| `when()` accepts only `Closure`, not `ConditionEvaluator` | `WorkflowBuilder.php:150` |
| Condition evaluated in `activateNextStep()`, not `submit()` | `ApprovalEngine.php:389-396` |
| Both `($approvable, $request)` always passed; PHP ignores extra args if closure declares fewer | `ApprovalEngine.php:391` |
| Falsy return (not strict `false`) skips step — `if (! $condition(...))` | `ApprovalEngine.php:391` |
| Exception in closure propagates; transaction rolls back | `ApprovalEngine.php:81,135` (transaction wrappers in `submit()` and `approve()`) |
| `ConditionEvaluator` interface exists but engine never calls `->evaluate()` directly | `ConditionEvaluator.php:10-13`; `ApprovalEngine.php:391` |
| All-skipped request goes `Pending → Approved`, never enters `InReview` | `ApprovalEngine.php:418-420` (InReview bump only in activation path, not skip path); `StateMachine.php:28` |
| `skipStep()` writes step row, logs action, dispatches `StepSkipped`, then calls `finalizeAsApproved()` if last step | `ApprovalEngine.php:433-461` |
| `activated_at` is null for skipped steps | `ApprovalEngine.php:433-438` (only `status` and `completed_at` updated, not `activated_at`) |
| `StepSkipped` constructor: `(ApprovalRequest, ApprovalRequestStep)` | `StepSkipped.php:16-20` |
| `snapshot` is `$approvable->toArray()` — uncast column values | `ApprovalEngine.php:95` |
