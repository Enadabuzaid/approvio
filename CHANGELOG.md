# Changelog

All notable changes to `enadstack/approvio` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
