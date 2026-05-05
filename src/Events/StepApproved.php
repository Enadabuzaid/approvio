<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Events;

use Enadstack\Approvio\Models\ApprovalAction;
use Enadstack\Approvio\Models\ApprovalRequest;
use Enadstack\Approvio\Models\ApprovalRequestStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalRequestStep $step,
        public ApprovalAction $action,
    ) {
    }
}
