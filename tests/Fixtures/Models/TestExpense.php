<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Enadstack\Approvio\Concerns\Approvable;
use Enadstack\Approvio\Strategies\SoftApproval;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseAllConditionalWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseConditionalWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseParallelAnyWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseParallelConditionalWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseParallelNofMWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseParallelWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseRelationshipWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseSingleStepWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseTwoStepWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestExpense extends Model
{
    use Approvable;

    protected $table = 'test_expenses';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @var class-string */
    protected string $approvalStrategy = SoftApproval::class;

    /** @var array<string, class-string> */
    protected array $approvalWorkflows = [
        'submission' => ExpenseSingleStepWorkflow::class,
        'two-step' => ExpenseTwoStepWorkflow::class,
        'parallel-all' => ExpenseParallelWorkflow::class,
        'parallel-any' => ExpenseParallelAnyWorkflow::class,
        'parallel-n-of-m' => ExpenseParallelNofMWorkflow::class,
        'conditional' => ExpenseConditionalWorkflow::class,
        'all-conditional' => ExpenseAllConditionalWorkflow::class,
        'parallel-conditional' => ExpenseParallelConditionalWorkflow::class,
        'relationship' => ExpenseRelationshipWorkflow::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TestTenant::class, 'tenant_id');
    }
}
