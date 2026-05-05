<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\ApprovalRejected;
use Enadstack\Approvio\Events\StepRejected;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->finance = TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Suspicious purchase',
        'amount' => 9999.00,
    ]);
});

it('terminates the request immediately on first-step rejection', function () {
    Event::fake([StepRejected::class, ApprovalRejected::class]);

    $request = $this->expense->requestApproval('two-step', $this->submitter);
    $request = $this->manager->reject($request, 'Need more justification.');

    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->completed_at)->not->toBeNull()
        ->and($request->steps[0]->status)->toBe(StepStatus::Rejected)
        ->and($request->steps[1]->status)->toBe(StepStatus::Pending);

    Event::assertDispatched(StepRejected::class);
    Event::assertDispatched(ApprovalRejected::class);
});

it('terminates the request on second-step rejection', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);
    $this->manager->approve($request);
    $request = $this->finance->reject($request, 'No budget.');

    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->steps[0]->status)->toBe(StepStatus::Approved)
        ->and($request->steps[1]->status)->toBe(StepStatus::Rejected);
});

it('records the rejection comment in the audit log', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);
    $this->manager->reject($request, 'Receipts missing.');

    $action = $request->actions()->where('action', 'rejected')->first();
    expect($action->comment)->toBe('Receipts missing.');
});

it('flips approval_status to rejected on the model (SoftApproval)', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($request, 'No.');

    $this->expense->refresh();
    expect($this->expense->approval_status)->toBe('rejected');
});
