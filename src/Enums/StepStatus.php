<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Skipped, self::Expired => true,
            default => false,
        };
    }
}
