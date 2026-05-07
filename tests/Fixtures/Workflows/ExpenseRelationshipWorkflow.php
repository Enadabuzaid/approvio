<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseRelationshipWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'relationship';

    public function define(WorkflowBuilder $flow): void
    {
        // Resolves the expense's 'user' BelongsTo relation as the approver.
        $flow->step('owner-review')
            ->relation('user');
    }
}
