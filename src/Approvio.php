<?php

declare(strict_types=1);

namespace Enadstack\Approvio;

use Enadstack\Approvio\Engine\ApprovalEngine;
use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Top-level facade-backing class. Convenience wrapper around the engine
 * for callers that prefer a static-style API:
 *
 *   Approvio::submit($expense, 'submission');
 *   Approvio::approve($request, $user);
 */
class Approvio
{
    public function __construct(protected ApprovalEngine $engine)
    {
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $changes
     */
    public function submit(
        Model $approvable,
        string $workflow = 'default',
        ?Model $requester = null,
        array $context = [],
        array $changes = [],
    ): ApprovalRequest {
        if (! method_exists($approvable, 'requestApproval')) {
            throw new \InvalidArgumentException(
                'Model ['.$approvable::class.'] does not use the Approvable trait.'
            );
        }

        return $approvable->requestApproval($workflow, $requester, $context, $changes);
    }

    public function approve(ApprovalRequest $request, Model $actor, ?string $comment = null): ApprovalRequest
    {
        return $this->engine->approve($request, $actor, $comment);
    }

    public function reject(ApprovalRequest $request, Model $actor, ?string $comment = null): ApprovalRequest
    {
        return $this->engine->reject($request, $actor, $comment);
    }

    public function cancel(ApprovalRequest $request, ?Model $actor = null, ?string $comment = null): ApprovalRequest
    {
        return $this->engine->cancel($request, $actor, $comment);
    }
}
