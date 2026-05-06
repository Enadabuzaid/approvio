# Sprint 6 — Escalation + deadlines + scheduled command

> **Goal:** Steps can declare a deadline and an escalation target. The
> `approvio:escalate` Artisan command scans for overdue steps and either adds
> the escalation target as a new assignee or marks the step/request as expired.

## Outcomes

When this sprint is done:

- `WorkflowBuilder` accepts `.deadline(hours: N)` and `.escalateTo(Closure)` on
  any step.
- At submit time, the engine writes `deadline_at` on each step row that has a
  deadline (`now() + N hours`).
- `php artisan approvio:escalate` scans for steps where `deadline_at < now()` and
  status is still `active`, then:
  - If the step has an escalation target: resolves it, **adds** a new assignee
    row with `assigned_via = 'escalation'` (original assignees are NOT replaced
    — they remain, with `status = escalated`), logs an `escalated` action,
    dispatches `StepEscalated`. Quorum re-evaluates with the new assignee included.
    **Decision (confirmed):** add the escalation target alongside the original —
    "Bob was originally responsible; Alice was added after 48 h of inactivity" is
    true and preserves audit history.
  - If no escalation target: transitions the step to `expired`, transitions the
    request to `expired`, logs the action, dispatches `ApprovalExpired` (new event).
- `php artisan approvio:expire` (or merged into `approvio:escalate`) scans
  `approval_requests.expires_at` and cancels expired requests.
- Any attempt to `approve()` or `reject()` on an expired request throws
  `InvalidStateTransitionException`.
- The command is documented in README and `config/approvio.php` includes a
  `schedule.enabled` key so apps can opt-in to automatic scheduling.

## Architecture notes

### New state machine transitions / step states

New `RequestStatus` case: `Expired = 'expired'` — a terminal state.
New `StepStatus` case: `Expired = 'expired'` — terminal for a step.
New `AssigneeStatus` case: `Escalated = 'escalated'` — the original assignee
superseded by escalation.

`StateMachine` transition additions:
```
in_review → expired   (allowed)
```

All existing transitions remain unchanged. `expired` is a terminal state — no
transitions out.

The `StateMachine` class needs updating: `isTerminal()` must include `Expired`.
All v0.1 `StateMachineTest` tests continue to pass because they test the existing
transition map; new tests cover the `in_review → expired` addition.

### v0.1 tests that must pass unmodified

All v0.1 test files (see Sprint 1 for the full list). Crucially:
- `tests/Unit/StateMachineTest.php` — all 5 existing tests pass. New test cases
  are added for the `expired` transition and terminal state, not modifying existing
  ones.
- `tests/Feature/RejectionTest.php` — unchanged (rejection still works as before).
- `tests/Feature/CancellationTest.php` — unchanged (cancellation is distinct from
  expiry; both are terminal but via different transitions).

### Contracts — new vs. extended

No new contracts. No existing contracts extended.

The `Step` VO (immutable) gains `deadline: ?int` (hours) and
`escalateTo: ?Closure` fields — additive, no existing code changes signatures.

### Database changes

**No new migration needed.** The v0.1 schema already has:
- `approval_request_steps.deadline_at` (nullable timestamp)
- `approval_requests.expires_at` (nullable timestamp)

The engine simply needs to populate `deadline_at` from the `Step` VO at submit
time, and the command reads it.

---

## Tasks

### 1. Enums

- [ ] `src/Enums/RequestStatus.php` — add `case Expired = 'expired'`.
- [ ] `src/Enums/StepStatus.php` — add `case Expired = 'expired'`.
- [ ] `src/Enums/AssigneeStatus.php` — add `case Escalated = 'escalated'`.
- [ ] `src/Enums/ActionType.php` — add `case Escalated = 'escalated'`.

### 2. State machine

- [ ] `src/Engine/StateMachine.php`:
  - Add `in_review → expired` to the transition map.
  - Add `Expired` to `isTerminal()`.

### 3. Workflow layer

- [ ] `src/Workflow/Step.php` — add `deadline: ?int` (hours), `escalateTo: ?Closure`.
- [ ] `src/Workflow/WorkflowBuilder.php`:
  - Add `deadline(int $hours): static`.
  - Add `escalateTo(Closure $resolver): static`.
- [ ] `src/Workflow/WorkflowDefinition.php` — carry through new Step fields.

### 4. Engine

- [ ] `src/Engine/ApprovalEngine.php`:
  - `activateNextStep()` — write `deadline_at = now() + deadline hours` to the
    step row if `$step->deadline` is set.
  - Add `expire(ApprovalRequest): void` — wraps in transaction, transitions
    request to `expired`, logs action, dispatches `ApprovalExpired`.
  - Guard in `approve()` and `reject()`: if request status is `expired`, throw
    `InvalidStateTransitionException`.

### 5. Events

- [ ] `src/Events/StepEscalated.php`:
  ```php
  StepEscalated(
      ApprovalRequest      $request,
      ApprovalRequestStep  $step,
      ApprovalStepAssignee $originalAssignee,
  )
  ```
- [ ] `src/Events/ApprovalExpired.php`:
  ```php
  ApprovalExpired(ApprovalRequest $request)
  ```

### 6. Exception

- [ ] `src/Exceptions/EscalationException.php` — for misconfigured escalation
      (e.g., escalation target resolves to empty collection with no fallback).

### 7. Artisan command

- [ ] `src/Commands/EscalateApprovalsCommand.php` (signature: `approvio:escalate`):
  - Query active steps where `deadline_at IS NOT NULL AND deadline_at < now()`.
  - For each: resolve escalation target from the step's frozen `config`.
    If target resolves non-empty: add escalation assignees, mark originals escalated,
    log, dispatch `StepEscalated`.
    If target is empty or not configured: expire the step and its request.
  - Query requests where `expires_at IS NOT NULL AND expires_at < now()` and
    status is non-terminal. Call `engine->expire($request)` for each.
  - Report a summary (N escalated, M expired) to stdout.

- [ ] `src/ApprovioServiceProvider.php` — register the command via `$this->commands([])`.

### 8. Config

- [ ] `config/approvio.php` — add:
  ```php
  'schedule' => [
      'escalate_cron' => '* * * * *', // run every minute; set null to disable
  ],
  ```

### 9. Tests

- [ ] `tests/Feature/EscalationTest.php`:
  - [ ] engine writes `deadline_at` on a step with a deadline
  - [ ] `approvio:escalate` adds escalation assignee for an overdue step
  - [ ] original assignee status becomes `escalated` after escalation
  - [ ] escalation assignee can `approve()` the step
  - [ ] `StepEscalated` event fires with correct payload
  - [ ] a step with no escalation target becomes `expired` when overdue
  - [ ] an expired request status is `expired`
  - [ ] `approve()` on an expired request throws `InvalidStateTransitionException`
  - [ ] `approvio:escalate` handles requests where `expires_at` has passed
  - [ ] a step without a deadline is not affected by the escalation command

- [ ] `tests/Unit/StateMachineTest.php` — add:
  - [ ] `in_review` → `expired` is allowed
  - [ ] `expired` is a terminal state (no transitions out)

## Acceptance checklist

- [ ] All v0.1 and prior sprint tests pass without modification.
- [ ] `EscalationTest` — all 10 cases pass.
- [ ] Updated `StateMachineTest` passes (2 new cases + all 5 original cases).
- [ ] `approvio:escalate` command registered and runnable.
- [ ] PHPStan green at level 7.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Automatic scheduling via `Schedule::command()` in the service provider
  (apps register their own schedule; we only register the command).
- Multiple escalation tiers (escalate to CFO if no one acts after 24 more hours)
  → v0.3.
- Escalation notification classes → v0.4.
- "Soft expiry" (allow act on expired request if explicitly unlocked) → v0.3.
