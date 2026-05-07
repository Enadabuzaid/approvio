<?php

declare(strict_types=1);

use Enadstack\Approvio\Engine\StateMachine;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Exceptions\InvalidStateTransitionException;

it('allows pending to in_review', function () {
    $sm = new StateMachine();

    expect($sm->canTransition(RequestStatus::Pending, RequestStatus::InReview))->toBeTrue();
});

it('allows in_review to approved', function () {
    $sm = new StateMachine();

    expect($sm->canTransition(RequestStatus::InReview, RequestStatus::Approved))->toBeTrue();
});

it('rejects approved -> anything (terminal state)', function () {
    $sm = new StateMachine();

    expect($sm->canTransition(RequestStatus::Approved, RequestStatus::InReview))->toBeFalse();
    expect($sm->canTransition(RequestStatus::Approved, RequestStatus::Rejected))->toBeFalse();
    expect($sm->canTransition(RequestStatus::Approved, RequestStatus::Cancelled))->toBeFalse();
});

it('rejects rejected -> anything (terminal state)', function () {
    $sm = new StateMachine();

    expect($sm->canTransition(RequestStatus::Rejected, RequestStatus::Approved))->toBeFalse();
    expect($sm->canTransition(RequestStatus::Rejected, RequestStatus::InReview))->toBeFalse();
});

it('throws InvalidStateTransitionException with a clear message', function () {
    $sm = new StateMachine();

    $sm->assertCanTransition(RequestStatus::Approved, RequestStatus::InReview);
})->throws(InvalidStateTransitionException::class, 'Cannot transition');

it('allows in_review to expired', function () {
    $sm = new StateMachine();

    expect($sm->canTransition(RequestStatus::InReview, RequestStatus::Expired))->toBeTrue();
});

it('rejects expired -> anything (terminal state)', function () {
    $sm = new StateMachine();

    expect($sm->canTransition(RequestStatus::Expired, RequestStatus::Approved))->toBeFalse();
    expect($sm->canTransition(RequestStatus::Expired, RequestStatus::InReview))->toBeFalse();
    expect($sm->canTransition(RequestStatus::Expired, RequestStatus::Cancelled))->toBeFalse();
});
