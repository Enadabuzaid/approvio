<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Workflow\Sources;

use Enadstack\Approvio\Contracts\WorkflowSource;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Loads workflows declared via the $approvalWorkflows property on the
 * Approvable model. The property is a map of slug => Workflow class.
 *
 * Example on the model:
 *
 *   protected array $approvalWorkflows = [
 *       'submission' => ExpenseSubmissionWorkflow::class,
 *       'edit'       => ExpenseEditWorkflow::class,
 *   ];
 */
class CodeWorkflowSource implements WorkflowSource
{
    public function find(Model $approvable, string $slug, ?Model $tenant = null): ?WorkflowDefinition
    {
        $workflows = method_exists($approvable, 'getApprovalWorkflows')
            ? $approvable->getApprovalWorkflows()
            : [];

        $class = $workflows[$slug] ?? null;

        if (! $class || ! class_exists($class)) {
            return null;
        }

        /** @var Workflow $workflow */
        $workflow = app($class);

        return $workflow->toDefinition();
    }
}
