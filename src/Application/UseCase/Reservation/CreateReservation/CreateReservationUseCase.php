<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\CancellationToken;

final class CreateReservationUseCase implements CreateReservationUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly ReservationSettingsRepositoryInterface $reservationSettingsRepository,
        private readonly ReservationEmailService $emailService,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Domain\Exception\InvalidTimeSlotException
     * @throws ConflictException
     * @throws DatabaseException
     */
    public function execute(CreateReservationRequest $request): CreateReservationResponse
    {
        $resources = [];

        foreach ($request->resourceIds as $resourceId) {
            try {
                $resources[] = $this->resourceRepository->findById($resourceId);
            } catch (DatabaseException $e) {
                throw new DatabaseException('Failed to load resource.', 0, $e);
            }
        }

        $slot = new TimeSlot($request->start, $request->end);

        foreach ($resources as $resource) {
            $this->assertAvailable($slot, $resource, $request->party->size);
        }

        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray($request->resourceIds),
            $slot,
            $request->party,
        );

        try {
            $settings = $this->reservationSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation settings.', 0, $e);
        }

        if ($settings->autoConfirm) {
            $reservation = $reservation->confirm();
        }

        try {
            $this->reservationRepository->save($reservation);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        $token = CancellationToken::generate($reservation->id, $this->usersConfig->cancellationSecret);

        if ($settings->autoConfirm) {
            $this->emailService->sendConfirmedIfEnabled($reservation, $token, $settings);
        } else {
            $this->emailService->sendCreatedIfEnabled($reservation, $token, $settings);
        }

        return new CreateReservationResponse($reservation);
    }

    private function assertAvailable(TimeSlot $slot, Resource $resource, int $partySize): void
    {
        if (!$this->availabilityService->isSlotAvailable($resource->id, $slot, $partySize)) {
            throw new ConflictException($slot, $resource);
        }
    }
}
