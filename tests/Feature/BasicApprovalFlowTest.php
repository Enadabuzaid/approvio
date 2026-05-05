<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\ApprovalCompleted;
use Enadstack\Approvio\Events\ApprovalRequested;
use Enadstack\Approvio\Events\StepActivated;
use Enadstack\Approvio\Events\StepApproved;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);

    $this->manager = TestUser::create([
        'name' => 'Bob',
        'email' => 'manager-bob@example.com',
    ]);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Conference tickets',
        'amount' => 500.00,
    ]);
});

it('creates an approval request when a model is submitted', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    expect($request->workflow_slug)->toBe('submission')
        ->and($request->approvable_id)->toBe($this->expense->id)
        ->and($request->approvable_type)->toBe(TestExpense::class)
        ->and($request->requester_id)->toBe($this->submitter->id)
        ->and($request->status)->toBe(RequestStatus::InReview);
});

it('creates request steps from the workflow definition', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    expect($request->steps)->toHaveCount(1)
        ->and($request->steps->first()->step_name)->toBe('manager-review')
        ->and($request->steps->first()->status)->toBe(StepStatus::Active);
});

it('assigns the resolved approvers to the active step', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $step = $request->steps->first();

    expect($step->assignees)->toHaveCount(1)
        ->and($step->assignees->first()->assignee_id)->toBe($this->manager->id)
        ->and($step->assignees->first()->status)->toBe(AssigneeStatus::Pending);
});

it('snapshots the approvable at submit time', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    expect($request->snapshot)->toBeArray()
        ->and($request->snapshot['title'])->toBe('Conference tickets')
        ->and((float) $request->snapshot['amount'])->toBe(500.00);
});

it('completes the request when the only step is approved', function () {
    Event::fake([ApprovalRequested::class, StepActivated::class, StepApproved::class, ApprovalCompleted::class]);

    $request = $this->expense->requestApproval('submission', $this->submitter);
    $request = $this->manager->approve($request, 'Looks fine.');

    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->completed_at)->not->toBeNull()
        ->and($request->steps->first()->status)->toBe(StepStatus::Approved);

    Event::assertDispatched(ApprovalRequested::class);
    Event::assertDispatched(StepActivated::class);
    Event::assertDispatched(StepApproved::class);
    Event::assertDispatched(ApprovalCompleted::class);
});

it('writes audit log entries for every action', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->approve($request, 'Approved.');

    $request->refresh();
    $actions = $request->actions->pluck('action')->map(fn ($a) => $a->value)->all();

    // submitted, step_activated, approved
    expect($actions)->toContain('submitted')
        ->and($actions)->toContain('step_activated')
        ->and($actions)->toContain('approved');
});

it('captures the comment on the audit action', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->approve($request, 'Looks great.');

    $approveAction = $request->actions()->where('action', 'approved')->first();
    expect($approveAction->comment)->toBe('Looks great.');
});
