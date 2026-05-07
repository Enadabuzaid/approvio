<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\ActionType;
use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\RequestDelegated;
use Enadstack\Approvio\Exceptions\DelegationException;
use Enadstack\Approvio\Exceptions\UnauthorizedActionException;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->finance = TestUser::create(['name' => 'Cara', 'email' => 'finance-cara@example.com']);
    $this->director = TestUser::create(['name' => 'Eve', 'email' => 'director-eve@example.com']);
    $this->deputy = TestUser::create(['name' => 'Frank', 'email' => 'frank@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Big purchase',
        'amount' => 1000,
    ]);
});

it("original assignee's status becomes delegated after delegating", function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    $this->manager->delegate($request, $this->deputy, 'OOO this week');
    $request->refresh();

    $assignee = $request->steps->first()->assignees
        ->firstWhere('assignee_id', $this->manager->id);

    expect($assignee->status)->toBe(AssigneeStatus::Delegated);
});

it('delegated_to_type and delegated_to_id are populated on the original assignee row', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    $this->manager->delegate($request, $this->deputy);
    $request->refresh();

    $assignee = $request->steps->first()->assignees->fresh()
        ->firstWhere('assignee_id', $this->manager->id);

    expect($assignee->delegated_to_type)->toBe($this->deputy->getMorphClass())
        ->and((int) $assignee->delegated_to_id)->toBe($this->deputy->id);
});

it('delegate becomes an active assignee on the step with assigned_via = delegation', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    $this->manager->delegate($request, $this->deputy);
    $request->refresh();

    $delegateAssignee = $request->steps->first()->assignees->fresh()
        ->firstWhere('assignee_id', $this->deputy->id);

    expect($delegateAssignee)->not->toBeNull()
        ->and($delegateAssignee->status)->toBe(AssigneeStatus::Pending)
        ->and($delegateAssignee->assigned_via)->toBe('delegation');
});

it('delegate can approve the step', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->delegate($request, $this->deputy);

    $request = $this->deputy->approve($request->fresh());

    expect($request->status)->toBe(RequestStatus::Approved);
});

it('delegate can reject the step', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->delegate($request, $this->deputy);

    $request = $this->deputy->reject($request->fresh(), 'Not approved.');

    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->steps->first()->status)->toBe(StepStatus::Rejected);
});

it('original assignee cannot approve after delegating', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->delegate($request, $this->deputy);

    $this->manager->approve($request->fresh());
})->throws(UnauthorizedActionException::class);

it('original assignee cannot reject after delegating', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->delegate($request, $this->deputy);

    $this->manager->reject($request->fresh());
})->throws(UnauthorizedActionException::class);

it('attempting to delegate as an already-delegated assignee throws DelegationException', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->delegate($request, $this->deputy);

    // Deputy is assigned_via='delegation'; they cannot delegate further.
    $this->deputy->delegate($request->fresh(), $this->director);
})->throws(DelegationException::class);

it('audit log records a delegated action with actor and comment', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    $this->manager->delegate($request, $this->deputy, 'Heading on holiday');
    $request->refresh();

    $action = $request->actions->firstWhere('action', ActionType::Delegated);

    expect($action)->not->toBeNull()
        ->and($action->actor_id)->toBe($this->manager->id)
        ->and($action->comment)->toBe('Heading on holiday');
});

it('RequestDelegated event fires with the correct payload', function () {
    Event::fake([RequestDelegated::class]);

    $request = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->delegate($request, $this->deputy, 'OOO');

    Event::assertDispatched(RequestDelegated::class, function (RequestDelegated $event) use ($request) {
        return $event->request->id === $request->id
            && $event->from->assignee_id === $this->manager->id
            && $event->to->assignee_id === $this->deputy->id;
    });
});

it('delegation on a parallel step adds a delegate slot and quorum excludes the delegated row', function () {
    $request = $this->expense->requestApproval('parallel-all', $this->submitter);
    // Step has manager + finance (quorum: all).

    // Manager delegates to director.
    $this->manager->delegate($request, $this->director);
    $request->refresh();

    // Step now has 3 assignees: manager (Delegated), finance (Pending), director (Pending).
    // Quorum 'all' excludes delegated rows → needs finance + director to approve.
    expect($request->steps->first()->assignees->fresh())->toHaveCount(3);

    // Finance approves — quorum not yet met (director still pending).
    $this->finance->approve($request->fresh());
    $request->refresh();
    expect($request->steps->first()->fresh()->status)->toBe(StepStatus::Active);

    // Director approves — quorum met (both non-delegated assignees approved).
    $request = $this->director->approve($request->fresh());
    expect($request->steps->first()->status)->toBe(StepStatus::Approved);
});
