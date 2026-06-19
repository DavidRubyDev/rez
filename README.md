# Rez

A pure PHP reservation engine built on Domain-Driven Design and Hexagonal Architecture. Framework-agnostic — plug in your own infrastructure and wire it up with any DI container.

## Requirements

- PHP 8.3+
- PDO with a supported database driver (MySQL included)

## Installation

```bash
composer require davidrubydev/rez
```

## Architecture

```
Domain        — Entities, value objects, domain exceptions. No dependencies.
Application   — Use cases and port interfaces.
Infrastructure — MySQL repository implementations and mappers.
Handler       — Entry points that call use cases (coming soon).
```

## Wiring with PHP-DI

The library ships a definitions file at `config/container.php` that registers what it owns (`AvailabilityServiceInterface`). Everything else is resolved by auto-wiring — you only need to supply the database connection and bind the three repository interfaces to their implementations.

```php
use DI\ContainerBuilder;
use function DI\autowire;

use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Infrastructure\Persistence\Mysql\MysqlAvailabilityRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;

$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/../vendor/davidrubydev/rez/config/container.php')
    ->addDefinitions([
        PDO::class => fn() => new PDO('mysql:host=127.0.0.1;dbname=mydb', 'user', 'pass'),

        ReservationRepositoryInterface::class  => autowire(MysqlReservationRepository::class),
        ResourceRepositoryInterface::class     => autowire(MysqlResourceRepository::class),
        AvailabilityRepositoryInterface::class => autowire(MysqlAvailabilityRepository::class),
    ])
    ->build();

// fully wired — no manual new anywhere
$useCase = $container->get(CreateReservationUseCase::class);
```

## Running tests

```bash
composer test          # unit tests
composer test-integration  # requires DB_HOST, DB_NAME, DB_USER, DB_PASS env vars
composer stan          # PHPStan level max
composer cs            # code style check
composer ca            # fix style + test + stan
```
