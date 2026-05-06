# Sprint 1 — Parallel steps with quorum rules

> **Goal:** A workflow step declared `.parallel()` activates all assigned approvers
> simultaneously; the engine evaluates quorum after each action and advances only
> when the configured threshold is met.

## Outcomes

When this sprint is done:

- `WorkflowBuilder` accepts `.parallel()`, `.quorum('any')`, `.quorum('all')`,
  and `.quorum('n_of_m', N)` on a step.
- `WorkflowDefinition` / `Step` value objects carry the step type and quorum config.
- The engine sets `type`, `quorum_rule`, and `quorum_count` on the materialized
  `ApprovalRequestStep` row at submit time.
- On each `approve()` call for a parallel step, the engine checks quorum:
  - `any` — complete the step on the first approval.
  - `all` — complete only when every assignee has approved.
  - `n_of_m` — complete when N distinct approvals are recorded.
- Rejection by any assignee on a parallel step terminates the step (and request)
  immediately, regardless of quorum.
- All v0.1 sequential tests still pass unmodified.

## Architecture notes

### New state machine transitions / step states

No new `RequestStatus` or `StepStatus` cases are needed. Parallel steps use the
existing `pending → active → approved / rejected` transitions. The distinction is
purely in how the engine decides whether to call `completeStep()`.

The key isolation point: extract a private method
`ApprovalEngine::isStepQuorumMet(ApprovalRequestStep $step): bool`
that is called after every `approve()` action. Sequential steps always return
true after the sole assignee approves (their quorum is implicitly `all` with one
assignee). This keeps the v0.1 code path unchanged — sequential steps go through
the same method, they just always resolve to true immediately.

For rejection: extract `ApprovalEngine::shouldStepTerminateOnRejection(ApprovalRequestStep): bool`.
In v0.2 this always returns `true`. In v0.3 it can be made configurable. Isolating
it now prevents painting into a corner.

### v0.1 tests that must pass unmodified

All of the following must remain green with zero modification:
- `tests/Feature/BasicApprovalFlowTest.php`
- `tests/Feature/MultiStepApprovalTest.php`
- `tests/Feature/RejectionTest.php`
- `tests/Feature/CancellationTest.php`
- `tests/Feature/SoftApprovalStrategyTest.php`
- `tests/Feature/DraftApprovalStrategyTest.php`
- `tests/Feature/UserApprovalQueriesTest.php`
- `tests/Feature/TenancyTest.php`
- `tests/Feature/WorkflowResolutionTest.php`
- `tests/Feature/ServiceProviderTest.php`
- `tests/Unit/StateMachineTest.php`
- `tests/Unit/WorkflowBuilderTest.php`

### Contracts — new vs. extended

No new contracts. No existing contracts extended (adding parallel support is
internal to the engine and the workflow VO layer).

### Database changes

**No new migration needed.** The v0.1 `approval_request_steps` migration already
has `type` (default `sequential`), `quorum_rule` (default `any`), and
`quorum_count` (nullable). The engine simply needs to populate them from the
`Step` VO at submit time.

---

## Tasks

### 1. Workflow layer

- [ ] `src/Workflow/Step.php` — add `type: StepType`, `quorumRule: QuorumRule`,
      `quorumCount: ?int` to the immutable VO. Verify `QuorumRule` enum exists
      in `src/Enums/QuorumRule.php`; if `StepType` enum is missing, create it
      (`Sequential`, `Parallel`).
- [ ] `src/Workflow/WorkflowBuilder.php` — add fluent methods:
  - `parallel(): static` — sets type to `Parallel`, default quorum `Any`
  - `quorum(string $rule, ?int $count = null): static`
- [ ] `src/Workflow/WorkflowDefinition.php` — ensure steps carry the new fields
      through to `toDefinition()`.

### 2. Engine

- [ ] `src/Engine/ApprovalEngine.php`:
  - [ ] `activateNextStep()` — write `type`, `quorum_rule`, `quorum_count` to the
        step row from the `Step` VO.
  - [ ] Extract `isStepQuorumMet(ApprovalRequestStep): bool`.
  - [ ] Extract `shouldStepTerminateOnRejection(ApprovalRequestStep): bool` (returns
        `true` for now, isolated for v0.3 override).
  - [ ] `approve()` — after marking assignee approved, call `isStepQuorumMet()`;
        advance only if true.
  - [ ] `reject()` — check `shouldStepTerminateOnRejection()` before terminating.

### 3. Models

- [ ] `src/Models/ApprovalRequestStep.php` — add casts for `type` → `StepType`
      and `quorum_rule` → `QuorumRule`.

### 4. Test fixtures

- [ ] `tests/Fixtures/Workflows/ExpenseParallelWorkflow.php` — two-approver parallel
      step with `quorum('all')`, followed by a sequential step.

### 5. Tests

- [ ] `tests/Feature/ParallelStepsTest.php`:
  - [ ] a parallel step activates all assigned approvers simultaneously
  - [ ] quorum `any` — completes on the first approval
  - [ ] quorum `all` — waits until every assignee approves
  - [ ] quorum `n_of_m` — completes when N approvals received, not before
  - [ ] quorum `n_of_m` — does not complete on fewer than N approvals
  - [ ] rejection on a parallel step terminates the request immediately
  - [ ] a sequential step after a parallel step only activates after parallel completes
  - [ ] events: `StepApproved` fires for each individual approval; `StepActivated`
        fires once when the step first becomes active (not once per assignee)

## Acceptance checklist

- [ ] All v0.1 tests pass without modification.
- [ ] `ParallelStepsTest` — all 8 cases pass.
- [ ] `WorkflowBuilderTest` — existing tests pass; add cases for `.parallel()`
      and `.quorum()` validation (missing `n_of_m` count throws, etc.).
- [ ] PHPStan still green at level 6 (level ratchet happens in Sprint 4).
- [ ] `CHANGELOG.md` updated under `[Unreleased]`.

## Out of scope

- Configurable rejection policy (require N rejections before terminating) → v0.3.
- Mixed parallel + sequential steps in the same workflow definition (works by
  default — just ensure sequential steps are materialized correctly alongside
  parallel ones).
