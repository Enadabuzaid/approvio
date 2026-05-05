<?php

declare(strict_types=1);

namespace Enadstack\Approvio;

use Enadstack\Approvio\Contracts\TenantResolver;
use Enadstack\Approvio\Contracts\WorkflowSource;
use Enadstack\Approvio\Engine\ApprovalEngine;
use Enadstack\Approvio\Engine\StateMachine;
use Enadstack\Approvio\Workflow\Sources\CodeWorkflowSource;
use Illuminate\Support\ServiceProvider;

class ApprovioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/approvio.php',
            'approvio'
        );

        // Tenant resolver: configurable. Singleton so tenant context is
        // consistent across a request.
        $this->app->singleton(TenantResolver::class, function ($app) {
            $class = config('approvio.tenant_resolver');

            return $app->make($class);
        });

        // State machine is stateless; bind once.
        $this->app->singleton(StateMachine::class);

        // Workflow source registry. v0.1 ships only CodeWorkflowSource.
        $this->app->singleton('approvio.workflow_sources', function ($app) {
            $sources = [];
            foreach (config('approvio.workflow_sources', ['code']) as $key) {
                $sources[] = match ($key) {
                    'code' => $app->make(CodeWorkflowSource::class),
                    // 'database' => $app->make(DatabaseWorkflowSource::class), // v0.3
                    default => null,
                };
            }

            return array_filter($sources);
        });

        $this->app->singleton(ApprovalEngine::class, function ($app) {
            return new ApprovalEngine(
                workflowSources: $app->make('approvio.workflow_sources'),
                tenantResolver: $app->make(TenantResolver::class),
                stateMachine: $app->make(StateMachine::class),
            );
        });

        $this->app->singleton(Approvio::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/approvio.php' => config_path('approvio.php'),
            ], 'approvio-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'approvio-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
