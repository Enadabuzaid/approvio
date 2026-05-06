<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseParallelWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'parallel-all';

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('joint-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->orWhere('email', 'like', 'finance%')
                ->get()
            )
            ->parallel()
            ->quorum('all');

        $flow->step('final-sign-off')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'ceo%')
                ->get()
            );
    }
}
