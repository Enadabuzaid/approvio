<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Engine;

use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Exceptions\InvalidStateTransitionException;

/**
 * Guards transitions on the ApprovalRequest's status.
 *
 * The allowed transitions are:
 *
 *   pending     -> in_review | cancelled | expired
 *   in_review   -> approved | rejected | cancelled | expired
 *   approved    -> (terminal)
 *   rejected    -> (terminal)
 *   cancelled   -> (terminal)
 *   expired     -> (terminal)
 */
class StateMachine
{
    /**
     * @var array<string, list<string>>
     */
    protected const TRANSITIONS = [
        'pending' => ['in_review', 'cancelled', 'expired', 'approved', 'rejected'],
        'in_review' => ['approved', 'rejected', 'cancelled', 'expired'],
        'approved' => [],
        'rejected' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    public function canTransition(RequestStatus $from, RequestStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function assertCanTransition(RequestStatus $from, RequestStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidStateTransitionException::between($from, $to);
        }
    }
}
