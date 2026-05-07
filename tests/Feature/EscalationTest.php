<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\StepEscalated;
use Enadstack\Approvio\Exceptions\InvalidStateTransitionException;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->director = TestUser::create(['name' => 'Eve', 'email' => 'director-eve@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Conference',
        'amount' => 500,
    ]);
});

it('engine writes deadline_at on a step with a deadline', function () {
    $request = $this->expense->requestApproval('escalation', $this->submitter);
    $step = $request->steps->first();

    expect($step->deadline_at)->not->toBeNull()
        ->and($step->deadline_at->isFuture())->toBeTrue();
});

it('approvio:escalate adds escalation assignee for an overdue step', function () {
    $request = $this->expense->requestApproval('escalation', $this->submitter);
    $step = $request->steps->first();
    $step->update(['deadline_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    $escalationAssignee = $step->assignees()->where('assignee_id', $this->director->id)->first();

    expect($escalationAssignee)->not->toBeNull()
        ->and($escalationAssignee->assigned_via)->toBe('escalation')
        ->and($escalationAssignee->status)->toBe(AssigneeStatus::Pending);
});

it('original assignee status becomes escalated after escalation', function () {
    $request = $this->expense->requestApproval('escalation', $this->submitter);
    $step = $request->steps->first();
    $step->update(['deadline_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    $original = $step->assignees()->where('assignee_id', $this->manager->id)->first();

    expect($original->status)->toBe(AssigneeStatus::Escalated);
});

it('escalation assignee can approve the step', function () {
    $request = $this->expense->requestApproval('escalation', $this->submitter);
    $step = $request->steps->first();
    $step->update(['deadline_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    $request = $this->director->approve($request->fresh());

    expect($request->status)->toBe(RequestStatus::Approved);
});

it('StepEscalated event fires with correct payload', function () {
    Event::fake([StepEscalated::class]);

    $request = $this->expense->requestApproval('escalation', $this->submitter);
    $step = $request->steps->first();
    $step->update(['deadline_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    Event::assertDispatched(StepEscalated::class, fn (StepEscalated $e) => $e->request->id === $request->id);
});

it('a step with no escalation target becomes expired when overdue', function () {
    $request = $this->expense->requestApproval('deadline-no-escalation', $this->submitter);
    $step = $request->steps->first();
    $step->update(['deadline_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    expect($step->fresh()->status)->toBe(StepStatus::Expired);
});

it('an expired request status is expired', function () {
    $request = $this->expense->requestApproval('deadline-no-escalation', $this->submitter);
    $step = $request->steps->first();
    $step->update(['deadline_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    expect($request->fresh()->status)->toBe(RequestStatus::Expired);
});

it('approve on an expired request throws InvalidStateTransitionException', function () {
    $request = $this->expense->requestApproval('escalation', $this->submitter);
    $request->update(['status' => RequestStatus::Expired]);

    $this->manager->approve($request->fresh());
})->throws(InvalidStateTransitionException::class);

it('approvio:escalate handles requests where expires_at has passed', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $request->update(['expires_at' => now()->subMinutes(5)]);

    Artisan::call('approvio:escalate');

    expect($request->fresh()->status)->toBe(RequestStatus::Expired);
});

it('a step without a deadline is not affected by the escalation command', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    Artisan::call('approvio:escalate');

    expect($request->fresh()->status)->toBe(RequestStatus::InReview);
});
