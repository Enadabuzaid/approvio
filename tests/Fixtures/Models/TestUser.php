<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Enadstack\Approvio\Concerns\HasApprovalActions;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestUser extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApprovalActions;

    protected $table = 'test_users';

    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TestTenant::class, 'tenant_id');
    }
}
