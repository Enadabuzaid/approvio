<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Resolvers\Approvers;

use Closure;
use Enadstack\Approvio\Contracts\ApproverResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves approvers from a closure that returns a User array, collection,
 * or any iterable of Eloquent models.
 *
 * Used by WorkflowBuilder when you call ->approvers(fn($model) => ...).
 */
class DirectUserResolver implements ApproverResolver
{
    public function __construct(protected Closure $callback)
    {
    }

    public function assignedVia(): string
    {
        return 'direct';
    }

    public function resolve(Model $approvable): Collection
    {
        $result = ($this->callback)($approvable);

        if ($result instanceof Collection) {
            return $result->values();
        }

        if (is_iterable($result)) {
            return collect($result)->values();
        }

        if ($result instanceof Model) {
            return collect([$result]);
        }

        return collect();
    }
}
