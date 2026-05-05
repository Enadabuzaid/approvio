<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Exceptions\WorkflowNotFoundException;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

it('throws WorkflowNotFoundException for an unregistered slug', function () {
    $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = TestExpense::create([
        'user_id' => $user->id,
        'title' => 'Test',
        'amount' => 1,
    ]);

    $expense->requestApproval('does-not-exist', $user);
})->throws(WorkflowNotFoundException::class, 'No workflow registered');

it('resolves a workflow registered via $approvalWorkflows on the model', function () {
    // Regression: CodeWorkflowSource accessed protected $approvalWorkflows via
    // Eloquent __get(), which routes through getAttribute() and returns null.
    $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = TestExpense::create([
        'user_id' => $user->id,
        'title' => 'Office supplies',
        'amount' => 50,
    ]);

    $request = $expense->requestApproval('submission', $user);

    expect($request->workflow_slug)->toBe('submission')
        ->and($request->status)->toBe(RequestStatus::InReview);
});
