<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a workflow definition stored in the database. v0.3 will
 * use this for tenant-customizable workflows. Defined now for schema
 * stability.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $version
 * @property string $approvable_type
 * @property array<string, mixed> $definition
 * @property bool $is_active
 */
class ApprovalWorkflow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'definition' => 'array',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('approvio.tables.workflows', 'approval_workflows');
    }

    /** @return MorphTo<Model, $this> */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }
}
