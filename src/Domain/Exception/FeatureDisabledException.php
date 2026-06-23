<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

use Rez\Domain\Shared\Feature;

final class FeatureDisabledException extends DomainException
{
    public function __construct(Feature $feature)
    {
        parent::__construct("Feature '{$feature->name}' is not enabled in PlatformConfig.");
    }
}
