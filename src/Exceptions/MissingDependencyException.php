<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Exceptions;

class MissingDependencyException extends ApprovioException
{
    public static function forPackage(string $package, string $feature): self
    {
        return new self(
            "Feature [{$feature}] requires [{$package}]. "
            . "Install it with: composer require {$package}"
        );
    }
}
