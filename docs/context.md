# Rez — Implementation Progress

## Project Setup

- `composer.json` — package `davidrubydev/rez`, PSR-4 autoloading for `Rez\\` and `Rez\\Tests\\`
- `phpunit.xml` — Domain, Application, Infrastructure, Handler test suites
- `.github/workflows/ci.yml` — CI pipeline with separate jobs for tests, PHPStan, and code style
- `.php-cs-fixer.php` — PSR-12 code style config
- Test directory structure mirrors `src/` exactly (e.g. `tests/Domain/Reservation/`, `tests/Domain/Resource/`)
- Library is a full engine: Domain, Application, Infrastructure (MySQL), Handler layers
- Enums are pure (no backing values) — string mapping in infrastructure mappers

---

## Completed Steps

### 1. Domain Exceptions

All exception classes created under `src/Domain/Exception/`.

- `DomainException` — abstract base extending `\RuntimeException`
- `ConflictException` — empty stub
- `ResourceNotFoundException` — empty stub
- `ReservationNotFoundException` — empty stub
- `InvalidTimeSlotException` — empty stub
- `InvalidPartyException` — empty stub

All concrete exceptions extend `DomainException`. Constructors to be added as needed.

---

### 2. TimeSlot + TimeSlotTest

`src/Domain/Reservation/TimeSlot.php` — immutable value object.

- Constructor: `DateTimeImmutable $start, DateTimeImmutable $end`
- Throws `InvalidTimeSlotException` if `$end <= $start`
- `start(): DateTimeImmutable`
- `end(): DateTimeImmutable`
- `overlapsWith(TimeSlot $other): bool` — adjacent slots (A end === B start) do NOT overlap
- `duration(): \DateInterval`
- `equals(TimeSlot $other): bool` — compares timestamps
- `__toString(): string` — format `Y-m-d H:i:s / Y-m-d H:i:s`

`tests/Domain/Reservation/TimeSlotTest.php` — all 12 cases passing.

---

### 3. ReservationId + ResourceId + Tests

Both are immutable value objects wrapping a UUID v4 string, under their respective namespaces.

`src/Domain/Reservation/ReservationId.php` and `src/Domain/Resource/ResourceId.php`:

- `static generate(): self` — generates UUID v4 via `random_bytes`
- `static fromString(string $id): self` — validates UUID v4 format, throws `\InvalidArgumentException` if invalid
- `toString(): string`
- `equals(self $other): bool`
- `__toString(): string`

`tests/Domain/Reservation/ReservationIdTest.php` and `tests/Domain/Resource/ResourceIdTest.php` — all 6 cases each passing.

---

### 4. ReservationStatus

`src/Domain/Reservation/ReservationStatus.php` — pure enum (no backing values).

Cases: `Pending`, `Confirmed`, `Cancelled`, `NoShow`.
String serialization handled by `ReservationStatusMapper` in infrastructure.
No test needed — used in Reservation tests.

---

### 4b. ReservationStatusMapper

`src/Infrastructure/Mapper/ReservationStatusMapper.php` — maps `ReservationStatus` pure enum to/from string for persistence.

- `toString(ReservationStatus): string` — Pending→'pending', Confirmed→'confirmed', Cancelled→'cancelled', NoShow→'no_show'
- `fromString(string): ReservationStatus` — throws `\InvalidArgumentException` for unknown values

`tests/Infrastructure/Mapper/ReservationStatusMapperTest.php` — all 9 cases passing.

---

### 5. ResourceType + ResourceTypeMapper

`src/Domain/Resource/ResourceType.php` — immutable value object wrapping a slug string.

- `static fromString(string $slug): self` — validates lowercase alphanumeric + hyphens, throws `\InvalidArgumentException` if invalid
- `toString(): string`
- `equals(self $other): bool`
- `__toString(): string`

`src/Infrastructure/Mapper/ResourceTypeMapper.php` — maps `ResourceType` to/from string for persistence.

- `toString(ResourceType): string`
- `fromString(string): ResourceType` — inherits validation from `ResourceType::fromString()`

`tests/Infrastructure/Mapper/ResourceTypeMapperTest.php` — all 4 cases passing.

---

### 6. Party + PartyTest

`src/Domain/Reservation/Party.php` — immutable value object.

- Constructor: `string $name`, `string $email`, `int $size`, `?string $phone`
- Throws `InvalidPartyException` for empty name, invalid email, or size < 1
- Getters: `name()`, `email()`, `size()`, `phone()`

`tests/Domain/Reservation/PartyTest.php` — all 6 cases passing.

---

### 7. Resource + ResourceTest

`src/Domain/Resource/Resource.php` — immutable entity.

- Constructor: `ResourceId`, `ResourceType`, `string $name`, `int $capacity`, `array<string,mixed> $attributes = []`
- Throws `\InvalidArgumentException` for empty name or capacity < 1
- Getters: `id()`, `type()`, `name()`, `capacity()`, `attributes()`
- `withAttributes(array): self` — returns new instance with merged attributes

`tests/Domain/Resource/ResourceTest.php` — all 5 cases passing.

---

### 8. ResourceCollection + ResourceCollectionTest

`src/Domain/Resource/ResourceCollection.php` — immutable collection wrapping `Resource[]`.

- `static empty(): self`
- `static fromArray(Resource[]): self`
- `add(Resource): self` — immutable, returns new instance
- `isEmpty(): bool`
- `count(): int`
- `toArray(): Resource[]`
- `filter(callable): self`
- `findById(ResourceId): ?Resource`

`tests/Domain/Resource/ResourceCollectionTest.php` — all 7 cases passing.

---

### 9. Reservation + ReservationTest

`src/Domain/Reservation/Reservation.php` — immutable entity, static factory only.

- `static create(ReservationId, ResourceIdCollection $resourceIds, TimeSlot, Party): self` — sets status to `Pending`, `createdAt` to UTC now. Throws `\InvalidArgumentException` if `$resourceIds` is empty.
- `confirm(): self` — only from `Pending`, throws `InvalidReservationStateException` otherwise
- `cancel(): self` — from `Pending` or `Confirmed`, throws `InvalidReservationStateException` if already `Cancelled`
- `markNoShow(): self` — only from `Confirmed`, throws `InvalidReservationStateException` otherwise
- Getters: `id()`, `resourceIds()`, `slot()`, `party()`, `status()`, `createdAt()`

Added `src/Domain/Exception/InvalidReservationStateException.php` — concrete `DomainException` subclass for state transition violations.

`tests/Domain/Reservation/ReservationTest.php` — all 12 cases passing.

---

### 9b. ResourceIdCollection + ResourceIdCollectionTest

`src/Domain/Resource/ResourceIdCollection.php` — immutable collection wrapping `ResourceId[]`.

- `static empty(): self`
- `static fromArray(ResourceId[]): self` — throws `\InvalidArgumentException` if empty
- `add(ResourceId): self` — immutable
- `contains(ResourceId): bool`
- `isEmpty(): bool`
- `count(): int`
- `toArray(): ResourceId[]`

`Reservation::create()` now accepts `ResourceIdCollection` (not `ResourceId[]`) and throws if empty.
`tests/Domain/Resource/ResourceIdCollectionTest.php` — all 8 cases passing.

---

### 10. ReservationCollection + ReservationCollectionTest

`src/Domain/Reservation/ReservationCollection.php` — immutable collection wrapping `Reservation[]`.

- `static empty(): self`
- `static fromArray(Reservation[]): self`
- `add(Reservation): self` — immutable
- `isEmpty(): bool`
- `count(): int`
- `toArray(): Reservation[]`
- `filter(callable): self`
- `filterByStatus(ReservationStatus): self`
- `findById(ReservationId): ?Reservation`

`tests/Domain/Reservation/ReservationCollectionTest.php` — all 7 cases passing.

---

### 11. DayOfWeek + AvailabilityRule + AvailabilityRuleTest + DayOfWeekMapper

`src/Domain/Availability/DayOfWeek.php` — pure enum, Monday-first (Monday … Sunday).

- `static fromDate(DateTimeImmutable): self` — uses ISO-8601 `format('N')` (1=Monday, 7=Sunday)
- String serialization in `DayOfWeekMapper`

`src/Domain/Availability/AvailabilityRule.php` — immutable value object.

- Constructor: `ResourceId $resourceId`, `DayOfWeek $dayOfWeek`, `string $openTime` ('HH:MM'), `string $closeTime` ('HH:MM')
- Throws `\InvalidArgumentException` if `$closeTime <= $openTime`
- `resourceId(): ResourceId`
- `dayOfWeek(): DayOfWeek`
- `openTime(): string`
- `closeTime(): string`
- `appliesToDate(DateTimeImmutable): bool` — uses `DayOfWeek::fromDate()`
- `openTimeForDate(DateTimeImmutable): DateTimeImmutable`
- `closeTimeForDate(DateTimeImmutable): DateTimeImmutable`

`src/Infrastructure/Mapper/DayOfWeekMapper.php` — maps `DayOfWeek` to/from string ('monday' … 'sunday').

`tests/Domain/Availability/AvailabilityRuleTest.php` — 6 cases passing.
`tests/Infrastructure/Mapper/DayOfWeekMapperTest.php` — 3 cases passing.

---

### 12. AvailabilityOverride

`src/Domain/Availability/AvailabilityOverride.php` — immutable value object.

- Constructor: `ResourceId $resourceId`, `DateTimeImmutable $date`, `bool $available`
- Getters: `resourceId()`, `date()`, `isAvailable()`
- No test — logic is trivial.

---

### 13. AvailabilityWindow

`src/Domain/Availability/AvailabilityWindow.php` — immutable value object.

- Constructor: `ResourceId $resourceId`, `DateTimeImmutable $date`, `TimeSlot[] $availableSlots`
- `static empty(ResourceId, DateTimeImmutable): self`
- `resourceId(): ResourceId`
- `date(): DateTimeImmutable`
- `slots(): TimeSlot[]`
- `isEmpty(): bool`
- `count(): int`
- No test — no logic beyond trivial delegation.

---

### 14. DateTimeRange + DateTimeRangeTest

`src/Domain/Shared/DateTimeRange.php` — shared utility (not a domain concept).

- Constructor: `DateTimeImmutable $start`, `DateTimeImmutable $end` — throws `\InvalidArgumentException` if `$end < $start` (equal allowed)
- `start(): DateTimeImmutable`
- `end(): DateTimeImmutable`
- `contains(DateTimeImmutable): bool`
- `overlapsWith(DateTimeRange): bool` — adjacent ranges do NOT overlap
- `toTimeSlot(): TimeSlot` — throws `InvalidTimeSlotException` if start === end

`tests/Domain/Shared/DateTimeRangeTest.php` — all 9 cases passing.

---

### 15. Port Interfaces

`src/Application/Port/ReservationRepositoryInterface.php`:
- `findById(ReservationId): Reservation`
- `findByTimeSlotAndResource(TimeSlot, ResourceId): ReservationCollection`
- `findAll(?DateTimeImmutable $from, ?DateTimeImmutable $to): ReservationCollection`
- `save(Reservation): void`

`src/Application/Port/ResourceRepositoryInterface.php`:
- `findById(ResourceId): Resource`
- `findAll(): ResourceCollection`
- `save(Resource): void`

`src/Application/Port/AvailabilityRepositoryInterface.php`:
- `findRulesForResource(ResourceId): AvailabilityRule[]`
- `findOverridesForResource(ResourceId, DateTimeImmutable $from, DateTimeImmutable $to): AvailabilityOverride[]`

---

### 16. Application Use Cases

All use cases follow Request/Response/UseCase pattern under `src/Application/UseCase/`.
`ConflictException` updated with `TimeSlot $slot` and `Resource $resource` constructor + getters.

**AvailabilityService** (`src/Application/Service/`) — shared availability logic extracted into a service to avoid duplication between `CreateReservation` and `GetAvailability`.
- `AvailabilityServiceInterface` — contract; both use cases depend on this.
- `AvailabilityService implements AvailabilityServiceInterface` — concrete implementation.
- `isSlotAvailable(ResourceId, TimeSlot): bool` — checks rule exists for date, override not blocking, no conflicting reservation.
- `getAvailableSlots(ResourceId, DateTimeImmutable, int): AvailabilityWindow` — full slot generation pipeline.
- 12 tests in `AvailabilityServiceTest`.

**CreateReservation** — validates resource exists, builds `TimeSlot`, delegates availability check to `AvailabilityService` per resource, creates and saves `Reservation`.
- 6 tests passing.

**CancelReservation** — loads reservation, calls `cancel()`, saves.
- 4 tests passing.

**GetReservation** — loads reservation, wraps in response.
- 2 tests passing.

**ListReservations** — `findAll(from, to)`, filters in memory by `resourceId` if provided.
- 2 tests passing.

**GetAvailability** — thin wrapper; delegates entirely to `AvailabilityService::getAvailableSlots()`.
- 1 test passing.

Total: 128 tests passing.

---

### 18. MySQL Repositories

`src/Infrastructure/Persistence/Mysql/MysqlRepository.php` — abstract base with type-narrowing helpers: `str()`, `int()`, `nullStr()`, `bool()`. Used by all repositories to safely extract typed values from PDO rows (required for PHPStan max).

`Reservation::reconstruct()` added as a static factory for hydration from persistence (bypasses `create()` which hardcodes `Pending` status and UTC now).

**MysqlReservationRepository** — implements `ReservationRepositoryInterface`.
- `save()` — upsert reservations + delete/reinsert reservation_resources rows
- `findById()` — throws `ReservationNotFoundException` if missing
- `findByTimeSlotAndResource()` — JOIN on reservation_resources, overlap query
- `findAll()` — optional from/to WHERE clause

**MysqlResourceRepository** — implements `ResourceRepositoryInterface`.
- `save()` — upsert, attributes stored as JSON
- `findById()` — throws `ResourceNotFoundException` if missing
- `findAll()` — returns full `ResourceCollection`

**MysqlAvailabilityRepository** — implements `AvailabilityRepositoryInterface`.
- `saveRule()` / `saveOverride()` — upsert with generated UUID
- `findRulesForResource()` / `findOverridesForResource()` — filtered queries

**Integration tests** — `tests/Integration/Persistence/Mysql/`, skip gracefully when `DB_HOST`/`DB_NAME`/`DB_USER` env vars not set.
- CI: separate `Integration Tests` job with MySQL 8.0 service container.
- `composer test-integration` runs the Integration suite only.
- `composer test` excludes integration tests.
- 13 integration tests (skip locally), 128 unit tests passing.

Schema: `resources`, `reservations`, `reservation_resources`, `availability_rules`, `availability_overrides` — created by `MysqlIntegrationTestCase::createSchema()` and truncated between tests.

---

### 17. Getter-prefix refactor + DI in repositories

All property-accessor methods across the domain now follow `get*` / `is*` naming conventions.

**Domain getter renames:**
- `TimeSlot`: `start()` → `getStart()`, `end()` → `getEnd()`
- `Party`: `name()` → `getName()`, `email()` → `getEmail()`, `size()` → `getSize()`, `phone()` → `getPhone()`
- `Reservation`: `id()` → `getId()`, `resourceIds()` → `getResourceIds()`, `slot()` → `getSlot()`, `party()` → `getParty()`, `status()` → `getStatus()`, `createdAt()` → `getCreatedAt()`
- `Resource`: `id()` → `getId()`, `type()` → `getType()`, `name()` → `getName()`, `capacity()` → `getCapacity()`, `attributes()` → `getAttributes()`
- `AvailabilityRule`: `resourceId()` → `getResourceId()`, `dayOfWeek()` → `getDayOfWeek()`, `openTime()` → `getOpenTime()`, `closeTime()` → `getCloseTime()`
- `AvailabilityOverride`: `resourceId()` → `getResourceId()`, `date()` → `getDate()` (`isAvailable()` unchanged)
- `AvailabilityWindow`: `resourceId()` → `getResourceId()`, `date()` → `getDate()`, `slots()` → `getSlots()`
- `DateTimeRange`: `start()` → `getStart()`, `end()` → `getEnd()`
- `ConflictException`: `slot()` → `getSlot()`, `resource()` → `getResource()`

**DI in MySQL repositories:** `ReservationStatusMapper`, `ResourceTypeMapper`, and `DayOfWeekMapper` are now injected via constructor parameters rather than instantiated internally.

All callers updated (application services, use cases, infrastructure repositories, all tests).
128 unit tests passing, PHPStan max clean, CS clean.

---

### 17b. PHP-DI container definitions

Added `php-di/php-di ^7.0` as a runtime dependency.

`config/container.php` — library-owned definitions file for the client app to import:
- `AvailabilityServiceInterface::class` → `autowire(AvailabilityService::class)` — the only interface-to-implementation binding the library owns

Everything else is resolved by PHP-DI auto-wiring:
- Mappers (`ReservationStatusMapper`, `ResourceTypeMapper`, `DayOfWeekMapper`) — no deps, auto-wired
- Repositories — auto-wired once the client binds `PDO` and the three repository interfaces
- Use cases — auto-wired once their interface dependencies are bound

Client app is responsible for binding: `PDO`, and the three `*RepositoryInterface` → `Mysql*Repository` pairs.

Example client app container config:

```php
use DI\ContainerBuilder;
use function DI\autowire;

$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/../vendor/davidrubydev/rez/config/container.php')
    ->addDefinitions([
        PDO::class                             => fn() => new PDO('mysql:host=...;dbname=...', 'user', 'pass'),
        ReservationRepositoryInterface::class  => autowire(MysqlReservationRepository::class),
        ResourceRepositoryInterface::class     => autowire(MysqlResourceRepository::class),
        AvailabilityRepositoryInterface::class => autowire(MysqlAvailabilityRepository::class),
    ])
    ->build();

// fully wired — no manual new anywhere
$useCase = $container->get(CreateReservationUseCase::class);
```

---

### 19. Handlers

Entry points under `src/Handler/` that accept raw `array<string, mixed>` input, delegate to a use case, and return a serialized array.

`src/Handler/ReservationSerializer.php` — shared serialization of `Reservation` to array.

**Reservation handlers** (`src/Handler/Reservation/`):
- `CreateReservationHandler::handle(array): array` — validates `resource_ids` and `party`, builds domain objects, delegates to `CreateReservationUseCaseInterface`
- `CancelReservationHandler::handle(array): array` — requires `id`, delegates to `CancelReservationUseCaseInterface`
- `GetReservationHandler::handle(array): array` — requires `id`, delegates to `GetReservationUseCaseInterface`
- `ListReservationsHandler::handle(array): array[]` — optional `from`, `to`, `resource_id` filters, delegates to `ListReservationsUseCaseInterface`

**Availability handlers** (`src/Handler/Availability/`):
- `GetAvailabilityHandler::handle(array): array` — requires `resource_id`, `date`, optional `slot_duration_minutes`, delegates to `GetAvailabilityUseCaseInterface`

All use cases now implement a `*UseCaseInterface` so handlers type-hint on the interface and tests can mock them.

14 new handler tests passing. Total: 142 unit tests passing, PHPStan max clean, CS clean.

---

### 20. Resource Use Cases + Handlers

`src/Application/UseCase/Resource/` — three use cases following the same Request/Response/UseCase/Interface pattern.

**CreateResource** — builds a `Resource` from `ResourceType::fromString()`, `ResourceId::generate()`, name, capacity, and optional attributes. Saves via repository and returns it in the response. Throws `\InvalidArgumentException` for invalid type slug or capacity < 1.

**GetResource** — `findById(ResourceId)` — propagates `ResourceNotFoundException` if missing.

**ListResources** — `findAll()` wrapped in `ListResourcesResponse`.

All three expose a `*UseCaseInterface` registered in `config/container.php`.

`src/Handler/ResourceSerializer.php` — shared serialization of `Resource` to typed array shape:
`array{id: string, type: string, name: string, capacity: int, attributes: array<string, mixed>}`

**Resource handlers** (`src/Handler/Resource/`):
- `CreateResourceHandler::handle(array{type, name, capacity, attributes?}): array`
- `GetResourceHandler::handle(array{id}): array`
- `ListResourcesHandler::handle(array{}): list<array{...}>` — `array_values(array_map(...))` ensures list type

11 new tests (6 use case, 5 handler). Total: 160 unit tests passing, PHPStan max clean, CS clean.

---

### 21. Database Setup

`database/seeds/000_schema.sql` — authoritative DDL for all five tables, safe to re-run (`IF NOT EXISTS`). Lives in the seeds directory so `bin/seed.php` applies it automatically before data files.

Tables: `resources`, `reservations`, `reservation_resources`, `availability_rules`, `availability_overrides`.

Foreign key constraints with `ON DELETE CASCADE` on all child tables. `open_time`/`close_time` stored as `CHAR(5)` (`HH:MM`). `attributes` as `JSON`. All PKs are UUID v4 strings. All timestamps in UTC, no timezone stored.

---

### 22. OpenAPI Spec

`docs/openapi.yaml` — OpenAPI 3.0.3 spec describing the full HTTP surface.

8 endpoints:
- `POST /resources`, `GET /resources`, `GET /resources/{id}`
- `POST /reservations`, `GET /reservations`, `GET /reservations/{id}`, `POST /reservations/{id}/cancel`
- `GET /availability`

Schemas: `Resource`, `Reservation`, `Party`, `PartyInput`, `TimeSlot`, `AvailabilityWindow`.

---

### 23. CLI Seed Script

Fully hexagonal seed pipeline — data lives in SQL files, the use case orchestrates execution via a port.

**`DatabaseSeederInterface`** (`src/Application/Port/`) — `executeFile(string $filePath): void`

**`SeedDatabaseUseCase`** (`src/Application/UseCase/Seed/SeedDatabase/`) — receives a `SeedDatabaseRequest(string $seedsDirectory)`, globs `*.sql` files, sorts by filename, delegates each to the port. Returns `SeedDatabaseResponse(int $filesExecuted)`.

**`MysqlDatabaseSeeder`** (`src/Infrastructure/Persistence/Mysql/`) — reads a file, splits on `;`, executes each non-empty statement via `PDO::exec()`.

**`database/seeds/`** — four idempotent SQL files with hardcoded UUIDs:
- `001_resources.sql` — 3 tables (UUIDs `aaaaaaaa-…-001/002/003`)
- `002_availability_rules.sql` — Mon–Fri 09:00–17:00, Sat 10:00–14:00 for all three
- `003_availability_overrides.sql` — Table 1 unavailable on 2024-06-08
- `004_reservations.sql` — 3 reservations (Mon/Tue 2024-06-03/04) + reservation_resources rows

**`bin/seed.php`** — thin CLI entry point: loads `.env`, boots PHP-DI container, calls `SeedDatabaseUseCaseInterface` with `__DIR__ . '/../database/seeds'`.

`DatabaseSeederInterface` and `SeedDatabaseUseCaseInterface` registered in `config/container.php`.

4 new tests in `SeedDatabaseUseCaseTest` (file count, filename order, non-sql ignored, empty dir). Total: 164 unit tests passing, PHPStan max clean, CS clean.

---

### 24. ConfirmReservation + MarkNoShow Use Cases + Handlers

**ConfirmReservationUseCase** and **MarkNoShowUseCase** follow the same pattern as `CancelReservationUseCase` — `findById`, transition, `save`, return response.

Both expose a `*UseCaseInterface` registered in `config/container.php`.

**ConfirmReservationHandler** and **MarkNoShowHandler** follow the same pattern as `CancelReservationHandler`.

`docs/openapi.yaml` updated with `POST /reservations/{id}/confirm` and `POST /reservations/{id}/no-show`.

10 new tests (8 use case, 2 handler). Total: 174 unit tests passing, PHPStan max clean, CS clean.

---

## Pending Steps

- **25.** Availability write use cases + handlers (`SaveAvailabilityRule`, `SaveAvailabilityOverride`)
- **26.** `UpdateResource` use case + handler
- **27.** Integration test — `MysqlDatabaseSeeder`
