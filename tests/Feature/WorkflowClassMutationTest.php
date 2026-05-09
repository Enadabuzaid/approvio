<?php

declare(strict_types=1);

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseTruncatingWorkflow;

beforeEach(function () {
    ExpenseTruncatingWorkflow::$truncated = false;

    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Server',
        'amount' => 500.00,
    ]);
});

afterEach(function () {
    ExpenseTruncatingWorkflow::$truncated = false;
});

it('throws when the workflow class no longer defines the step being activated', function () {
    // Submit while both steps exist — creates DB rows for index 0 and 1.
    $request = $this->expense->requestApproval('truncating', $this->submitter);

    // Simulate the developer removing the second step from the workflow class.
    ExpenseTruncatingWorkflow::$truncated = true;

    // Approving step 0 triggers activation of step 1; the class no longer defines it.
    $this->manager->approve($request);
})->throws(
    \Enadstack\Approvio\Exceptions\WorkflowStepNotFoundException::class,
);
