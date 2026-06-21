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
use Rez\Application\UseCase\Seed\SeedDatabase\SeedDatabaseRequest;
use Rez\Application\UseCase\Seed\SeedDatabase\SeedDatabaseUseCaseInterface;
use Rez\Infrastructure\Persistence\Mysql\MysqlAvailabilityRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;

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

$useCase  = $container->get(SeedDatabaseUseCaseInterface::class);
$response = $useCase->execute(new SeedDatabaseRequest(__DIR__ . '/../database/seeds'));

echo "Seeded {$response->filesExecuted} file(s).\n";
