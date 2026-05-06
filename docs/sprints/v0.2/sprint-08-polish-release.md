# Sprint 8 — Polish + release v0.2.0

> **Goal:** PHPStan at level 8, CI matrix expanded to MySQL and PostgreSQL,
> README updated for all v0.2 features, `v0.2.0` tagged and published.

## Outcomes

When this sprint is done:

- PHPStan passes at level 8 with 0 errors across all analysed files.
- GitHub Actions runs 18 CI cells: PHP 8.2/8.3/8.4 × Laravel 11/12 × SQLite/MySQL/PostgreSQL.
- README covers all v0.2 public API additions with clear examples.
- CHANGELOG `[Unreleased]` is renamed to `[0.2.0]` with the release date.
- `v0.2.0` tag pushed and visible on Packagist.

## Tasks

### 1. PHPStan level 8

- [ ] Change `level: 7` to `level: 8` in `phpstan.neon`.
- [ ] Fix all new errors introduced at level 8 (typically: stricter return type
      inference, `mixed` usage in closures, unresolved generics on Eloquent Builder
      queries).
- [ ] For each error: fix the root cause if possible; add a scoped `@phpstan-ignore`
      with a parenthetical reason only as a last resort.
- [ ] Run `vendor/bin/phpstan analyse` and confirm `[OK] No errors`.

### 2. CI matrix expansion

- [ ] `.github/workflows/tests.yml` — add database dimension to the matrix:
  ```yaml
  matrix:
    php: ['8.2', '8.3', '8.4']
    laravel: ['11.*', '12.*']
    database: ['sqlite', 'mysql', 'postgres']
    include:
      - laravel: '11.*'
        testbench: '9.*'
      - laravel: '12.*'
        testbench: '10.*'
  ```
- [ ] Add `services:` blocks for MySQL and PostgreSQL in the workflow.
- [ ] Add a `defineEnvironment` override in `TestCase` (or a separate base class
      `MySqlTestCase` / `PostgresTestCase`) that reads `DB_CONNECTION` from the
      environment and configures the connection accordingly.
- [ ] Ensure all tests pass on all three database drivers (SQLite quirks —
      e.g., JSON extraction — must be verified against MySQL and PostgreSQL).

### 3. README updates

- [ ] **Parallel steps section** — `.parallel()`, `.quorum()` API with example.
- [ ] **Conditional steps section** — `.when()` with a practical example (CFO step
      only when `amount > 10000`).
- [ ] **Spatie Permission section** — installation + `.role()` usage.
- [ ] **Relationship resolver section** — `.relation()` with example.
- [ ] **Delegation section** — `$user->delegate($request, $deputy)`.
- [ ] **Escalation + deadlines section** — `.deadline()` + `.escalateTo()` +
      artisan command setup.
- [ ] **Resubmit section** — `$model->resubmit()` with DraftApproval note.
- [ ] **Events table** — add the 4 new events (`StepSkipped`, `StepEscalated`,
      `RequestDelegated`, `RequestResubmitted`, `ApprovalExpired`).
- [ ] **Upgrade guide** — from v0.1 to v0.2 (run the one new migration;
      no API changes required).
- [ ] Update badges if needed.

### 4. Inline docblocks audit

- [ ] Every new public class added in Sprints 1–7 has a class-level docblock.
- [ ] Every new public method has a docblock where types alone don't tell the
      full story.
- [ ] Every new contract lists known implementations.

### 5. CHANGELOG

- [ ] Review `[Unreleased]` section — ensure every Sprint 1–7 feature is listed.
- [ ] Rename `[Unreleased]` → `[0.2.0] - YYYY-MM-DD`.

### 6. composer.json

- [ ] Bump no version constraints (the package does not pin its own version;
      Packagist reads the git tag).
- [ ] Verify all keywords still accurate; add any new relevant keywords.

### 7. Release

- [ ] `git tag v0.2.0`
- [ ] `git push origin v0.2.0`
- [ ] Verify Packagist auto-updates within 5 minutes (webhook should be wired
      from v0.1 release).
- [ ] Verify `composer require enadstack/approvio:^0.2` works on a clean project.

## Acceptance checklist

- [ ] All v0.1 tests pass without modification on all 18 CI matrix cells.
- [ ] All v0.2 tests pass on all 18 CI matrix cells.
- [ ] PHPStan green at **level 8** — 0 errors.
- [ ] CI matrix: 18 cells all green.
- [ ] README upgrade guide present and accurate.
- [ ] `CHANGELOG.md` has a `[0.2.0]` section with release date.
- [ ] `v0.2.0` tag pushed and visible on Packagist.
- [ ] At least one manual end-to-end smoke test: submit a parallel step with
      `n_of_m` quorum, have one approver delegate, the delegate approve, confirm
      the step does not complete until quorum is met.

## Out of scope

- Full docs site → v1.0.
- Example app repository → v0.3.
- Default notification classes → v0.4.
