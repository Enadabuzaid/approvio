# AGENTS.md

> Operating manual for any AI coding agent (Claude Code, Cursor, Codex, Aider, etc.)
> working on **enadstack/approvio**.
>
> If you only read one file, read this one.

---

## Project at a glance

- **Package:** `enadstack/approvio`
- **Type:** Laravel package, MIT, open-source.
- **Stack:** PHP 8.2+, Laravel 11/12.
- **Tests:** Pest 3, Orchestra Testbench, SQLite in-memory.
- **Static analysis:** Larastan / PHPStan.
- **CI:** GitHub Actions matrix (PHP × Laravel).
- **Status:** v0.1 alpha — public API still settling.

## Workflow contract for agents

When you receive a task, follow this loop:

1. **Read.** Open the files mentioned in the task. Read `CLAUDE.md` if you haven't already this session.
2. **Plan.** State in 3–5 bullets what you're about to do and which files you'll touch. Stop and confirm with the human if the plan crosses architectural layers (see CLAUDE.md "Mental model").
3. **Test first when fixing bugs.** Add a failing test before changing source.
4. **Implement.** Make the smallest change that satisfies the test or feature.
5. **Verify.** Run `vendor/bin/pest` and `vendor/bin/phpstan analyse`. Both must pass.
6. **Document.** Update `CHANGELOG.md` under `[Unreleased]`. Update `README.md` if the public API changed.
7. **Summarize.** End your turn with: files changed, tests added, anything skipped, anything risky.

## Hard rules

These rules are enforced by code review. Violating any is grounds for a rejected PR.

- **Never edit a published migration.** Add a new one.
- **Never break the public API in a minor version.** The public API is: `Approvable`, `HasApprovalActions`, the `Approvio` facade, every event class constructor, every published exception class, and every contract under `src/Contracts/`.
- **Never add a hard dependency** without asking. The current `require` block is intentionally minimal.
- **Never write tests that depend on internet access.**
- **Never use `Eloquent::unguard()`** or any other "let everything through" mechanism. Use `$guarded = []` per model with intent.
- **Never silence PHPStan errors with `@phpstan-ignore` without a comment** explaining why.
- **Never commit** `.env`, `vendor/`, `composer.lock` (this is a library, not an app), or `.phpunit.cache`.

## Soft rules (judgment calls)

- **Prefer composition over inheritance.** New behavior usually means a new class implementing a contract, not extending an existing class.
- **Prefer immutable value objects** for anything that represents config or specification (workflows, steps, definitions).
- **Prefer named constructors** (`static::for(...)`, `Exception::between(...)`) over passing flag arrays.
- **Use Pest's expectations API**, not `$this->assert*`. Tests are written declaratively.
- **One concept per file.** A 200-line class with a clear purpose is fine. A 200-line class doing three things is not.

## Repository layout

```
.
├── .github/workflows/         CI: tests + static analysis
├── config/approvio.php        User-facing config (publishable)
├── database/migrations/       5 migrations, do not edit after release
├── src/
│   ├── ApprovioServiceProvider.php
│   ├── Approvio.php           Facade-backing class
│   ├── Concerns/              Approvable, HasApprovalActions traits
│   ├── Contracts/             All extension points live here
│   ├── Engine/                Orchestration + state machine
│   ├── Enums/                 RequestStatus, StepStatus, ActionType, etc.
│   ├── Events/                Lifecycle events
│   ├── Exceptions/            Domain exceptions
│   ├── Facades/               Approvio facade
│   ├── Models/                Eloquent models, lean
│   ├── Resolvers/             Approver + Tenant resolver implementations
│   ├── Strategies/            SoftApproval, DraftApproval
│   └── Workflow/              Workflow class, builder, definition
└── tests/
    ├── Feature/               End-to-end behavior
    ├── Fixtures/              Test models + workflows
    ├── Unit/                  Single-class tests
    ├── Pest.php
    └── TestCase.php
```

## Common commands

```bash
# Install
composer install

# Run all tests
vendor/bin/pest

# Run a single test file
vendor/bin/pest tests/Feature/BasicApprovalFlowTest.php

# Run with filter
vendor/bin/pest --filter="creates an approval request"

# Static analysis
vendor/bin/phpstan analyse

# Both, as CI runs them
vendor/bin/pest && vendor/bin/phpstan analyse
```

## Definition of "done" for a task

A task is done when **all** are true:

- ✅ Code compiles and runs.
- ✅ All tests pass (`vendor/bin/pest`).
- ✅ Static analysis passes (`vendor/bin/phpstan analyse`).
- ✅ New behavior has a feature or unit test.
- ✅ Bug fixes have a regression test.
- ✅ `CHANGELOG.md` updated under `[Unreleased]`.
- ✅ `README.md` updated if public API changed.
- ✅ No commented-out code, no debug `dd()` / `dump()` / `var_dump()`.
- ✅ Commit message follows Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`).

## How to think about scope

This package is small on purpose. Before adding a feature, ask:

1. **Does this belong in core, or in a companion package?** UI, framework integrations, and opinionated workflows belong in companion packages.
2. **Can this be a userland extension point instead of a hard-coded feature?** If yes, build the extension point and let userland or a companion package implement it.
3. **Does this make the package harder to learn?** Every new public method is documentation debt.

When unsure, choose the smaller change. v0.x is for finding the right shape; v1.x is for stability.

## Communication norms with the human

- **Surface ambiguity early.** If a task could be interpreted two ways, ask before coding.
- **Flag risk explicitly.** "I changed X and that could affect Y" is more valuable than a clean diff.
- **Be honest about what you didn't test.** "I couldn't run the suite because…" is fine. Pretending you ran it is not.
- **Suggest follow-ups.** If you noticed something off but it's outside the task, mention it; don't silently fix it.
