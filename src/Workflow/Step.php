<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Workflow;

use Enadstack\Approvio\Contracts\ApproverResolver;
use Enadstack\Approvio\Enums\QuorumRule;
use Enadstack\Approvio\Enums\StepType;

/**
 * Immutable value object describing a single step in a workflow definition.
 *
 * v0.1 supports sequential steps with a single direct ApproverResolver.
 * Schema fields for parallel/quorum/deadlines are present so v0.2 can
 * extend without migration churn.
 */
final class Step
{
    public function __construct(
        public readonly string $name,
        public readonly ApproverResolver $approvers,
        public readonly StepType $type = StepType::Sequential,
        public readonly QuorumRule $quorumRule = QuorumRule::Any,
        public readonly ?int $quorumCount = null,
        public readonly ?int $deadlineHours = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'quorum_rule' => $this->quorumRule->value,
            'quorum_count' => $this->quorumCount,
            'deadline_hours' => $this->deadlineHours,
            // approvers resolver is not serialized — it's resolved live.
        ];
    }
}
