# Changelog

All notable changes to `enadstack/approvio` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `WorkflowBuilder::when(Closure)` — marks a step as conditional; the closure is evaluated against the live model at activation time.
- `ConditionEvaluator` contract (`src/Contracts/ConditionEvaluator.php`) — extension point for class-based conditions.
- `StepSkipped` event — fires whenever a step is skipped due to a false condition.
- `ActionType::Skipped` enum case — recorded in the audit log for each skipped step.
- `ApprovalEngine::skipStep()` — marks a step as skipped, logs the action, dispatches `StepSkipped`, and advances to the next step.
- `ApprovalEngine::finalizeAsApproved()` — extracted from `advanceOrComplete()`; reused by the skip path so all-skipped workflows still complete.
- Conditional steps section in `README.md` with live-model vs snapshot semantics documented.
- `StepType` enum (`Sequential`, `Parallel`) — cast on `ApprovalRequestStep`.
- `WorkflowBuilder::parallel()` — marks a step as parallel (all assignees activated simultaneously).
- `WorkflowBuilder::quorum(string $rule, ?int $count)` — sets quorum rule (`any`, `all`, `n_of_m`) on a step.
- `ApprovalEngine::isStepQuorumMet()` — evaluates whether the configured quorum threshold is satisfied after each approval.
- `ApprovalEngine::shouldStepTerminateOnRejection()` — policy hook (always `true` in v0.2; configurable threshold planned for v0.3).
- Parallel steps section in `README.md` documenting quorum rules and rejection policy.

### Changed

- `ApprovalEngine::approve()` now evaluates quorum before completing a step, enabling partial-approval accumulation on parallel steps.
- `Step::$type` changed from `string` to `StepType` enum; `PendingStep::$type` likewise.
- `PendingStep::toStep()` now forwards `quorumRule` and `quorumCount` to the immutable `Step` value object.

## [0.1.0] - 2026-05-05

### Added

- Initial package scaffolding: service provider, config, 5 migrations,
  5 Eloquent models, enums, contracts, engine, strategies, workflow layer,
  resolvers, events, exceptions, traits, and Pest test harness.
- `Approvable` trait for any Eloquent model.
- `HasApprovalActions` trait for User-like models.
- Code-defined workflows via `Workflow` base class and `WorkflowBuilder`.
- Sequential multi-step workflows with direct user approvers.
- `SoftApproval` and `DraftApproval` strategies.
- Append-only audit log via `ApprovalAction` (`UPDATED_AT = null`).
- State-machine-guarded request transitions.
- Tenant resolver contract with `NullTenantResolver` and `ColumnTenantResolver`.
- Events: `ApprovalRequested`, `StepActivated`, `StepApproved`, `StepRejected`,
  `ApprovalCompleted`, `ApprovalRejected`, `ApprovalCancelled`.
- Pest test suite covering submit / approve / reject / cancel happy paths,
  multi-step sequencing, and both strategies.
- GitHub Actions matrix: PHP 8.2/8.3/8.4 × Laravel 11/12.

### Added (Sprint 4 — Strategies + tenancy)

- `TenancyTest`: 4 new tests covering `NullTenantResolver`, `ColumnTenantResolver`
  reading from the approvable's `tenant` relation, and auth-user fallback.
- `TestTenant` fixture model and `test_tenants` sandbox table for tenancy tests.
- `tenant()` relation on `TestExpense` and `TestUser` fixtures.
- `TestUser` now implements `Authenticatable` (required for `auth()->setUser()` in tests).

### Verified (Sprint 4 — Strategies + tenancy)

- `SoftApproval` and `DraftApproval` strategies fully implemented and covered by
  dedicated feature test files.
- `ColumnTenantResolver` and `NullTenantResolver` implemented; engine writes
  `tenant_type` / `tenant_id` onto the request at submit time.
- `resolveApprovalStrategy()` on `Approvable` reads `$approvalStrategy` property
  with config fallback; all strategy tests pass for both implementations.

### Verified (Sprint 3 — Multi-step + reject + cancel)

- Multi-step workflows activate one step at a time; second step stays `pending`
  until the first is approved.
- Rejection at any step terminates the request immediately; remaining steps
  stay `pending` — asserted in `RejectionTest`.
- Cancellation blocked at terminal states with `InvalidStateTransitionException`.
- Double-approval and wrong-actor guards both enforced and tested.
- `StepRejected`, `ApprovalRejected`, `ApprovalCancelled` events dispatched
  correctly and covered in tests.
- `MultiStepApprovalTest` (5), `RejectionTest` (4), `CancellationTest` (3)
  all pass.

### Verified (Sprint 2 — Engine core)

- `ApprovalEngine::submit()`, `approve()`, `advanceOrComplete()`, `activateNextStep()`,
  and `logAction()` all implemented and passing full test coverage.
- Single-step happy path: submit → step activated → approve → request completed,
  with audit log entries and all four lifecycle events dispatched correctly.
- `BasicApprovalFlowTest`, `StateMachineTest`, and `WorkflowBuilderTest` all pass.

### Fixed

- `CHANGELOG.md` restructured: `[0.1.0] - TBD` moved back to `[Unreleased]`
  to comply with Keep-a-Changelog convention (version dated only at tag time).
- `phpunit.xml`: removed `beStrictAboutCoversAnnotation` attribute invalid in
  PHPUnit 11+.
- `phpstan.neon`: marked `DatabaseWorkflowSource.php` exclusion path as
  optional (`?`) so PHPStan does not error when the file is absent.
- `CodeWorkflowSource`: accessing protected `$approvalWorkflows` directly via
  Eloquent's `__get()` silently returned `null`, causing every `submit()` call
  to throw `WorkflowNotFoundException`. Fixed by exposing the map through a
  public `getApprovalWorkflows()` accessor on the `Approvable` trait and
  updating `CodeWorkflowSource` to call it. Regression test added.
- `approval_actions` migration: removed duplicate `(actor_type, actor_id)` index
  — `nullableMorphs()` already creates it; the explicit `$table->index()` call
  caused a fatal "index already exists" error when migrations were first executed.
