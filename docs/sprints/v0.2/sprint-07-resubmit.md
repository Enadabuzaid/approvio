# Sprint 7 — Resubmit after rejection

> **Goal:** A rejected request can be resubmitted, creating a new linked request
> that carries forward the original snapshot and context. The `DraftApproval`
> strategy pre-populates `pending_changes` from the original.

## Outcomes

When this sprint is done:

- `$expense->resubmit()` (or `Approvio::resubmit($rejectedRequest)`) creates a
  new `ApprovalRequest` for the same approvable/workflow with the same strategy.
- Signature: `resubmit(?Model $requester = null, array $context = [], ?array $changes = null)`.
  - When `$changes` is `null`: carry forward the original request's `pending_changes`
    (correct default for `DraftApproval`; a no-op for `SoftApproval`).
  - When `$changes` is provided: override with the supplied array. This lets callers
    submit corrected data without a separate `requestApprovalFor()` call.
- The new request has `parent_request_id` pointing to the rejected one.
- The new request's `snapshot` and `context` are inherited from the original
  (context merged with any new `$context` passed by caller).
- `RequestResubmitted` event fires.
- The new request goes through the normal submit lifecycle (strategy `onSubmit`,
  first step activated, etc.).
- Attempting to resubmit a non-rejected request throws `InvalidStateTransitionException`
  with a clear message.
- Attempting to resubmit a request that already has a non-rejected child request
  throws `InvalidStateTransitionException` (prevent duplicate active resubmits).

## Tasks

### 1. Migration

- [ ] `database/migrations/2024_01_01_000006_add_parent_request_id_to_approval_requests.php`:
  ```php
  $table->foreignId('parent_request_id')
      ->nullable()
      ->constrained(config('approvio.tables.requests'))
      ->nullOnDelete();
  ```
  This is the **only new migration in all of v0.2**.

### 2. Enums

- [ ] `src/Enums/ActionType.php` — add `case Resubmitted = 'resubmitted'`.

### 3. Event

- [ ] `src/Events/RequestResubmitted.php`:
  ```php
  RequestResubmitted(
      ApprovalRequest $newRequest,
      ApprovalRequest $originalRequest,
  )
  ```

### 4. Model

- [ ] `src/Models/ApprovalRequest.php`:
  - Add `parent_request_id` to fillable / casts.
  - Add `parent(): BelongsTo` relation.
  - Add `children(): HasMany` relation (resubmitted requests).

### 5. Engine

- [ ] `src/Engine/ApprovalEngine.php` — add `resubmit()`:
  - Accepts: `ApprovalRequest $original`, `?Model $requester`, `array $context`,
    `?array $changes = null`.
  - Guard: `$original->status !== Rejected` → throw `InvalidStateTransitionException`.
  - Guard: if `$original->children()->whereNotIn('status', terminal states)->exists()`
    → throw `InvalidStateTransitionException('This request already has an active resubmission.')`.
  - Wrap in `DB::transaction()`.
  - Resolve effective changes: `$changes ?? $original->pending_changes ?? []`.
  - Call `submit()` with same approvable, workflow slug, strategy, and requester;
    merge original `context` with any new `$context` passed by caller; pass
    effective changes as `$changes`.
  - After `submit()`: set `parent_request_id` on the new request, save.
  - Log `resubmitted` action on the **original** request (audit trail on the parent).
  - Dispatch `RequestResubmitted`.
  - Return the new request.

### 6. Public surface

- [ ] `src/Concerns/Approvable.php` — add `resubmit()`:
  ```php
  public function resubmit(
      ?Model  $requester = null,
      array   $context   = [],
      ?array  $changes   = null,
  ): ApprovalRequest {
      $latest = $this->approvalRequests()
          ->where('status', RequestStatus::Rejected)
          ->latest()
          ->firstOrFail();
      return app(ApprovalEngine::class)->resubmit(
          original:  $latest,
          requester: $requester ?? auth()->user(),
          context:   $context,
          changes:   $changes,
      );
  }
  ```
  **Decision (confirmed):** `$changes = null` carries forward parent `pending_changes`;
  explicit array overrides. Matches `requestApprovalFor()` pattern.
- [ ] `src/Approvio.php` + `src/Facades/Approvio.php` — add `resubmit()` and
      `@method` docblock.

### 7. Tests

- [ ] `tests/Feature/ResubmitTest.php`:
  - [ ] resubmit creates a new request linked via `parent_request_id`
  - [ ] new request has the original snapshot and context
  - [ ] new request goes through the normal submit lifecycle (step activated)
  - [ ] `DraftApproval` resubmit carries forward `pending_changes` when `$changes` is null
  - [ ] explicit `$changes` overrides the parent `pending_changes`
  - [ ] `RequestResubmitted` event fires with both request instances
  - [ ] audit log on the original request records a `resubmitted` action
  - [ ] attempting to resubmit a non-rejected request throws
        `InvalidStateTransitionException`
  - [ ] attempting to resubmit a request that already has an active child throws
  - [ ] `$approvable->resubmit()` targets the most recent rejected request
  - [ ] `Approvio::resubmit($rejectedRequest)` facade method works

### 8. ServiceProviderTest

- [ ] Add `parent_request_id` to the migration smoke test in
      `ServiceProviderTest` (the existing test checks table existence, which
      still passes; verify the new column is present with `Schema::hasColumn()`).

## Acceptance checklist

- [ ] All v0.1 and prior sprint tests pass without modification.
- [ ] `ResubmitTest` — all 10 cases pass.
- [ ] Migration runs cleanly and rolls back cleanly.
- [ ] PHPStan green at level 7.
- [ ] `CHANGELOG.md` updated.

## Out of scope

- Resubmit with changes (modifying the snapshot or pending_changes at resubmit
  time beyond carrying the originals forward) → caller passes their own `$changes`
  to the underlying submit; this already works via `requestApproval(changes: ...)`.
- Resubmit chains beyond one level (no limit is enforced; the schema supports
  arbitrary depth via `parent_request_id`).
- UI for viewing the resubmit chain → v0.4.
