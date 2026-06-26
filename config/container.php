<?php

declare(strict_types=1);

use Rez\Application\Service\AvailabilityService;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\UseCase\Availability\DeleteAvailabilityOverride\DeleteAvailabilityOverrideUseCase;
use Rez\Application\UseCase\Availability\DeleteAvailabilityOverride\DeleteAvailabilityOverrideUseCaseInterface;
use Rez\Application\UseCase\Availability\DeleteAvailabilityRule\DeleteAvailabilityRuleUseCase;
use Rez\Application\UseCase\Availability\DeleteAvailabilityRule\DeleteAvailabilityRuleUseCaseInterface;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCase;
use Rez\Application\UseCase\Availability\GetAvailability\GetAvailabilityUseCaseInterface;
use Rez\Application\UseCase\Availability\GetAvailabilityOverrides\GetAvailabilityOverridesUseCase;
use Rez\Application\UseCase\Availability\GetAvailabilityOverrides\GetAvailabilityOverridesUseCaseInterface;
use Rez\Application\UseCase\Availability\GetAvailabilityRules\GetAvailabilityRulesUseCase;
use Rez\Application\UseCase\Availability\GetAvailabilityRules\GetAvailabilityRulesUseCaseInterface;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCase;
use Rez\Application\UseCase\Availability\SaveAvailabilityOverride\SaveAvailabilityOverrideUseCaseInterface;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCase;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCaseInterface;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsUseCase;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsUseCaseInterface;
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
use Rez\Application\Service\FeatureGuard;
use Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCase;
use Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCaseInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCase;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCase;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCaseInterface;
use Rez\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder;

use function DI\autowire;

return [
    AvailabilityServiceInterface::class      => autowire(AvailabilityService::class),
    CreateReservationUseCaseInterface::class  => autowire(CreateReservationUseCase::class),
    BulkCancelReservationsUseCaseInterface::class => autowire(BulkCancelReservationsUseCase::class),
    CancelReservationUseCaseInterface::class      => autowire(CancelReservationUseCase::class),
    ConfirmReservationUseCaseInterface::class => autowire(ConfirmReservationUseCase::class),
    MarkNoShowUseCaseInterface::class         => autowire(MarkNoShowUseCase::class),
    GetReservationUseCaseInterface::class     => autowire(GetReservationUseCase::class),
    ListReservationsUseCaseInterface::class   => autowire(ListReservationsUseCase::class),
    GetAvailabilityUseCaseInterface::class               => autowire(GetAvailabilityUseCase::class),
    GetAvailabilityRulesUseCaseInterface::class          => autowire(GetAvailabilityRulesUseCase::class),
    GetAvailabilityOverridesUseCaseInterface::class      => autowire(GetAvailabilityOverridesUseCase::class),
    SaveAvailabilityRuleUseCaseInterface::class          => autowire(SaveAvailabilityRuleUseCase::class),
    DeleteAvailabilityRuleUseCaseInterface::class        => autowire(DeleteAvailabilityRuleUseCase::class),
    SaveAvailabilityOverrideUseCaseInterface::class      => autowire(SaveAvailabilityOverrideUseCase::class),
    DeleteAvailabilityOverrideUseCaseInterface::class    => autowire(DeleteAvailabilityOverrideUseCase::class),
    CreateResourceUseCaseInterface::class     => autowire(CreateResourceUseCase::class),
    GetResourceUseCaseInterface::class        => autowire(GetResourceUseCase::class),
    ListResourcesUseCaseInterface::class      => autowire(ListResourcesUseCase::class),
    UpdateResourceUseCaseInterface::class     => autowire(UpdateResourceUseCase::class),
    DeleteResourceUseCaseInterface::class     => autowire(DeleteResourceUseCase::class),
    DatabaseSeederInterface::class            => autowire(MysqlDatabaseSeeder::class),
    SeedDatabaseUseCaseInterface::class       => autowire(SeedDatabaseUseCase::class),
    // PlatformConfig must be bound by the client app — not defined here.
    // FeatureGuard is autowired — PHP-DI resolves PlatformConfig from client binding.
    FeatureGuard::class                       => autowire(),
    // MailerInterface and NewsletterRepositoryInterface must be bound by the client app.
    SubscribeUseCaseInterface::class          => autowire(SubscribeUseCase::class),
    UnsubscribeUseCaseInterface::class        => autowire(UnsubscribeUseCase::class),
    BroadcastUseCaseInterface::class          => autowire(BroadcastUseCase::class),
];
