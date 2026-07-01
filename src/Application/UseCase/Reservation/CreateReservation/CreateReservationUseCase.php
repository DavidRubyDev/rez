<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use Psr\Log\LoggerInterface;
use Rez\Application\Config\PlatformConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceIdCollection;

final class CreateReservationUseCase implements CreateReservationUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly PlatformConfig $config,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
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

        if ($this->config->reservations->autoConfirm) {
            $reservation = $reservation->confirm();
        }

        try {
            $this->reservationRepository->save($reservation);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        try {
            $this->mailer->sendBookingConfirmation(
                $reservation->party->email,
                $reservation->party->name,
                $reservation,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send reservation confirmation email', [
                'reservationId' => $reservation->id->toString(),
                'email'         => $reservation->party->email,
                'error'         => $e->getMessage(),
            ]);
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
