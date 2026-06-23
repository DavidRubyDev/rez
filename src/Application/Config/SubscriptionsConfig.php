<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class SubscriptionsConfig
{
    /** @var PlanConfig[] */
    public readonly array $plans;

    /** @param array<mixed> $plans */
    public function __construct(array $plans)
    {
        if ($plans === []) {
            throw new \InvalidArgumentException('plans must not be empty.');
        }

        $this->plans = array_map(
            static fn (mixed $plan): PlanConfig => $plan instanceof PlanConfig
                ? $plan
                : throw new \InvalidArgumentException('Every element of plans must be a PlanConfig instance.'),
            $plans,
        );
    }

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
