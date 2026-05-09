# Parallel steps

Sequential steps force a queue: approver B cannot act until approver A is done. That works for a simple management chain, but breaks down when two independent authorities must both sign off and neither depends on the other's decision. Parallel steps activate all assigned approvers simultaneously and advance when a configurable quorum is reached.

---

## The simplest parallel step

Add `.parallel()` after `->approvers()`. Without an explicit `.quorum()` call, the default is `'any'` — the first approval completes the step.

```php
protected function define(WorkflowBuilder $flow): void
{
    $flow->step('dual-approval')
        ->approvers(fn (Expense $expense) => [
            $expense->departmentHead,
            $expense->financeLead,
        ])
        ->parallel();
}
```

Both assignees receive the request simultaneously. Whoever acts first determines the outcome — one approval completes the step, one rejection terminates the request.

---

## Quorum rules

### `any` — first approval wins (default)

```php
->parallel()
->quorum('any')   // explicit, same as omitting quorum()
```

The step completes as soon as one assignee approves. Useful when you have a pool of approvers and any one of them is sufficient — e.g. any member of the finance team can clear an expense.

If the step has multiple assignees and one approves, the remaining assignees can no longer act on that step (the step is no longer `active`). They do not receive a "step closed" notification automatically — wire that up in a listener on `ApprovalCompleted` or `StepActivated` for the next step.

### `all` — every assignee must approve

```php
->parallel()
->quorum('all')
```

The step completes only when every non-delegated, non-escalated assignee has approved. Use this when you need genuine sign-off from all parties — department head AND finance AND legal.

The denominator the engine uses excludes assignees with status `delegated` or `escalated`. If assignee A delegates to B (or A is escalated and B is added), only B's approval counts toward the `'all'` threshold. The original assignee is removed from the denominator.

If the quorum can never be reached because a resolver returned fewer users than expected (see [zero-assignee edge case](#zero-assignees-resolved)), the step stalls indefinitely.

### `n_of_m` — N of M assignees must approve

```php
->parallel()
->quorum('n_of_m', 2)   // 2 of however many are resolved
```

The step completes when at least `N` assignees have approved. The total count `M` is determined at activation time — the engine resolves approvers and creates that many assignee rows. The `N` you supply is fixed at workflow-definition time.

**Edge case:** if `N > M` (the resolver returns fewer users than your quorum count), quorum can never be met and the step stalls indefinitely with no error. The engine does not guard against this. If your approver pool can shrink (e.g. role-based resolvers on a team that might have no members), set a deadline and escalation target on the step to handle the stall path.

**Edge case:** if you call `.quorum('n_of_m')` without the second argument, `quorum_count` is stored as `null`. The engine casts `(int) null` to `0`, making every parallel approval satisfy the quorum. Avoid this — always supply the count.

---

## The rejection policy

In v0.2, **any single rejection on a parallel step immediately terminates the entire request**, regardless of quorum rule or how many approvals are already in.

```php
$flow->step('dual-approval')
    ->approvers(fn (Expense $expense) => [
        $expense->departmentHead,   // has approved
        $expense->financeLead,      // approves
        $expense->cfo,              // rejects — request is terminated
    ])
    ->parallel()
    ->quorum('all');
```

Even with `quorum('all')` and two of three approvals already recorded, one rejection ends the request. The remaining assignees' rows stay at `Pending` — they are not notified or updated.

This policy is fixed in v0.2 and intentional: it makes the failure mode unambiguous. Configurable rejection thresholds (e.g. "require 2 rejections before terminating") are planned for v0.3.

---

## Combining parallel with conditions

`.parallel()` and `.when()` compose cleanly. A parallel step with a condition is skipped wholesale if the condition returns false — none of its assignees are resolved and no assignee rows are created.

```php
$flow->step('executive-approval')
    ->approvers(fn (Expense $expense) => [
        $expense->ceo,
        $expense->cfo,
    ])
    ->parallel()
    ->quorum('all')
    ->when(fn (Expense $expense) => $expense->amount > 50_000);
```

If `amount <= 50000`, the engine sets the step to `Skipped` at activation time and advances immediately to the next step. See [Conditional steps](./conditional-steps.md) for full coverage.

---

## Mixing parallel and sequential steps

Most real workflows combine both. Steps run in the order they are declared.

```php
protected function define(WorkflowBuilder $flow): void
{
    // Step 0: manager must approve first
    $flow->step('manager-review')
        ->approvers(fn (Expense $expense) => [$expense->manager]);

    // Step 1: legal AND finance must both sign off, in any order
    $flow->step('compliance-review')
        ->approvers(fn (Expense $expense) => [
            $expense->legalCounsel,
            $expense->financeLead,
        ])
        ->parallel()
        ->quorum('all');

    // Step 2: final sign-off, sequential
    $flow->step('final-approval')
        ->approvers(fn (Expense $expense) => [$expense->ceo]);
}
```

The engine runs these strictly in index order. The compliance step only activates after the manager approves. The final sign-off only activates after both legal and finance have approved.

---

## What the database looks like

### While the parallel step is active (two of three have approved)

**`approval_request_steps`**

| id | step_name         | type     | quorum_rule | status | activated_at        |
|----|-------------------|----------|-------------|--------|---------------------|
| 2  | compliance-review | parallel | all         | active | 2026-05-07 11:00:00 |

**`approval_step_assignees`**

| id | step_id | assignee_id | status   | acted_at            |
|----|---------|-------------|----------|---------------------|
| 3  | 2       | 10          | approved | 2026-05-07 11:05:00 |
| 4  | 2       | 20          | approved | 2026-05-07 11:08:00 |
| 5  | 2       | 30          | pending  | null                |

### After assignee 30 rejects

**`approval_request_steps`**

| id | step_name         | type     | quorum_rule | status   | completed_at        |
|----|-------------------|----------|-------------|----------|---------------------|
| 2  | compliance-review | parallel | all         | rejected | 2026-05-07 11:10:00 |

**`approval_step_assignees`**

| id | step_id | assignee_id | status   | acted_at            |
|----|---------|-------------|----------|---------------------|
| 3  | 2       | 10          | approved | 2026-05-07 11:05:00 |
| 4  | 2       | 20          | approved | 2026-05-07 11:08:00 |
| 5  | 2       | 30          | rejected | 2026-05-07 11:10:00 |

Assignees 10 and 20 remain `approved` — their rows are not rolled back. The `approval_requests` row moves to `status = rejected`.

---

## Events fired

For a parallel step with three assignees under `quorum('all')` that completes successfully:

| Event | Carries | Fires |
|---|---|---|
| `StepActivated` | `$request`, `$step` | Once — when the step first becomes active |
| `StepApproved` | `$request`, `$step`, `$action` | Once per individual `approve()` call — three times in this case |
| `ApprovalCompleted` | `$request` | Once — when the last step of the request approves |

There is no dedicated "quorum met" event. `StepApproved` fires every time an individual approves, including the approval that tips the quorum. To detect the quorum-completing approval in a listener, check whether `$step->status === StepStatus::Approved` after the event fires — if the step is no longer `active`, the quorum was just met.

For a rejected step:

| Event | Carries | Fires |
|---|---|---|
| `StepRejected` | `$request`, `$step`, `$action` | Once — when the first rejection occurs |
| `ApprovalRejected` | `$request` | Once — immediately after `StepRejected` |

---

## Edge cases and gotchas

**The same user appearing twice in the approvers collection**

The engine does not deduplicate the resolver's return value. If `[$expense->manager, $expense->manager]` is returned, two `approval_step_assignees` rows are created for the same user. When that user acts, `markAssigneeStatus()` updates both rows simultaneously via a mass-update query keyed on `(assignee_type, assignee_id)`. For `any` and `all` quorum this is harmless in practice, though the assignee count is inflated. For `n_of_m`, the duplicate counts as two approvals toward the threshold. Deduplicate in your resolver closure to avoid this.

**Assignees can act in any order**

On a parallel step, all assignees hold `status = pending` and `step.status = active` simultaneously. The engine does not impose any internal ordering within a parallel step — `assertActorCanActOnStep()` permits any pending assignee to act. First-come, first-served.

**Zero assignees resolved**

If the resolver returns an empty collection — e.g. the role resolver finds no users with the required role, or a relation chain returns null — the engine still sets the step to `Active` but creates no `ApprovalStepAssignee` rows. Nobody can act on the step (`assertActorCanActOnStep()` throws `UnauthorizedActionException::notAssignee()` for anyone). The request stalls in `InReview` indefinitely with no error logged.

Guard against this by setting a deadline and escalation target on steps whose resolver could return empty:

```php
$flow->step('finance-review')
    ->role('finance')
    ->parallel()
    ->deadline(hours: 48)
    ->escalateTo(fn (Expense $expense) => [$expense->cfo]);
```

If no finance users exist, the deadline fires, the escalation target (CFO) is added as an assignee, and the request can proceed. Full escalation coverage in [Escalation and deadlines](../advanced/escalation.md).

Choose an escalation target that's guaranteed to resolve to a real user — a specific named individual, a config-defined system fallback, or any closure that cannot return empty. Escalating to another resolver path that could also be empty just delays the stall by one cycle.

**Deadlines on parallel steps**

Escalation and deadline logic works the same on parallel and sequential steps. The `deadline_at` column is set at step activation time. The `approvio:escalate` command checks it on schedule. See [Escalation and deadlines](../advanced/escalation.md) for full coverage.

---

**Previous:** [Concepts](../getting-started/concepts.md) | **Next:** [Conditional steps](./conditional-steps.md)
