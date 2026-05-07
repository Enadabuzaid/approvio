<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Commands;

use Enadstack\Approvio\Engine\ApprovalEngine;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Models\ApprovalRequest;
use Enadstack\Approvio\Models\ApprovalRequestStep;
use Illuminate\Console\Command;

class EscalateApprovalsCommand extends Command
{
    protected $signature = 'approvio:escalate';

    protected $description = 'Escalate overdue approval steps and expire overdue requests.';

    public function handle(ApprovalEngine $engine): int
    {
        $escalated = 0;
        $expired = 0;

        // Escalate / expire overdue active steps.
        ApprovalRequestStep::query()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now())
            ->where('status', StepStatus::Active->value)
            ->each(function (ApprovalRequestStep $step) use ($engine, &$escalated) {
                $engine->escalateStep($step);
                $escalated++;
            });

        // Expire requests whose top-level expires_at has passed.
        $terminalValues = [
            RequestStatus::Approved->value,
            RequestStatus::Rejected->value,
            RequestStatus::Cancelled->value,
            RequestStatus::Expired->value,
        ];

        ApprovalRequest::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNotIn('status', $terminalValues)
            ->each(function (ApprovalRequest $request) use ($engine, &$expired) {
                $engine->expire($request);
                $expired++;
            });

        $this->info("Escalated: {$escalated} step(s). Expired: {$expired} request(s).");

        return Command::SUCCESS;
    }
}
