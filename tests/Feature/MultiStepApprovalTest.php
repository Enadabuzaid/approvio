<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->finance = TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Server',
        'amount' => 1500.00,
    ]);
});

it('activates only the first step on submit', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);

    expect($request->steps)->toHaveCount(2)
        ->and($request->steps[0]->status)->toBe(StepStatus::Active)
        ->and($request->steps[1]->status)->toBe(StepStatus::Pending)
        ->and($request->current_step_index)->toBe(0);
});

it('advances to the next step after first step is approved', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);
    $request = $this->manager->approve($request, 'Step 1 OK');

    expect($request->status)->toBe(RequestStatus::InReview)
        ->and($request->current_step_index)->toBe(1)
        ->and($request->steps[0]->status)->toBe(StepStatus::Approved)
        ->and($request->steps[1]->status)->toBe(StepStatus::Active);
});

it('completes only when the final step is approved', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);
    $request = $this->manager->approve($request);
    $request = $this->finance->approve($request);

    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->steps[0]->status)->toBe(StepStatus::Approved)
        ->and($request->steps[1]->status)->toBe(StepStatus::Approved);
});

it('refuses approval from the wrong step approver', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);

    // Finance is on step 2, but step 1 is active. They cannot approve yet.
    $this->finance->approve($request);
})->throws(\Enadstack\Approvio\Exceptions\UnauthorizedActionException::class);

it('refuses double-approval from the same approver', function () {
    $request = $this->expense->requestApproval('two-step', $this->submitter);
    $this->manager->approve($request);

    // Step 1 is now closed; manager cannot act on step 2.
    $this->manager->approve($request);
})->throws(\Enadstack\Approvio\Exceptions\UnauthorizedActionException::class);
