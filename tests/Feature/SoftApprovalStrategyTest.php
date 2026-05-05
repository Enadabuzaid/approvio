<?php

declare(strict_types=1);

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
});

it('flips approval_status to pending on submit', function () {
    $expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Snacks',
        'amount' => 30.00,
    ]);

    $expense->requestApproval('submission', $this->submitter);

    $expense->refresh();
    expect($expense->approval_status)->toBe('pending');
});

it('flips approval_status to approved on full approval', function () {
    $expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Snacks',
        'amount' => 30.00,
    ]);

    $request = $expense->requestApproval('submission', $this->submitter);
    $this->manager->approve($request);

    $expense->refresh();
    expect($expense->approval_status)->toBe('approved');
});

it('keeps the model row visible during pending state', function () {
    $expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Snacks',
        'amount' => 30.00,
    ]);

    $expense->requestApproval('submission', $this->submitter);

    expect(TestExpense::find($expense->id))->not->toBeNull()
        ->and($expense->resolveApprovalStrategy()->isVisibleWhilePending())->toBeTrue();
});
