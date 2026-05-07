<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

class DelegationException extends ApprovioException
{
    public static function notAnAssignee(): self
    {
        return new self('The actor is not a pending assignee on the current step.');
    }

    public static function cannotDelegateFurther(): self
    {
        return new self('A delegated assignee cannot delegate further (one level only).');
    }

    public static function alreadyDelegated(): self
    {
        return new self('The assignee has already delegated their responsibility.');
    }
}
