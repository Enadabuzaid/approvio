# CLAUDE.md

> Instructions for Claude (and Claude Code) when working on **enadstack/approvio**.
>
> Read this file in full before making any change. It is the single source of truth
> for how this codebase wants to be touched.

---

## What this package is

`enadstack/approvio` is a Laravel package that adds approval workflows to any Eloquent
model via a single trait. It is open-source (MIT), targets Laravel 11/12+, and is
built to be production-ready, multi-tenant-aware, and extensible.

The package is **headless by design** — no UI ships in the core. Companion packages
(Filament, Inertia/Vue) come later.

## Mental model

The codebase has six layers. Keep concerns where they belong:

```
Public API   →  Approvable trait, HasApprovalActions trait, Approvio facade
Workflow     →  Workflow class, WorkflowBuilder, WorkflowDefinition (immutable VOs)
Engine       →  ApprovalEngine (orchestrator), StateMachine (transition guards)
Resolvers    →  ApproverResolver, TenantResolver, WorkflowSource (contracts + impls)
Persistence  →  Eloquent models + migrations
Integration  →  Events, Exceptions, Strategies
```

A change that crosses two layers is usually a smell. Stop and ask whether the layers
need a new contract instead.

## Non-negotiables

These rules exist because violating them creates bugs that are very hard to find later.

1. **Every PHP file starts with `<?php` then a blank line then `declare(strict_types=1);`.**
2. **Every public method has typed parameters and a return type.** No `mixed` unless genuinely necessary.
3. **No business logic in models.** Models hold relationships, casts, and trivial accessors. Logic lives in the Engine and Strategies.
4. **No business logic in the trait.** The `Approvable` trait is a thin facade over `ApprovalEngine`.
5. **Wrap every state-changing operation in `DB::transaction(...)`.** Submit, approve, reject, cancel — all transactional.
6. **The audit log (`ApprovalAction`) is append-only.** Never update or delete rows from it. The model has `UPDATED_AT = null` to enforce this.
7. **Workflow definitions are immutable value objects.** Once built, never mutated.
8. **Resolvers are stateless.** Inject dependencies via the constructor. Never store request state.
9. **All extension points are interfaces in `Contracts/`.** If you find yourself adding a `if ($strategy instanceof X)` check, you've gone wrong — add a method to the contract.
10. **Tenant scoping happens at one place: `TenantResolver`.** Never read `tenant_id` directly from an Eloquent model in the engine.

## Coding standards

- **PSR-12** with Laravel conventions (4-space indent, opening brace on its own line for classes).
- **Imports sorted** alphabetically, grouped by namespace root (`Enadstack\` first, then `Illuminate\`, then global).
- **Constructor property promotion** for value objects and services with simple dependencies.
- **Named arguments** when calling methods with 3+ parameters or when readability benefits.
- **Enums (backed)** for any closed set of strings. Never use string constants.
- **No Facades inside `src/Engine/` or `src/Workflow/`.** Use injected dependencies. Facades are fine in the trait and the public `Approvio` class.

## Test discipline

- Tests are written in **Pest 3**. New tests go in `tests/Feature` (end-to-end) or `tests/Unit` (single-class behavior).
- **Every bug fix lands with a regression test** that fails before the fix and passes after.
- **Every new public method gets a feature test** that exercises it.
- Use the **fixtures in `tests/Fixtures/`** rather than building new test models inline.
- Run `vendor/bin/pest` and `vendor/bin/phpstan analyse` before considering work done.

## Common tasks — how to approach them

### "Add a new approver type" (e.g., role-based, relationship-based)
1. Create a class implementing `Enadstack\Approvio\Contracts\ApproverResolver`.
2. Place in `src/Resolvers/Approvers/`.
3. Wire into `WorkflowBuilder::approvers()` if the new resolver should be reachable via the fluent API.
4. Write unit tests for the resolver in isolation.
5. Write a feature test exercising it through a workflow.

### "Add a new strategy"
1. Implement `Enadstack\Approvio\Contracts\ApprovalStrategy`.
2. Place in `src/Strategies/`.
3. Document the column/table requirements in the docblock.
4. Write a feature test that submits, approves, and rejects through the new strategy and verifies model state.

### "Add a new event"
1. Create the event class in `src/Events/` using `Dispatchable` + `SerializesModels`.
2. Dispatch it from `ApprovalEngine` at the right transition.
3. Update the Events table in `README.md`.
4. Update `CHANGELOG.md`.

### "Fix a bug"
1. **Write a failing test first** that reproduces the bug.
2. Make the smallest change that makes it pass.
3. Confirm the rest of the suite still passes.
4. Add a `CHANGELOG.md` entry under `[Unreleased]`.

### "Add a new tenant resolver" (e.g., for Stancl/Tenancy)
1. Implement `Enadstack\Approvio\Contracts\TenantResolver`.
2. Place in `src/Resolvers/Tenants/`.
3. Add config documentation in `config/approvio.php` comments.
4. Document setup in `README.md` under "Multi-tenancy".

## What NOT to do

- ❌ Do not couple the engine to a specific tenancy package. The engine reads from `TenantResolver` only.
- ❌ Do not add a UI dependency to the core package. Filament, Inertia, Livewire — all live in companion packages.
- ❌ Do not add Spatie Permission, Stancl Tenancy, or any other heavy package as a hard `require`. They are optional integrations behind contracts.
- ❌ Do not change migration files in place after release. Add new migrations.
- ❌ Do not break the public API (trait method signatures, facade methods, event constructor signatures) without a major version bump.
- ❌ Do not write to the audit log table directly from outside the engine. Use `ApprovalEngine::logAction()` (or expose a public wrapper if needed).

## When in doubt

- **Read the contract first.** If it doesn't already exist, you may be adding a new extension point — that's fine, design it deliberately.
- **Look for a similar feature** already in the codebase and mirror its structure.
- **Prefer adding a new file to extending an existing one.** Small, focused classes age better.
- **Ask the human before introducing a new dependency.** The package's value comes from being lean.
