<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Resolvers\Tenants;

use Enadstack\Approvio\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * No-op tenant resolver. Use for non-tenant applications.
 * Always returns null and reports no active tenant context.
 */
class NullTenantResolver implements TenantResolver
{
    public function resolve(?Model $approvable = null): ?Model
    {
        return null;
    }

    public function hasTenant(): bool
    {
        return false;
    }
}
