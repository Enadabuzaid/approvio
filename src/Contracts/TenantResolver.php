<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the current tenant for an approval operation.
 *
 * Implementations:
 *  - NullTenantResolver: always returns null. For non-tenant apps.
 *  - ColumnTenantResolver: reads tenant_id from the approvable or auth user.
 *  - StanclTenantResolver (v0.3): integrates with stancl/tenancy.
 *  - SpatieTenantResolver (v0.3): integrates with spatie/laravel-multitenancy.
 */
interface TenantResolver
{
    /**
     * Resolve the tenant model for the given approvable, or null if none.
     */
    public function resolve(?Model $approvable = null): ?Model;

    /**
     * Whether the resolver is "active" — i.e., the application is currently
     * operating in a tenant context. Affects scoping of queries.
     */
    public function hasTenant(): bool;
}
