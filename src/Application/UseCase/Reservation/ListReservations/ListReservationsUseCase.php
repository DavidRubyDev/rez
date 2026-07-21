<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Validation\ListParamsValidator;

final class ListReservationsUseCase implements ListReservationsUseCaseInterface
{
    private const SORTABLE = ['start', 'end', 'status', 'party_name', 'created_at'];

    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    /**
     * @throws DatabaseException
     * @throws \InvalidArgumentException
     */
    public function execute(ListReservationsRequest $request): ListReservationsResponse
    {
        ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

        try {
            $reservations = $this->reservationRepository->findPage(
                from: $request->from,
                to: $request->to,
                resourceId: $request->resourceId,
                status: $request->status,
                search: $request->search,
                offset: $request->offset,
                limit: $request->limit,
                sortBy: $request->sortBy,
                sortDir: $request->sortDir,
            );
            $total = $this->reservationRepository->countPage(
                from: $request->from,
                to: $request->to,
                resourceId: $request->resourceId,
                status: $request->status,
                search: $request->search,
            );
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list reservations.', 0, $e);
        }

        return new ListReservationsResponse($reservations, $total);
    }
}
