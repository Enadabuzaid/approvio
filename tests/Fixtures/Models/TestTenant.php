<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class TestTenant extends Model
{
    protected $table = 'test_tenants';

    protected $guarded = [];
}
