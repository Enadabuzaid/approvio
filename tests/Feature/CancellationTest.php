<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Events\ApprovalCancelled;
use Enadstack\Approvio\Facades\Approvio;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Cancelled later',
        'amount' => 100.00,
    ]);
});

it('marks the request as cancelled', function () {
    Event::fake([ApprovalCancelled::class]);

    $request = $this->expense->requestApproval('submission', $this->submitter);
    $request = Approvio::cancel($request, $this->submitter, 'Changed my mind');

    expect($request->status)->toBe(RequestStatus::Cancelled)
        ->and($request->completed_at)->not->toBeNull();

    Event::assertDispatched(ApprovalCancelled::class);
});

it('writes a cancelled action with the comment', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    Approvio::cancel($request, $this->submitter, 'Changed my mind');

    $action = $request->actions()->where('action', 'cancelled')->first();
    expect($action)->not->toBeNull()
        ->and($action->comment)->toBe('Changed my mind');
});

it('refuses to cancel an already-approved request', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $request = $this->manager->approve($request);

    Approvio::cancel($request, $this->submitter);
})->throws(\Enadstack\Approvio\Exceptions\InvalidStateTransitionException::class);
