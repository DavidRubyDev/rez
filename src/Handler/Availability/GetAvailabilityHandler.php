<?php

declare(strict_types=1);

namespace Rez\Handler\Availability;

use DateTimeImmutable;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityRequest;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCaseInterface;
use Rez\Domain\Resource\ResourceId;

final class GetAvailabilityHandler
{
    public function __construct(
        private readonly GetAvailabilityUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{resource_id: string, date: string, slot_duration_minutes?: int} $data
     * @return array{
     *     resource_id: string,
     *     date: string,
     *     slots: list<array{start: string, end: string}>
     * }
     */
    public function handle(array $data): array
    {
        $window = $this->useCase->execute(new GetAvailabilityRequest(
            ResourceId::fromString($data['resource_id']),
            new DateTimeImmutable($data['date'], new \DateTimeZone('UTC')),
            $data['slot_duration_minutes'] ?? 0,
        ))->window;

        return [
            'resource_id' => $window->resourceId->toString(),
            'date'        => $window->date->format('Y-m-d'),
            'slots'       => array_map(
                fn ($slot) => [
                    'start' => $slot->start->format('Y-m-d H:i:s'),
                    'end'   => $slot->end->format('Y-m-d H:i:s'),
                ],
                $window->slots,
            ),
        ];
    }
}
