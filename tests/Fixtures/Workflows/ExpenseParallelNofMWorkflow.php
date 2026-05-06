<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseParallelNofMWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'parallel-n-of-m';

    public function define(WorkflowBuilder $flow): void
    {
        // 3 potential approvers — 2 of 3 must approve.
        $flow->step('committee-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->orWhere('email', 'like', 'finance%')
                ->orWhere('email', 'like', 'director%')
                ->get()
            )
            ->parallel()
            ->quorum('n_of_m', 2);
    }
}
