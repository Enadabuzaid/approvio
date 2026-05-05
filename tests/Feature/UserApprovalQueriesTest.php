<?php

declare(strict_types=1);

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->finance = TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);
});

it('lists pending approvals for the assignee', function () {
    $expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Tools',
        'amount' => 200.00,
    ]);

    $expense->requestApproval('two-step', $this->submitter);

    $managerPending = $this->manager->pendingApprovals();
    $financePending = $this->finance->pendingApprovals();

    expect($managerPending)->toHaveCount(1)
        ->and($financePending)->toHaveCount(0);
});

it('moves the request from manager to finance after first approval', function () {
    $expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Tools',
        'amount' => 200.00,
    ]);

    $request = $expense->requestApproval('two-step', $this->submitter);
    $this->manager->approve($request);

    expect($this->manager->pendingApprovals())->toHaveCount(0)
        ->and($this->finance->pendingApprovals())->toHaveCount(1);
});

it('records every action the user takes', function () {
    $expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Tools',
        'amount' => 200.00,
    ]);

    $request = $expense->requestApproval('two-step', $this->submitter);
    $this->manager->approve($request, 'Step 1 OK');

    expect($this->manager->approvalActions()->count())->toBe(1);
});
