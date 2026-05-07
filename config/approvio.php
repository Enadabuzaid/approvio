<?php

declare(strict_types=1);

use Enadstack\Approvio\Resolvers\Tenants\ColumnTenantResolver;
use Enadstack\Approvio\Resolvers\Tenants\NullTenantResolver;
use Enadstack\Approvio\Strategies\SoftApproval;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Approval Strategy
    |--------------------------------------------------------------------------
    |
    | The strategy used by Approvable models when none is explicitly set on
    | the model itself. Available out of the box:
    |
    |   - Enadstack\Approvio\Strategies\SoftApproval
    |       The model row exists with `approval_status = pending`. Visible
    |       and queryable from day one. Best for low-risk content.
    |
    |   - Enadstack\Approvio\Strategies\DraftApproval
    |       Changes are held in a `pending_changes` JSON payload and only
    |       applied to the row on approval. Best for high-stakes data.
    |
    */

    'default_strategy' => SoftApproval::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | How Approvio determines the current tenant for a given approval. Set
    | to NullTenantResolver if your app is not multi-tenant. Set to
    | ColumnTenantResolver and configure the column name if you use a
    | shared-database-with-tenant_id pattern.
    |
    | Future v0.3 will ship StanclTenantResolver and SpatieTenantResolver
    | for the popular multi-database packages.
    |
    */

    'tenant_resolver' => NullTenantResolver::class,

    'tenant' => [
        // Used by ColumnTenantResolver
        'column' => 'tenant_id',

        // If true, requests without a tenant will be rejected when a
        // non-null resolver is active. Useful for catching bugs early.
        'require_tenant' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Customize table names if you have collisions or naming conventions.
    | Changing these after data exists requires manual migration.
    |
    */

    'tables' => [
        'workflows' => 'approval_workflows',
        'requests' => 'approval_requests',
        'request_steps' => 'approval_request_steps',
        'step_assignees' => 'approval_step_assignees',
        'actions' => 'approval_actions',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model representing your application's users. Used as the default
    | actor type for approval actions. Override per-action if you have
    | multiple actor types (e.g., admin users vs regular users).
    |
    */

    'user_model' => env('APPROVIO_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Workflow Sources
    |--------------------------------------------------------------------------
    |
    | The order in which Approvio looks for workflow definitions. The first
    | source to return a definition wins. v0.1 only supports 'code'; v0.3
    | will add 'database' for tenant-customizable workflows.
    |
    */

    'workflow_sources' => [
        'code',
        // 'database', // v0.3
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | Configure what gets captured in the immutable audit trail. The audit
    | log is append-only by design — never updated, never deleted.
    |
    */

    'audit' => [
        'capture_ip' => true,
        'capture_user_agent' => true,
        'capture_request_metadata' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Approvio fires events on every state transition. Wire your own
    | notifications by listening to those events. v0.4 will ship
    | optional default notification classes.
    |
    */

    'notifications' => [
        'enabled' => true,
        'queue' => env('APPROVIO_QUEUE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Commands
    |--------------------------------------------------------------------------
    |
    | Set escalate_cron to a valid cron expression to enable automatic
    | scheduling of approvio:escalate via your app's scheduler. Set to
    | null to disable — you can then call the command manually or from
    | your own scheduler entry.
    |
    */

    'schedule' => [
        'escalate_cron' => '* * * * *',
    ],

];
