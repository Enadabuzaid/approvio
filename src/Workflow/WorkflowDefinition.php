<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Workflow;

/**
 * Immutable representation of a workflow that the engine executes.
 *
 * Produced by either CodeWorkflowSource (PHP class via WorkflowBuilder)
 * or DatabaseWorkflowSource (JSON, v0.3+). The engine treats both the same.
 */
final class WorkflowDefinition
{
    /**
     * @param  array<int, Step>  $steps
     */
    public function __construct(
        public readonly string $slug,
        public readonly int $version,
        public readonly string $approvableType,
        public readonly array $steps,
    ) {
    }

    public function stepCount(): int
    {
        return count($this->steps);
    }

    public function stepAt(int $index): ?Step
    {
        return $this->steps[$index] ?? null;
    }
}
