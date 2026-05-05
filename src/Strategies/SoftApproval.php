<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Strategies;

use Enadstack\Approvio\Contracts\ApprovalStrategy;
use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * SoftApproval strategy.
 *
 * The model row exists and is queryable from day one. The Approvable
 * trait sets approval_status = 'pending' on creation, and the strategy
 * flips it to 'approved' or 'rejected' when the request resolves.
 *
 * Best for: low-risk content where pending visibility is acceptable
 * (blog posts, profile changes that don't touch sensitive data, etc).
 *
 * Requires: an `approval_status` column on the approvable's table.
 * The package ships a helper to add this column.
 */
class SoftApproval implements ApprovalStrategy
{
    public const COLUMN = 'approval_status';

    public function onSubmit(Model $approvable, ApprovalRequest $request, array $changes = []): void
    {
        if ($this->hasColumn($approvable)) {
            $approvable->setAttribute(self::COLUMN, 'pending');
            $approvable->save();
        }
    }

    public function onApprove(Model $approvable, ApprovalRequest $request): void
    {
        if ($this->hasColumn($approvable)) {
            $approvable->setAttribute(self::COLUMN, 'approved');
            $approvable->save();
        }
    }

    public function onReject(Model $approvable, ApprovalRequest $request): void
    {
        if ($this->hasColumn($approvable)) {
            $approvable->setAttribute(self::COLUMN, 'rejected');
            $approvable->save();
        }
    }

    public function onCancel(Model $approvable, ApprovalRequest $request): void
    {
        // Soft strategy leaves the model in place on cancel. Consumers
        // can listen to ApprovalCancelled event if they want different behavior.
    }

    public function isVisibleWhilePending(): bool
    {
        return true;
    }

    protected function hasColumn(Model $approvable): bool
    {
        return in_array(
            self::COLUMN,
            $approvable->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($approvable->getTable()),
            true,
        );
    }
}
