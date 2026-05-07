<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\AssigneeStatus;
use Enadstack\Approvio\Enums\RequestStatus;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

$spatieInstalled = class_exists(\Spatie\Permission\PermissionServiceProvider::class);

/**
 * These tests require spatie/laravel-permission to be installed AND the
 * Spatie migrations to be present. They are skipped automatically in
 * environments where the package is absent.
 *
 * To run these tests locally:
 *   composer require spatie/laravel-permission --dev
 *   vendor/bin/pest tests/Feature/RoleResolverIntegrationTest.php
 */
it('resolves users by role and writes assigned_via = "role" on assignee rows', function () {
    // Seed a role and assign it to a user via Spatie.
    $manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $manager->assignRole('manager'); // @phpstan-ignore-line

    $submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = TestExpense::create([
        'user_id' => $submitter->id,
        'title' => 'Spatie test purchase',
        'amount' => 500,
    ]);

    $request = $expense->requestApproval('submission', $submitter);

    $assignee = $request->steps->first()->assignees->first();
    expect($assignee->assignee_id)->toBe($manager->id)
        ->and($assignee->assigned_via)->toBe('role')
        ->and($assignee->status)->toBe(AssigneeStatus::Pending);
})->skip(! $spatieInstalled, 'spatie/laravel-permission not installed');

it('completes the request when the role-assigned approver approves', function () {
    $manager = TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $manager->assignRole('manager'); // @phpstan-ignore-line

    $submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = TestExpense::create([
        'user_id' => $submitter->id,
        'title' => 'Spatie test purchase',
        'amount' => 500,
    ]);

    $request = $expense->requestApproval('submission', $submitter);
    $request = $manager->approve($request);

    expect($request->status)->toBe(RequestStatus::Approved);
})->skip(! $spatieInstalled, 'spatie/laravel-permission not installed');
