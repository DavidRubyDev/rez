<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

final class FeatureDisabledException extends DomainException
{
    public function __construct(string $feature)
    {
        parent::__construct("Feature '{$feature}' is not enabled in PlatformConfig.");
    }
}
