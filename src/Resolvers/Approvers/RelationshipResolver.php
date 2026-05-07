<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Resolvers\Approvers;

use Enadstack\Approvio\Contracts\ApproverResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves approvers by walking a dot-notation chain of Eloquent relations.
 *
 * Examples:
 *   'user'          → $approvable->user  (BelongsTo → single Model)
 *   'user.manager'  → $approvable->user->manager
 *   'department.members' → Collection of Models
 *
 * If any segment in the chain is null or not a Model, an empty collection is
 * returned — no exception is thrown.
 */
class RelationshipResolver implements ApproverResolver
{
    public function __construct(private readonly string $chain)
    {
    }

    public function assignedVia(): string
    {
        return 'relationship';
    }

    public function resolve(Model $approvable): Collection
    {
        if ($this->chain === '') {
            return collect();
        }

        $segments = explode('.', $this->chain);

        $current = $approvable;

        foreach ($segments as $segment) {
            if (! $current instanceof Model) {
                return collect();
            }

            /** @var mixed $next */
            $next = $current->{$segment};

            if ($next === null) {
                return collect();
            }

            $current = $next;
        }

        if ($current instanceof Collection) {
            return $current->filter(fn (mixed $item): bool => $item instanceof Model)->values();
        }

        if ($current instanceof Model) {
            return collect([$current]);
        }

        return collect();
    }
}
