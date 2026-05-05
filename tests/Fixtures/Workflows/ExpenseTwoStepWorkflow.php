<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseTwoStepWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'two-step';

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('manager-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->get()
            );

        $flow->step('finance-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'finance%')
                ->get()
            );
    }
}
