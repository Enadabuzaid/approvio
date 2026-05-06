<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Models;

use Enadstack\Approvio\Enums\QuorumRule;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Enums\StepType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $approval_request_id
 * @property int $step_index
 * @property string $step_name
 * @property StepType $type
 * @property QuorumRule $quorum_rule
 * @property int|null $quorum_count
 * @property StepStatus $status
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $deadline_at
 * @property array<string, mixed>|null $config
 */
class ApprovalRequestStep extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => StepStatus::class,
        'type' => StepType::class,
        'quorum_rule' => QuorumRule::class,
        'config' => 'array',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approvio.tables.request_steps', 'approval_request_steps');
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** @return HasMany<ApprovalStepAssignee, $this> */
    public function assignees(): HasMany
    {
        return $this->hasMany(ApprovalStepAssignee::class);
    }

    public function isActive(): bool
    {
        return $this->status === StepStatus::Active;
    }
}
