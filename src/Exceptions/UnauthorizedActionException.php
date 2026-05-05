<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

class UnauthorizedActionException extends ApprovioException
{
    public static function notAssignee(): self
    {
        return new self('The actor is not an assignee on the active step.');
    }

    public static function alreadyActed(): self
    {
        return new self('The actor has already acted on this step.');
    }

    public static function stepNotActive(): self
    {
        return new self('The target step is not currently active.');
    }
}
