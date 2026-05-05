# Sprint 2 — Engine core

> **Goal:** Submit a model for approval, have the engine create the request,
> snapshot the data, materialize the steps, assign approvers, and complete
> the request when the single step is approved.
>
> **Why it matters:** This is the heart of the package. After this sprint,
> the smallest meaningful workflow works end-to-end.

## Outcomes

When this sprint is done:

- A model using the `Approvable` trait can call `requestApproval('my-flow')`
  and get back an `ApprovalRequest` row plus its child step + assignees.
- A user with `HasApprovalActions` can call `$user->approve($request)` and
  the request completes.
- The audit log captures `submitted`, `step_activated`, `approved`, and
  `step_completed` actions.
- All lifecycle events fire.

## Tasks

### 1. Contracts

- [ ] `src/Contracts/ApproverResolver.php`
- [ ] `src/Contracts/TenantResolver.php`
- [ ] `src/Contracts/WorkflowSource.php`
- [ ] `src/Contracts/ApprovalStrategy.php` (interface only — implementations in sprint 4)

### 2. Workflow layer

- [ ] `src/Workflow/Step.php` — immutable VO.
- [ ] `src/Workflow/WorkflowDefinition.php` — immutable VO with steps.
- [ ] `src/Workflow/WorkflowBuilder.php` — fluent API. `step('name')->approvers(closure)`.
- [ ] `src/Workflow/Workflow.php` — abstract base class users extend.
- [ ] `src/Workflow/Sources/CodeWorkflowSource.php` — reads `$approvalWorkflows` from the model.

### 3. Resolvers (just enough for v0.1)

- [ ] `src/Resolvers/Approvers/DirectUserResolver.php` — wraps a closure.
- [ ] `src/Resolvers/Tenants/NullTenantResolver.php` — always null.

### 4. State machine

- [ ] `src/Engine/StateMachine.php` — transition map + `assertCanTransition()`.

### 5. Engine

- [ ] `src/Engine/ApprovalEngine.php` with:
  - [ ] `submit()` — wraps in DB transaction, creates request, materializes
        steps, takes snapshot, calls strategy `onSubmit`, activates first
        step, dispatches `ApprovalRequested`.
  - [ ] `approve()` — refreshes request, guards terminal state, asserts actor
        is an active assignee, marks assignee approved, completes step,
        dispatches `StepApproved`, advances or completes request.
  - [ ] `advanceOrComplete()` — moves to next step or finalizes the request.
  - [ ] `activateNextStep()` — resolves approvers, creates assignee rows,
        activates the step, dispatches `StepActivated`.
  - [ ] `logAction()` — writes audit row with IP, user-agent (configurable).

### 6. Public surface

- [ ] `src/Concerns/Approvable.php` — `requestApproval()`, `requestApprovalFor()`,
      `pendingApprovalRequest()`, `hasPendingApproval()`, etc.
- [ ] `src/Concerns/HasApprovalActions.php` — `pendingApprovals()`, `approve()`,
      `approvalActions()`.
- [ ] `src/Approvio.php` — facade-backing class.
- [ ] `src/Facades/Approvio.php`.

### 7. Events (just submit + approve flow)

- [ ] `ApprovalRequested`, `StepActivated`, `StepApproved`, `ApprovalCompleted`.

### 8. Exceptions

- [ ] `ApprovioException`, `WorkflowNotFoundException`,
      `InvalidStateTransitionException`, `UnauthorizedActionException`.

### 9. Test fixtures

- [ ] `tests/Fixtures/Models/TestUser.php`
- [ ] `tests/Fixtures/Models/TestExpense.php` (with placeholder strategy for now)
- [ ] `tests/Fixtures/Workflows/ExpenseSingleStepWorkflow.php`

### 10. Tests

- [ ] `tests/Unit/StateMachineTest.php` — transition rules.
- [ ] `tests/Unit/WorkflowBuilderTest.php` — builds definitions, errors on
      missing approvers.
- [ ] `tests/Feature/BasicApprovalFlowTest.php` covering:
  - [ ] creates an approval request when a model is submitted
  - [ ] creates request steps from the workflow definition
  - [ ] assigns the resolved approvers to the active step
  - [ ] snapshots the approvable at submit time
  - [ ] completes the request when the only step is approved
  - [ ] writes audit log entries for every action
  - [ ] dispatches all four events at the right time

## Acceptance checklist

- [ ] All Sprint 1 acceptance items still pass.
- [ ] `BasicApprovalFlowTest` passes — every test.
- [ ] `StateMachineTest` and `WorkflowBuilderTest` pass.
- [ ] PHPStan still green.
- [ ] You can copy the README's quickstart into a fresh app and submit + approve
      one request manually.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Multi-step workflows (sprint 3).
- Rejection (sprint 3).
- Cancellation (sprint 3).
- Real strategies (sprint 4) — use a no-op placeholder.
- Tenant scoping beyond null (sprint 4).
