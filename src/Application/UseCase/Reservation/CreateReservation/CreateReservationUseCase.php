<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Exception\InvalidSessionStateException;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Session\SessionStatus;
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
        private readonly SessionRepositoryInterface $sessionRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws \Rez\Domain\Exception\InvalidTimeSlotException
     * @throws InvalidSessionStateException
     * @throws ConflictException
     * @throws DatabaseException
     */
    public function execute(CreateReservationRequest $request): CreateReservationResponse
    {
        if ($request->sessionId !== null) {
            [$slot, $resourceIds, $sessionId] = $this->resolveSessionSlot($request);
        } else {
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

            $resourceIds = $request->resourceIds;
            $sessionId   = null;
        }

        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray($resourceIds),
            $slot,
            $request->party,
            $sessionId,
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

    /**
     * @return array{0: TimeSlot, 1: ResourceId[], 2: SessionId}
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws InvalidSessionStateException
     * @throws ConflictException
     * @throws DatabaseException
     */
    private function resolveSessionSlot(CreateReservationRequest $request): array
    {
        /** @var string $sessionIdValue */
        $sessionIdValue = $request->sessionId;

        try {
            $session = $this->sessionRepository->findById(SessionId::fromString($sessionIdValue));
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load session.', 0, $e);
        }

        if ($session->status !== SessionStatus::Scheduled) {
            throw new InvalidSessionStateException('Only a scheduled session can accept new reservations.');
        }

        try {
            $resource = $this->resourceRepository->findById($session->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        $slot = $session->toTimeSlot();

        $this->assertSessionHasCapacity($session, $slot, $resource, $request->party->size);

        return [$slot, [$session->resourceId], $session->id];
    }

    /** @throws ConflictException */
    private function assertSessionHasCapacity(Session $session, TimeSlot $slot, Resource $resource, int $incomingPartySize): void
    {
        try {
            $existingReservations = $this->reservationRepository->findBySessionId($session->id);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservations.', 0, $e);
        }

        $bookedSize = 0;
        foreach ($existingReservations->toArray() as $reservation) {
            if ($reservation->status !== ReservationStatus::Cancelled) {
                $bookedSize += $reservation->party->size;
            }
        }

        if ($bookedSize + $incomingPartySize > $session->capacity) {
            throw new ConflictException($slot, $resource);
        }
    }

    private function assertAvailable(TimeSlot $slot, Resource $resource, int $partySize): void
    {
        if (!$this->availabilityService->isSlotAvailable($resource->id, $slot, $partySize)) {
            throw new ConflictException($slot, $resource);
        }
    }
}
