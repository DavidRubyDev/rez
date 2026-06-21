<?php

declare(strict_types=1);

use Rez\Application\Service\AvailabilityService;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCase;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCaseInterface;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCase;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCaseInterface;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCase;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCaseInterface;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCase;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationUseCase;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCase;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCaseInterface;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCase;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCaseInterface;
use Rez\Application\UseCase\Reservation\MarkNoShow\MarkNoShowUseCase;
use Rez\Application\UseCase\Reservation\MarkNoShow\MarkNoShowUseCaseInterface;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceUseCase;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceUseCaseInterface;
use Rez\Application\UseCase\Resource\GetResource\GetResourceUseCase;
use Rez\Application\UseCase\Resource\GetResource\GetResourceUseCaseInterface;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesUseCase;
use Rez\Application\UseCase\Resource\ListResources\ListResourcesUseCaseInterface;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceUseCase;
use Rez\Application\UseCase\Resource\DeleteResource\DeleteResourceUseCaseInterface;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceUseCase;
use Rez\Application\UseCase\Resource\UpdateResource\UpdateResourceUseCaseInterface;
use Rez\Application\UseCase\Seed\SeedDatabase\SeedDatabaseUseCase;
use Rez\Application\UseCase\Seed\SeedDatabase\SeedDatabaseUseCaseInterface;
use Rez\Application\Port\DatabaseSeederInterface;
use Rez\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder;

use function DI\autowire;

return [
    AvailabilityServiceInterface::class      => autowire(AvailabilityService::class),
    CreateReservationUseCaseInterface::class  => autowire(CreateReservationUseCase::class),
    CancelReservationUseCaseInterface::class  => autowire(CancelReservationUseCase::class),
    ConfirmReservationUseCaseInterface::class => autowire(ConfirmReservationUseCase::class),
    MarkNoShowUseCaseInterface::class         => autowire(MarkNoShowUseCase::class),
    GetReservationUseCaseInterface::class     => autowire(GetReservationUseCase::class),
    ListReservationsUseCaseInterface::class   => autowire(ListReservationsUseCase::class),
    GetAvailabilityUseCaseInterface::class          => autowire(GetAvailabilityUseCase::class),
    SaveAvailabilityRuleUseCaseInterface::class     => autowire(SaveAvailabilityRuleUseCase::class),
    SaveAvailabilityOverrideUseCaseInterface::class => autowire(SaveAvailabilityOverrideUseCase::class),
    CreateResourceUseCaseInterface::class     => autowire(CreateResourceUseCase::class),
    GetResourceUseCaseInterface::class        => autowire(GetResourceUseCase::class),
    ListResourcesUseCaseInterface::class      => autowire(ListResourcesUseCase::class),
    UpdateResourceUseCaseInterface::class     => autowire(UpdateResourceUseCase::class),
    DeleteResourceUseCaseInterface::class     => autowire(DeleteResourceUseCase::class),
    DatabaseSeederInterface::class            => autowire(MysqlDatabaseSeeder::class),
    SeedDatabaseUseCaseInterface::class       => autowire(SeedDatabaseUseCase::class),
];
