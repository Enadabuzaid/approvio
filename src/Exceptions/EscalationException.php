<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

class EscalationException extends ApprovioException
{
    public static function emptyTarget(): self
    {
        return new self('The escalation target resolver returned no users for this step.');
    }
}
