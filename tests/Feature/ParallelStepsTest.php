<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Enums\StepType;
use Enadstack\Approvio\Events\StepActivated;
use Enadstack\Approvio\Events\StepApproved;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->finance = TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);
    $this->ceo = TestUser::create(['name' => 'Dave', 'email' => 'ceo-dave@example.com']);
    $this->director = TestUser::create(['name' => 'Eve', 'email' => 'director-eve@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Big purchase',
        'amount' => 1000,
    ]);
});

it('a parallel step activates all assigned approvers simultaneously', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);
    $step = $request->steps->first();

    expect($step->type)->toBe(StepType::Parallel)
        ->and($step->status)->toBe(StepStatus::Active)
        ->and($step->assignees)->toHaveCount(2);

    $emails = $step->assignees->map(fn ($a) => $a->assignee->email)->sort()->values();
    expect($emails)->toContain('manager-bob@example.com')
        ->and($emails)->toContain('finance-cara@example.com');
});

it('quorum any — completes on the first approval', function () {
    $request = $this->expense->requestApproval('parallel-any', $this->submitter);
    $step = $request->steps->first();

    expect($step->assignees)->toHaveCount(2);

    $request = $this->manager->approve($request, 'First.');

    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->steps->first()->status)->toBe(StepStatus::Approved);
});

it('quorum all — does not complete after only one approval', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);

    $request = $this->manager->approve($request, 'Manager OK.');
    $request->refresh();

    // Step must still be active — quorum not met yet.
    $step = $request->steps->first();
    expect($step->status)->toBe(StepStatus::Active)
        ->and($request->status)->toBe(RequestStatus::InReview);

    // Manager's own assignee row should be approved.
    $managerAssignee = $step->assignees
        ->firstWhere('assignee_id', $this->manager->id);
    expect($managerAssignee->status)->toBe(AssigneeStatus::Approved);
});

it('quorum all — completes only when every assignee approves', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);

    $this->manager->approve($request);
    $request = $this->finance->approve($request->fresh(), 'Finance OK.');

    // Both approved — parallel step done, request advances to next step.
    $step = $request->steps->first();
    expect($step->status)->toBe(StepStatus::Approved);
});

it('quorum n_of_m — completes when N approvals received', function () {
    $request = $this->expense->requestApproval('parallel-n-of-m', $this->submitter);

    // 3 assignees, need 2.
    $this->manager->approve($request);
    $request = $this->finance->approve($request->fresh());

    expect($request->steps->first()->status)->toBe(StepStatus::Approved)
        ->and($request->status)->toBe(RequestStatus::Approved);
});

it('quorum n_of_m — does not complete on fewer than N approvals', function () {
    $request = $this->expense->requestApproval('parallel-n-of-m', $this->submitter);

    $request = $this->manager->approve($request);
    $request->refresh();

    expect($request->steps->first()->status)->toBe(StepStatus::Active)
        ->and($request->status)->toBe(RequestStatus::InReview);
});

it('rejection on a parallel step terminates the request immediately regardless of quorum', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);

    // Manager approves but finance rejects — request must still terminate.
    $this->manager->approve($request);
    $request = $this->finance->reject($request->fresh(), 'Nope.');

    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->completed_at)->not->toBeNull()
        ->and($request->steps->first()->status)->toBe(StepStatus::Rejected);
});

it('a sequential step after a parallel step only activates after the parallel step completes', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);

    // Second step must be pending while parallel step is active.
    expect($request->steps[1]->status)->toBe(StepStatus::Pending);

    // Both parallel approvers approve.
    $this->manager->approve($request);
    $request = $this->finance->approve($request->fresh());
    $request->refresh();

    // Now second step should be active.
    expect($request->steps->fresh()[1]->status)->toBe(StepStatus::Active);
});

it('StepApproved fires for each individual approval on a parallel step; StepActivated fires once per step', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);

    // Start faking after submit so we only measure events from the approve() calls.
    Event::fake([StepActivated::class, StepApproved::class]);

    $this->manager->approve($request);
    $this->finance->approve($request->fresh());

    // StepApproved fires once per individual approval (not just once at quorum).
    Event::assertDispatched(StepApproved::class, 2);

    // StepActivated fires once during the approve calls — when the sequential
    // final-sign-off step activates after the parallel quorum is satisfied.
    // It does NOT fire again for the parallel step itself (already active).
    Event::assertDispatched(StepActivated::class, 1);
});
