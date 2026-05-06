<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Enums;

enum StepType: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';
}
