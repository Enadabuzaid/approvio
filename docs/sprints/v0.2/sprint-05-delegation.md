# Sprint 5 — Delegation

> **Goal:** An assignee on an active step can delegate their responsibility to a
> named deputy. The original assignee is locked out; the delegate acts in their
> place. The full chain is recorded in the audit log.

## Outcomes

When this sprint is done:

- `$user->delegate($request, $deputy, 'Out of office')` works end-to-end.
- The original assignee's `status` is set to `delegated`; `delegated_to_type` and
  `delegated_to_id` are populated on their row.
- A new `ApprovalStepAssignee` row is created for `$deputy` with
  `assigned_via = 'delegation'`.
- A `delegated` action is written to the audit log with the comment.
- `RequestDelegated` event fires.
- The original assignee can no longer `approve()` or `reject()` after delegating
  (the "actor is an active assignee" guard rejects them).
- The delegate CAN approve or reject (they are now an active assignee).
- Delegation is one-level only: a delegated assignee cannot further delegate.
  Attempting to do so throws `DelegationException`.
- All v0.1 and prior sprint tests pass unmodified.

## Architecture notes

### New state machine transitions / step states

No new `RequestStatus` or `StepStatus` transitions needed. Delegation is scoped
entirely to the `ApprovalStepAssignee` level.

New `AssigneeStatus` case: `Delegated = 'delegated'`.

The existing "actor is an active assignee" guard in `ApprovalEngine::approve()`
and `ApprovalEngine::reject()` already checks that the assignee has status
`Pending`. After delegation, the original assignee's status is `Delegated` (not
`Pending`), so the guard naturally blocks them without any special-case code.

New engine method: `ApprovalEngine::delegate(ApprovalRequest, Model $actor, Model $delegateTo, ?string $comment): ApprovalRequest`.

### v0.1 tests that must pass unmodified

All v0.1 test files (see Sprint 1 architecture notes for full list).
Additionally all Sprint 1–4 test files.

The guard change in `approve()` / `reject()` must NOT break v0.1 tests because
v0.1 tests use `Pending` status assignees; adding `Delegated` as a new status does
not affect the existing guard logic.

### Contracts — new vs. extended

No new contracts. No existing contracts extended.

`HasApprovalActions` gets a new public method `delegate()` — additive, no
signature changes.

### Database changes

**No new migration needed.** The v0.1 `approval_step_assignees` migration already
has `delegated_to_type` and `delegated_to_id` (via `nullableMorphs('delegated_to')`).

The only schema change needed is the new `AssigneeStatus::Delegated` enum case —
that is a PHP enum change, not a DB migration (the column stores strings).

---

## Tasks

### 1. Enums

- [ ] `src/Enums/AssigneeStatus.php` — add `case Delegated = 'delegated'`.
- [ ] `src/Enums/ActionType.php` — add `case Delegated = 'delegated'`.

### 2. Exception

- [ ] `src/Exceptions/DelegationException.php`:
  ```php
  class DelegationException extends ApprovioException
  {
      public static function alreadyDelegated(): self { ... }
      public static function cannotDelegateFurther(): self { ... }
      public static function notAnAssignee(): self { ... }
  }
  ```

### 3. Event

- [ ] `src/Events/RequestDelegated.php`:
  ```php
  RequestDelegated(
      ApprovalRequest      $request,
      ApprovalRequestStep  $step,
      ApprovalStepAssignee $from,
      ApprovalStepAssignee $to,
  )
  ```

### 4. Engine

- [ ] `src/Engine/ApprovalEngine.php` — add `delegate()`:
  - Wrap in `DB::transaction()`.
  - Refresh request; guard terminal state.
  - Find the active assignee row for `$actor` on the current step.
    If not found or status ≠ `Pending`: throw `DelegationException::notAnAssignee()`.
  - If `$actor` is already a delegated assignee (`assigned_via = 'delegation'`):
    throw `DelegationException::cannotDelegateFurther()`.
  - Set `$assignee->status = Delegated`, `$assignee->delegated_to_type/id`, save.
  - Create new `ApprovalStepAssignee` for `$delegateTo` on the same step with
    `status = Pending`, `assigned_via = 'delegation'`.
  - Log `delegated` action with comment.
  - Dispatch `RequestDelegated`.
  - Return refreshed request.

### 5. Public surface

- [ ] `src/Concerns/HasApprovalActions.php` — add `delegate()`:
  ```php
  public function delegate(
      ApprovalRequest $request,
      Model           $delegateTo,
      ?string         $comment = null,
  ): ApprovalRequest {
      return app(ApprovalEngine::class)->delegate($request, $this, $delegateTo, $comment);
  }
  ```
- [ ] `src/Approvio.php` + `src/Facades/Approvio.php` — add `delegate()` method
      and `@method` docblock.

### 6. README (delegation section)

- [ ] Add **Delegation** section to README documenting:
  - `$user->delegate($request, $deputy, $comment)` API.
  - **Explicitly state**: delegation is non-revocable in v0.2. The escape hatch
    is to cancel the request and resubmit. Revocation is planned for v0.3.

### 7. Tests

- [ ] `tests/Feature/DelegationTest.php`:
  - [ ] original assignee's status becomes `delegated` after delegating
  - [ ] `delegated_to_type/id` are populated on the original assignee row
  - [ ] delegate becomes an active assignee on the step
  - [ ] delegate can `approve()` the step
  - [ ] delegate can `reject()` the step
  - [ ] original assignee cannot `approve()` after delegating (throws)
  - [ ] original assignee cannot `reject()` after delegating (throws)
  - [ ] attempting to delegate as an already-delegated assignee throws `DelegationException`
  - [ ] audit log records a `delegated` action with actor, delegate, and comment
  - [ ] `RequestDelegated` event fires with correct payload
  - [ ] delegation on a parallel step — delegate gets a new assignee slot,
        quorum is re-evaluated with the new assignee list

## Acceptance checklist

- [ ] All v0.1 and prior sprint tests pass without modification.
- [ ] `DelegationTest` — all 11 cases pass.
- [ ] PHPStan green at level 7.
- [ ] `CHANGELOG.md` updated.

## Revocation (v0.3)

**Decision (confirmed):** Delegation is non-revocable in v0.2. Once an assignee
delegates, they are locked out for the request lifetime. The v0.2 escape hatch
for users who need to undo a delegation is:

1. Cancel the current request (`$approvable->latestApprovalRequest()->cancel()`).
2. Resubmit (`$approvable->resubmit()`).

This must be documented in the README delegation section so users aren't surprised.
True revocation (re-activating the original assignee and removing the delegate)
is planned for v0.3.

## Out of scope

- Revoking a delegation (un-delegating) → v0.3. See revocation note above.
- Delegation chains beyond 1 level → v0.3.
- Delegation notifications → v0.4 (default notification classes).
- Delegation expiry (delegate only valid for N hours) → v0.3.
