<?php

declare(strict_types=1);

namespace Rez\Handler\Reservation;

use Rez\Application\UseCase\Reservation\GetReservation\GetReservationRequest;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCaseInterface;
use Rez\Domain\Reservation\ReservationId;
use Rez\Handler\ReservationSerializer;

final class GetReservationHandler
{
    public function __construct(
        private readonly GetReservationUseCaseInterface $useCase,
    ) {
    }

    /**
     * @param array{id: string} $data
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
        $response = $this->useCase->execute(
            new GetReservationRequest(ReservationId::fromString($data['id'])),
        );

        return ReservationSerializer::serialize($response->reservation);
    }
}
