<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Resolvers\Approvers;

use Enadstack\Approvio\Contracts\ApproverResolver;
use Enadstack\Approvio\Exceptions\MissingDependencyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves approvers by Spatie Permission role name.
 *
 * Requires: composer require spatie/laravel-permission
 *
 * Configure the user model in config/approvio.php under 'user_model'.
 */
class RoleResolver implements ApproverResolver
{
    public function __construct(
        protected string $roleName,
        protected ?string $guardName = null,
    ) {
        if (! class_exists(\Spatie\Permission\PermissionServiceProvider::class)) {
            throw MissingDependencyException::forPackage(
                'spatie/laravel-permission',
                'RoleResolver',
            );
        }
    }

    public function assignedVia(): string
    {
        return 'role';
    }

    public function resolve(Model $approvable): Collection
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('approvio.user_model', 'App\\Models\\User');

        // @phpstan-ignore staticMethod.notFound
        return $userModel::role($this->roleName, $this->guardName)->get();
    }
}
