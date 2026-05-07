<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests;

use Enadstack\Approvio\ApprovioServiceProvider;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSandboxSchema();
    }

    /**
     * Tell Testbench to run the package migrations before each test.
     * The service provider registers the migration paths via loadMigrationsFrom();
     * calling artisan migrate here ensures Testbench actually executes them.
     * Using artisan directly (not loadMigrationsFrom again) prevents double-run.
     *
     * When spatie/laravel-permission is installed we also load its migrations so
     * the role/permission tables exist for RoleResolverIntegrationTest.
     */
    protected function defineDatabaseMigrations(): void
    {
        if (class_exists(\Spatie\Permission\PermissionServiceProvider::class)) {
            $reflector = new \ReflectionClass(\Spatie\Permission\PermissionServiceProvider::class);
            $migrationsPath = dirname(dirname($reflector->getFileName())) . '/database/migrations';
            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }

        $this->artisan('migrate')->run();

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback')->run();
        });
    }

    protected function getPackageProviders($app): array
    {
        $providers = [ApprovioServiceProvider::class];

        if (class_exists(\Spatie\Permission\PermissionServiceProvider::class)) {
            $providers[] = \Spatie\Permission\PermissionServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $driver = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', 'testing');

        if ($driver === 'mysql') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'approvio_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ]);
        } elseif ($driver === 'pgsql') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'approvio_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ]);
        } else {
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        $app['config']->set('approvio.user_model', TestUser::class);

        if (class_exists(\Spatie\Permission\PermissionServiceProvider::class)) {
            require_once __DIR__ . '/Fixtures/Models/SpatieTestUser.php';
            $app['config']->set('approvio.user_model', \Enadstack\Approvio\Tests\Fixtures\Models\SpatieTestUser::class);
        }
    }

    /**
     * Sandbox tables used by the fixture models. Approvio's own migrations
     * are loaded automatically by the service provider.
     */
    protected function setUpSandboxSchema(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('test_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('approval_status')->default('draft');
            $table->timestamps();
        });

        Schema::create('test_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('test_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
