<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Models;

use Enadstack\Approvio\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $workflow_slug
 * @property int $workflow_version
 * @property string $approvable_type
 * @property int|string $approvable_id
 * @property RequestStatus $status
 * @property int $current_step_index
 * @property array<string, mixed>|null $snapshot
 * @property array<string, mixed>|null $context
 * @property array<string, mixed>|null $pending_changes
 * @property string|null $strategy
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class ApprovalRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'context' => 'array',
        'pending_changes' => 'array',
        'status' => RequestStatus::class,
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approvio.tables.requests', 'approval_requests');
    }

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ApprovalRequestStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalRequestStep::class)->orderBy('step_index');
    }

    /** @return HasMany<ApprovalAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('created_at');
    }

    public function currentStep(): ?ApprovalRequestStep
    {
        return $this->steps()
            ->where('step_index', $this->current_step_index)
            ->first();
    }

    public function isPending(): bool
    {
        return $this->status->isActive();
    }

    public function isApproved(): bool
    {
        return $this->status === RequestStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === RequestStatus::Rejected;
    }
}
