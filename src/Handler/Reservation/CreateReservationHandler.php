<?php

declare(strict_types=1);

namespace Rez\Handler\Reservation;

use DateTimeImmutable;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationRequest;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCaseInterface;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\ReservationSerializer;

final class CreateReservationHandler
{
    public function __construct(
        private readonly CreateReservationUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{
     *     resource_ids: string[],
     *     start: string,
     *     end: string,
     *     party: array{name: string, email: string, size: int, phone?: string}
     * } $data
     * @return array{
     *     id: string,
     *     status: string,
     *     start: string,
     *     end: string,
     *     resource_ids: string[],
     *     party: array{name: string, email: string, size: int, phone: string|null},
     *     created_at: string
     * }
     */
    public function handle(array $data): array
    {
        if (empty($data['resource_ids'])) {
            throw new \InvalidArgumentException('resource_ids must not be empty.');
        }

        $resourceIds = array_map(
            fn (string $id) => ResourceId::fromString($id),
            $data['resource_ids'],
        );

        $party = new Party(
            $data['party']['name'],
            $data['party']['email'],
            $data['party']['size'],
            $data['party']['phone'] ?? null,
        );

        $response = $this->useCase->execute(new CreateReservationRequest(
            $resourceIds,
            new DateTimeImmutable($data['start']),
            new DateTimeImmutable($data['end']),
            $party,
        ));

        return ReservationSerializer::serialize($response->reservation);
    }
}
