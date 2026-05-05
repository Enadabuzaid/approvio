<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Workflows;

use Enadstack\Approvio\Tests\Fixtures\Models\TestDocument;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Workflow\Workflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;

class DocumentEditWorkflow extends Workflow
{
    protected string $approvableType = TestDocument::class;

    protected ?string $slug = 'edit';

    public function define(WorkflowBuilder $flow): void
    {
        $flow->step('editor-review')
            ->approvers(fn () => TestUser::query()
                ->where('email', 'like', 'editor%')
                ->get()
            );
    }
}
