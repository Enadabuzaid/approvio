# Changelog

All notable changes to `enadstack/approvio` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-05-09

### Fixed

- **Issue #7 — Silent stall when workflow class removes a step.** `ApprovalEngine::activateNextStep()` previously returned silently when `stepAt()` returned null (DB has a step row the PHP class no longer defines). It now throws `WorkflowStepNotFoundException` with the workflow slug, step index, and a resolution hint. Regression test added (`WorkflowClassMutationTest`).
- **Issue #8 — Silent n_of_m quorum always met when count is missing.** `WorkflowBuilder::PendingStep::quorum()` previously stored a null count without validation; the engine cast it to `0`, meaning the quorum was immediately satisfied. It now throws `\InvalidArgumentException` at build time when the rule is `n_of_m` and the count is null or less than 1. Regression tests added (`WorkflowBuilderTest`).

### Added

- `WorkflowStepNotFoundException` — thrown when the engine cannot find a step definition for an index that exists in the database.

## [0.2.0] - 2026-05-07

### Breaking changes

- **`ApproverResolver` contract gains `assignedVia(): string`.** Any application that implemented `ApproverResolver` directly before v0.2 must add this method returning a source label (e.g. `'custom'`). Built-in resolvers (`DirectUserResolver`, `RoleResolver`, `RelationshipResolver`) implement it transparently and are unaffected. v0.1 code that only **uses** the built-in resolvers requires no changes.

### Added

- `ApprovalEngine::resubmit()` — creates a new request from a rejected one; guards against non-rejected or already-active-child states; forwards context and `pending_changes`; links via `parent_request_id`; logs `ActionType::Resubmitted` on the original; dispatches `RequestResubmitted`.
- `Approvable::resubmit(?Model, array, ?array)` — convenience method targeting the most recent rejected request for the approvable; `$changes = null` carries forward `pending_changes` from the parent.
- `Approvio::resubmit()` — facade-backed resubmit method.
- `RequestResubmitted` event — fires with `$newRequest` and `$originalRequest` after a successful resubmit.
- `ActionType::Resubmitted` — recorded on the original request's audit log.
- `approval_requests.parent_request_id` — self-referential nullable FK added via migration `2024_01_01_000006_add_parent_request_id_to_approval_requests.php`; `nullOnDelete`.
- `ApprovalRequest::parent()` / `children()` — Eloquent relations for the parent–child chain.
- Resubmit section in `README.md`.
- `EscalateApprovalsCommand` (`approvio:escalate`) — scans overdue active steps and expired requests; escalates or expires each one.
- `ApprovalEngine::escalateStep()` — re-resolves the workflow, marks Pending assignees `Escalated`, adds escalation target(s) as new Pending assignees, logs `ActionType::Escalated`, dispatches `StepEscalated`.
- `ApprovalEngine::expire()` — transitions a request to `RequestStatus::Expired`, logs, dispatches `ApprovalExpired`.
- `StepEscalated` event — fires with `$request`, `$step`, `$originalAssignee`.
- `ApprovalExpired` event — fires with `$request` when a request expires.
- `EscalationException` — `emptyTarget()` named constructor for misconfigured escalation targets.
- `AssigneeStatus::Escalated` — marks an assignee whose slot was superseded by escalation; excluded from `All` quorum denominator alongside `Delegated`.
- `WorkflowBuilder::deadline(int $hours)` — sets `deadline_at` on the step at activation time.
- `WorkflowBuilder::escalateTo(Closure)` — registers escalation resolver on the step.
- `Step::$escalateTo` — new immutable field on the `Step` value object.
- `Step::toArray()` now includes `has_escalation: bool`.
- `config/approvio.php` — `schedule.escalate_cron` key for apps that opt-in to automatic scheduling.
- Escalation and deadlines section in `README.md`.
- `StateMachineTest` — 2 new cases: `in_review → expired` and `expired` terminal state.
- `ApprovalEngine::delegate()` — one-level delegation; marks original assignee as `Delegated`, creates a new pending assignee row for the delegate with `assigned_via = 'delegation'`.
- `HasApprovalActions::delegate()` — actor-shorthand: `$manager->delegate($request, $deputy, 'OOO')`.
- `Approvio::delegate()` — facade-backed delegation method.
- `DelegationException` — `notAnAssignee()`, `cannotDelegateFurther()`, `alreadyDelegated()` named constructors.
- `RequestDelegated` event — fires after a successful delegation with `$from` and `$to` assignee references.
- `ActionType::Delegated` — recorded in audit log on every delegation.
- `ApprovalEngine::isStepQuorumMet()` now excludes `Delegated` rows from the `All` quorum denominator so vacated slots do not block completion.
- Delegation section in `README.md` documenting one-level-only policy and non-revocability.
- `RelationshipResolver` — resolves approvers by walking a dot-notation Eloquent relation chain; returns empty collection gracefully when any segment is null.
- `WorkflowBuilder::relation(string $chain)` — fluent shorthand for relationship-based steps.
- PHPStan ratcheted from level 6 to level 7 — 0 errors across all source files.
- Relationship-based approvers section in `README.md`.
- `RoleResolver` — resolves approvers by Spatie Permission role name; throws `MissingDependencyException` when `spatie/laravel-permission` is not installed.
- `MissingDependencyException` — descriptive exception for missing optional dependencies with `composer require` hint.
- `ApproverResolver::assignedVia(): string` — new contract method; `DirectUserResolver` returns `'direct'`, `RoleResolver` returns `'role'`.
- `WorkflowBuilder::role(string $roleName, ?string $guardName)` — fluent shorthand for role-based steps.
- Spatie Permission integration section in `README.md`.
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

- PHPStan ratcheted from level 7 to level 8 — 0 errors, 0 suppressions. All `fresh()` call-sites replaced with `refresh()->load()` / `refresh()` to produce non-nullable return types; `approvable` null-guards added in `activateNextStep()`, `escalateStep()`, and `resubmit()`.
- CI matrix expanded to PHP 8.2/8.3/8.4 × Laravel 11/12 × SQLite/MySQL/PostgreSQL (18 cells).
- Second CI job (`spatie-integration`) installs `spatie/laravel-permission` and runs the full suite with `RoleResolverIntegrationTest` un-skipped.
- `SpatieTestUser` test fixture (extends `TestUser`, adds `HasRoles`); loaded conditionally via `require_once` only when Spatie is installed.
- `ExpenseRoleWorkflow` test fixture — uses `->role('manager')` to drive `RoleResolverIntegrationTest`.
- `TestCase` conditionally registers `Spatie\Permission\PermissionServiceProvider` and its migrations when Spatie is installed.
- `RoleResolverIntegrationTest` fixed: wrong workflow slug corrected (`'submission'` → `'role'`), uses `SpatieTestUser` for role assignment.

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
