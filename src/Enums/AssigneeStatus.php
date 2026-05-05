<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Enums;

enum AssigneeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Delegated = 'delegated';
    case Expired = 'expired';
}
