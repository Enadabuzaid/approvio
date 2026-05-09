<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

/**
 * Two-step workflow whose second step can be toggled off via a static flag.
 * Used to simulate a workflow class being modified after requests were submitted.
 */
class ExpenseTruncatingWorkflow extends Workflow
{
    protected string $approvableType = TestExpense::class;

    protected ?string $slug = 'truncating';

    /** Set to true to simulate the second step being removed from the class. */
    public static bool $truncated = false;

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('manager-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'manager%')
                ->get()
            );

        if (! static::$truncated) {
            $flow->step('finance-review')
                ->approvers(fn () => TestUser::query()
                    ->where('email', 'like', 'finance%')
                    ->get()
                );
        }
    }
}
