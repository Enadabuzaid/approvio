<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Contracts;

use Enadstack\Approvio\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

interface ConditionEvaluator
{
    public function evaluate(Model $approvable, ApprovalRequest $request): bool;
}
