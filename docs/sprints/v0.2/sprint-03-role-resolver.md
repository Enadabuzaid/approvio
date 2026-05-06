# Sprint 3 — Role-based approver resolver (Spatie Permission)

> **Goal:** A workflow step can declare `.role('finance')` and the engine will
> resolve all users holding that role via `spatie/laravel-permission`. The feature
> is entirely opt-in: the package is not a hard dependency.

## Outcomes

When this sprint is done:

- `WorkflowBuilder` accepts `.role(string $roleName, ?string $guardName = null)`.
- `RoleResolver` resolves users with that role at step-activation time.
- If `spatie/laravel-permission` is not installed, constructing a `RoleResolver`
  (or calling `.role()` on a builder) throws a descriptive
  `MissingDependencyException` with the exact `composer require` command to fix it.
- If the package IS installed, the resolver returns a `Collection<int, Model>` of
  users with the role, using the configured user model.
- The `assigned_via` column on assignee rows is set to `'role'`.
- Unit test for `RoleResolver` in isolation; feature test through a full workflow.

## Tasks

### 1. Exception

- [ ] `src/Exceptions/MissingDependencyException.php`:
  ```php
  class MissingDependencyException extends ApprovioException
  {
      public static function forPackage(string $package, string $feature): self
      {
          return new self(
              "Feature [{$feature}] requires [{$package}]. "
              . "Install it with: composer require {$package}"
          );
      }
  }
  ```

### 2. Resolver

- [ ] `src/Resolvers/Approvers/RoleResolver.php`:
  - Constructor accepts `string $roleName`, `?string $guardName = null`,
    `?string $teamId = null` (for Spatie teams support).
  - On construction: if `!class_exists(\Spatie\Permission\PermissionServiceProvider::class)`,
    throw `MissingDependencyException::forPackage('spatie/laravel-permission', 'RoleResolver')`.
  - `resolve(Model $approvable): Collection` — returns
    `User::role($this->roleName)->get()` (using the configured `approvio.user_model`).

### 3. WorkflowBuilder

- [ ] Add `role(string $roleName, ?string $guardName = null): static` to the
      pending step fluent API. Internally constructs a `RoleResolver` and calls
      `approvers()` with it.

### 4. Tests

- [ ] `tests/Unit/RoleResolverTest.php`:
  - [ ] throws `MissingDependencyException` when Spatie is not installed (mock
        `class_exists` via a test-only seam or test in a separate process).
  - [ ] resolves users by role when Spatie IS present (use a lightweight mock of
        the Spatie query if the package is not in require-dev; OR conditionally
        skip if not installed using `->skip()`).

- [ ] `tests/Feature/RoleResolverIntegrationTest.php` (conditional):
  - Marked with `->skipWithoutPackage('spatie/laravel-permission')` helper (or
    `$this->markTestSkipped()`).
  - [ ] end-to-end: workflow with `.role()`, submit, approver resolved correctly,
        `assigned_via = 'role'`.

### 5. Config

- [ ] `config/approvio.php` — document `user_model` key (already present); add
      comment noting it is used by `RoleResolver`.

### 6. README

- [ ] Add a "Spatie Permission integration" section to README.

## Acceptance checklist

- [ ] All v0.1 and prior sprint tests pass without modification.
- [ ] `RoleResolverTest` (unit) passes regardless of whether Spatie is installed.
- [ ] `RoleResolverIntegrationTest` passes when Spatie is installed; skips cleanly
      when it is not.
- [ ] PHPStan green at level 6.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Spatie Permission team scoping beyond passing `teamId` to the resolver constructor.
- Permission-based resolvers (vs. role-based) → v0.3.
- Other permission packages (Silber Bouncer, etc.) → community packages.
