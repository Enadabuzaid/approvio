<?php

declare(strict_types=1);

use Enadstack\Approvio\Tests\Fixtures\Models\TestDocument;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->editor = TestUser::create(['name' => 'Eve', 'email' => 'editor-eve@example.com']);
});

it('does NOT mutate the model on submit', function () {
    $doc = TestDocument::create([
        'title' => 'Original title',
        'body' => 'Original body',
    ]);

    $doc->requestApprovalFor(
        ['title' => 'New title', 'body' => 'New body'],
        'edit'
    );

    $doc->refresh();
    expect($doc->title)->toBe('Original title')
        ->and($doc->body)->toBe('Original body');
});

it('stores the proposed changes on the request', function () {
    $doc = TestDocument::create([
        'title' => 'Original',
        'body' => 'Original body',
    ]);

    $request = $doc->requestApprovalFor(
        ['title' => 'New title'],
        'edit'
    );

    $request->refresh();
    expect($request->pending_changes)->toBe(['title' => 'New title']);
});

it('applies the changes only on approval', function () {
    $doc = TestDocument::create([
        'title' => 'Original title',
        'body' => 'Original body',
    ]);

    $request = $doc->requestApprovalFor(
        ['title' => 'New title', 'body' => 'New body'],
        'edit'
    );

    $this->editor->approve($request);

    $doc->refresh();
    expect($doc->title)->toBe('New title')
        ->and($doc->body)->toBe('New body');
});

it('leaves the model untouched on rejection', function () {
    $doc = TestDocument::create([
        'title' => 'Original',
        'body' => 'Body',
    ]);

    $request = $doc->requestApprovalFor(
        ['title' => 'Hijacked'],
        'edit'
    );

    $this->editor->reject($request, 'Nope.');

    $doc->refresh();
    expect($doc->title)->toBe('Original');

    $request->refresh();
    // The proposed change is preserved on the request as a record.
    expect($request->pending_changes)->toBe(['title' => 'Hijacked']);
});
