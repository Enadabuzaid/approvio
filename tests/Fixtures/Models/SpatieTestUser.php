<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Spatie\Permission\Traits\HasRoles;

/**
 * Test fixture: TestUser extended with Spatie's HasRoles.
 *
 * This file is NOT autoloaded by Composer. It is require_once'd
 * conditionally (only when spatie/laravel-permission is installed)
 * by TestCase::defineEnvironment() so the main CI job never loads it.
 */
class SpatieTestUser extends TestUser
{
    use HasRoles;
}
