<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Infrastructure\Token\RandomTokenGenerator;
use Rez\Application\Service\AvailabilityService;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate\CreateEmailTemplateUseCase;
use Rez\Application\UseCase\EmailTemplate\CreateEmailTemplate\CreateEmailTemplateUseCaseInterface;
use Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate\DeleteEmailTemplateUseCase;
use Rez\Application\UseCase\EmailTemplate\DeleteEmailTemplate\DeleteEmailTemplateUseCaseInterface;
use Rez\Application\UseCase\EmailTemplate\GetEmailTemplate\GetEmailTemplateUseCase;
use Rez\Application\UseCase\EmailTemplate\GetEmailTemplate\GetEmailTemplateUseCaseInterface;
use Rez\Application\UseCase\EmailTemplate\ListEmailTemplates\ListEmailTemplatesUseCase;
use Rez\Application\UseCase\EmailTemplate\ListEmailTemplates\ListEmailTemplatesUseCaseInterface;
use Rez\Application\UseCase\EmailTemplate\SendEmailTemplate\SendEmailTemplateUseCase;
use Rez\Application\UseCase\EmailTemplate\SendEmailTemplate\SendEmailTemplateUseCaseInterface;
use Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate\UpdateEmailTemplateUseCase;
use Rez\Application\UseCase\EmailTemplate\UpdateEmailTemplate\UpdateEmailTemplateUseCaseInterface;
use Rez\Application\UseCase\MailerSettings\GetMailerSettings\GetMailerSettingsUseCase;
use Rez\Application\UseCase\MailerSettings\GetMailerSettings\GetMailerSettingsUseCaseInterface;
use Rez\Application\UseCase\MailerSettings\UpdateMailerSettings\UpdateMailerSettingsUseCase;
use Rez\Application\UseCase\MailerSettings\UpdateMailerSettings\UpdateMailerSettingsUseCaseInterface;
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
use Rez\Application\UseCase\Reservation\CheckIn\CheckInUseCase;
use Rez\Application\UseCase\Reservation\CheckIn\CheckInUseCaseInterface;
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
use Rez\Application\UseCase\ReservationEmail\SendReservationCancelledEmail\SendReservationCancelledEmailUseCase;
use Rez\Application\UseCase\ReservationEmail\SendReservationCancelledEmail\SendReservationCancelledEmailUseCaseInterface;
use Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail\SendReservationConfirmedEmailUseCase;
use Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail\SendReservationConfirmedEmailUseCaseInterface;
use Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail\SendReservationCreatedEmailUseCase;
use Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail\SendReservationCreatedEmailUseCaseInterface;
use Rez\Application\UseCase\ReservationSettings\GetReservationSettings\GetReservationSettingsUseCase;
use Rez\Application\UseCase\ReservationSettings\GetReservationSettings\GetReservationSettingsUseCaseInterface;
use Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings\UpdateReservationSettingsUseCase;
use Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings\UpdateReservationSettingsUseCaseInterface;
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
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\UseCase\Session\CancelSession\CancelSessionUseCase;
use Rez\Application\UseCase\Session\CancelSession\CancelSessionUseCaseInterface;
use Rez\Application\UseCase\Session\CreateSession\CreateSessionUseCase;
use Rez\Application\UseCase\Session\CreateSession\CreateSessionUseCaseInterface;
use Rez\Application\UseCase\Session\GetSession\GetSessionUseCase;
use Rez\Application\UseCase\Session\GetSession\GetSessionUseCaseInterface;
use Rez\Application\UseCase\Session\ListSessions\ListSessionsUseCase;
use Rez\Application\UseCase\Session\ListSessions\ListSessionsUseCaseInterface;
use Rez\Infrastructure\Persistence\Mysql\MysqlSessionRepository;
use Rez\Application\Service\FeatureGuard;
use Rez\Application\Service\ReservationEmailService;
use Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCase;
use Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCaseInterface;
use Rez\Application\UseCase\Newsletter\ListSubscribers\ListSubscribersUseCase;
use Rez\Application\UseCase\Newsletter\ListSubscribers\ListSubscribersUseCaseInterface;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCase;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCase;
use Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCaseInterface;
use Rez\Application\UseCase\Auth\Register\RegisterUseCase;
use Rez\Application\UseCase\Auth\Register\RegisterUseCaseInterface;
use Rez\Application\UseCase\Auth\Login\LoginUseCase;
use Rez\Application\UseCase\Auth\Login\LoginUseCaseInterface;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetUseCase;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetUseCaseInterface;
use Rez\Application\UseCase\Auth\ResetPassword\ResetPasswordUseCase;
use Rez\Application\UseCase\Auth\ResetPassword\ResetPasswordUseCaseInterface;
use Rez\Application\UseCase\User\DeleteUser\DeleteUserUseCase;
use Rez\Application\UseCase\User\DeleteUser\DeleteUserUseCaseInterface;
use Rez\Application\UseCase\User\GetUser\GetUserUseCase;
use Rez\Application\UseCase\User\GetUser\GetUserUseCaseInterface;
use Rez\Application\UseCase\User\UpdateUser\UpdateUserUseCase;
use Rez\Application\UseCase\User\UpdateUser\UpdateUserUseCaseInterface;
use Rez\Application\UseCase\User\ListUsers\ListUsersUseCase;
use Rez\Application\UseCase\User\ListUsers\ListUsersUseCaseInterface;
use Rez\Application\UseCase\User\AdminUpdateUser\AdminUpdateUserUseCase;
use Rez\Application\UseCase\User\AdminUpdateUser\AdminUpdateUserUseCaseInterface;
use Rez\Application\UseCase\User\AdminCreateUser\AdminCreateUserUseCase;
use Rez\Application\UseCase\User\AdminCreateUser\AdminCreateUserUseCaseInterface;
use Rez\Application\Service\JwtService;
use Rez\Infrastructure\Mailer\NullMailer;
use Rez\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder;

use function DI\autowire;

return [
    // Default no-op bindings. $mailer and $logger are required (non-optional) constructor
    // parameters throughout this library specifically so PHP-DI's reflection autowiring always
    // resolves them from the container — autowiring silently skips any optional parameter
    // (one with a default value) without ever consulting the container for it, which previously
    // caused client-bound mailers/loggers to be ignored. Client apps override these two bindings
    // with concrete implementations (e.g. SymfonyMailer, Monolog).
    MailerInterface::class                   => autowire(NullMailer::class),
    LoggerInterface::class                   => autowire(NullLogger::class),

    AvailabilityServiceInterface::class      => autowire(AvailabilityService::class),
    CreateReservationUseCaseInterface::class  => autowire(CreateReservationUseCase::class),
    BulkCancelReservationsUseCaseInterface::class => autowire(BulkCancelReservationsUseCase::class),
    CancelReservationUseCaseInterface::class      => autowire(CancelReservationUseCase::class),
    ConfirmReservationUseCaseInterface::class => autowire(ConfirmReservationUseCase::class),
    MarkNoShowUseCaseInterface::class         => autowire(MarkNoShowUseCase::class),
    CheckInUseCaseInterface::class            => autowire(CheckInUseCase::class),
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
    SessionRepositoryInterface::class         => autowire(MysqlSessionRepository::class),
    CreateSessionUseCaseInterface::class      => autowire(CreateSessionUseCase::class),
    CancelSessionUseCaseInterface::class      => autowire(CancelSessionUseCase::class),
    GetSessionUseCaseInterface::class         => autowire(GetSessionUseCase::class),
    ListSessionsUseCaseInterface::class       => autowire(ListSessionsUseCase::class),
    SeedDatabaseUseCaseInterface::class       => autowire(SeedDatabaseUseCase::class),
    // ReservationSettingsRepositoryInterface must be bound by the client app (same pattern as
    // NewsletterRepositoryInterface below — the MySQL implementation lives in this library, but
    // the interface-to-implementation binding is a client-app concern).
    GetReservationSettingsUseCaseInterface::class    => autowire(GetReservationSettingsUseCase::class),
    UpdateReservationSettingsUseCaseInterface::class => autowire(UpdateReservationSettingsUseCase::class),
    // MailerSettingsRepositoryInterface must be bound by the client app, same as
    // ReservationSettingsRepositoryInterface above.
    GetMailerSettingsUseCaseInterface::class         => autowire(GetMailerSettingsUseCase::class),
    UpdateMailerSettingsUseCaseInterface::class      => autowire(UpdateMailerSettingsUseCase::class),
    // EmailTemplateRepositoryInterface must be bound by the client app, same pattern.
    CreateEmailTemplateUseCaseInterface::class       => autowire(CreateEmailTemplateUseCase::class),
    GetEmailTemplateUseCaseInterface::class          => autowire(GetEmailTemplateUseCase::class),
    ListEmailTemplatesUseCaseInterface::class        => autowire(ListEmailTemplatesUseCase::class),
    UpdateEmailTemplateUseCaseInterface::class       => autowire(UpdateEmailTemplateUseCase::class),
    DeleteEmailTemplateUseCaseInterface::class       => autowire(DeleteEmailTemplateUseCase::class),
    SendEmailTemplateUseCaseInterface::class         => autowire(SendEmailTemplateUseCase::class),
    SendReservationCreatedEmailUseCaseInterface::class   => autowire(SendReservationCreatedEmailUseCase::class),
    SendReservationConfirmedEmailUseCaseInterface::class => autowire(SendReservationConfirmedEmailUseCase::class),
    SendReservationCancelledEmailUseCaseInterface::class => autowire(SendReservationCancelledEmailUseCase::class),
    // PlatformConfig and MailerConfig must be bound by the client app — not defined here.
    // FeatureGuard and ReservationEmailService are autowired — PHP-DI resolves their config/
    // port dependencies from client bindings.
    FeatureGuard::class                       => autowire(),
    ReservationEmailService::class            => autowire(),
    // NewsletterRepositoryInterface must be bound by the client app.
    SubscribeUseCaseInterface::class          => autowire(SubscribeUseCase::class),
    UnsubscribeUseCaseInterface::class        => autowire(UnsubscribeUseCase::class),
    BroadcastUseCaseInterface::class          => autowire(BroadcastUseCase::class),
    ListSubscribersUseCaseInterface::class    => autowire(ListSubscribersUseCase::class),
    // Users are core (always present, never gated). UserRepositoryInterface and
    // PasswordResetRepositoryInterface must be bound by the client app — same pattern as every
    // other rez-owned repository interface above. TokenGeneratorInterface is bound directly
    // below (unlike the repositories) since RandomTokenGenerator has no external dependency of
    // its own — nothing client-specific to override. MailerInterface (already bound above) must
    // resolve to a concrete implementation that implements sendPasswordReset() for
    // RequestPasswordResetUseCase to work.
    TokenGeneratorInterface::class            => autowire(RandomTokenGenerator::class),
    JwtService::class                         => autowire(),
    RegisterUseCaseInterface::class           => autowire(RegisterUseCase::class),
    LoginUseCaseInterface::class              => autowire(LoginUseCase::class),
    RequestPasswordResetUseCaseInterface::class => autowire(RequestPasswordResetUseCase::class),
    ResetPasswordUseCaseInterface::class      => autowire(ResetPasswordUseCase::class),
    GetUserUseCaseInterface::class            => autowire(GetUserUseCase::class),
    DeleteUserUseCaseInterface::class         => autowire(DeleteUserUseCase::class),
    UpdateUserUseCaseInterface::class         => autowire(UpdateUserUseCase::class),
    ListUsersUseCaseInterface::class          => autowire(ListUsersUseCase::class),
    AdminUpdateUserUseCaseInterface::class    => autowire(AdminUpdateUserUseCase::class),
    AdminCreateUserUseCaseInterface::class    => autowire(AdminCreateUserUseCase::class),
];
