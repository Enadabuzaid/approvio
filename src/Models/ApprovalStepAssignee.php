<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Models;

use Enadstack\Approvio\Enums\AssigneeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $approval_request_step_id
 * @property string $assignee_type
 * @property int|string $assignee_id
 * @property string $assigned_via
 * @property AssigneeStatus $status
 * @property \Illuminate\Support\Carbon|null $acted_at
 * @property string|null $delegated_to_type
 * @property int|string|null $delegated_to_id
 */
class ApprovalStepAssignee extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AssigneeStatus::class,
        'acted_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approvio.tables.step_assignees', 'approval_step_assignees');
    }

    /** @return BelongsTo<ApprovalRequestStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestStep::class, 'approval_request_step_id');
    }

    /** @return MorphTo<Model, $this> */
    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function delegatedTo(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasActed(): bool
    {
        return in_array($this->status, [
            AssigneeStatus::Approved,
            AssigneeStatus::Rejected,
            AssigneeStatus::Delegated,
        ], true);
    }
}
