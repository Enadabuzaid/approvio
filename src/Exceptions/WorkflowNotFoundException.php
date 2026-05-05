<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

class WorkflowNotFoundException extends ApprovioException
{
    public static function for(string $modelClass, string $slug): self
    {
        return new self("No workflow registered for [{$modelClass}] with slug [{$slug}].");
    }
}
