<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Enums;

enum ActionType: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Commented = 'commented';
    case Delegated = 'delegated';
    case Reassigned = 'reassigned';
    case Escalated = 'escalated';
    case Skipped = 'skipped';
    case StepActivated = 'step_activated';
    case StepCompleted = 'step_completed';
    case Expired = 'expired';
}
