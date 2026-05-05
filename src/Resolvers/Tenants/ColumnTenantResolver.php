<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Resolvers\Tenants;

use Enadstack\Approvio\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the tenant from a tenant_id column on the approvable model
 * (or its `tenant` relation if present). Falls back to the authenticated
 * user's tenant_id.
 *
 * The column name is configurable via approvio.tenant.column.
 */
class ColumnTenantResolver implements TenantResolver
{
    public function resolve(?Model $approvable = null): ?Model
    {
        // Prefer an explicit `tenant` relation if defined on the approvable.
        if ($approvable && method_exists($approvable, 'tenant')) {
            // @phpstan-ignore-next-line property.notFound -- `tenant` is a dynamic Eloquent relation loaded via __get(); method_exists() guards the call above
            $tenant = $approvable->tenant;
            if ($tenant instanceof Model) {
                return $tenant;
            }
        }

        // Fall back to the authenticated user's tenant relation.
        $user = auth()->user();
        if ($user instanceof Model && method_exists($user, 'tenant')) {
            // @phpstan-ignore-next-line property.notFound -- same as above; `tenant` is a dynamic relation on the user model
            $tenant = $user->tenant;
            if ($tenant instanceof Model) {
                return $tenant;
            }
        }

        return null;
    }

    public function hasTenant(): bool
    {
        return $this->resolve() !== null;
    }
}
