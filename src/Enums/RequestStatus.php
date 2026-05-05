<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Cancelled, self::Expired => true,
            default => false,
        };
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }
}
