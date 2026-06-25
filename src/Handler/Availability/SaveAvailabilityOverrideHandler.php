<?php

declare(strict_types=1);

namespace Rez\Handler\Availability;

use DateTimeImmutable;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideRequest;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCaseInterface;
use Rez\Domain\Resource\ResourceId;

final class SaveAvailabilityOverrideHandler
{
    public function __construct(
        private readonly SaveAvailabilityOverrideUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{resource_id: string, date: string, available: bool} $data
     * @return array{resource_id: string, date: string, available: bool}
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(new SaveAvailabilityOverrideRequest(
            ResourceId::fromString($data['resource_id']),
            new DateTimeImmutable($data['date'], new \DateTimeZone('UTC')),
            $data['available'],
        ));

        $override = $response->override;

        return [
            'resource_id' => $override->resourceId->toString(),
            'date'        => $override->date->format('Y-m-d'),
            'available'   => $override->isAvailable,
        ];
    }
}
