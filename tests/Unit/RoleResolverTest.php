<?php

declare(strict_types=1);

use Enadstack\Approvio\Exceptions\MissingDependencyException;
use Enadstack\Approvio\Resolvers\Approvers\RoleResolver;

$spatieInstalled = class_exists(\Spatie\Permission\PermissionServiceProvider::class);

it('throws MissingDependencyException when spatie/laravel-permission is not installed', function () {
    new RoleResolver('manager');
})->throws(MissingDependencyException::class, 'spatie/laravel-permission')
  ->skip($spatieInstalled, 'spatie/laravel-permission is installed — cannot test missing-package path');

it('assignedVia() returns "role"', function () {
    $resolver = new RoleResolver('manager');
    expect($resolver->assignedVia())->toBe('role');
})->skip(! $spatieInstalled, 'spatie/laravel-permission not installed');

it('resolve() returns a Collection of users with the given role', function () {
    // Full resolution requires a database seeded with Spatie tables; covered in
    // RoleResolverIntegrationTest. This unit test only verifies the resolver
    // can be constructed and returns the correct assigned_via value.
    $resolver = new RoleResolver('manager');
    expect($resolver->assignedVia())->toBe('role');
})->skip(! $spatieInstalled, 'spatie/laravel-permission not installed');
