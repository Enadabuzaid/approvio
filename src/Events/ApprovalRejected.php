<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Events;

use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public ApprovalRequest $request)
    {
    }
}
