<?php

declare(strict_types=1);

namespace Rez\Handler\Reservation;

use DateTimeImmutable;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsRequest;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCaseInterface;
use Rez\Domain\Resource\ResourceId;
use Rez\Handler\ReservationSerializer;

final class ListReservationsHandler
{
    public function __construct(
        private readonly ListReservationsUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{from?: string, to?: string, resource_id?: string} $data
     * @return list<array{
     *     id: string,
     *     status: string,
     *     start: string,
     *     end: string,
     *     resource_ids: string[],
     *     party: array{name: string, email: string, size: int, phone: string|null, external_ref: string|null},
     *     created_at: string
     * }>
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(new ListReservationsRequest(
            from:       isset($data['from']) ? new DateTimeImmutable($data['from']) : null,
            to:         isset($data['to']) ? new DateTimeImmutable($data['to']) : null,
            resourceId: isset($data['resource_id']) ? ResourceId::fromString($data['resource_id']) : null,
        ));

        return array_map(
            ReservationSerializer::serialize(...),
            $response->reservations->toArray(),
        );
    }
}
