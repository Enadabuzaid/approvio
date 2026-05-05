# Sprint 3 — Multi-step + reject + cancel

> **Goal:** Complete the full sequential lifecycle. Workflows can have any
> number of sequential steps. Rejection at any step terminates the request.
> Cancellation works from any active state.
>
> **Why it matters:** This is what makes the package useful for real apps.
> A single-step approval is rare; manager → finance → CFO is the norm.

## Outcomes

When this sprint is done:

- A workflow with N sequential steps activates them one at a time, only
  advancing when the previous step is approved.
- Rejecting at any step terminates the request immediately, and remaining
  steps stay `pending` (never activated).
- Cancellation works at any non-terminal state and is blocked at terminal
  states with a clean error.
- Trying to approve a step you're not assigned to throws.
- Trying to approve twice from the same user throws.

## Tasks

### 1. Engine — reject

- [ ] `ApprovalEngine::reject()`:
  - [ ] Refresh request, guard terminal state.
  - [ ] Assert actor is an active assignee on the current step.
  - [ ] Mark assignee status `rejected`.
  - [ ] Log `rejected` action.
  - [ ] Complete step with `rejected` status.
  - [ ] Dispatch `StepRejected`.
  - [ ] Transition request to `rejected`, set `completed_at`.
  - [ ] Call strategy `onReject()`.
  - [ ] Dispatch `ApprovalRejected`.

### 2. Engine — cancel

- [ ] `ApprovalEngine::cancel()`:
  - [ ] Refresh request.
  - [ ] `assertCanTransition()` to `cancelled`.
  - [ ] Update status + `completed_at`.
  - [ ] Log `cancelled` action.
  - [ ] Call strategy `onCancel()`.
  - [ ] Dispatch `ApprovalCancelled`.

### 3. Engine — multi-step advance

- [ ] Confirm `advanceOrComplete()` correctly moves to the next step,
      activates it, and only marks the request approved when ALL steps
      are done.
- [ ] Confirm rejection at step N leaves steps N+1..end as `pending` (not
      activated).

### 4. New events

- [ ] `StepRejected`, `ApprovalRejected`, `ApprovalCancelled`.

### 5. Public surface — `Approvio` facade

- [ ] `Approvio::reject()`, `Approvio::cancel()`.
- [ ] `HasApprovalActions::reject()`.

### 6. Test fixtures

- [ ] `tests/Fixtures/Workflows/ExpenseTwoStepWorkflow.php`.

### 7. Tests

- [ ] `tests/Feature/MultiStepApprovalTest.php`:
  - [ ] activates only the first step on submit
  - [ ] advances to the next step after first step is approved
  - [ ] completes only when the final step is approved
  - [ ] refuses approval from the wrong step approver
  - [ ] refuses double-approval from the same approver
- [ ] `tests/Feature/RejectionTest.php`:
  - [ ] terminates the request immediately on first-step rejection
  - [ ] terminates the request on second-step rejection
  - [ ] records the rejection comment in the audit log
  - [ ] leaves un-activated steps as pending
- [ ] `tests/Feature/CancellationTest.php`:
  - [ ] marks the request as cancelled
  - [ ] writes a cancelled action with the comment
  - [ ] refuses to cancel an already-approved request

## Acceptance checklist

- [ ] All Sprint 1 + 2 acceptance items still pass.
- [ ] All new tests pass.
- [ ] PHPStan still green.
- [ ] State machine refuses every illegal transition.
- [ ] No assignee can act outside their active step.
- [ ] No assignee can act twice on the same step.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Real strategies — still using the placeholder (sprint 4).
- Tenant scoping (sprint 4).
- Parallel steps (deferred to v0.2).
