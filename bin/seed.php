#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

use DI\ContainerBuilder;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationRequest;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCaseInterface;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceRequest;
use Rez\Application\UseCase\Resource\CreateResource\CreateResourceUseCaseInterface;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Resource\ResourceId;
use Rez\Infrastructure\Persistence\Mysql\MysqlAvailabilityRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;
use Rez\Infrastructure\Mapper\DayOfWeekMapper;
use Rez\Infrastructure\Mapper\ReservationStatusMapper;
use Rez\Infrastructure\Mapper\ResourceTypeMapper;

use function DI\autowire;
use function DI\factory;

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_NAME'] ?? 'rez',
    ),
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/../config/container.php')
    ->addDefinitions([
        PDO::class                             => factory(fn () => $pdo),
        ReservationRepositoryInterface::class  => autowire(MysqlReservationRepository::class),
        ResourceRepositoryInterface::class     => autowire(MysqlResourceRepository::class),
        AvailabilityRepositoryInterface::class => autowire(MysqlAvailabilityRepository::class),
    ])
    ->build();

$createResource    = $container->get(CreateResourceUseCaseInterface::class);
$createReservation = $container->get(CreateReservationUseCaseInterface::class);
$availabilityRepo  = new MysqlAvailabilityRepository($pdo, new DayOfWeekMapper());

// -------------------------------------------------------------------------
// 1. Resources
// -------------------------------------------------------------------------

echo "Creating resources...\n";

$resourceIds = [];

foreach (['Table 1', 'Table 2', 'Table 3'] as $name) {
    $resource       = $createResource->execute(new CreateResourceRequest('table', $name, 4))->resource;
    $resourceIds[]  = $resource->id;
    echo "  Created: {$name} ({$resource->id->toString()})\n";
}

// -------------------------------------------------------------------------
// 2. Availability rules — Mon–Fri 09:00–17:00, Sat 10:00–14:00
// -------------------------------------------------------------------------

echo "Creating availability rules...\n";

$weekdays = [
    DayOfWeek::Monday,
    DayOfWeek::Tuesday,
    DayOfWeek::Wednesday,
    DayOfWeek::Thursday,
    DayOfWeek::Friday,
];

foreach ($resourceIds as $resourceId) {
    foreach ($weekdays as $day) {
        $availabilityRepo->saveRule(new AvailabilityRule($resourceId, $day, '09:00', '17:00'));
    }
    $availabilityRepo->saveRule(new AvailabilityRule($resourceId, DayOfWeek::Saturday, '10:00', '14:00'));
    echo "  Rules set for resource {$resourceId->toString()}\n";
}

// -------------------------------------------------------------------------
// 3. Override — this Saturday unavailable for Table 1
// -------------------------------------------------------------------------

echo "Creating availability override...\n";

$now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$saturday = $now->modify('saturday this week');

$availabilityRepo->saveOverride(new AvailabilityOverride(
    $resourceIds[0],
    $saturday,
    false,
));

echo "  Table 1 marked unavailable on {$saturday->format('Y-m-d')}\n";

// -------------------------------------------------------------------------
// 4. Reservations — three bookings in the current week
// -------------------------------------------------------------------------

echo "Creating reservations...\n";

$monday = $now->modify('monday this week');

$bookings = [
    [
        'resource' => $resourceIds[0],
        'start'    => $monday->setTime(10, 0),
        'end'      => $monday->setTime(11, 0),
        'party'    => new Party('Alice Martin', 'alice@example.com', 2, null),
    ],
    [
        'resource' => $resourceIds[1],
        'start'    => $monday->setTime(14, 0),
        'end'      => $monday->setTime(15, 0),
        'party'    => new Party('Bob Chen', 'bob@example.com', 3, '+1234567890'),
    ],
    [
        'resource' => $resourceIds[2],
        'start'    => (clone $monday)->modify('+1 day')->setTime(11, 0),
        'end'      => (clone $monday)->modify('+1 day')->setTime(12, 0),
        'party'    => new Party('Carol Davis', 'carol@example.com', 4, null),
    ],
];

foreach ($bookings as $booking) {
    $reservation = $createReservation->execute(new CreateReservationRequest(
        [$booking['resource']],
        $booking['start'],
        $booking['end'],
        $booking['party'],
    ))->reservation;

    echo "  Reserved {$reservation->slot->start->format('Y-m-d H:i')}–{$reservation->slot->end->format('H:i')}"
        . " for {$reservation->party->name} ({$reservation->id->toString()})\n";
}

echo "\nSeed complete.\n";
