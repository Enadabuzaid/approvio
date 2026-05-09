# Concepts — the mental model

This file explains the entities, state machines, and design decisions behind Approvio. Read it after the quickstart when you're ready to build something more complex than a single-step flow.

---

## The five entities

Every approval lifecycle involves five Eloquent models. They nest hierarchically:

```
ApprovalRequest
 └── ApprovalRequestStep  (one per step defined in the workflow)
      └── ApprovalStepAssignee  (one per approver slot in that step)
ApprovalAction  (append-only log; one row per event on the request)
```

The **Workflow** is not an Eloquent model — it is a PHP class that returns a definition. The engine reads it at runtime; nothing from the class is stored in the database except the slug, the version, and a serialized summary of each step's configuration (not the closures themselves).

### ApprovalRequest

One row per approval lifecycle on one approvable model. If a request is rejected and resubmitted, the resubmission creates a new `ApprovalRequest` linked back via `parent_request_id` — the original is never mutated.

Key columns:

| Column | What it holds |
|---|---|
| `workflow_slug` | The slug the engine used to look up the workflow class |
| `workflow_version` | The `$version` integer from the workflow class at submit time |
| `status` | `RequestStatus` enum — the current lifecycle state |
| `current_step_index` | Which step is active (zero-based) |
| `snapshot` | `$approvable->toArray()` captured at submit time |
| `context` | Arbitrary array the caller can attach at submission |
| `pending_changes` | Used by `DraftApproval` to hold proposed changes until approval |
| `strategy` | FQCN of the strategy class used — recorded for re-use on resubmit |
| `expires_at` | If set, the request expires after this timestamp |
| `parent_request_id` | FK to the original request, for resubmissions |

### ApprovalRequestStep

One row per step defined in the workflow, created when the request is submitted. A three-step workflow → three rows, all created upfront, all initially `Pending`. Steps become `Active` one at a time (or in parallel) as the engine advances.

Key columns:

| Column | What it holds |
|---|---|
| `step_index` | Position in the workflow (zero-based) |
| `step_name` | The name string passed to `->step('name')` |
| `type` | `StepType::Sequential` or `StepType::Parallel` |
| `quorum_rule` | `QuorumRule::Any`, `QuorumRule::All`, or `QuorumRule::NofM` |
| `quorum_count` | Used only when `quorum_rule` is `NofM` |
| `status` | `StepStatus` enum |
| `deadline_at` | Computed from `->deadline(hours: N)` at activation time |
| `config` | JSON summary of the step definition (booleans for `has_condition`, `has_escalation`, etc.) |

### ApprovalStepAssignee

One row per approver slot within a step. Created when the step is activated (not at submit time — see [Approver resolution timing](#approver-resolution-timing)). A step with three approvers has three assignee rows.

Key columns:

| Column | What it holds |
|---|---|
| `assignee_type` / `assignee_id` | Morph pointer to the approver model |
| `assigned_via` | Source: `'direct'`, `'relationship'`, `'role'`, `'delegation'`, `'escalation'` |
| `status` | `AssigneeStatus` enum |
| `acted_at` | Timestamp of the action |
| `delegated_to_type` / `delegated_to_id` | Morph pointer to the delegate (if `Delegated`) |

### ApprovalAction

The append-only audit log. One row per event. `UPDATED_AT = null` is set on the model — Laravel omits the `updated_at` column entirely, which makes it impossible to accidentally update a row through the ORM.

Every state change — submit, approve, reject, cancel, delegate, escalate, skip, expire, resubmit — appends a new row. Rows are never deleted except by cascade when the parent request is deleted.

Key columns: `approval_request_id`, `approval_request_step_id` (nullable), `actor_type` / `actor_id` (nullable — system events have no actor), `action` (cast to `ActionType` enum), `comment`, `ip_address`, `user_agent`.

---

## State machines

### RequestStatus

```
                        ┌──────────────────────────────┐
                        │                              │
[submit]                ▼         [step activated]     │
──────► Pending ──────────────────────► InReview       │
            │                              │           │
            │           ┌──────────────────┘           │
            │           │                              │
            ▼           ▼                              │
        Cancelled ◄─────┤ [cancel]                     │
                        │                              │
                    Approved ◄── [all steps pass] ─────┘
                    Rejected ◄── [any rejection]
                    Expired  ◄── [expires_at passed / no escalation target]
```

Allowed transitions (from `StateMachine::TRANSITIONS` in v0.2):

| From | To (allowed) |
|---|---|
| `pending` | `in_review`, `approved`, `rejected`, `cancelled`, `expired` |
| `in_review` | `approved`, `rejected`, `cancelled`, `expired` |
| `approved` | — (terminal) |
| `rejected` | — (terminal) |
| `cancelled` | — (terminal) |
| `expired` | — (terminal) |

Attempting a disallowed transition throws `InvalidStateTransitionException`. The engine always calls `StateMachine::assertCanTransition()` before updating status.

**`RequestStatus::isPending()` vs `RequestStatus::Pending`**

`ApprovalRequest::isPending()` delegates to `$this->status->isActive()`, which returns `true` for both `Pending` and `InReview`. This is intentional — both states mean the request still needs attention. If you need to distinguish between the two:

```php
// Colloquial — true for both Pending and InReview:
$request->isPending();

// Exact check for the literal Pending state (before first step activates):
$request->status === RequestStatus::Pending;

// Exact check for InReview (at least one step is active):
$request->status === RequestStatus::InReview;
```

### StepStatus

Steps move through: `Pending` → `Active` → `Approved | Rejected | Skipped | Expired`.

| Status | Meaning |
|---|---|
| `pending` | Not yet activated — waiting for prior steps to complete |
| `active` | Assignees can act on this step now |
| `approved` | Quorum met; step passed |
| `rejected` | An assignee rejected; step (and request) terminated |
| `skipped` | The `when()` condition returned false at activation time |
| `expired` | Deadline passed and no escalation target resolved |

### AssigneeStatus

| Status | Meaning |
|---|---|
| `pending` | Waiting for this assignee to act |
| `approved` | Assignee approved |
| `rejected` | Assignee rejected |
| `delegated` | Assignee transferred responsibility to a deputy; locked out |
| `escalated` | Original assignee superseded by escalation; a new assignee was added |
| `expired` | Deadline expired; assignee did not act |

### ActionType (audit log events)

Every row written to `approval_actions` has an `action` column of one of these values:

`submitted`, `approved`, `rejected`, `cancelled`, `commented`, `delegated`, `skipped`, `reassigned`, `escalated`, `step_activated`, `step_completed`, `expired`, `resubmitted`

---

## Strategies and the model-coupling tradeoff

A strategy controls what happens to your approvable model at each lifecycle event. The package ships two.

### SoftApproval

`SoftApproval` mirrors approval state onto an `approval_status` column on your model's table.

| Lifecycle event | What the strategy does |
|---|---|
| Submit | Sets `approval_status = 'pending'` and saves the model |
| Approve | Sets `approval_status = 'approved'` and saves |
| Reject | Sets `approval_status = 'rejected'` and saves |
| Cancel | Does nothing to the model |

The strategy checks for the column's existence at runtime before writing via `Schema::getColumnListing()`. If the column is absent the strategy does nothing — no exception is thrown. This means removing the column silently breaks the status mirroring without any diagnostic.

**When to use SoftApproval:** low-stakes content where visibility during review is acceptable (blog posts, profile updates, expense items that finance can see as "pending"). Fast to query — `approval_status = 'approved'` is a single indexed column scan.

### DraftApproval

`DraftApproval` does not touch your model until the request is approved. Proposed changes are held in `approval_requests.pending_changes`.

| Lifecycle event | What the strategy does |
|---|---|
| Submit | Saves `changes` into `request->pending_changes`; model is untouched |
| Approve | Applies every key in `pending_changes` to the model via `setAttribute` + `save()` |
| Reject | Nothing — model stays as-is; `pending_changes` remain on the request as a record |
| Cancel | Nothing |

`isVisibleWhilePending()` returns `true` for both strategies — the live model row is always queryable. `DraftApproval` just means the live row shows the **pre-change** version until approval.

**When to use DraftApproval:** high-stakes edits where the pending version must never be mistaken for current truth (medical records, contracts, financial line-items, legal documents). The tradeoff: reading "what will this look like when approved" requires loading `$request->pending_changes`.

### Selecting a strategy

Set it per model:

```php
class Expense extends Model
{
    use Approvable;

    protected string $approvalStrategy = \Enadstack\Approvio\Strategies\DraftApproval::class;
}
```

Or set the package-wide default in `config/approvio.php`:

```php
'default_strategy' => \Enadstack\Approvio\Strategies\SoftApproval::class,
```

Models without an explicit `$approvalStrategy` property fall back to the config default.

---

## Approver resolution

Three resolver classes ship with the package.

### DirectUserResolver (closures)

When you pass a closure to `->approvers(fn ($model) => [...])`, the builder wraps it in `DirectUserResolver`. The closure may return a `Collection`, an array, or a single `Model`. The resolver normalises all three to a `Collection`.

```php
$flow->step('review')
    ->approvers(fn (Expense $expense) => [$expense->manager]);
```

The `assigned_via` column will contain `'direct'` for these assignees.

### RelationshipResolver (dot-notation paths)

`->relation('user.department.manager')` creates a `RelationshipResolver` that walks the chain using Eloquent's dynamic property access. Each segment must resolve to a `Model` or the chain returns an empty collection — no exception is thrown if a segment is null.

```php
$flow->step('review')
    ->relation('user.department.head');
```

The `assigned_via` column will contain `'relationship'`.

### RoleResolver (Spatie Permission)

`->role('finance')` creates a `RoleResolver`. The constructor throws `MissingDependencyException` immediately if `spatie/laravel-permission` is not installed — it does not wait until the step is activated. See [Role-based approvers](../approvers/roles-via-spatie.md) for setup details.

The `assigned_via` column will contain `'role'`.

### Approver resolution timing

**Resolvers are invoked at step activation time, not at submission time.**

When a request is submitted, the engine creates all `ApprovalRequestStep` rows upfront (one per step), but it does not yet resolve approvers for future steps. Approvers for step `N` are resolved when step `N-1` completes and the engine calls `activateNextStep()`.

This has a practical consequence: if your approval depends on model state that can change between submission and step activation (e.g. `$expense->manager` changes because the employee was reassigned), the step will be assigned to whoever is the manager **at activation time**, not who it was at submission time.

The submitted model's state at submission time is captured in `$request->snapshot` (`$approvable->toArray()`). If you want snapshot-based approver resolution, read from the snapshot explicitly:

```php
->approvers(function (Expense $expense) use ($request) {
    // Use snapshot instead of live model:
    $managerId = $request->snapshot['manager_id'] ?? null;
    return $managerId ? [User::find($managerId)] : [];
})
```

---

## Tenant scoping

Most applications do not need multi-tenancy configuration. By default, `NullTenantResolver` is active — all approval records have null `tenant_type` / `tenant_id` and no scoping is applied.

To record which tenant owns each approval request, switch to `ColumnTenantResolver` in `config/approvio.php`:

```php
'tenant_resolver' => \Enadstack\Approvio\Resolvers\Tenants\ColumnTenantResolver::class,
```

Despite its name, `ColumnTenantResolver` in v0.2 resolves tenants via Eloquent relations, not by reading a raw column. Its priority chain:

1. Reads `$approvable->tenant` if the approvable has a `tenant()` method — expects an Eloquent `Model` in return
2. Falls back to `auth()->user()->tenant` if the user model has a `tenant()` method
3. Returns `null` if neither relation is present or either returns a non-Model value

The resolved tenant is stored as a morph pair (`tenant_type` / `tenant_id`) on the `ApprovalRequest`. It is also passed to `WorkflowSource::find()` so workflow definitions can be tenant-scoped in future sources.

For deeper tenancy integrations (Stancl Tenancy, Spatie Multitenancy, or a custom resolver that reads a raw `tenant_id` column), implement the `TenantResolver` contract and bind it in your service provider. See [Multi-tenancy](../advanced/multi-tenancy.md).

---

## Workflow definition lifecycle

### When is the workflow class read?

The engine reads your workflow class **twice** per step:

1. At `submit()` — to materialize all `ApprovalRequestStep` rows and capture the step count.
2. At `activateNextStep()` — to re-read the definition and resolve approvers and conditions against the live model.

The second read means the approver closures and `when()` conditions are evaluated from the **current version of your PHP class**, not from the version that existed at submit time.

### What is stored vs what is re-evaluated

| Piece of data | Stored in DB | Re-evaluated at activation |
|---|---|---|
| Workflow slug + version | ✅ `approval_requests` | — |
| Model state at submit | ✅ `snapshot` column | — |
| Step names, types, quorum rules | ✅ `approval_request_steps.config` (summary) | — |
| Approver closures / resolvers | ❌ not serialized | ✅ called from PHP class |
| `when()` conditions | ❌ not serialized | ✅ called from PHP class |
| `escalateTo()` targets | ❌ not serialized | ✅ called at escalation time |

### If you change the workflow class after a request is submitted

Steps that are already `Approved`, `Rejected`, or `Skipped` are unaffected — they are done. Steps that are still `Pending` or becoming `Active` will use the **updated** class definition when they are next activated.

This is intentional: it allows bug fixes and approver corrections to take effect on in-flight requests without resubmission. However, **removing a step is dangerous.** The `ApprovalRequestStep` rows are created upfront at submission and counted from the database — the engine uses the DB row count to decide whether to advance or finalize. If the workflow class loses a step that the DB still has a row for, `activateNextStep()` will get `null` from `stepAt()` and return early without advancing `current_step_index`. The request stalls indefinitely in `InReview` with no error thrown and no way to advance without manual intervention.

Safe changes after submission: updating approver closures, adjusting conditions, fixing escalation targets. Unsafe: adding or removing steps on a workflow that has active in-flight requests.

**`$version` is audit metadata, not a behavioral switch.** The engine looks up workflows by slug only — `CodeWorkflowSource::find()` does not filter by version. Incrementing `$version` records which definition was in place at submission time but does not freeze in-flight requests against the old definition. To genuinely isolate in-flight requests from a breaking change, create a new workflow class with a new slug and migrate new submissions to that slug.

---

**Previous:** [Quickstart](./quickstart.md) | **Next:** [Parallel steps](../workflows/parallel-steps.md)
