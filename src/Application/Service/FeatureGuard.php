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

    /**
     * @throws FeatureDisabledException
     */
    public function requirePayments(): void
    {
        if (!$this->config->hasPayments()) {
            throw new FeatureDisabledException(Feature::Payments);
        }
    }

    /**
     * @throws FeatureDisabledException
     */
    public function requireCredits(): void
    {
        if (!$this->config->hasCredits()) {
            throw new FeatureDisabledException(Feature::Credits);
        }
    }

    /**
     * @throws FeatureDisabledException
     */
    public function requireSubscriptions(): void
    {
        if (!$this->config->hasSubscriptions()) {
            throw new FeatureDisabledException(Feature::Subscriptions);
        }
    }
}
