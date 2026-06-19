<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceIdCollection;

final class CreateReservationUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
        private readonly AvailabilityServiceInterface $availabilityService,
    ) {
    }

    public function execute(CreateReservationRequest $request): CreateReservationResponse
    {
        $resources = [];

        foreach ($request->resourceIds as $resourceId) {
            $resources[] = $this->resourceRepository->findById($resourceId);
        }

        $slot = new TimeSlot($request->start, $request->end);

        foreach ($resources as $resource) {
            $this->assertAvailable($slot, $resource);
        }

        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray($request->resourceIds),
            $slot,
            $request->party,
        );

        $this->reservationRepository->save($reservation);

        return new CreateReservationResponse($reservation);
    }

    private function assertAvailable(TimeSlot $slot, Resource $resource): void
    {
        if (!$this->availabilityService->isSlotAvailable($resource->id(), $slot)) {
            throw new ConflictException($slot, $resource);
        }
    }
}
