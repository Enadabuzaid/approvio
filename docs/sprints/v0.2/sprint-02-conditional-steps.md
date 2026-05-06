# Sprint 2 — Conditional steps via `when()`

> **Goal:** A step declared `.when(fn($model) => bool)` is evaluated at
> activation time. If the condition is false, the step is skipped cleanly,
> recorded in the audit log, and the engine advances to the next step.

## Outcomes

When this sprint is done:

- `WorkflowBuilder` accepts `.when(Closure)` on any step (sequential or parallel).
- At submit time, the condition closure is serialized into the step's `config`
  JSON column (by storing the fully-qualified class name if a class-based evaluator
  is used, or by evaluating immediately and caching the result — see design decision
  below).
- When the engine would activate a step, it first evaluates the condition against
  the live model. If false, it sets step status to `skipped`, logs a `skipped`
  action, and moves to the next step without firing `StepActivated`.
- `StepSkipped` event fires for each skipped step.
- A workflow where every remaining step is conditionally skipped still marks the
  request as `approved` (the request is complete when there are no more pending/active
  steps to process).
- All v0.1 tests and Sprint 1 tests pass unmodified.

## Design note — when to evaluate conditions

Conditions are evaluated **lazily at activation time** against the **live model**
(not the snapshot). Rationale: the condition often depends on live data that may
have changed since submission (e.g., a manager may have been assigned after
submission). Storing the pre-evaluated boolean in `config` at submit time would
be wrong for use-cases like "if the request is still pending after 24 hours, add
a CFO step". Lazy evaluation is the correct default.

The `config` column stores the resolver metadata (e.g., the class name of a
`ConditionEvaluator` implementation) so the condition is recoverable from the
frozen step record for audit purposes.

## Tasks

### 1. Contracts

- [ ] `src/Contracts/ConditionEvaluator.php` — new interface:
  ```php
  interface ConditionEvaluator {
      public function evaluate(Model $approvable, ApprovalRequest $request): bool;
  }
  ```

### 2. Workflow layer

- [ ] `src/Workflow/Step.php` — add `condition: ?Closure` field.
- [ ] `src/Workflow/WorkflowBuilder.php` — add `.when(Closure $condition): static`.
- [ ] Serialization strategy: when building the `Step` VO, store the raw `Closure`
      in the VO. At `activateNextStep()` time in the engine, evaluate it. Write
      `['condition' => 'closure_provided']` to `config` as an audit marker (the
      closure itself cannot be serialized to JSON).

### 3. Enums

- [ ] `src/Enums/StepStatus.php` — add `case Skipped = 'skipped'`.
- [ ] `src/Enums/ActionType.php` — add `case Skipped = 'skipped'`.

### 4. Events

- [ ] `src/Events/StepSkipped.php` — `StepSkipped(ApprovalRequest $request, ApprovalRequestStep $step)`.

### 5. Engine

- [ ] `src/Engine/ApprovalEngine.php`:
  - [ ] `activateNextStep()` (or a new helper `shouldSkipStep()`):
    - Retrieve condition from the `Step` VO on the `WorkflowDefinition`.
    - Evaluate it against the live approvable model.
    - If false: set `status = skipped`, `completed_at = now()` on the step row,
      log `skipped` action, dispatch `StepSkipped`, recurse to next step index.
  - [ ] Termination condition: if `current_step_index` exceeds all steps (or all
        remaining are skipped), call `finalizeRequest()` as approved.
  - [ ] Handle the edge case: all steps skipped → request approved immediately.

### 6. Test fixtures

- [ ] `tests/Fixtures/Workflows/ExpenseConditionalWorkflow.php` — three steps:
  - Step 1 (always active): manager review.
  - Step 2 (conditional, `amount > 5000`): finance review.
  - Step 3 (always active): final sign-off.

### 7. Tests

- [ ] `tests/Feature/ConditionalStepsTest.php`:
  - [ ] a step with a true condition activates normally
  - [ ] a step with a false condition is skipped, `StepSkipped` fires
  - [ ] skipped step status is `skipped` in the database
  - [ ] engine advances past skipped step to the next one
  - [ ] workflow completes when all non-skipped steps are approved
  - [ ] a workflow where ALL steps are skipped still completes the request
  - [ ] the audit log records a `skipped` action for each skipped step
  - [ ] a conditional step can be parallel (both features compose)

## Acceptance checklist

- [ ] All v0.1 tests pass without modification.
- [ ] All Sprint 1 (`ParallelStepsTest`) tests pass without modification.
- [ ] `ConditionalStepsTest` — all 8 cases pass.
- [ ] PHPStan green at level 6.
- [ ] `CHANGELOG.md` updated under `[Unreleased]`.

## Out of scope

- Class-based `ConditionEvaluator` implementations (contract is defined here,
  but the package ships no bundled implementations beyond closure support).
- Condition evaluation at submit time (deferred — lazy evaluation is the default).
- `when()` on the entire workflow (not per-step) → v0.3.
