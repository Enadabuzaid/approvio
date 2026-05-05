<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

use Enadstack\Approvio\Enums\RequestStatus;

class InvalidStateTransitionException extends ApprovioException
{
    public static function between(RequestStatus $from, RequestStatus $to): self
    {
        return new self(
            "Cannot transition approval request from [{$from->value}] to [{$to->value}]."
        );
    }
}
