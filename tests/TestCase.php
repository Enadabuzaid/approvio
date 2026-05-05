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
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback')->run();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            ApprovioServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('approvio.user_model', TestUser::class);
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
    }
}
