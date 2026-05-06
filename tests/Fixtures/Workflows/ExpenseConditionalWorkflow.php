<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class ExpenseConditionalWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'conditional';

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('manager-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->get()
            );

        // Only required when amount exceeds 5000.
        $flow->step('finance-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'finance%')
                ->get()
            )
            ->when(fn (TestExpense $expense) => $expense->amount > 5000);

        $flow->step('final-sign-off')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'ceo%')
                ->get()
            );
    }
}
