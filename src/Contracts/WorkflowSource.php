<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Contracts;

use Enadstack\Approvio\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * A source from which workflow definitions are loaded. v0.1 ships
 * CodeWorkflowSource (PHP classes). v0.3 will add DatabaseWorkflowSource
 * for tenant-customizable workflows.
 *
 * The first source in the configured chain to return a definition wins.
 */
interface WorkflowSource
{
    /**
     * Find a workflow definition by approvable model and workflow slug.
     * Return null if this source has no matching workflow.
     */
    public function find(
        Model $approvable,
        string $slug,
        ?Model $tenant = null,
    ): ?WorkflowDefinition;
}
