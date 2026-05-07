<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Concerns;

use Enadstack\Approvio\Engine\ApprovalEngine;
use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Models\ApprovalAction;
use Enadstack\Approvio\Models\ApprovalRequest;
use Enadstack\Approvio\Models\ApprovalStepAssignee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Add to your User (or any actor) model to expose approval action helpers.
 *
 *   class User extends Authenticatable
 *   {
 *       use HasApprovalActions;
 *   }
 *
 * Then:
 *
 *   $user->pendingApprovals();           // requests awaiting this user's action
 *   $user->approve($request, '...');     // shorthand
 *   $user->reject($request, '...');      // shorthand
 *   $user->approvalActions();            // every action this user has taken
 */
trait HasApprovalActions
{
    public function approvalActions(): MorphMany
    {
        return $this->morphMany(ApprovalAction::class, 'actor');
    }

    public function approvalAssignments(): MorphMany
    {
        return $this->morphMany(ApprovalStepAssignee::class, 'assignee');
    }

    /**
     * Approval requests awaiting this user's action right now.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function pendingApprovals(): Collection
    {
        $assigneeIds = $this->approvalAssignments()
            ->where('status', AssigneeStatus::Pending->value)
            ->pluck('approval_request_step_id');

        return ApprovalRequest::query()
            ->whereHas('steps', function (Builder $q) use ($assigneeIds) {
                $q->whereIn('id', $assigneeIds)
                    ->where('status', 'active');
            })
            ->whereIn('status', ['pending', 'in_review'])
            ->get();
    }

    public function approve(ApprovalRequest $request, ?string $comment = null): ApprovalRequest
    {
        return app(ApprovalEngine::class)->approve($request, $this, $comment);
    }

    public function reject(ApprovalRequest $request, ?string $comment = null): ApprovalRequest
    {
        return app(ApprovalEngine::class)->reject($request, $this, $comment);
    }

    public function delegate(ApprovalRequest $request, Model $delegateTo, ?string $comment = null): ApprovalRequest
    {
        return app(ApprovalEngine::class)->delegate($request, $this, $delegateTo, $comment);
    }
}
