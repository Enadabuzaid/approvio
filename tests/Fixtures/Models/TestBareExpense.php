<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Enadstack\Approvio\Concerns\Approvable;
use Enadstack\Approvio\Strategies\SoftApproval;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal approvable model that intentionally does NOT declare
 * $approvalWorkflows, used to verify CodeWorkflowSource produces a clean
 * WorkflowNotFoundException rather than an undefined-property warning.
 */
class TestBareExpense extends Model
{
    use Approvable;

    protected $table = 'test_expenses';

    protected $guarded = [];

    protected string $approvalStrategy = SoftApproval::class;
}
