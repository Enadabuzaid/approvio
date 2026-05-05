<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Facades;

use Enadstack\Approvio\Approvio as ApprovioManager;
use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ApprovalRequest submit(Model $approvable, string $workflow = 'default', ?Model $requester = null, array<string, mixed> $context = [], array<string, mixed> $changes = [])
 * @method static ApprovalRequest approve(ApprovalRequest $request, Model $actor, ?string $comment = null)
 * @method static ApprovalRequest reject(ApprovalRequest $request, Model $actor, ?string $comment = null)
 * @method static ApprovalRequest cancel(ApprovalRequest $request, ?Model $actor = null, ?string $comment = null)
 */
class Approvio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ApprovioManager::class;
    }
}
