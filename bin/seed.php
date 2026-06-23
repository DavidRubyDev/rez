#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = file_exists(__DIR__ . '/../vendor/autoload.php')
    ? __DIR__ . '/../vendor/autoload.php'
    : __DIR__ . '/../../../autoload.php';
require $autoload;

// Load .env if present
$envFile = file_exists(__DIR__ . '/../.env')
    ? __DIR__ . '/../.env'
    : __DIR__ . '/../../../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $k = trim($key);
        if (!isset($_ENV[$k])) {
            $_ENV[$k] = trim($value);
        }
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
        getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'),
        getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306'),
        getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'rez'),
    ),
    getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'),
    getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ''),
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
$response = $useCase->execute(new SeedDatabaseRequest(seedsDirectories: [__DIR__ . '/../database/seeds']));

echo "Seeded {$response->filesExecuted} file(s).\n";
