<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseParallelAnyWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'parallel-any';

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('peer-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->orWhere('email', 'like', 'finance%')
                ->get()
            )
            ->parallel()
            ->quorum('any');
    }
}
