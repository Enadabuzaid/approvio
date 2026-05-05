<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Enadstack\Approvio\Concerns\Approvable;
use Enadstack\Approvio\Strategies\SoftApproval;
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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }
}
