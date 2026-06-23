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

### 25. Availability Write Use Cases + Handlers

**`AvailabilityRepositoryInterface`** extended with two write methods: `saveRule(AvailabilityRule $rule): void` and `saveOverride(AvailabilityOverride $override): void`.

**`SaveAvailabilityRuleUseCase`** — validates resource exists via `resourceRepository->findById()`, constructs `AvailabilityRule`, delegates to `availabilityRepository->saveRule()`.

**`SaveAvailabilityOverrideUseCase`** — same pattern with `AvailabilityOverride` and `saveOverride()`.

Both handlers use typed `array{...}` shapes. `SaveAvailabilityRuleHandler` instantiates `DayOfWeekMapper` internally for string ↔ enum conversion.

`docs/openapi.yaml` updated with `PUT /resources/{id}/availability/rules` and `PUT /resources/{id}/availability/overrides/{date}`, plus `AvailabilityRule` and `AvailabilityOverride` component schemas.

Both use case interfaces registered in `config/container.php`.

9 new tests (4 use case, 5 handler). Total: 183 unit tests passing, PHPStan max clean, CS clean.

---

### 26. UpdateResource Use Case + Handler

**`UpdateResourceUseCase`** — PATCH semantics: all fields nullable. `findById` (throws `ResourceNotFoundException` if missing), constructs a new `Resource` with the same `id` and `type`, using `??` to fall back to existing values for any field not provided. Sending `attributes: []` explicitly clears them. Validation (empty name, capacity < 1) is enforced by the `Resource` constructor.

**`UpdateResourceHandler`** — input: `array{id: string, name?: string, capacity?: int, attributes?: array<string, mixed>}`, passes `null` for absent keys, output: serialized resource via `ResourceSerializer::serialize()`.

`docs/openapi.yaml` updated with `PATCH /resources/{id}` (no required fields in body).

`UpdateResourceUseCaseInterface` registered in `config/container.php`.

9 new tests (7 use case, 2 handler). Total: 194 unit tests passing, PHPStan max clean, CS clean.

---

### 27. Integration Test — MysqlDatabaseSeeder

`tests/Integration/Persistence/Mysql/MysqlDatabaseSeederTest.php` — 3 integration tests:

- `testExecutesSingleStatementFromFile` — writes an INSERT to a temp SQL file, executes it, verifies the row is in the DB
- `testExecutesMultipleStatementsFromFile` — file with two statements separated by `;`, verifies both rows inserted
- `testThrowsWhenFileDoesNotExist` — passes a non-existent path, expects `RuntimeException`

Uses `tempnam()` for isolated temp files; `tearDown` always cleans up. Skipped locally (no DB); runs in CI. `$tmpFile` initialized before `parent::setUp()` to avoid typed property errors when the test is skipped.

197 tests (16 skipped — all integration), PHPStan max clean, CS clean. All roadmap steps complete.

---

### 28. DeleteResource Use Case + Handler

`delete(ResourceId $id): void` added to `ResourceRepositoryInterface`.

**`DeleteResourceUseCase`** — `findById` (throws `ResourceNotFoundException` if missing), then `delete()`. Order enforced and tested.

**`DeleteResourceHandler`** — input: `array{id: string}`, returns `array{}`.

**`MysqlResourceRepository`** — implements `delete()` via `DELETE FROM resources WHERE id = :id` (FK cascade handles child rows).

`MysqlResourceRepositoryTest` extended with `testDeleteRemovesResource` (integration, skipped locally).

`docs/openapi.yaml` updated with `DELETE /resources/{id}` → 204 | 404.

`DeleteResourceUseCaseInterface` registered in `config/container.php`.

5 new tests (3 use case, 2 handler) + 1 integration. Total: 203 unit tests passing (17 skipped), PHPStan max clean, CS clean.

---

### 29. Integration Tests — MysqlAvailabilityRepository

`tests/Integration/Persistence/Mysql/MysqlAvailabilityRepositoryTest.php` extended with 3 additional tests (file pre-existed with 4):

- `testSaveRuleIsIdempotent` — saving the same rule (same resource + day) twice overwrites it, no duplicate row
- `testFindRulesForResourceReturnsMultipleRules` — 3 rules for different days all returned
- `testSaveOverrideIsIdempotent` — saving the same override twice overwrites `available`, no duplicate row

206 tests (20 skipped — all integration), PHPStan max clean, CS clean.

---

### 30. Slim Framework Adapter

`examples/slim/` — standalone Slim 4 application wiring all Rez handlers to HTTP routes. Has its own `composer.json` (path repo pointing to `../../`, requires `slim/slim`, `nyholm/psr7`, `php-di/slim-bridge`). Not covered by main PHPStan or CS-fixer.

**`config/container.php`** — merges library container definitions, adds `PDO` factory (reads `$_ENV`), binds three repository interfaces to MySQL implementations via `autowire()`.

**`routes.php`** — returns `fn(App): void`. All 14 routes wired. PHP-DI Slim Bridge resolves handlers by type-hint. Path params injected as named `string` parameters. Query params extracted from `$request->getQueryParams()`.

**`public/index.php`** — loads `.env`, boots PHP-DI + Slim bridge, registers error middleware mapping domain exceptions → HTTP codes (404/409/422/500), includes routes. Defines `jsonResponse()` helper.

Main suite unaffected: 206 tests (20 skipped), PHPStan max clean, CS clean.

---

### 31. Party `externalRef` field (platform-readiness Step 1)

`src/Domain/Reservation/Party.php` — added optional `externalRef` field.

- New constructor parameter: `public readonly ?string $externalRef = null` (appended after `$phone` — defaults to `null`, backwards-compatible)

`tests/Domain/Reservation/PartyTest.php` — 2 new tests:
- `testNullExternalRefIsAccepted` — constructs with `externalRef: null`, asserts `null`
- `testExternalRefIsStoredAndReturned` — constructs with `externalRef: 'some-uuid'`, asserts value round-trips

208 unit tests passing (20 skipped), PHPStan max clean, CS clean.

---

### 32. Schema `external_ref` column (platform-readiness Step 2)

`database/seeds/000_schema.sql` — added `external_ref` column to `reservations` table.

- `external_ref VARCHAR(255) NULL` — inserted after `party_phone`, before `created_at`
- No new tests (DDL-only change)

208 unit tests passing (20 skipped), PHPStan max clean, CS clean.

---

### 33. MysqlReservationRepository `external_ref` (platform-readiness Step 3)

`src/Infrastructure/Persistence/Mysql/MysqlReservationRepository.php` — `external_ref` now fully persisted and hydrated.

- `save()` — `external_ref` added to INSERT column list, VALUES, and `ON DUPLICATE KEY UPDATE`
- `hydrate()` — `$this->nullStr($row['external_ref'])` passed as fifth `Party` constructor argument

`tests/Integration/Persistence/Mysql/MysqlReservationRepositoryTest.php` — 2 new integration tests:
- `testExternalRefIsPersistedAndHydrated` — saves a reservation with `externalRef: 'user-uuid-123'`, asserts it round-trips
- `testNullExternalRefRoundtrips` — saves a reservation with null `externalRef`, asserts `null` returned

210 tests (22 skipped — all integration), PHPStan max clean, CS clean.

---

### 34. ReservationSerializer `external_ref` + OpenAPI (platform-readiness Step 4)

`src/Handler/ReservationSerializer.php` — `external_ref` added to `party` array in `serialize()` output and `@return` type annotation.

All five reservation handlers updated with matching `@return` type annotation (`external_ref: string|null` in the `party` shape).

`docs/openapi.yaml` — `external_ref` (nullable string) added to both `Party` and `PartyInput` component schemas.

`tests/Handler/Reservation/GetReservationHandlerTest.php` — 2 new tests:
- `testHandleReturnsSerializedReservation` extended to assert `external_ref: null`
- `testHandleIncludesExternalRefInParty` — creates a party with `externalRef: 'user-uuid-456'`, asserts it appears in serialized output

211 tests (22 skipped — all integration), PHPStan max clean, CS clean.

---

### 35. SeedDatabase multi-directory support (platform-readiness Steps 5–7)

**Step 5** — `src/Application/UseCase/Seed/SeedDatabase/SeedDatabaseRequest.php`: `string $seedsDirectory` → `array $seedsDirectories` (`string[]`).

**Step 6** — `src/Application/UseCase/Seed/SeedDatabase/SeedDatabaseUseCase.php`: iterates all directories in order, globs `*.sql` per directory, sorts within each, executes across all. Returns total file count.

All 4 existing tests updated to pass `[$tempDir]` (array). 2 new tests added:
- `testExecutesMultipleDirectoriesInOrder` — two dirs, asserts dir-A files run before dir-B files
- `testEmptyDirectoryInListIsSkipped` — empty dir in array causes no error, only valid files counted

**Step 7** — `bin/seed.php`: updated to `new SeedDatabaseRequest(seedsDirectories: [__DIR__ . '/../database/seeds'])`.

213 tests (22 skipped — all integration), PHPStan max clean, CS clean.

---

### 36. Seed directory naming convention (platform-readiness Step 8)

`database/seeds/README.md` — created. Documents numeric prefix ranges (000–099 rez, 100–199 rez-platform, 200+ client) and explains that files execute in filename order within each directory.

213 tests (22 skipped — all integration), PHPStan max clean, CS clean.

---

### 37. MysqlDatabaseSeeder::seedsPath() (platform-readiness Step 9)

`src/Infrastructure/Persistence/Mysql/MysqlDatabaseSeeder.php` — added `public static function seedsPath(): string` returning `dirname(__DIR__, 4) . '/database/seeds'`.

Allows client repos and `rez-platform` to reference the rez seeds path without hardcoding vendor paths.

No test — trivial path computation.

213 tests (22 skipped — all integration), PHPStan max clean, CS clean.

All `docs/rez-platform-modifications.md` steps complete.

---

### 38. Currency + CurrencyMapper + Money + InsufficientFundsException

Pre-step before `02_rez-config.md` — shared financial domain types needed across config, payments, credits, subscriptions, and booking.

`src/Domain/Shared/Currency.php` — pure enum: `Czk`, `Eur`, `Usd`. `getCode(): string` returns uppercase ISO code (`'CZK'`, `'EUR'`, `'USD'`). No test — same convention as other pure enums.

`src/Infrastructure/Mapper/CurrencyMapper.php` — `fromString(string): Currency` (input normalized via `strtoupper()`, throws `\InvalidArgumentException` for unknown values) and `toString(Currency): string` (explicit match, returns uppercase ISO code e.g. `'CZK'`). Domain code uses `Currency::getCode()` directly where the mapper is unreachable (Domain layer only).

`src/Domain/Exception/InsufficientFundsException.php` — extends `DomainException`. Constructor: `int $required, int $available, Currency $currency`. No test — trivial constructor.

`src/Domain/Shared/Money.php` — immutable value object. `public readonly int $amount`, `public readonly Currency $currency` (no getters). Throws `\InvalidArgumentException` if amount < 0. Methods: `add()`, `subtract()` (throws `InsufficientFundsException`), `isZero()`, `equals()`, `isGreaterThan()`, `__toString()` (e.g. `'150000 CZK'`, uses `getCode()`). Cross-currency operations throw `\InvalidArgumentException`.

`tests/Infrastructure/Mapper/CurrencyMapperTest.php` — 8 cases passing.
`tests/Domain/Shared/MoneyTest.php` — 16 cases passing.

237 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 39. FeatureDisabledException + Feature enum (`02_rez-config.md` pre-steps)

`src/Domain/Exception/FeatureDisabledException.php` — extends `DomainException`. Constructor: `Feature $feature`. Message: `"Feature '{$feature->name}' is not enabled in PlatformConfig."`. No test — trivial constructor.

`src/Domain/Shared/Feature.php` — pure enum: `Payments`, `Users`, `Credits`, `Subscriptions`. Centralises all gated feature names so use cases never pass raw strings to `FeatureDisabledException`. No test — same convention as other pure enums.

237 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 40. MailerConfig (`02_rez-config.md` step 2)

`src/Application/Config/MailerConfig.php` — immutable config value object. `public readonly string $fromAddress`, `public readonly string $fromName`. Throws `\InvalidArgumentException` if `$fromAddress` is not a valid email (`filter_var`) or `$fromName` is empty.

`tests/Application/Config/MailerConfigTest.php` — 3 cases: valid construction, invalid email throws, empty name throws.

240 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 41. PaymentsConfig (`02_rez-config.md` step 3)

`src/Application/Config/PaymentsConfig.php` — immutable config value object. `public readonly string $currency`, `public readonly string $webhookSecret`. Throws `\InvalidArgumentException` if either is empty.

`tests/Application/Config/PaymentsConfigTest.php` — 3 cases: valid construction, empty currency throws, empty webhook secret throws.

243 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 42. UsersConfig (`02_rez-config.md` step 4)

`src/Application/Config/UsersConfig.php` — immutable config value object. `public readonly string $jwtSecret`, `public readonly int $jwtTtlSeconds = 3600`, `public readonly int $passwordResetTtlMinutes = 60`. Throws `\InvalidArgumentException` if `$jwtSecret` is empty or either TTL is less than 1.

`tests/Application/Config/UsersConfigTest.php` — 5 cases: valid construction with custom values, defaults applied, empty secret throws, TTL below 1 throws (both fields).

248 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 43. CreditsConfig (`02_rez-config.md` step 5)

`src/Application/Config/CreditsConfig.php` — immutable config value object. `public readonly int $minimumTopUpAmount` (haléře/cents, min 1), `public readonly string $currency` (non-empty). Throws `\InvalidArgumentException` if either constraint is violated.

`tests/Application/Config/CreditsConfigTest.php` — 3 cases: valid construction, amount below 1 throws, empty currency throws.

251 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 44. Plan (`02_rez-config.md` step 6)

`src/Application/Config/PlanConfig.php` — immutable config value object. Fields: `id` (non-empty slug), `name` (non-empty), `priceAmount` (int ≥ 0, zero allowed for free plans), `currency` (non-empty), `intervalDays` (min 1), `stripePriceId` (non-empty — added per `07_rez-subscriptions.md`). Throws `\InvalidArgumentException` for each violated constraint. Named `PlanConfig` (not `Plan`) because it lives in Application/Config and uses primitive types rather than domain objects.

`tests/Application/Config/PlanConfigTest.php` — 8 cases: valid construction, zero price valid, empty id/name/currency/stripePriceId throws, negative price throws, intervalDays below 1 throws.

259 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 45. SubscriptionsConfig (`02_rez-config.md` step 7)

`src/Application/Config/SubscriptionsConfig.php` — immutable config. Constructor promotion: `@param PlanConfig[] $plans`. No empty guard — empty plans is valid (subscriptions enabled but none configured yet; no getPlanById call will be made if frontend has no plans to show). Element type enforced by PHPStan at max level. `getPlanById(string $id): PlanConfig` — throws if not found. No `getPlans()` — `->plans` is public readonly.

`tests/Application/Config/SubscriptionsConfigTest.php` — 3 cases: valid construction, getPlanById returns match, getPlanById throws for unknown id.

264 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 46. PlatformConfig (`02_rez-config.md` step 8)

`src/Application/Config/PlatformConfig.php` — immutable config root. Constructor promotion for all five fields (`MailerConfig $mailer`, `?PaymentsConfig`, `?UsersConfig`, `?CreditsConfig`, `?SubscriptionsConfig`). Validates dependency chain at construction: users requires payments; credits requires payments + users; subscriptions requires payments + users. Feature check methods: `hasMailer()` (always true), `hasPayments()`, `hasUsers()`, `hasCredits()`, `hasSubscriptions()`.

`tests/Application/Config/PlatformConfigTest.php` — 16 cases: valid mailer-only, valid all features, 5 dependency chain violations, hasMailer always true, has* false/true for each nullable feature.

278 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 47. FeatureGuard (`02_rez-config.md` step 9)

`src/Application/Service/FeatureGuard.php` — accepts `PlatformConfig` in constructor. Methods: `requirePayments()`, `requireUsers()`, `requireCredits()`, `requireSubscriptions()` — each throws `FeatureDisabledException(Feature::X)` if the corresponding config is null. No `requireMailer()` — mailer is always present.

`tests/Application/Service/FeatureGuardTest.php` — 8 cases: passes/throws for each of the 4 gated features. Uses `expectNotToPerformAssertions()` for the passing cases (avoids PHPStan `method.alreadyNarrowedType` error from `assertTrue(true)`).

286 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 48. Container update (`02_rez-config.md` step 10)

`config/container.php` — `FeatureGuard::class => autowire()` added. Comment documents that `PlatformConfig` must be bound by the client app. `02_rez-config.md` fully complete.

### 49. NewsletterSubscriberNotFoundException (`03_rez-mailer-newsletter.md` step 1)

`src/Domain/Exception/NewsletterSubscriberNotFoundException.php` — extends `DomainException`. Constructor: `string $email`. Message: `"Newsletter subscriber with email '{$email}' not found."`. No test — trivial constructor.

### 50. SubscriberSource (`03_rez-mailer-newsletter.md` step 2)

`src/Domain/Newsletter/SubscriberSource.php` — pure enum: `Guest`, `Registered`. String serialization handled by infrastructure mapper. No test — same convention as other pure enums.
