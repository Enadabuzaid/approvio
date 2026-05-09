# Installation

## Requirements

- PHP **8.2** or higher
- Laravel **11** or **12**

No mandatory dependencies beyond Laravel itself. Spatie Permission and tenancy packages are optional integrations — never required.

## Install the package

```bash
composer require enadstack/approvio
```

The service provider and `Approvio` facade are registered automatically via Laravel's package auto-discovery. Nothing to add to `config/app.php`.

## Run the migrations

Five tables are created across six migrations. You have two options for how to manage them.

### Option A — let the package manage the migration files

The service provider calls `loadMigrationsFrom()` at boot, so running `artisan migrate` is all you need:

```bash
php artisan migrate
```

Option A is the lowest-friction path. The migration files live inside the vendor directory; your app only sees the results in the `migrations` table.

### Option B — publish the migration files to your app

Choose Option B if you need to edit the migrations for your app's conventions (custom indexes, renamed columns, adjusted timestamps):

```bash
php artisan vendor:publish --tag="approvio-migrations"
php artisan migrate
```

When both the published files in `database/migrations/` and the package's `loadMigrationsFrom()` path exist simultaneously, Laravel's migrator resolves the conflict by name: it builds a map keyed by filename and processes `database/migrations` last, so your published files take precedence and no migration runs twice.

> **Do not rename published migration files.** The deduplication relies on identical filenames. If you rename a published file, both the vendor original and your renamed copy end up with different migration names — both will run, causing schema errors.

## What gets created

Five tables, in dependency order:

| Table | Purpose |
|---|---|
| `approval_workflows` | Reserved for future DB-defined workflow sources. Present for schema stability — v0.2 does not write to this table. |
| `approval_requests` | One row per approval lifecycle on any model. Holds status, snapshot, context, tenant scope, and a pointer to the current step. |
| `approval_request_steps` | One row per step in a request. Stores step type (sequential/parallel), quorum rule, deadline, and status. |
| `approval_step_assignees` | One row per approver slot in a step. Tracks who was assigned, how they were resolved, and whether they have acted. |
| `approval_actions` | Append-only audit log. Every state change (submit, approve, reject, delegate, escalate, …) is a new row. Never updated or deleted. |

The sixth migration adds `parent_request_id` to `approval_requests` — the self-referential foreign key used by the resubmit feature.

## Publish the config (optional)

```bash
php artisan vendor:publish --tag="approvio-config"
```

This creates `config/approvio.php`. The key values you are most likely to set:

```php
// The Eloquent model that represents your application's users/actors.
// Can also be set via the APPROVIO_USER_MODEL environment variable.
'user_model' => App\Models\User::class,

// Strategy applied when a model has no explicit $approvalStrategy.
'default_strategy' => \Enadstack\Approvio\Strategies\SoftApproval::class,
```

If you are not publishing the config, set the user model via your `.env` file:

```
APPROVIO_USER_MODEL=App\Models\User
```

For all available configuration options — including custom table names, tenant resolver setup, audit settings, and the escalation scheduler — see [Configuration reference](../reference/config.md).

## Verify the installation

Open a Tinker session and run a query against one of the new tables. This confirms both that the service provider booted and that the migrations ran:

```bash
php artisan tinker
>>> \Enadstack\Approvio\Models\ApprovalRequest::query()->count()
# => 0
```

- A `QueryException` ("table not found") → migrations didn't run → `php artisan migrate`
- A "Class not found" error → stale autoloader → `composer dump-autoload`
- A container binding or instantiation error → auto-discovery missed → `php artisan package:discover`

---

**Next:** [Quickstart — your first workflow in 5 minutes](./quickstart.md)
