<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the set of users (or other entities) who may act on a step.
 *
 * v0.1 ships:
 *  - DirectUserResolver: explicit list of users.
 *
 * v0.2 will add:
 *  - RoleResolver (Spatie permission roles)
 *  - RelationshipResolver (e.g., $model->team->manager)
 *  - Custom resolvers via this interface.
 */
interface ApproverResolver
{
    /**
     * Resolve approvers for the given approvable model.
     *
     * @return Collection<int, Model>
     */
    public function resolve(Model $approvable): Collection;

    /**
     * The value written to approval_step_assignees.assigned_via.
     * Resolvers that find users through a different mechanism (e.g. role, relation)
     * should return a descriptive source name.
     */
    public function assignedVia(): string;
}
