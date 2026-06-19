<?php

declare(strict_types=1);

use Rez\Application\Service\AvailabilityService;
use Rez\Application\Service\AvailabilityServiceInterface;

use function DI\autowire;

return [
    AvailabilityServiceInterface::class => autowire(AvailabilityService::class),
];
