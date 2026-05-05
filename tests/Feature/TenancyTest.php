<?php

declare(strict_types=1);

use Enadstack\Approvio\Contracts\TenantResolver;
use Enadstack\Approvio\Resolvers\Tenants\ColumnTenantResolver;
use Enadstack\Approvio\Resolvers\Tenants\NullTenantResolver;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestTenant;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;

beforeEach(function () {
    $this->submitter = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    TestUser::create(['name' => 'Bob', 'email' => 'manager-bob@example.com']);
    $this->expense = TestExpense::create([
        'user_id' => $this->submitter->id,
        'title' => 'Office supplies',
        'amount' => 50,
    ]);
});

it('returns null tenant on the request when using NullTenantResolver', function () {
    $this->app->instance(TenantResolver::class, new NullTenantResolver());

    $request = $this->expense->requestApproval('submission', $this->submitter);

    expect($request->tenant_type)->toBeNull()
        ->and($request->tenant_id)->toBeNull();
});

it('writes the tenant onto the request from the approvable relation', function () {
    $tenant = TestTenant::create(['name' => 'Acme Corp']);
    $this->expense->update(['tenant_id' => $tenant->id]);

    $this->app->instance(TenantResolver::class, new ColumnTenantResolver());

    $request = $this->expense->fresh()->requestApproval('submission', $this->submitter);

    expect($request->tenant_id)->toBe($tenant->id)
        ->and($request->tenant_type)->toBe($tenant->getMorphClass());
});

it('reads the tenant from the approvable tenant relation', function () {
    $tenant = TestTenant::create(['name' => 'Beta LLC']);
    $this->expense->update(['tenant_id' => $tenant->id]);

    $resolved = (new ColumnTenantResolver())->resolve($this->expense->fresh());

    expect($resolved)->toBeInstanceOf(TestTenant::class)
        ->and($resolved->id)->toBe($tenant->id);
});

it('reads the tenant from the auth user when the approvable has none', function () {
    $tenant = TestTenant::create(['name' => 'Gamma Inc']);
    $this->submitter->update(['tenant_id' => $tenant->id]);

    auth()->setUser($this->submitter->fresh());

    $resolved = (new ColumnTenantResolver())->resolve($this->expense); // expense has no tenant

    expect($resolved)->toBeInstanceOf(TestTenant::class)
        ->and($resolved->id)->toBe($tenant->id);
});
