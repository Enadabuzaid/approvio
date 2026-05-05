<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Enadstack\Approvio\Concerns\Approvable;
use Enadstack\Approvio\Strategies\DraftApproval;
use Enadstack\Approvio\Tests\Fixtures\Workflows\DocumentEditWorkflow;
use Illuminate\Database\Eloquent\Model;

class TestDocument extends Model
{
    use Approvable;

    protected $table = 'test_documents';

    protected $guarded = [];

    /** @var class-string */
    protected string $approvalStrategy = DraftApproval::class;

    /** @var array<string, class-string> */
    protected array $approvalWorkflows = [
        'edit' => DocumentEditWorkflow::class,
    ];
}
