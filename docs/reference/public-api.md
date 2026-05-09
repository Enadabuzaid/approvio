# Public API reference

Complete reference for every public method and type in `enadstack/approvio` v0.2. Signatures, return types, and behavior in one place — no source reading required.

---

## Approvable trait

Add to any Eloquent model to enable approval workflows.

```php
use Enadstack\Approvio\Concerns\Approvable;
```

---

### `requestApproval()`

```php
public function requestApproval(
    string $workflow = 'default',
    ?Model $requester = null,
    array $context = [],
    array $changes = [],
): ApprovalRequest
```

Submits this model for approval. Creates an `ApprovalRequest`, materializes all step rows, and activates the first step — all inside one database transaction. Returns the refreshed `ApprovalRequest` with `steps.assignees` and `actions` eagerly loaded.

- `$workflow` — key in `$approvalWorkflows` on the model; defaults to `'default'`
- `$requester` — the submitting actor; defaults to `auth()->user()` if `null`
- `$context` — arbitrary data stored on `approval_requests.context`
- `$changes` — proposed changes for `DraftApproval` strategy; ignored by `SoftApproval`

**Throws:** `WorkflowNotFoundException` if the slug resolves to no registered workflow class.

**See:** [Quickstart](../getting-started/quickstart.md)

---

### `requestApprovalFor()`

```php
public function requestApprovalFor(
    array $changes,
    string $workflow = 'default',
    array $context = [],
): ApprovalRequest
```

Convenience wrapper for `requestApproval()` that places `$changes` first. Intended for `DraftApproval` workflows where proposed changes are the primary argument. The requester defaults to `auth()->user()`.

---

### `resubmit()`

```php
public function resubmit(
    ?Model $requester = null,
    array $context = [],
    ?array $changes = null,
): ApprovalRequest
```

Resubmits the most recent rejected request for this model. The new request links back via `parent_request_id`. Merges `$context` with the original's context. If `$changes` is `null`, carries forward `pending_changes` from the original request.

**Throws:** `InvalidStateTransitionException` if the most recent request is not `Rejected`. Throws if an active (non-terminal) resubmission already exists.

---

### `pendingApprovalRequest()`

```php
public function pendingApprovalRequest(): ?ApprovalRequest
```

Returns the latest `ApprovalRequest` with status `Pending` or `InReview`, or `null` if none exists.

---

### `hasPendingApproval()`

```php
public function hasPendingApproval(): bool
```

Returns `true` if a request in `Pending` or `InReview` state exists for this model.

---

### `latestApprovalRequest()`

```php
public function latestApprovalRequest(): ?ApprovalRequest
```

Returns the most recent `ApprovalRequest` for this model regardless of status, or `null` if none.

---

### `approvalRequests()`

```php
public function approvalRequests(): MorphMany
```

Eloquent relation — all `ApprovalRequest` rows for this model, unscoped.

---

### `getApprovalWorkflows()`

```php
public function getApprovalWorkflows(): array<string, class-string>
```

Returns the `$approvalWorkflows` map. Called by `WorkflowSource` implementations to locate the workflow class for a given slug.

---

### `resolveApprovalStrategy()`

```php
public function resolveApprovalStrategy(): ApprovalStrategy
```

Resolves and instantiates the strategy class. Reads `$approvalStrategy` property on the model if set; falls back to `config('approvio.default_strategy', SoftApproval::class)`.

---

## HasApprovalActions trait

Add to the model that performs approval actions — typically `App\Models\User`.

```php
use Enadstack\Approvio\Concerns\HasApprovalActions;
```

---

### `approve()`

```php
public function approve(ApprovalRequest $request, ?string $comment = null): ApprovalRequest
```

Records an approval for this actor on the current active step. Returns the refreshed `ApprovalRequest`. If approving meets the step's quorum, the engine advances to the next step or finalizes the request as `Approved`.

**Throws:** `UnauthorizedActionException` if the actor is not a pending assignee on the current step. `InvalidStateTransitionException` if the request is already in a terminal state.

---

### `reject()`

```php
public function reject(ApprovalRequest $request, ?string $comment = null): ApprovalRequest
```

Records a rejection for this actor. In v0.2, any rejection on any step type immediately terminates the entire request regardless of other approvals already recorded.

**Throws:** Same as `approve()`.

---

### `delegate()`

```php
public function delegate(
    ApprovalRequest $request,
    Model $delegateTo,
    ?string $comment = null,
): ApprovalRequest
```

Transfers this actor's assignment to `$delegateTo`. The original assignee's status becomes `Delegated`; a new `ApprovalStepAssignee` row is created for the delegate with `assigned_via = 'delegation'`. Delegation is one level deep and non-revocable.

**Throws:** `DelegationException::notAnAssignee()` if the actor has no pending assignment on the active step. `DelegationException::cannotDelegateFurther()` if the actor is already a delegate. `DelegationException::alreadyDelegated()` if the actor has already delegated.

---

### `pendingApprovals()`

```php
public function pendingApprovals(): Collection<int, ApprovalRequest>
```

Returns all `ApprovalRequest` instances where this actor has a pending assignee slot on a currently active step.

---

### `approvalActions()`

```php
public function approvalActions(): MorphMany
```

Relation — all `ApprovalAction` rows where this actor performed the action.

---

### `approvalAssignments()`

```php
public function approvalAssignments(): MorphMany
```

Relation — all `ApprovalStepAssignee` rows for this actor across all requests.

> **Note:** cancellation is not exposed on `HasApprovalActions`. Cancel a request programmatically via `Approvio::cancel($request, $actor, $comment)` on the facade.

---

## Approvio facade

Static-style wrapper over the engine. Useful when you need to act on a request from outside the actor model — system jobs, scheduled commands, API controllers.

```php
use Enadstack\Approvio\Facades\Approvio;
```

---

### `Approvio::submit()`

```php
Approvio::submit(
    Model $approvable,
    string $workflow = 'default',
    ?Model $requester = null,
    array $context = [],
    array $changes = [],
): ApprovalRequest
```

Delegates to `$approvable->requestApproval()`. **Throws `\InvalidArgumentException`** if the model does not use the `Approvable` trait.

---

### `Approvio::approve()`

```php
Approvio::approve(ApprovalRequest $request, Model $actor, ?string $comment = null): ApprovalRequest
```

---

### `Approvio::reject()`

```php
Approvio::reject(ApprovalRequest $request, Model $actor, ?string $comment = null): ApprovalRequest
```

---

### `Approvio::cancel()`

```php
Approvio::cancel(ApprovalRequest $request, ?Model $actor = null, ?string $comment = null): ApprovalRequest
```

Cancels the request from any non-terminal state (`Pending` or `InReview`). `$actor` may be `null` for system-initiated cancellations. Dispatches `ApprovalCancelled`.

**Throws:** `InvalidStateTransitionException` if the request is already terminal.

---

### `Approvio::delegate()`

```php
Approvio::delegate(
    ApprovalRequest $request,
    Model $actor,
    Model $delegateTo,
    ?string $comment = null,
): ApprovalRequest
```

---

### `Approvio::resubmit()`

```php
Approvio::resubmit(
    ApprovalRequest $original,
    ?Model $requester = null,
    array $context = [],
    ?array $changes = null,
): ApprovalRequest
```

---

## Artisan commands

### `approvio:escalate`

Scans `approval_request_steps` where `status = active` and `deadline_at < now()`, then dispatches each overdue step to the engine's escalation handler.

Schedule it in `routes/console.php` (Laravel 11+):

```php
Schedule::command('approvio:escalate')->everyMinute();
```

For each overdue step, the engine either:

- **Escalates** — resolves a target via the step's `escalateTo` closure, adds a new assignee with `assigned_via = 'escalation'`, marks overdue originals as `Escalated`, dispatches `StepEscalated`
- **Expires** — transitions the step and request to `Expired` if no `escalateTo` is configured or the resolver returns empty

**See:** [Escalation and deadlines](../advanced/escalation.md) for full coverage.

---

## WorkflowBuilder

Fluent DSL for declaring steps inside `Workflow::define()`. Every method returns `PendingStep` for chaining.

---

### `step()`

```php
// On WorkflowBuilder:
public function step(string $name): PendingStep
```

Creates and registers a new step. Steps execute in declaration order. The following methods are on the returned `PendingStep`:

---

### `->approvers()`

```php
public function approvers(
    ApproverResolver|Closure|iterable $resolver,
): PendingStep
```

Sets the approver source. Accepts a `Closure` (wrapped in `DirectUserResolver`), an `ApproverResolver` implementation (passed through), or a static iterable. The resolver is called at step activation time, not at submission time.

**Note:** If the closure returns an array with `null` elements, `DirectUserResolver` does not filter them. Calling `->getMorphClass()` on `null` throws a fatal error at the engine level. Use `array_filter()` in any closure that accesses nullable relations.

**Throws `LogicException`** at `build()` time if no `approvers()` (or `->relation()` / `->role()`) call was made for the step.

**Required.** Every step must have an approver source.

---

### `->relation()`

```php
public function relation(string $chain): PendingStep
```

Shorthand for `->approvers(new RelationshipResolver($chain))`. Walks a dot-notation Eloquent relation chain at activation time. Returns an empty collection — not an exception — if any segment in the chain resolves to `null`.

```php
$flow->step('manager-review')
    ->relation('user.manager'); // walks $approvable->user->manager
```

---

### `->role()`

```php
public function role(string $roleName, ?string $guardName = null): PendingStep
```

Shorthand for `->approvers(new RoleResolver($roleName, $guardName))`. Queries all users currently holding the named Spatie role at activation time.

**Throws `MissingDependencyException`** at workflow-resolution time (inside `requestApproval()`) if `spatie/laravel-permission` is not installed — not at resolver call time.

**See:** [Role-based approvers](../approvers/roles-via-spatie.md)

---

### `->parallel()`

```php
public function parallel(): PendingStep
```

Marks the step as parallel — all resolved assignees hold `Pending` status simultaneously and can act in any order. Without this call, the step type is `Sequential` (one assignee, activated once). Does not change the quorum rule; combine with `->quorum()`.

---

### `->quorum()`

```php
public function quorum(string $rule, ?int $count = null): PendingStep
```

Sets the quorum rule for a parallel step. `$rule` must be `'any'`, `'all'`, or `'n_of_m'` — any other string throws `ValueError` via `QuorumRule::from()`. `$count` is required for `'n_of_m'`; omitting it stores `null`, which the engine casts to `0`, making the quorum always met. The default is `'any'` if this method is never called.

**See:** [Parallel steps](../workflows/parallel-steps.md)

---

### `->when()`

```php
public function when(Closure $condition): PendingStep
```

Attaches a condition evaluated at activation time against the live model. The closure receives `(Model $approvable, ApprovalRequest $request)` — both are always passed. A falsy return skips the step; any truthy value activates it. Must be a `Closure`, not a `ConditionEvaluator` instance.

**See:** [Conditional steps](../workflows/conditional-steps.md)

---

### `->deadline()`

```php
public function deadline(int $hours): PendingStep
```

Sets a deadline `$hours` hours from activation. Stored as `deadline_at` on the `ApprovalRequestStep` row. The `approvio:escalate` artisan command checks this on a schedule — register it in your `Console/Kernel.php` or `routes/console.php`.

---

### `->escalateTo()`

```php
public function escalateTo(Closure $resolver): PendingStep
```

Sets the escalation target resolver, called when the deadline passes. The closure receives `(Model $approvable)` and must return an array of approver models. Must be a `Closure`.

**Throws `EscalationException::emptyTarget()`** if the resolver returns an empty array — the step expires instead of escalating.

---

## Workflow base class

```php
use Enadstack\Approvio\Workflow\Workflow;

abstract class Workflow
```

---

### Properties

| Property | Type | Required | Default | Description |
|---|---|---|---|---|
| `$approvableType` | `class-string` | **Yes** | — | FQCN of the model this workflow approves. Throws `LogicException` if blank. |
| `$slug` | `?string` | No | `null` | Workflow slug; auto-derived from class name if null. |
| `$version` | `int` | No | `1` | Audit metadata — stored on `approval_requests.workflow_version` but never used for class resolution. |

---

### `define()`

```php
abstract public function define(WorkflowBuilder $flow): void
```

Declare all steps here using `$flow->step()`. Called fresh on every `submit()` and on every step activation — closures are never serialized to the database.

---

### `slug()`

```php
public function slug(): string
```

Returns `$slug ?? Str::kebab(class_basename(static::class))`. For `ExpenseApprovalWorkflow`, the default is `'expense-approval-workflow'`.

---

### `version()`

```php
public function version(): int
```

Returns `$version`. Stored at submission; not used when resolving the workflow class.

---

## ApprovalRequest model

`Enadstack\Approvio\Models\ApprovalRequest`

---

### Key columns

| Column | Cast type | Description |
|---|---|---|
| `status` | `RequestStatus` | Current lifecycle state |
| `workflow_slug` | `string` | Slug used to look up the workflow class |
| `workflow_version` | `int` | `$version` at submission time |
| `current_step_index` | `int` | Zero-based index of the active step |
| `snapshot` | `array\|null` | `$approvable->toArray()` at submission; uncast column values |
| `context` | `array\|null` | Caller-supplied data passed at submission |
| `pending_changes` | `array\|null` | Proposed changes for `DraftApproval` |
| `strategy` | `string\|null` | FQCN of the strategy class used |
| `submitted_at` | `Carbon\|null` | Submission timestamp |
| `completed_at` | `Carbon\|null` | Terminal-state timestamp |
| `expires_at` | `Carbon\|null` | Optional hard expiry timestamp |
| `parent_request_id` | `int\|null` | FK to the original request, for resubmissions |

---

### Methods

#### `currentStep(): ?ApprovalRequestStep`

Returns the step at `current_step_index`, or `null` if the index is out of range.

#### `isPending(): bool`

Returns `true` for both `Pending` and `InReview` (calls `status->isActive()`). For an exact state check, compare `$request->status === RequestStatus::Pending`.

#### `isApproved(): bool`

Returns `true` if `status === RequestStatus::Approved`.

#### `isRejected(): bool`

Returns `true` if `status === RequestStatus::Rejected`.

---

### Relations

| Relation | Returns | Notes |
|---|---|---|
| `steps()` | `HasMany<ApprovalRequestStep>` | Ordered by `step_index` ascending |
| `actions()` | `HasMany<ApprovalAction>` | Ordered by `created_at`; append-only |
| `approvable()` | `MorphTo` | The model being approved |
| `requester()` | `MorphTo` | The submitting actor |
| `tenant()` | `MorphTo` | Resolved tenant; null if `NullTenantResolver` is active |
| `parent()` | `BelongsTo<self>` | Original request; null if not a resubmission |
| `children()` | `HasMany<self>` | All resubmissions linked back to this request |

---

## Events

All events are in `Enadstack\Approvio\Events\` and use `Dispatchable` + `SerializesModels`.

| Event | Constructor | Fires when |
|---|---|---|
| `ApprovalRequested` | `(ApprovalRequest $request)` | `submit()` completes |
| `ApprovalCompleted` | `(ApprovalRequest $request)` | Request reaches `Approved` |
| `ApprovalRejected` | `(ApprovalRequest $request)` | Request reaches `Rejected` |
| `ApprovalCancelled` | `(ApprovalRequest $request)` | Request is cancelled |
| `ApprovalExpired` | `(ApprovalRequest $request)` | Request expires |
| `StepActivated` | `(ApprovalRequest $request, ApprovalRequestStep $step)` | A step becomes `Active` |
| `StepApproved` | `(ApprovalRequest $request, ApprovalRequestStep $step, ApprovalAction $action)` | An individual `approve()` is recorded; fires once per approval on parallel steps |
| `StepRejected` | `(ApprovalRequest $request, ApprovalRequestStep $step, ApprovalAction $action)` | A rejection is recorded |
| `StepSkipped` | `(ApprovalRequest $request, ApprovalRequestStep $step)` | A `when()` condition returned falsy |
| `StepEscalated` | `(ApprovalRequest $request, ApprovalRequestStep $step, ApprovalStepAssignee $originalAssignee)` | Escalation fires; `$originalAssignee` is the stalled original |
| `RequestDelegated` | `(ApprovalRequest $request, ApprovalRequestStep $step, ApprovalStepAssignee $from, ApprovalStepAssignee $to)` | Delegation completes |
| `RequestResubmitted` | `(ApprovalRequest $newRequest, ApprovalRequest $originalRequest)` | A rejected request is resubmitted |

---

## Exceptions

All exceptions are in `Enadstack\Approvio\Exceptions\` and extend `ApprovioException` (which extends `RuntimeException`). Catch `ApprovioException` to handle all package exceptions in one place.

| Exception | Named constructors | Thrown when |
|---|---|---|
| `ApprovioException` | — | Base class |
| `DelegationException` | `notAnAssignee()` · `cannotDelegateFurther()` · `alreadyDelegated()` | Invalid delegation — actor not assigned, already delegated, or attempting a second level |
| `EscalationException` | `emptyTarget()` | Escalation target resolver returned an empty array; step expires |
| `InvalidStateTransitionException` | `between(RequestStatus $from, RequestStatus $to)` | Attempted a state transition not in `StateMachine::TRANSITIONS` |
| `MissingDependencyException` | `forPackage(string $package, string $feature)` | Required soft dependency absent (e.g., `spatie/laravel-permission` for `RoleResolver`) |
| `UnauthorizedActionException` | `notAssignee()` · `alreadyActed()` · `stepNotActive()` | Actor attempted approve/reject/delegate without a valid pending assignment |
| `WorkflowNotFoundException` | `for(string $modelClass, string $slug)` | No entry in `$approvalWorkflows` for the requested slug |

---

## Enums

### `RequestStatus`

`Enadstack\Approvio\Enums\RequestStatus`

| Case | Value | Terminal? | Meaning |
|---|---|---|---|
| `Pending` | `'pending'` | No | Submitted; first step not yet active |
| `InReview` | `'in_review'` | No | At least one step is active |
| `Approved` | `'approved'` | Yes | All steps passed |
| `Rejected` | `'rejected'` | Yes | A rejection terminated the request |
| `Cancelled` | `'cancelled'` | Yes | Manually cancelled |
| `Expired` | `'expired'` | Yes | Deadline passed; no escalation target resolved |

`isActive()` returns `true` for `Pending` and `InReview`. `isTerminal()` returns `true` for the four terminal states. `ApprovalRequest::isPending()` calls `status->isActive()` — it returns `true` for both active states.

---

### `StepStatus`

`Enadstack\Approvio\Enums\StepStatus`

| Case | Value | Meaning |
|---|---|---|
| `Pending` | `'pending'` | Not yet activated; waiting for a prior step |
| `Active` | `'active'` | Assignees can act on this step now |
| `Approved` | `'approved'` | Quorum met |
| `Rejected` | `'rejected'` | A rejection terminated this step and the request |
| `Skipped` | `'skipped'` | `when()` condition returned falsy; `activated_at` is null |
| `Expired` | `'expired'` | Deadline passed with no escalation target |

---

### `AssigneeStatus`

`Enadstack\Approvio\Enums\AssigneeStatus`

| Case | Value | Meaning |
|---|---|---|
| `Pending` | `'pending'` | Waiting for this assignee to act |
| `Approved` | `'approved'` | Assignee approved |
| `Rejected` | `'rejected'` | Assignee rejected |
| `Delegated` | `'delegated'` | Assignee delegated; excluded from quorum denominator |
| `Escalated` | `'escalated'` | Original assignee superseded by escalation; excluded from quorum denominator |
| `Expired` | `'expired'` | Deadline passed before this assignee acted |

---

### `ActionType`

`Enadstack\Approvio\Enums\ActionType`

Stored in `approval_actions.action`. Every row in the audit log uses one of these values.

| Case | Value |
|---|---|
| `Submitted` | `'submitted'` |
| `Approved` | `'approved'` |
| `Rejected` | `'rejected'` |
| `Cancelled` | `'cancelled'` |
| `Commented` | `'commented'` |
| `Delegated` | `'delegated'` |
| `Skipped` | `'skipped'` |
| `Reassigned` | `'reassigned'` |
| `Escalated` | `'escalated'` |
| `StepActivated` | `'step_activated'` |
| `StepCompleted` | `'step_completed'` |
| `Expired` | `'expired'` |
| `Resubmitted` | `'resubmitted'` |

---

### `StepType`

`Enadstack\Approvio\Enums\StepType`

| Case | Value | Set by |
|---|---|---|
| `Sequential` | `'sequential'` | Default — no `->parallel()` call |
| `Parallel` | `'parallel'` | `->parallel()` on `PendingStep` |

---

### `QuorumRule`

`Enadstack\Approvio\Enums\QuorumRule`

| Case | Value | Meaning |
|---|---|---|
| `Any` | `'any'` | First approval completes the step |
| `All` | `'all'` | Every non-delegated, non-escalated assignee must approve |
| `NofM` | `'n_of_m'` | At least N of the resolved assignees must approve |

---

**Previous:** [Expense approval recipe](../recipes/expense-approval.md)

---

**Verification summary**

| Behavioral claim | Verified at |
|---|---|
| `Approvable::requestApproval()` full signature | `Approvable.php:43-48` |
| `Approvable::requestApprovalFor()` — convenience wrapper; requester defaults to `auth()->user()` | `Approvable.php:70-80` |
| `Approvable::resubmit()` — most recent rejected request; merges context; `null` changes carries forward `pending_changes` | `Approvable.php:106-124` |
| `Approvable::pendingApprovalRequest()` — queries `pending` and `in_review` statuses | `Approvable.php:82-88` |
| `HasApprovalActions::approve()`, `reject()`, `delegate()` signatures | `HasApprovalActions.php:64-77` |
| `HasApprovalActions::pendingApprovals()` — pending assignee on active step | `HasApprovalActions.php:49-62` |
| `Approvio::submit()` delegates to `requestApproval()`; throws `\InvalidArgumentException` if trait absent | `Approvio.php:34-42` |
| `Approvio::cancel(?Model $actor)` — actor may be null | `Approvio.php:54-57` |
| `WorkflowBuilder::quorum()` uses `QuorumRule::from()` — invalid string throws `ValueError` | `WorkflowBuilder.php:112` |
| `WorkflowBuilder::when()` — closure receives both `($approvable, $request)`; accepts only `Closure` | `WorkflowBuilder.php:150` |
| `WorkflowBuilder::escalateTo()` — requires `Closure`; `EscalationException::emptyTarget()` if empty | `WorkflowBuilder.php:143-148`; `EscalationException.php` |
| `Workflow::$slug` derived via `Str::kebab(class_basename(static::class))` when null | `Workflow.php:51-54` |
| `Workflow::$version` default `1`; audit metadata; not used for class resolution | `Workflow.php:47`; `CodeWorkflowSource.php` |
| `Workflow::$approvableType` empty string throws `LogicException` | `Workflow.php:61-70` |
| All 12 event constructors and dispatch points | `Events/*.php`; `ApprovalEngine.php` |
| All exception named constructors and messages | `Exceptions/*.php` |
| All enum cases and raw string values | `Enums/*.php` |
| `DelegationException::cannotDelegateFurther()` — one delegation level enforced | `ApprovalEngine.php:604-606` |
| `ApprovalRequest::isPending()` calls `status->isActive()` — true for Pending AND InReview | `ApprovalRequest.php:98-101` |
| `steps()` ordered by `step_index` ascending | `ApprovalRequest.php:67-71` |
| `actions()` ordered by `created_at` | `ApprovalRequest.php:73-77` |
