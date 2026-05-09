<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Workflow;

use Closure;
use Enadstack\Approvio\Contracts\ApproverResolver;
use Enadstack\Approvio\Enums\QuorumRule;
use Enadstack\Approvio\Enums\StepType;
use Enadstack\Approvio\Resolvers\Approvers\DirectUserResolver;
use Enadstack\Approvio\Resolvers\Approvers\RelationshipResolver;
use Enadstack\Approvio\Resolvers\Approvers\RoleResolver;

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

    public StepType $type = StepType::Sequential;

    public QuorumRule $quorumRule = QuorumRule::Any;

    public ?int $quorumCount = null;

    public ?int $deadlineHours = null;

    public ?Closure $escalateTo = null;

    public ?Closure $condition = null;

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

    public function parallel(): self
    {
        $this->type = StepType::Parallel;

        return $this;
    }

    public function quorum(string $rule, ?int $count = null): self
    {
        $this->quorumRule = QuorumRule::from($rule);

        if ($this->quorumRule === QuorumRule::NofM && ($count === null || $count < 1)) {
            throw new \InvalidArgumentException(
                'n_of_m quorum requires a count of at least 1; received ' . var_export($count, true) . '.'
            );
        }

        $this->quorumCount = $count;

        return $this;
    }

    /**
     * Resolve approvers by walking a dot-notation Eloquent relation chain.
     * e.g. 'user', 'user.manager', 'department.members'
     */
    public function relation(string $chain): self
    {
        return $this->approvers(new RelationshipResolver($chain));
    }

    /**
     * Resolve approvers by Spatie Permission role.
     * Throws MissingDependencyException if spatie/laravel-permission is not installed.
     */
    public function role(string $roleName, ?string $guardName = null): self
    {
        return $this->approvers(new RoleResolver($roleName, $guardName));
    }

    public function deadline(int $hours): self
    {
        $this->deadlineHours = $hours;

        return $this;
    }

    public function escalateTo(Closure $resolver): self
    {
        $this->escalateTo = $resolver;

        return $this;
    }

    public function when(Closure $condition): self
    {
        $this->condition = $condition;

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
            quorumRule: $this->quorumRule,
            quorumCount: $this->quorumCount,
            deadlineHours: $this->deadlineHours,
            escalateTo: $this->escalateTo,
            condition: $this->condition,
        );
    }
}
