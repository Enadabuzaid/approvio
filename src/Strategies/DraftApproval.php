<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Strategies;

use Enadstack\Approvio\Contracts\ApprovalStrategy;
use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * DraftApproval strategy.
 *
 * The model's pending changes are buffered in the request's pending_changes
 * column and only applied on approval. The "live" version of the row remains
 * untouched until the request is approved.
 *
 * Two usage shapes:
 *
 *   1. New record: model is created in DB, but caller passes the proposed
 *      attributes via context['changes']. The model row holds the original
 *      defaults; the request holds the buffered changes.
 *
 *   2. Edit: caller calls $model->requestApprovalFor(['name' => 'New Name']).
 *      The DB row remains unchanged. On approval, those keys are written.
 *
 * Best for: high-stakes data (medical records, contracts, financial details)
 * where the pending version must not be mistaken for current truth.
 */
class DraftApproval implements ApprovalStrategy
{
    public function onSubmit(Model $approvable, ApprovalRequest $request, array $changes = []): void
    {
        // Persist the proposed changes onto the request, not the model.
        if ($changes !== []) {
            $request->pending_changes = $changes;
            $request->save();
        }
        // The model itself is not modified at submit time.
    }

    public function onApprove(Model $approvable, ApprovalRequest $request): void
    {
        $changes = $request->pending_changes ?? [];

        if ($changes === []) {
            return;
        }

        // Apply the buffered changes to the model.
        foreach ($changes as $key => $value) {
            $approvable->setAttribute($key, $value);
        }

        $approvable->save();
    }

    public function onReject(Model $approvable, ApprovalRequest $request): void
    {
        // Nothing to do — the model was never modified. The pending_changes
        // remain on the request as a record of what was proposed.
    }

    public function onCancel(Model $approvable, ApprovalRequest $request): void
    {
        // Same as reject — model is untouched.
    }

    public function isVisibleWhilePending(): bool
    {
        // The live version is always visible. Pending changes are not
        // visible until approved.
        return true;
    }
}
