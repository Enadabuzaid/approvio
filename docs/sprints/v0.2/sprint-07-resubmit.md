# Sprint 7 — Resubmit after rejection

> **Goal:** A rejected request can be resubmitted, creating a new linked request
> that carries forward the original snapshot and context. The `DraftApproval`
> strategy pre-populates `pending_changes` from the original.

## Outcomes

When this sprint is done:

- `$expense->resubmit()` (or `$rejectedRequest->resubmit()` via
  `Approvio::resubmit()`) creates a new `ApprovalRequest` for the same
  approvable/workflow with the same strategy.
- The new request has `parent_request_id` pointing to the rejected one.
- The new request's `snapshot` and `context` are inherited from the original.
- For `DraftApproval` requests, the original `pending_changes` are carried forward
  into the new request's `pending_changes` as the starting point.
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
  - Accepts: `ApprovalRequest $original`, `?Model $requester`, `array $context`.
  - Guard: `$original->status !== Rejected` → throw `InvalidStateTransitionException`.
  - Guard: if `$original->children()->whereNotIn('status', terminal states)->exists()`
    → throw `InvalidStateTransitionException('This request already has an active resubmission.')`.
  - Wrap in `DB::transaction()`.
  - Call `submit()` with same approvable, workflow slug, strategy, and requester;
    merge original `context` with any new `$context` passed by caller.
  - For `DraftApproval` strategy: pass `$original->pending_changes ?? []` as the
    `$changes` argument to `submit()`.
  - After `submit()`: set `parent_request_id` on the new request, save.
  - Log `resubmitted` action on the **original** request (audit trail on the parent).
  - Dispatch `RequestResubmitted`.
  - Return the new request.

### 6. Public surface

- [ ] `src/Concerns/Approvable.php` — add `resubmit()`:
  ```php
  public function resubmit(
      ?Model $requester = null,
      array  $context   = [],
  ): ApprovalRequest {
      $latest = $this->approvalRequests()
          ->where('status', RequestStatus::Rejected)
          ->latest()
          ->firstOrFail();
      return app(ApprovalEngine::class)->resubmit($latest, $requester ?? auth()->user(), $context);
  }
  ```
- [ ] `src/Approvio.php` + `src/Facades/Approvio.php` — add `resubmit()` and
      `@method` docblock.

### 7. Tests

- [ ] `tests/Feature/ResubmitTest.php`:
  - [ ] resubmit creates a new request linked via `parent_request_id`
  - [ ] new request has the original snapshot and context
  - [ ] new request goes through the normal submit lifecycle (step activated)
  - [ ] `DraftApproval` resubmit carries forward `pending_changes`
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
