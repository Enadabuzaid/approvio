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
 *
 * The 'role' workflow on TestExpense uses RoleResolver('manager'), which
 * queries the configured user model (SpatieTestUser when Spatie is present)
 * for users assigned the 'manager' role.
 */
it('resolves users by role and writes assigned_via = "role" on assignee rows', function () {
    /** @var \Enadstack\Approvio\Tests\Fixtures\Models\SpatieTestUser $manager */
    $manager = \Enadstack\Approvio\Tests\Fixtures\Models\SpatieTestUser::create([
        'name' => 'Bob',
        'email' => 'manager-bob@example.com',
    ]);
    $manager->assignRole('manager');

    $submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = TestExpense::create([
        'user_id' => $submitter->id,
        'title' => 'Spatie test purchase',
        'amount' => 500,
    ]);

    $request = $expense->requestApproval('role', $submitter);

    $assignee = $request->steps->first()->assignees->first();
    expect($assignee->assignee_id)->toBe($manager->id)
        ->and($assignee->assigned_via)->toBe('role')
        ->and($assignee->status)->toBe(AssigneeStatus::Pending);
})->skip(! $spatieInstalled, 'spatie/laravel-permission not installed');

it('completes the request when the role-assigned approver approves', function () {
    /** @var \Enadstack\Approvio\Tests\Fixtures\Models\SpatieTestUser $manager */
    $manager = \Enadstack\Approvio\Tests\Fixtures\Models\SpatieTestUser::create([
        'name' => 'Bob',
        'email' => 'manager-bob@example.com',
    ]);
    $manager->assignRole('manager');

    $submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = TestExpense::create([
        'user_id' => $submitter->id,
        'title' => 'Spatie test purchase',
        'amount' => 500,
    ]);

    $request = $expense->requestApproval('role', $submitter);
    $request = $manager->approve($request);

    expect($request->status)->toBe(RequestStatus::Approved);
})->skip(! $spatieInstalled, 'spatie/laravel-permission not installed');
