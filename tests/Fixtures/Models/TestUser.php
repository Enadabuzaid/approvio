<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Enadstack\Approvio\Concerns\HasApprovalActions;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    use HasApprovalActions;

    protected $table = 'test_users';

    protected $guarded = [];
}
