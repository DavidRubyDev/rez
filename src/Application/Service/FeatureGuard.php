<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use Rez\Application\Config\PlatformConfig;
use Rez\Domain\Exception\FeatureDisabledException;
use Rez\Domain\Shared\Feature;

final class FeatureGuard
{
    public function __construct(
        private readonly PlatformConfig $config,
    ) {
    }

    public function requirePayments(): void
    {
        if (!$this->config->hasPayments()) {
            throw new FeatureDisabledException(Feature::Payments);
        }
    }

    public function requireUsers(): void
    {
        if (!$this->config->hasUsers()) {
            throw new FeatureDisabledException(Feature::Users);
        }
    }

    public function requireCredits(): void
    {
        if (!$this->config->hasCredits()) {
            throw new FeatureDisabledException(Feature::Credits);
        }
    }

    public function requireSubscriptions(): void
    {
        if (!$this->config->hasSubscriptions()) {
            throw new FeatureDisabledException(Feature::Subscriptions);
        }
    }
}
