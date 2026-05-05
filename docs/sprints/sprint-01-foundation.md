# Sprint 1 — Foundation

> **Goal:** A composer-installable package that boots in a Laravel app,
> publishes migrations that run cleanly, and has a passing (empty) test suite.
>
> **Why it matters:** Everything else stands on this. If the service provider
> doesn't boot, no engine work can be tested.

## Outcomes

When this sprint is done, you can:

- Clone the repo, run `composer install`, and `vendor/bin/pest` returns green
  (with at least one passing smoke test).
- Run `php artisan vendor:publish --tag=approvio-migrations` in a host app
  and have all 5 migrations land in `database/migrations`.
- Run `php artisan migrate` and create all tables without errors.
- See the `Approvio` facade resolve from the container.

## Tasks

### 1. Repo skeleton

- [ ] `composer.json` with correct name, requires, autoload, and Laravel
      package discovery (`extra.laravel.providers` and `aliases`).
- [ ] `.gitignore`, `LICENSE.md`, `README.md` (placeholder), `CHANGELOG.md`,
      `CONTRIBUTING.md`.
- [ ] `phpunit.xml`, `phpstan.neon`.
- [ ] `.github/workflows/tests.yml` and `.github/workflows/static-analysis.yml`.

### 2. Service provider + config

- [ ] `src/ApprovioServiceProvider.php` — registers config, publishes config
      and migrations, loads migrations.
- [ ] `config/approvio.php` — all knobs documented inline.
- [ ] Bind `Approvio` and `ApprovalEngine` (stubs are fine for now) into
      the container.

### 3. Enums

- [ ] `RequestStatus`, `StepStatus`, `AssigneeStatus`, `ActionType`, `QuorumRule`.

### 4. Migrations

- [ ] `approval_workflows` (with `tenant` morphs)
- [ ] `approval_requests` (polymorphic approvable + tenant + requester, snapshot,
      context, pending_changes, strategy, status, current_step_index, etc.)
- [ ] `approval_request_steps` (step_index, type, quorum_rule, quorum_count, status)
- [ ] `approval_step_assignees` (polymorphic assignee, polymorphic delegated_to)
- [ ] `approval_actions` (polymorphic actor, append-only — no `updated_at`)

### 5. Models (lean, just relationships and casts)

- [ ] `ApprovalWorkflow`, `ApprovalRequest`, `ApprovalRequestStep`,
      `ApprovalStepAssignee`, `ApprovalAction`.
- [ ] All five with proper `getTable()` driven by config.
- [ ] Enum casts applied to status columns.

### 6. Test harness

- [ ] `tests/TestCase.php` extending `Orchestra\Testbench\TestCase`.
- [ ] `tests/Pest.php`.
- [ ] One smoke test in `tests/Feature/ServiceProviderTest.php`:

```php
it('boots the service provider', function () {
    expect(app(\Enadstack\Approvio\Approvio::class))->toBeInstanceOf(\Enadstack\Approvio\Approvio::class);
});

it('runs all migrations cleanly', function () {
    $tables = ['approval_workflows', 'approval_requests', 'approval_request_steps', 'approval_step_assignees', 'approval_actions'];
    foreach ($tables as $t) {
        expect(\Illuminate\Support\Facades\Schema::hasTable($t))->toBeTrue();
    }
});
```

## Acceptance checklist

- [ ] `composer install` works without errors.
- [ ] `vendor/bin/pest` passes (smoke tests only).
- [ ] `vendor/bin/phpstan analyse` passes.
- [ ] All 5 migrations exist and run cleanly under SQLite (Testbench).
- [ ] Service provider is auto-discovered.
- [ ] `CHANGELOG.md` `[Unreleased]` mentions "initial scaffolding".

## Out of scope

- The actual `ApprovalEngine` orchestration (sprint 2).
- The `Approvable` trait (sprint 2).
- Strategies (sprint 4).
- Workflow builder (sprint 2).
