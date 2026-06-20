<?php

declare(strict_types=1);

namespace Rez\Handler;

use Rez\Domain\Reservation\Reservation;
use Rez\Infrastructure\Mapper\ReservationStatusMapper;

final class ReservationSerializer
{
    /**
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
    public static function serialize(Reservation $reservation): array
    {
        $mapper = new ReservationStatusMapper();

        return [
            'id'           => $reservation->id->toString(),
            'status'       => $mapper->toString($reservation->status),
            'start'        => $reservation->slot->start->format('Y-m-d H:i:s'),
            'end'          => $reservation->slot->end->format('Y-m-d H:i:s'),
            'resource_ids' => array_map(
                fn ($id) => $id->toString(),
                $reservation->resourceIds->toArray(),
            ),
            'party'        => [
                'name'  => $reservation->party->name,
                'email' => $reservation->party->email,
                'size'  => $reservation->party->size,
                'phone' => $reservation->party->phone,
            ],
            'created_at'   => $reservation->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
