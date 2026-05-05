# Sprint 4 — Strategies + tenancy

> **Goal:** Real `SoftApproval` and `DraftApproval` strategies. Column-based
> tenancy resolution. Both behave correctly through the full lifecycle.
>
> **Why it matters:** These are the package's two most differentiating
> features. Without strategies, it's just a state machine; without tenancy,
> it can't ship in a multi-tenant SaaS.

## Outcomes

When this sprint is done:

- A model using `SoftApproval` shows `pending` → `approved` / `rejected` on
  its `approval_status` column at the right times.
- A model using `DraftApproval` keeps the live row untouched until approval,
  then applies the buffered changes.
- A multi-tenant app with a `tenant_id` column scopes approvals correctly:
  Tenant A's pending approvals don't appear in Tenant B's queries.
- Tenant resolution is overridable globally via config or per-resolver via
  the `TenantResolver` contract.

## Tasks

### 1. SoftApproval strategy

- [ ] `src/Strategies/SoftApproval.php`:
  - [ ] `onSubmit` — set `approval_status = 'pending'` on the model if column exists.
  - [ ] `onApprove` — flip to `'approved'`.
  - [ ] `onReject` — flip to `'rejected'`.
  - [ ] `onCancel` — leave alone (configurable later).
  - [ ] `isVisibleWhilePending` returns `true`.
  - [ ] Detect column presence gracefully (no error if column is missing,
        just no-op — log a warning).

### 2. DraftApproval strategy

- [ ] `src/Strategies/DraftApproval.php`:
  - [ ] `onSubmit($model, $request, $changes)` — write `$changes` into
        `$request->pending_changes`. Don't touch the model.
  - [ ] `onApprove` — apply `pending_changes` to the model and save.
  - [ ] `onReject` — leave model untouched, keep `pending_changes` on the
        request as a record.
  - [ ] `onCancel` — same as reject.
  - [ ] `isVisibleWhilePending` returns `true` (live version is the truth).

### 3. Strategy resolution

- [ ] `Approvable::resolveApprovalStrategy()` reads `$approvalStrategy`
      property on the model, falls back to config default.
- [ ] `ApprovalRequest::strategy` column is populated at submit time so the
      engine can recover the right strategy when finalizing.

### 4. Tenancy

- [ ] `src/Resolvers/Tenants/ColumnTenantResolver.php`:
  - [ ] Try to read `tenant` relation on the approvable first.
  - [ ] Fall back to `auth()->user()->tenant` if available.
  - [ ] Configurable column name via `approvio.tenant.column`.
- [ ] Service provider binds the configured `TenantResolver` as a singleton.
- [ ] Engine writes `tenant_type`/`tenant_id` on the request from the
      resolver's result.

### 5. Test fixtures

- [ ] `tests/Fixtures/Models/TestDocument.php` (uses `DraftApproval`).
- [ ] `tests/Fixtures/Workflows/DocumentEditWorkflow.php`.
- [ ] Update `tests/TestCase.php` to create `test_documents` and a
      `test_tenants` table for tenancy tests.

### 6. Tests

- [ ] `tests/Feature/SoftApprovalStrategyTest.php`:
  - [ ] flips approval_status to pending on submit
  - [ ] flips approval_status to approved on full approval
  - [ ] flips approval_status to rejected on rejection
  - [ ] keeps the model row visible during pending state
- [ ] `tests/Feature/DraftApprovalStrategyTest.php`:
  - [ ] does NOT mutate the model on submit
  - [ ] stores the proposed changes on the request
  - [ ] applies the changes only on approval
  - [ ] leaves the model untouched on rejection
- [ ] `tests/Feature/TenancyTest.php`:
  - [ ] writes the tenant onto the request from the resolver
  - [ ] returns null when using `NullTenantResolver`
  - [ ] reads tenant from the approvable's `tenant` relation
  - [ ] reads tenant from the auth user when approvable lacks one
  - [ ] (Optional) scopes `pendingApprovals()` by tenant

## Acceptance checklist

- [ ] All previous sprint acceptance items still pass.
- [ ] All strategy tests pass for both strategies.
- [ ] All tenancy tests pass.
- [ ] You can switch a model between `SoftApproval` and `DraftApproval`
      with a single `protected string $approvalStrategy = ...` change and
      the test suite rewards that change correctly.
- [ ] PHPStan still green.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Stancl / Spatie multi-tenancy adapters (deferred to v0.3).
- DB-defined workflows (v0.3).
- UI components (v0.4).
