<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Models;

use Enadstack\Approvio\Enums\ActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit log entry. Once written, never updated and never deleted
 * (except via cascade when its parent request is deleted).
 *
 * @property int $id
 * @property int $approval_request_id
 * @property int|null $approval_request_step_id
 * @property string|null $actor_type
 * @property int|string|null $actor_id
 * @property ActionType $action
 * @property string|null $comment
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 */
class ApprovalAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'action' => ActionType::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approvio.tables.actions', 'approval_actions');
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** @return BelongsTo<ApprovalRequestStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStep::class, 'approval_request_step_id');
    }

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
