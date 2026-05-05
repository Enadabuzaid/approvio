<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Workflow;

use Closure;
use Enadstack\Approvio\Contracts\ApproverResolver;
use Enadstack\Approvio\Resolvers\Approvers\DirectUserResolver;

/**
 * Fluent builder for declaring workflows in PHP. v0.1 only supports
 * sequential steps with direct user approvers. The builder returns
 * an immutable WorkflowDefinition.
 *
 * Example:
 *
 *   $flow->step('manager-review')
 *       ->approvers(fn($expense) => [$expense->user->manager]);
 *
 *   $flow->step('finance-review')
 *       ->approvers(fn($expense) => $expense->company->financeOfficers);
 */
class WorkflowBuilder
{
    /** @var array<int, PendingStep> */
    protected array $steps = [];

    public function __construct(
        protected string $slug,
        protected int $version,
        protected string $approvableType,
    ) {
    }

    public function step(string $name): PendingStep
    {
        $pending = new PendingStep($name);
        $this->steps[] = $pending;

        return $pending;
    }

    public function build(): WorkflowDefinition
    {
        $steps = array_map(fn (PendingStep $p) => $p->toStep(), $this->steps);

        return new WorkflowDefinition(
            slug: $this->slug,
            version: $this->version,
            approvableType: $this->approvableType,
            steps: $steps,
        );
    }
}

/**
 * Internal mutable builder for a single step. Converts to an immutable
 * Step value object when the parent WorkflowBuilder builds.
 *
 * @internal
 */
class PendingStep
{
    public ?ApproverResolver $approverResolver = null;

    public string $type = 'sequential';

    public function __construct(public readonly string $name)
    {
    }

    /**
     * Provide a resolver, a closure returning users, or a User array/collection.
     *
     * @param  ApproverResolver|Closure|iterable<mixed>  $resolver
     */
    public function approvers(ApproverResolver|Closure|iterable $resolver): self
    {
        $this->approverResolver = match (true) {
            $resolver instanceof ApproverResolver => $resolver,
            $resolver instanceof Closure => new DirectUserResolver($resolver),
            default => new DirectUserResolver(fn () => $resolver),
        };

        return $this;
    }

    public function toStep(): Step
    {
        if (! $this->approverResolver) {
            throw new \LogicException("Step [{$this->name}] is missing approvers().");
        }

        return new Step(
            name: $this->name,
            approvers: $this->approverResolver,
            type: $this->type,
        );
    }
}
