<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Enums;

enum QuorumRule: string
{
    /**
     * Any single approver completes the step.
     */
    case Any = 'any';

    /**
     * All approvers must approve to complete the step.
     */
    case All = 'all';

    /**
     * A specific count of approvers must approve.
     */
    case NofM = 'n_of_m';
}
