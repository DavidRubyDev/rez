<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

use Rez\Domain\Shared\Feature;

final class FeatureDisabledException extends DomainException
{
    public function __construct(private readonly Feature $feature)
    {
        parent::__construct("Feature '{$feature->name}' is not enabled in PlatformConfig.");
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::FeatureDisabled;
    }

    public function errorParams(): array
    {
        return ['feature' => $this->feature->name];
    }
}
