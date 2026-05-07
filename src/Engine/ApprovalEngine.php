<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Engine;

use Enadstack\Approvio\Contracts\ApprovalStrategy;
use Enadstack\Approvio\Contracts\TenantResolver;
use Enadstack\Approvio\Contracts\WorkflowSource;
use Enadstack\Approvio\Enums\ActionType;
use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Enums\QuorumRule;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\ApprovalCancelled;
use Enadstack\Approvio\Events\ApprovalCompleted;
use Enadstack\Approvio\Events\ApprovalRejected;
use Enadstack\Approvio\Events\ApprovalRequested;
use Enadstack\Approvio\Events\RequestDelegated;
use Enadstack\Approvio\Events\StepActivated;
use Enadstack\Approvio\Events\StepApproved;
use Enadstack\Approvio\Events\StepRejected;
use Enadstack\Approvio\Events\StepSkipped;
use Enadstack\Approvio\Exceptions\DelegationException;
use Enadstack\Approvio\Exceptions\UnauthorizedActionException;
use Enadstack\Approvio\Exceptions\InvalidStateTransitionException;
use Enadstack\Approvio\Exceptions\WorkflowNotFoundException;
use Enadstack\Approvio\Models\ApprovalAction;
use Enadstack\Approvio\Models\ApprovalRequest;
use Enadstack\Approvio\Models\ApprovalRequestStep;
use Enadstack\Approvio\Models\ApprovalStepAssignee;
use Enadstack\Approvio\Workflow\Step as StepDef;
use Enadstack\Approvio\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The orchestrator. All approval lifecycle actions go through this class.
 *
 * Public surface:
 *   - submit(): creates a request, snapshots, activates first step
 *   - approve(): records approval, advances step or completes request
 *   - reject(): records rejection, terminates request
 *   - cancel(): user-initiated termination
 *
 * Everything is wrapped in DB transactions and dispatches events.
 */
class ApprovalEngine
{
    /**
     * @param  array<int, WorkflowSource>  $workflowSources
     */
    public function __construct(
        protected array $workflowSources,
        protected TenantResolver $tenantResolver,
        protected StateMachine $stateMachine,
    ) {
    }

    /* -----------------------------------------------------------------
     |  Submit
     | ----------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $changes  For DraftApproval strategy.
     */
    public function submit(
        Model $approvable,
        string $workflowSlug,
        ApprovalStrategy $strategy,
        ?Model $requester = null,
        array $context = [],
        array $changes = [],
    ): ApprovalRequest {
        $definition = $this->resolveWorkflow($approvable, $workflowSlug);

        return DB::transaction(function () use ($approvable, $definition, $strategy, $requester, $context, $changes) {
            $tenant = $this->tenantResolver->resolve($approvable);

            $request = ApprovalRequest::create([
                'workflow_slug' => $definition->slug,
                'workflow_version' => $definition->version,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'tenant_type' => $tenant?->getMorphClass(),
                'tenant_id' => $tenant?->getKey(),
                'requester_type' => $requester?->getMorphClass(),
                'requester_id' => $requester?->getKey(),
                'status' => RequestStatus::Pending,
                'current_step_index' => 0,
                'snapshot' => $approvable->toArray(),
                'context' => $context,
                'strategy' => $strategy::class,
                'submitted_at' => now(),
            ]);

            // Materialize the workflow's steps into request steps.
            foreach ($definition->steps as $index => $stepDef) {
                $this->createRequestStep($request, $stepDef, $index);
            }

            // Strategy hook for any model-side bookkeeping.
            $strategy->onSubmit($approvable, $request, $changes);

            $this->logAction(
                request: $request,
                step: null,
                actor: $requester,
                action: ActionType::Submitted,
                comment: null,
            );

            // Activate the first step.
            $this->activateNextStep($request);

            ApprovalRequested::dispatch($request);

            return $request->fresh(['steps.assignees', 'actions']);
        });
    }

    /* -----------------------------------------------------------------
     |  Approve
     | ----------------------------------------------------------------- */

    public function approve(
        ApprovalRequest $request,
        Model $actor,
        ?string $comment = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($request, $actor, $comment) {
            // Re-load to defend against callers passing stale models.
            $request->refresh();

            if ($request->status->isTerminal()) {
                throw InvalidStateTransitionException::between(
                    $request->status,
                    RequestStatus::Approved,
                );
            }

            $step = $this->assertActorCanActOnStep($request, $actor);

            // Mark the actor's assignee record as approved.
            $this->markAssigneeStatus($step, $actor, AssigneeStatus::Approved);

            $action = $this->logAction(
                request: $request,
                step: $step,
                actor: $actor,
                action: ActionType::Approved,
                comment: $comment,
            );

            StepApproved::dispatch($request, $step, $action);

            $step->refresh();

            if ($this->isStepQuorumMet($step)) {
                $this->completeStep($step, StepStatus::Approved);
                $request->refresh();
                $next = $this->advanceOrComplete($request);
            } else {
                $next = $request;
            }

            return $next->fresh(['steps.assignees', 'actions']);
        });
    }

    /* -----------------------------------------------------------------
     |  Reject
     | ----------------------------------------------------------------- */

    public function reject(
        ApprovalRequest $request,
        Model $actor,
        ?string $comment = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $request->refresh();

            if ($request->status->isTerminal()) {
                throw InvalidStateTransitionException::between(
                    $request->status,
                    RequestStatus::Rejected,
                );
            }

            $step = $this->assertActorCanActOnStep($request, $actor);

            $this->markAssigneeStatus($step, $actor, AssigneeStatus::Rejected);

            $action = $this->logAction(
                request: $request,
                step: $step,
                actor: $actor,
                action: ActionType::Rejected,
                comment: $comment,
            );

            $this->completeStep($step, StepStatus::Rejected);

            StepRejected::dispatch($request, $step, $action);

            if ($this->shouldStepTerminateOnRejection($step)) {
                $this->stateMachine->assertCanTransition($request->status, RequestStatus::Rejected);
                $request->update([
                    'status' => RequestStatus::Rejected,
                    'completed_at' => now(),
                ]);

                $strategy = $this->resolveStrategy($request);
                $approvable = $request->approvable;
                if ($approvable instanceof Model) {
                    $strategy->onReject($approvable, $request);
                }

                ApprovalRejected::dispatch($request);
            }

            return $request->fresh(['steps.assignees', 'actions']);
        });
    }

    /* -----------------------------------------------------------------
     |  Cancel
     | ----------------------------------------------------------------- */

    public function cancel(
        ApprovalRequest $request,
        ?Model $actor = null,
        ?string $comment = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($request, $actor, $comment) {
            $request->refresh();

            $this->stateMachine->assertCanTransition($request->status, RequestStatus::Cancelled);

            $request->update([
                'status' => RequestStatus::Cancelled,
                'completed_at' => now(),
            ]);

            $this->logAction(
                request: $request,
                step: $request->currentStep(),
                actor: $actor,
                action: ActionType::Cancelled,
                comment: $comment,
            );

            $strategy = $this->resolveStrategy($request);
            $approvable = $request->approvable;
            if ($approvable instanceof Model) {
                $strategy->onCancel($approvable, $request);
            }

            ApprovalCancelled::dispatch($request);

            return $request->fresh(['steps.assignees', 'actions']);
        });
    }

    /* -----------------------------------------------------------------
     |  Internals
     | ----------------------------------------------------------------- */

    protected function resolveWorkflow(Model $approvable, string $slug): WorkflowDefinition
    {
        $tenant = $this->tenantResolver->resolve($approvable);

        foreach ($this->workflowSources as $source) {
            $definition = $source->find($approvable, $slug, $tenant);
            if ($definition) {
                return $definition;
            }
        }

        throw WorkflowNotFoundException::for($approvable::class, $slug);
    }

    protected function createRequestStep(ApprovalRequest $request, StepDef $stepDef, int $index): ApprovalRequestStep
    {
        return ApprovalRequestStep::create([
            'approval_request_id' => $request->id,
            'step_index' => $index,
            'step_name' => $stepDef->name,
            'type' => $stepDef->type->value,
            'quorum_rule' => $stepDef->quorumRule->value,
            'quorum_count' => $stepDef->quorumCount,
            'status' => StepStatus::Pending,
            'config' => $stepDef->toArray(),
        ]);
    }

    protected function activateNextStep(ApprovalRequest $request): void
    {
        $step = $request->steps()
            ->where('step_index', $request->current_step_index)
            ->first();

        if (! $step) {
            return;
        }

        $definition = $this->resolveWorkflow($request->approvable, $request->workflow_slug);
        $stepDef = $definition->stepAt($step->step_index);

        if (! $stepDef) {
            return;
        }

        // Evaluate condition against the live model; skip if it returns false.
        if ($stepDef->condition !== null) {
            $approvable = $request->approvable;
            if (! ($stepDef->condition)($approvable, $request)) {
                $this->skipStep($request, $step);

                return;
            }
        }

        // Resolve approvers freshly against the live model.
        $approvers = $stepDef->approvers->resolve($request->approvable);

        foreach ($approvers as $approver) {
            ApprovalStepAssignee::create([
                'approval_request_step_id' => $step->id,
                'assignee_type' => $approver->getMorphClass(),
                'assignee_id' => $approver->getKey(),
                'assigned_via' => $stepDef->approvers->assignedVia(),
                'status' => AssigneeStatus::Pending,
            ]);
        }

        $step->update([
            'status' => StepStatus::Active,
            'activated_at' => now(),
        ]);

        // Bump request status to in_review on first activation.
        if ($request->status === RequestStatus::Pending) {
            $request->update(['status' => RequestStatus::InReview]);
        }

        $this->logAction(
            request: $request,
            step: $step,
            actor: null,
            action: ActionType::StepActivated,
            comment: null,
        );

        StepActivated::dispatch($request, $step);
    }

    protected function skipStep(ApprovalRequest $request, ApprovalRequestStep $step): void
    {
        $step->update([
            'status' => StepStatus::Skipped,
            'completed_at' => now(),
        ]);

        $this->logAction(
            request: $request,
            step: $step,
            actor: null,
            action: ActionType::Skipped,
            comment: null,
        );

        StepSkipped::dispatch($request, $step);

        $totalSteps = $request->steps()->count();
        $nextIndex = $step->step_index + 1;

        if ($nextIndex >= $totalSteps) {
            $this->finalizeAsApproved($request->fresh());

            return;
        }

        $request->update(['current_step_index' => $nextIndex]);
        $this->activateNextStep($request->fresh());
    }

    protected function advanceOrComplete(ApprovalRequest $request): ApprovalRequest
    {
        $totalSteps = $request->steps()->count();
        $nextIndex = $request->current_step_index + 1;

        if ($nextIndex >= $totalSteps) {
            $this->finalizeAsApproved($request);

            return $request;
        }

        // Move to the next step.
        $request->update(['current_step_index' => $nextIndex]);
        $this->activateNextStep($request->fresh());

        return $request->fresh();
    }

    protected function finalizeAsApproved(ApprovalRequest $request): void
    {
        $this->stateMachine->assertCanTransition($request->status, RequestStatus::Approved);

        $request->update([
            'status' => RequestStatus::Approved,
            'completed_at' => now(),
        ]);

        $strategy = $this->resolveStrategy($request);
        $approvable = $request->approvable;
        if ($approvable instanceof Model) {
            $strategy->onApprove($approvable, $request);
        }

        ApprovalCompleted::dispatch($request);
    }

    protected function assertActorCanActOnStep(ApprovalRequest $request, Model $actor): ApprovalRequestStep
    {
        $step = $request->currentStep();

        if (! $step || ! $step->isActive()) {
            throw UnauthorizedActionException::stepNotActive();
        }

        $assignee = $step->assignees()
            ->where('assignee_type', $actor->getMorphClass())
            ->where('assignee_id', $actor->getKey())
            ->first();

        if (! $assignee) {
            throw UnauthorizedActionException::notAssignee();
        }

        if ($assignee->hasActed()) {
            throw UnauthorizedActionException::alreadyActed();
        }

        return $step;
    }

    protected function markAssigneeStatus(
        ApprovalRequestStep $step,
        Model $actor,
        AssigneeStatus $status,
    ): void {
        $step->assignees()
            ->where('assignee_type', $actor->getMorphClass())
            ->where('assignee_id', $actor->getKey())
            ->update([
                'status' => $status->value,
                'acted_at' => now(),
            ]);
    }

    protected function completeStep(ApprovalRequestStep $step, StepStatus $status): void
    {
        $step->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    protected function logAction(
        ApprovalRequest $request,
        ?ApprovalRequestStep $step,
        ?Model $actor,
        ActionType $action,
        ?string $comment,
    ): ApprovalAction {
        return ApprovalAction::create([
            'approval_request_id' => $request->id,
            'approval_request_step_id' => $step?->id,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'comment' => $comment,
            'metadata' => null,
            'ip_address' => config('approvio.audit.capture_ip', true) ? request()->ip() : null,
            'user_agent' => config('approvio.audit.capture_user_agent', true) ? request()->userAgent() : null,
        ]);
    }

    protected function isStepQuorumMet(ApprovalRequestStep $step): bool
    {
        $approvedCount = $step->assignees()
            ->where('status', AssigneeStatus::Approved->value)
            ->count();

        return match ($step->quorum_rule) {
            QuorumRule::Any => $approvedCount >= 1,
            QuorumRule::All => $approvedCount >= $step->assignees()
                ->where('status', '!=', AssigneeStatus::Delegated->value)
                ->count(),
            QuorumRule::NofM => $approvedCount >= (int) $step->quorum_count,
        };
    }

    public function delegate(
        ApprovalRequest $request,
        Model $actor,
        Model $delegateTo,
        ?string $comment = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($request, $actor, $delegateTo, $comment) {
            $request->refresh();

            $step = $request->currentStep();

            if (! $step || ! $step->isActive()) {
                throw DelegationException::notAnAssignee();
            }

            $assignee = $step->assignees()
                ->where('assignee_type', $actor->getMorphClass())
                ->where('assignee_id', $actor->getKey())
                ->first();

            if (! $assignee) {
                throw DelegationException::notAnAssignee();
            }

            if ($assignee->assigned_via === 'delegation') {
                throw DelegationException::cannotDelegateFurther();
            }

            if ($assignee->hasActed()) {
                throw DelegationException::alreadyDelegated();
            }

            $assignee->update([
                'status' => AssigneeStatus::Delegated,
                'delegated_to_type' => $delegateTo->getMorphClass(),
                'delegated_to_id' => $delegateTo->getKey(),
                'acted_at' => now(),
            ]);

            $delegateAssignee = ApprovalStepAssignee::create([
                'approval_request_step_id' => $step->id,
                'assignee_type' => $delegateTo->getMorphClass(),
                'assignee_id' => $delegateTo->getKey(),
                'assigned_via' => 'delegation',
                'status' => AssigneeStatus::Pending,
            ]);

            $this->logAction(
                request: $request,
                step: $step,
                actor: $actor,
                action: ActionType::Delegated,
                comment: $comment,
            );

            RequestDelegated::dispatch($request, $step, $assignee->fresh(), $delegateAssignee);

            return $request->fresh(['steps.assignees', 'actions']);
        });
    }

    protected function shouldStepTerminateOnRejection(ApprovalRequestStep $step): bool
    {
        return true;
    }

    protected function resolveStrategy(ApprovalRequest $request): ApprovalStrategy
    {
        $strategyClass = $request->strategy ?: config('approvio.default_strategy');

        return app($strategyClass);
    }
}
