<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class SubscriptionsConfig
{
    /** @param PlanConfig[] $plans */
    public function __construct(
        public readonly array $plans,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getPlanById(string $id): PlanConfig
    {
        foreach ($this->plans as $plan) {
            if ($plan->id === $id) {
                return $plan;
            }
        }

        throw new \InvalidArgumentException("PlanConfig with id '{$id}' not found.");
    }
}
