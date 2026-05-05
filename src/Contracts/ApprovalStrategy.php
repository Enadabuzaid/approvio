<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Contracts;

use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * An ApprovalStrategy defines what happens to the approvable model
 * during the lifecycle of an approval request:
 *
 *  - SoftApproval: model row exists with `approval_status = pending`
 *    from day one. Approval just clears the flag.
 *
 *  - DraftApproval: changes are buffered in the request's pending_changes
 *    column and only applied to the model on approval.
 *
 * Strategies are stateless services. They receive everything they need
 * via method arguments.
 */
interface ApprovalStrategy
{
    /**
     * Called when an approval request is being created. The strategy
     * decides what to persist on the model itself vs the request.
     *
     * @param  array<string, mixed>  $changes  Proposed changes (for draft strategy).
     */
    public function onSubmit(Model $approvable, ApprovalRequest $request, array $changes = []): void;

    /**
     * Called when the approval request is fully approved.
     */
    public function onApprove(Model $approvable, ApprovalRequest $request): void;

    /**
     * Called when the approval request is rejected.
     */
    public function onReject(Model $approvable, ApprovalRequest $request): void;

    /**
     * Called when the approval request is cancelled or expired.
     */
    public function onCancel(Model $approvable, ApprovalRequest $request): void;

    /**
     * Whether the model row should be visible in regular queries while
     * its approval is pending. SoftApproval returns true, DraftApproval
     * generally returns false (or true with the original snapshot).
     */
    public function isVisibleWhilePending(): bool;
}
