# Rez

A pure PHP 8.3 reservation engine built on Domain-Driven Design and Hexagonal Architecture. Framework-agnostic — plug in your own HTTP layer and wire it up with any DI container.

## Requirements

- PHP 8.3+
- PDO with MySQL

## Installation

```bash
composer require davidrubydev/rez
```

## Architecture

```
Domain         Entities, value objects, domain exceptions, pure enums. No dependencies.
Application    Use cases and port interfaces.
Infrastructure MySQL repository implementations and mappers.
Handler        Driving adapters — pure PHP arrays in, arrays out. No HTTP dependency.
```

The library ends at the Handler layer. Your application owns the HTTP wiring.

## Handlers

Every operation is exposed through a handler with a single `handle(array $data): array` method.

| Handler | Input keys | Returns |
|---|---|---|
| `CreateResourceHandler` | `type`, `name`, `capacity`, `attributes?` | Resource |
| `GetResourceHandler` | `id` | Resource |
| `ListResourcesHandler` | _(none)_ | Resource[] |
| `UpdateResourceHandler` | `id`, `name?`, `capacity?`, `attributes?` | Resource |
| `DeleteResourceHandler` | `id` | `[]` |
| `SaveAvailabilityRuleHandler` | `resource_id`, `day_of_week`, `open_time`, `close_time` | Rule |
| `SaveAvailabilityOverrideHandler` | `resource_id`, `date`, `available` | Override |
| `GetAvailabilityHandler` | `resource_id`, `date`, `slot_duration_minutes?` | Availability window |
| `CreateReservationHandler` | `resource_ids[]`, `start`, `end`, `party{name,email,size,phone?}` | Reservation |
| `GetReservationHandler` | `id` | Reservation |
| `ListReservationsHandler` | `from?`, `to?`, `resource_id?` | Reservation[] |
| `CancelReservationHandler` | `id` | Reservation |
| `ConfirmReservationHandler` | `id` | Reservation |
| `MarkNoShowHandler` | `id` | Reservation |

## Wiring with PHP-DI

The library ships `config/container.php` which registers all use cases. Supply a `PDO` instance and bind the three repository interfaces to their MySQL implementations:

```php
use DI\ContainerBuilder;
use function DI\autowire;

use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Infrastructure\Persistence\Mysql\MysqlAvailabilityRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;

$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/vendor/davidrubydev/rez/config/container.php')
    ->addDefinitions([
        PDO::class => fn () => new PDO(
            'mysql:host=127.0.0.1;dbname=rez;charset=utf8mb4',
            'user',
            'pass',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        ),

        ReservationRepositoryInterface::class  => autowire(MysqlReservationRepository::class),
        ResourceRepositoryInterface::class     => autowire(MysqlResourceRepository::class),
        AvailabilityRepositoryInterface::class => autowire(MysqlAvailabilityRepository::class),
    ])
    ->build();
```

## Example: Slim 4 HTTP layer

This is your application's responsibility, not the library's. Here is a complete reference using Slim 4 and PHP-DI Slim Bridge.

**`composer.json`** (your app):
```json
{
    "require": {
        "davidrubydev/rez": "^1.0",
        "slim/slim": "^4.14",
        "nyholm/psr7": "^1.8",
        "php-di/slim-bridge": "^3.4"
    }
}
```

**`config/container.php`** (extends the library definitions above, same pattern).

**`public/index.php`**:
```php
<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$builder = new DI\ContainerBuilder();
$builder->addDefinitions(require __DIR__ . '/../config/container.php');
$container = $builder->build();

$app = DI\Bridge\Slim\Bridge::create($container);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Map domain exceptions to HTTP status codes
$errorMiddleware = $app->addErrorMiddleware(false, false, false);
$errorMiddleware->setDefaultErrorHandler(
    function (Psr\Http\Message\ServerRequestInterface $request, Throwable $e) use ($app) {
        $status = match (true) {
            $e instanceof \Rez\Domain\Exception\ResourceNotFoundException,
            $e instanceof \Rez\Domain\Exception\ReservationNotFoundException => 404,
            $e instanceof \Rez\Domain\Exception\ConflictException            => 409,
            $e instanceof \InvalidArgumentException,
            $e instanceof \Rez\Domain\Exception\DomainException              => 422,
            default                                                          => 500,
        };
        $response = $app->getResponseFactory()->createResponse($status);
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json');
    }
);

(require __DIR__ . '/../routes.php')($app);
$app->run();

function jsonResponse(Psr\Http\Message\ResponseInterface $r, mixed $data, int $status = 200): Psr\Http\Message\ResponseInterface {
    $r->getBody()->write(json_encode($data));
    return $r->withHeader('Content-Type', 'application/json')->withStatus($status);
}
```

**`routes.php`** — PHP-DI Slim Bridge resolves handlers by type-hint; path params are injected as named `string` parameters:
```php
<?php declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Rez\Handler\Resource\{CreateResourceHandler, GetResourceHandler, ListResourcesHandler, UpdateResourceHandler, DeleteResourceHandler};
use Rez\Handler\Availability\{GetAvailabilityHandler, SaveAvailabilityRuleHandler, SaveAvailabilityOverrideHandler};
use Rez\Handler\Reservation\{CreateReservationHandler, GetReservationHandler, ListReservationsHandler, CancelReservationHandler, ConfirmReservationHandler, MarkNoShowHandler};
use Slim\App;

return function (App $app): void {

    $app->post('/resources', function (Request $req, Response $res, CreateResourceHandler $h): Response {
        return jsonResponse($res, $h->handle((array) $req->getParsedBody()), 201);
    });
    $app->get('/resources', function (Request $req, Response $res, ListResourcesHandler $h): Response {
        return jsonResponse($res, $h->handle([]));
    });
    $app->get('/resources/{id}', function (Request $req, Response $res, string $id, GetResourceHandler $h): Response {
        return jsonResponse($res, $h->handle(['id' => $id]));
    });
    $app->patch('/resources/{id}', function (Request $req, Response $res, string $id, UpdateResourceHandler $h): Response {
        return jsonResponse($res, $h->handle(array_merge((array) $req->getParsedBody(), ['id' => $id])));
    });
    $app->delete('/resources/{id}', function (Request $req, Response $res, string $id, DeleteResourceHandler $h): Response {
        $h->handle(['id' => $id]);
        return $res->withStatus(204);
    });

    $app->put('/resources/{id}/availability/rules', function (Request $req, Response $res, string $id, SaveAvailabilityRuleHandler $h): Response {
        return jsonResponse($res, $h->handle(array_merge((array) $req->getParsedBody(), ['resource_id' => $id])));
    });
    $app->put('/resources/{id}/availability/overrides/{date}', function (Request $req, Response $res, string $id, string $date, SaveAvailabilityOverrideHandler $h): Response {
        return jsonResponse($res, $h->handle(array_merge((array) $req->getParsedBody(), ['resource_id' => $id, 'date' => $date])));
    });

    $app->post('/reservations', function (Request $req, Response $res, CreateReservationHandler $h): Response {
        return jsonResponse($res, $h->handle((array) $req->getParsedBody()), 201);
    });
    $app->get('/reservations', function (Request $req, Response $res, ListReservationsHandler $h): Response {
        return jsonResponse($res, $h->handle(array_filter($req->getQueryParams())));
    });
    $app->get('/reservations/{id}', function (Request $req, Response $res, string $id, GetReservationHandler $h): Response {
        return jsonResponse($res, $h->handle(['id' => $id]));
    });
    $app->post('/reservations/{id}/cancel', function (Request $req, Response $res, string $id, CancelReservationHandler $h): Response {
        return jsonResponse($res, $h->handle(['id' => $id]));
    });
    $app->post('/reservations/{id}/confirm', function (Request $req, Response $res, string $id, ConfirmReservationHandler $h): Response {
        return jsonResponse($res, $h->handle(['id' => $id]));
    });
    $app->post('/reservations/{id}/no-show', function (Request $req, Response $res, string $id, MarkNoShowHandler $h): Response {
        return jsonResponse($res, $h->handle(['id' => $id]));
    });

    $app->get('/availability', function (Request $req, Response $res, GetAvailabilityHandler $h): Response {
        $p = $req->getQueryParams();
        return jsonResponse($res, $h->handle([
            'resource_id'           => $p['resource_id'] ?? '',
            'date'                  => $p['date'] ?? '',
            'slot_duration_minutes' => (int) ($p['slot_duration_minutes'] ?? 60),
        ]));
    });
};
```

## Database setup

```bash
mysql -u root -p rez < database/seeds/000_schema.sql
php bin/seed.php   # populate with sample data (requires .env)
```

## OpenAPI spec

See [`docs/openapi.yaml`](docs/openapi.yaml) for the full HTTP contract.

## Running tests

```bash
composer test   # unit tests
composer ca     # cs-fix + tests + phpstan (run before every commit)
```

Integration tests require a real database:

```bash
DB_HOST=127.0.0.1 DB_NAME=rez DB_USER=root DB_PASS=secret \
  vendor/bin/phpunit --group integration
```
