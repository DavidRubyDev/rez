<?php

declare(strict_types=1);

use Rez\Application\Service\AvailabilityService;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCase;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCaseInterface;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCase;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCase;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCase;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCaseInterface;

use function DI\autowire;

return [
    AvailabilityServiceInterface::class      => autowire(AvailabilityService::class),
    CreateReservationUseCaseInterface::class  => autowire(CreateReservationUseCase::class),
    CancelReservationUseCaseInterface::class  => autowire(CancelReservationUseCase::class),
    GetReservationUseCaseInterface::class     => autowire(GetReservationUseCase::class),
    ListReservationsUseCaseInterface::class   => autowire(ListReservationsUseCase::class),
    GetAvailabilityUseCaseInterface::class    => autowire(GetAvailabilityUseCase::class),
];
