<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseParallelConditionalWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'parallel-conditional';

    public function define(WorkflowBuilder $flow): void
    {
        // Both manager and finance must approve, but only when amount > 5000.
        $flow->step('committee-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->orWhere('email', 'like', 'finance%')
                ->get()
            )
            ->parallel()
            ->quorum('all')
            ->when(fn (TestExpense $expense) => $expense->amount > 5000);
    }
}
