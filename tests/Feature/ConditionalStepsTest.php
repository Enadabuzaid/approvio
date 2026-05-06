<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\ActionType;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\StepSkipped;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->finance = TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);
    $this->ceo = TestUser::create(['name' => 'Dave', 'email' => 'ceo-dave@example.com']);

    // amount = 1000  →  finance-review condition (amount > 5000) is FALSE
    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Small purchase',
        'amount' => 1000,
    ]);

    // amount = 10000  →  finance-review condition (amount > 5000) is TRUE
    $this->bigExpense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Big purchase',
        'amount' => 10000,
    ]);
});

it('a step with a true condition activates normally', function () {
    $request = $this->bigExpense->requestApproval('conditional', $this->submitter);

    // Manager step active, finance + final still pending.
    expect($request->steps[0]->status)->toBe(StepStatus::Active)
        ->and($request->steps[1]->status)->toBe(StepStatus::Pending)
        ->and($request->steps[2]->status)->toBe(StepStatus::Pending);

    // After manager approves, finance step (condition TRUE) should activate.
    $this->manager->approve($request);
    $request->refresh();

    expect($request->steps->fresh()[1]->status)->toBe(StepStatus::Active);
});

it('a step with a false condition is skipped', function () {
    $request = $this->expense->requestApproval('conditional', $this->submitter);

    $this->manager->approve($request);
    $request->refresh();

    expect($request->steps->fresh()[1]->status)->toBe(StepStatus::Skipped);
});

it('skipped step status is skipped in the database', function () {
    $request = $this->expense->requestApproval('conditional', $this->submitter);
    $this->manager->approve($request);
    $request->refresh();

    $financeStep = $request->steps->fresh()->firstWhere('step_name', 'finance-review');
    expect($financeStep->status)->toBe(StepStatus::Skipped);
});

it('the audit log records a skipped action for each skipped step', function () {
    $request = $this->expense->requestApproval('conditional', $this->submitter);
    $this->manager->approve($request);
    $request->refresh();

    $skippedAction = $request->actions->firstWhere('action', ActionType::Skipped);
    expect($skippedAction)->not->toBeNull();
});

it('StepSkipped fires for each skipped step', function () {
    Event::fake([StepSkipped::class]);

    $request = $this->expense->requestApproval('conditional', $this->submitter);
    $this->manager->approve($request);

    Event::assertDispatched(StepSkipped::class, 1);
});

it('engine advances past a skipped step to the next one', function () {
    $request = $this->expense->requestApproval('conditional', $this->submitter);

    $this->manager->approve($request);
    $request->refresh();

    // Finance skipped; final-sign-off (index 2) should now be active.
    expect($request->steps->fresh()[2]->status)->toBe(StepStatus::Active);
});

it('workflow completes when all non-skipped steps are approved', function () {
    $request = $this->expense->requestApproval('conditional', $this->submitter);

    $this->manager->approve($request);
    $request = $this->ceo->approve($request->fresh());

    expect($request->status)->toBe(RequestStatus::Approved);
});

it('a workflow where all steps are skipped still completes the request as approved', function () {
    // amount = 1000, every step has condition amount > 5000 — all skipped.
    $request = $this->expense->requestApproval('all-conditional', $this->submitter);
    $request->refresh();

    expect($request->status)->toBe(RequestStatus::Approved);
});

it('a conditional step can be parallel — both features compose', function () {
    // Big expense: committee-review (parallel+all+conditional) activates.
    $bigRequest = $this->bigExpense->requestApproval('parallel-conditional', $this->submitter);
    expect($bigRequest->steps->first()->status)->toBe(StepStatus::Active)
        ->and($bigRequest->steps->first()->assignees)->toHaveCount(2);

    // Small expense: committee-review is skipped → request approved immediately.
    $smallRequest = $this->expense->requestApproval('parallel-conditional', $this->submitter);
    $smallRequest->refresh();
    expect($smallRequest->status)->toBe(RequestStatus::Approved);
});
