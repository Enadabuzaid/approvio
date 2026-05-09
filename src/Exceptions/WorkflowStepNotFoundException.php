<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

class WorkflowStepNotFoundException extends ApprovioException
{
    public static function for(string $slug, int $stepIndex): self
    {
        return new self(
            "Workflow [{$slug}] no longer defines a step at index [{$stepIndex}]. "
            . 'A step exists in the database but is missing from the workflow class. '
            . 'If you removed the step, cancel in-flight requests before deploying or add a migration.'
        );
    }
}
