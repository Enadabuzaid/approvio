<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\ActionType;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Enums\StepStatus;
use Enadstack\Approvio\Events\RequestResubmitted;
use Enadstack\Approvio\Exceptions\InvalidStateTransitionException;
use Enadstack\Approvio\Facades\Approvio;
use Enadstack\Approvio\Tests\Fixtures\Models\TestDocument;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);

    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Conference',
        'amount' => 500,
    ]);
});

it('resubmit creates a new request linked via parent_request_id', function () {
    $original = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($original->fresh(), 'Too expensive');

    $new = $this->expense->fresh()->resubmit($this->submitter);

    expect($new->parent_request_id)->toBe($original->id);
});

it('new request has the original snapshot and context', function () {
    $original = $this->expense->requestApproval('submission', $this->submitter, context: ['source' => 'web']);
    $this->manager->reject($original->fresh());

    $new = $this->expense->fresh()->resubmit($this->submitter);

    expect($new->context)->toMatchArray(['source' => 'web'])
        ->and($new->snapshot)->not->toBeNull();
});

it('new request goes through the normal submit lifecycle — first step is activated', function () {
    $original = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($original->fresh());

    $new = $this->expense->fresh()->resubmit($this->submitter);

    expect($new->status)->toBe(RequestStatus::InReview)
        ->and($new->steps->first()->status)->toBe(StepStatus::Active);
});

it('DraftApproval resubmit carries forward pending_changes when $changes is null', function () {
    $editor = TestUser::create(['name' => 'Ed', 'email' => 'editor-eve@example.com']);
    $doc = TestDocument::create(['title' => 'Old', 'body' => 'Old body']);
    $original = $doc->requestApprovalFor(['title' => 'New title', 'body' => 'New body'], 'edit');

    $editor->reject($original->fresh());

    $new = $doc->fresh()->resubmit();

    expect($new->pending_changes)->toMatchArray(['title' => 'New title', 'body' => 'New body']);
});

it('explicit $changes override the parent pending_changes', function () {
    $editor = TestUser::create(['name' => 'Ed', 'email' => 'editor-eve@example.com']);
    $doc = TestDocument::create(['title' => 'Old', 'body' => 'Old body']);
    $original = $doc->requestApprovalFor(['title' => 'New title', 'body' => 'New body'], 'edit');

    $editor->reject($original->fresh());

    $new = $doc->fresh()->resubmit(changes: ['title' => 'Corrected title', 'body' => 'Fixed body']);

    expect($new->pending_changes)->toMatchArray(['title' => 'Corrected title', 'body' => 'Fixed body']);
});

it('RequestResubmitted event fires with both request instances', function () {
    Event::fake([RequestResubmitted::class]);

    $original = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($original->fresh());

    $new = $this->expense->fresh()->resubmit($this->submitter);

    Event::assertDispatched(RequestResubmitted::class, function (RequestResubmitted $e) use ($original, $new) {
        return $e->originalRequest->id === $original->id
            && $e->newRequest->id === $new->id;
    });
});

it('audit log on the original request records a resubmitted action', function () {
    $original = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($original->fresh());

    $this->expense->fresh()->resubmit($this->submitter);

    $action = $original->fresh()->actions->firstWhere('action', ActionType::Resubmitted);
    expect($action)->not->toBeNull();
});

it('attempting to resubmit a non-rejected request throws InvalidStateTransitionException', function () {
    $request = $this->expense->requestApproval('submission', $this->submitter);

    app(\Enadstack\Approvio\Engine\ApprovalEngine::class)->resubmit($request, $this->submitter);
})->throws(InvalidStateTransitionException::class);

it('attempting to resubmit a request with an active child throws InvalidStateTransitionException', function () {
    $original = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($original->fresh());

    $this->expense->fresh()->resubmit($this->submitter);

    // Second resubmit — active child already exists.
    app(\Enadstack\Approvio\Engine\ApprovalEngine::class)->resubmit($original->fresh(), $this->submitter);
})->throws(InvalidStateTransitionException::class);

it('$approvable->resubmit() targets the most recent rejected request', function () {
    $r1 = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($r1->fresh());

    $r2 = $this->expense->fresh()->resubmit($this->submitter);
    $this->manager->reject($r2->fresh());

    $r3 = $this->expense->fresh()->resubmit($this->submitter);

    expect($r3->parent_request_id)->toBe($r2->id);
});

it('Approvio::resubmit($rejectedRequest) facade method works', function () {
    $original = $this->expense->requestApproval('submission', $this->submitter);
    $this->manager->reject($original->fresh());

    $new = Approvio::resubmit($original->fresh(), $this->submitter);

    expect($new->parent_request_id)->toBe($original->id)
        ->and($new->status)->toBe(RequestStatus::InReview);
});
