# Sprint 4 — Relationship-based approvers + PHPStan level 7

> **Goal:** A workflow step can declare `.relation('user.department.head')` and
> the engine follows the dot-notation chain on the approvable model at activation
> time. PHPStan is ratcheted from level 6 to level 7 with zero errors.

## Outcomes

When this sprint is done:

- `WorkflowBuilder` accepts `.relation(string $chain)`.
- `RelationshipResolver` walks the dot-notation chain, loading each segment as an
  Eloquent relation. The final node may be a `Model` (wrapped in a collection) or
  a `Collection<int, Model>`.
- If any segment in the chain is null or missing, the resolver returns an empty
  collection (no exception — same graceful behaviour as returning zero approvers
  in v0.1).
- The `assigned_via` column is set to `'relationship'` on resolved assignee rows.
- PHPStan runs clean at level 7 across all 42+ analysed files.

## Tasks

### 1. Resolver

- [ ] `src/Resolvers/Approvers/RelationshipResolver.php`:
  - Constructor: `__construct(private string $chain)`.
  - `resolve(Model $approvable): Collection`:
    - Split `$chain` on `.` into segments.
    - Walk segments via `$current = $current->{$segment}`.
    - If any step returns `null`, return empty collection.
    - If final value is a `Model`, wrap in `collect([$final])`.
    - If final value is a `Collection`, return it filtered to `Model` instances.
    - If final value is anything else, return empty collection.
  - Each segment access uses `method_exists()` guard (same pattern as
    `ColumnTenantResolver`) with `@phpstan-ignore` only where unavoidable.

### 2. WorkflowBuilder

- [ ] Add `relation(string $chain): static` to the pending step fluent API.

### 3. PHPStan ratchet

- [ ] Change `level: 6` to `level: 7` in `phpstan.neon`.
- [ ] Fix any new errors surfaced at level 7 (likely `missingType.iterableValue`
      on untyped Collections and nullable return paths in the engine).
- [ ] DO NOT suppress errors that can be fixed — only add `@phpstan-ignore` for
      genuinely dynamic access patterns (like Eloquent magic).

### 4. Tests

- [ ] `tests/Unit/RelationshipResolverTest.php`:
  - [ ] resolves a single-hop relation returning a Model
  - [ ] resolves a multi-hop chain (`user.manager`)
  - [ ] resolves a chain ending in a Collection
  - [ ] returns empty collection when a hop is null
  - [ ] returns empty collection when the chain is empty string

- [ ] `tests/Feature/RelationshipResolverIntegrationTest.php`:
  - [ ] end-to-end: workflow with `.relation('user')`, submitter's user is the
        resolved approver, `assigned_via = 'relationship'`
  - [ ] chain resolves from the approvable at activation time (live model, not
        snapshot)

### 5. Fixture

- [ ] `tests/Fixtures/Workflows/ExpenseRelationshipWorkflow.php` — step uses
      `.relation('user')` to resolve the expense submitter's user as approver
      (self-approval for test simplicity).

## Acceptance checklist

- [ ] All v0.1 and prior sprint tests pass without modification.
- [ ] `RelationshipResolverTest` (unit) and `RelationshipResolverIntegrationTest`
      pass.
- [ ] PHPStan green at **level 7** — 0 errors.
- [ ] `phpstan.neon` shows `level: 7`.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Caching resolved relation chains (lazy re-evaluation at activation time is correct).
- Relation chains on the `requester` (not the approvable) → use a closure for that.
- Validation that the chain segments are valid at workflow-definition time → v0.3.
