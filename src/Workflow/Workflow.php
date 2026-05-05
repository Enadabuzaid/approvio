<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Workflow;

use Illuminate\Support\Str;

/**
 * Extend this class to define a workflow in code.
 *
 * Example:
 *
 *   class ExpenseSubmissionWorkflow extends Workflow
 *   {
 *       protected string $approvableType = \App\Models\Expense::class;
 *
 *       public function define(WorkflowBuilder $flow): void
 *       {
 *           $flow->step('manager')->approvers(fn($e) => [$e->user->manager]);
 *           $flow->step('finance')->approvers(fn($e) => $e->financeOfficers);
 *       }
 *   }
 *
 * Register it on the Approvable model via $approvalWorkflows.
 */
abstract class Workflow
{
    /**
     * The model class this workflow approves. Subclasses MUST set this.
     *
     * @var class-string
     * @phpstan-ignore property.defaultValue (Empty string is a sentinel value; subclasses override with a valid FQCN. The runtime guard in approvableType() enforces this.)
     */
    protected string $approvableType = '';

    /**
     * The workflow's identifying slug. Defaults to the class basename
     * kebab-cased (e.g., ExpenseSubmissionWorkflow -> expense-submission-workflow).
     */
    protected ?string $slug = null;

    /**
     * Workflow version. Bump this when you make breaking changes so
     * historical requests retain their original definition.
     */
    protected int $version = 1;

    abstract public function define(WorkflowBuilder $flow): void;

    public function slug(): string
    {
        return $this->slug ?? Str::kebab(class_basename(static::class));
    }

    public function version(): int
    {
        return $this->version;
    }

    public function approvableType(): string
    {
        if ($this->approvableType === '') {
            throw new \LogicException(
                'Workflow ['.static::class.'] must declare $approvableType.'
            );
        }

        return $this->approvableType;
    }

    public function toDefinition(): WorkflowDefinition
    {
        $builder = new WorkflowBuilder(
            slug: $this->slug(),
            version: $this->version(),
            approvableType: $this->approvableType(),
        );

        $this->define($builder);

        return $builder->build();
    }
}
