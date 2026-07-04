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

`database/seeds/schema/000_schema.sql` — authoritative DDL for all five tables, safe to re-run (`IF NOT EXISTS`).

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

**`database/seeds/data/`** — four idempotent SQL files with hardcoded UUIDs:
- `001_resources.sql` — 3 resources (UUIDs `aaaaaaaa-…-001/002/003`)
- `002_availability_rules.sql` — Mon–Fri 09:00–17:00, Sat 10:00–14:00 for all three
- `003_availability_overrides.sql` — Table 1 unavailable on 2024-06-08
- `004_reservations.sql` — 3 reservations (Mon/Tue 2024-06-03/04) + reservation_resources rows

The CLI entry point (`bin/seed.php`) lives in `rez-starter` — see step 67.

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

> **Updated in step 67:** `seedsPath()` now returns `database/seeds/schema`; `dataPath()` added returning `database/seeds/data`.

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

`src/Domain/Newsletter/SubscriberSource.php` — pure enum: `Guest`, `Registered`, `Admin`. String serialization handled by infrastructure mapper. No test — same convention as other pure enums.

### 51. NewsletterSubscriberId (`03_rez-mailer-newsletter.md` step 3)

`src/Domain/Newsletter/NewsletterSubscriberId.php` — UUID v4 value object, same pattern as `ReservationId` and `ResourceId`. Methods: `generate()`, `fromString()` (throws `\InvalidArgumentException` for invalid UUID), `toString()`, `equals()`, `__toString()`.

`tests/Domain/Newsletter/NewsletterSubscriberIdTest.php` — 5 cases: generate produces valid UUID v4, fromString roundtrips, fromString with invalid UUID throws, equals true for same, equals false for different.

291 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 52. NewsletterSubscriber (`03_rez-mailer-newsletter.md` step 4)

`src/Domain/Newsletter/NewsletterSubscriber.php` — immutable entity. `create(NewsletterSubscriberId, string $email, ?string $name, SubscriberSource): self` — validates email (throws `\InvalidArgumentException`), sets `$optedInAt` to UTC now. `reconstruct(...)` — static factory for DB hydration with a specific `$optedInAt`, bypasses `create()`. Properties are `public readonly` — no getter methods.

`tests/Domain/Newsletter/NewsletterSubscriberTest.php` — 6 cases: valid construction, invalid email throws, null name accepted, optedInAt ≈ UTC now, guest source, registered source. Tests access `$subscriber->email`, `$subscriber->source`, etc. (direct property access, no getters).

297 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 53. MailerInterface (`03_rez-mailer-newsletter.md` step 5)

`src/Application/Port/MailerInterface.php` — port contract. Methods: `sendBookingConfirmation(string, string, Reservation): void`, `sendBookingCancellation(string, string, Reservation): void`, `sendPasswordReset(string, string): void`, `sendNewClassNotification(string, string, DateTimeImmutable): void`. Recipient details are plain strings (not User objects) so the interface is usable without the users feature. No test — interface only.

297 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 54. NewsletterRepositoryInterface (`03_rez-mailer-newsletter.md` step 6)

`src/Application/Port/NewsletterRepositoryInterface.php` — port contract. Methods: `findByEmail(string): NewsletterSubscriber` (throws `NewsletterSubscriberNotFoundException`), `findAll(): NewsletterSubscriber[]`, `save(NewsletterSubscriber): void` (upsert by email), `delete(NewsletterSubscriberId): void`. No test — interface only.

297 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 55. Newsletter use cases (`03_rez-mailer-newsletter.md` step 7)

All three use cases follow Request/Response/UseCase/Interface pattern under `src/Application/UseCase/Newsletter/`.

**SubscribeUseCase** — idempotent: `findByEmail()` first; if not found creates new subscriber via `NewsletterSubscriber::create()`, saves, returns. If found returns existing without saving. 4 tests.

**UnsubscribeUseCase** — silent: `findByEmail()` first; if not found returns `removed: false`. If found calls `delete($subscriber->id)`, returns `removed: true`. 2 tests.

**BroadcastUseCase** — `findAll()` subscribers, calls `mailer->sendNewClassNotification()` for each, returns sent count. `BroadcastRequest` fields: `$resourceName` (string), `$resourceDate` (DateTimeImmutable). 3 tests.

306 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 56. Newsletter schema (`03_rez-mailer-newsletter.md` step 8)

`database/seeds/000_schema.sql` — added `newsletter_subscribers` table: `id CHAR(36) PK`, `email VARCHAR(255) UNIQUE NOT NULL`, `name VARCHAR(255) NULL`, `source VARCHAR(20) NOT NULL`, `opted_in_at DATETIME NOT NULL`. No new tests (DDL-only).

306 tests (22 skipped — all integration), PHPStan max clean, CS clean.

### 57. MysqlNewsletterRepository (`03_rez-mailer-newsletter.md` step 9)

`src/Infrastructure/Persistence/Mysql/MysqlNewsletterRepository.php` — implements `NewsletterRepositoryInterface`. Constructor: `PDO`, `SubscriberSourceMapper`. Methods: `findByEmail()` (throws `NewsletterSubscriberNotFoundException`), `findAll()` (ordered by `opted_in_at ASC`), `save()` (upsert by email — updates `name`/`source` only, preserves `opted_in_at`), `delete()`. Private `hydrate()` uses `NewsletterSubscriber::reconstruct()`.

`src/Infrastructure/Mapper/SubscriberSourceMapper.php` — maps `SubscriberSource` pure enum to/from string (`'guest'`/`'registered'`/`'admin'`). Same pattern as `ReservationStatusMapper`.

`tests/Infrastructure/Mapper/SubscriberSourceMapperTest.php` — 7 cases: toString for each case, fromString for each case, unknown string throws.

`NewsletterSubscriber::reconstruct()` added — static factory for hydration, bypasses `create()` which sets `optedInAt` to UTC now.

`MysqlIntegrationTestCase` updated — `newsletter_subscribers` table added to schema creation and truncation.

`tests/Integration/Persistence/Mysql/MysqlNewsletterRepositoryTest.php` — 5 integration tests (skipped locally): save+findByEmail, findByEmail throws, save is idempotent, findAll returns all, delete removes.

316 tests (27 skipped — all integration), PHPStan max clean, CS clean.

### 58. Newsletter container (`03_rez-mailer-newsletter.md` step 10)

`config/container.php` — `SubscribeUseCaseInterface`, `UnsubscribeUseCaseInterface`, `BroadcastUseCaseInterface` all registered via `autowire()`. Comment documents that `MailerInterface` and `NewsletterRepositoryInterface` must be bound by the client app. `03_rez-mailer-newsletter.md` fully complete.

316 tests (27 skipped — all integration), PHPStan max clean, CS clean.

---

### 59. `@throws` PHPDoc backfill (`04_rez-throws-phpdoc.md`)

`@throws` PHPDoc added to every public method that directly throws or propagates an exception. All 8 steps of the instruction file completed in a single branch (`feature/throws-phpdoc`), one commit per step.

**Step 1 — Domain value objects and entities:** `TimeSlot`, `ReservationId`, `Party`, `Reservation` (create/confirm/cancel/markNoShow), `ResourceId`, `ResourceType`, `ResourceIdCollection`, `Resource`, `Money` (construct/add/subtract/isGreaterThan), `DateTimeRange` (construct/toTimeSlot), `NewsletterSubscriberId`, `NewsletterSubscriber::create()`, `AvailabilityRule`.

**Step 2 — Application config classes:** `MailerConfig`, `PaymentsConfig`, `UsersConfig`, `CreditsConfig`, `PlanConfig` constructors + `SubscriptionsConfig::getPlanById()` + `PlatformConfig` constructor.

**Step 3 — Application request classes:** `GetAvailabilityRequest` constructor.

**Step 4 — Application service:** `FeatureGuard::requirePayments/Users/Credits/Subscriptions()`.

**Step 5 — Port interfaces:** `ReservationRepositoryInterface::findById()`, `ResourceRepositoryInterface::findById()`, `NewsletterRepositoryInterface::findByEmail()`.

**Step 6 — Use case interfaces and implementations:** All 12 throwing use cases tagged on both interface and concrete class — CreateReservation, CancelReservation, ConfirmReservation, MarkNoShow, GetReservation, SaveAvailabilityRule, SaveAvailabilityOverride, CreateResource, GetResource, UpdateResource, DeleteResource, SeedDatabase.

**Step 7 — Infrastructure mappers:** `ReservationStatusMapper`, `DayOfWeekMapper`, `ResourceTypeMapper`, `CurrencyMapper`, `SubscriberSourceMapper` — all `fromString()` methods.

**Step 8 — Infrastructure repositories and seeder:** `MysqlReservationRepository::findById()`, `MysqlResourceRepository::findById()`, `MysqlNewsletterRepository::findByEmail()`, `MysqlDatabaseSeeder::executeFile()`.

316 tests (27 skipped — all integration), PHPStan max clean, CS clean. `04_rez-throws-phpdoc.md` fully complete.

---

### 60. `DatabaseException` and PDO wrapping (`05_rez-pdo-exceptions.md`)

`05_rez-pdo-exceptions.md` fully complete. All 6 sub-steps implemented in branch `feature/rez-pdo-exceptions`, one commit per sub-step.

**Sub-step 1 — `DatabaseException`:** `src/Application/Exception/DatabaseException.php` — final class extending `\RuntimeException`. No custom constructor (marker class). No test needed.

**Sub-step 2 — Wrap PDO calls in MySQL repositories:** All `$pdo->prepare()` + `$stmt->execute()` pairs (and standalone `$pdo->exec()`) wrapped in `try { ... } catch (\PDOException $e) { throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e); }`. `fetch()`/`fetchAll()` after successful `execute()` remain outside the try/catch. Files: `MysqlReservationRepository` (findById, findByTimeSlotAndResource, findAll, save), `MysqlResourceRepository` (findById, findAll, save, delete), `MysqlAvailabilityRepository` (findRulesForResource, findOverridesForResource, saveRule, saveOverride), `MysqlNewsletterRepository` (findByEmail, findAll, save, delete), `MysqlDatabaseSeeder` (executeFile — each `$pdo->exec()`).

**Sub-step 3 — `@throws DatabaseException` on port interfaces:** All 4 methods on `ReservationRepositoryInterface`, `ResourceRepositoryInterface`, `AvailabilityRepositoryInterface`, `NewsletterRepositoryInterface`; `executeFile()` on `DatabaseSeederInterface`. FQNs used.

**Sub-step 4 — Handle `DatabaseException` in use cases (TDD):** Tests written first (`testRepositoryDatabaseExceptionPropagates` in each of 18 use case test classes), confirmed RED, then implementation added and amend-committed. Pattern: `try { $repo->... } catch (DatabaseException $e) { throw new DatabaseException('Failed to {verb} {entity}.', 0, $e); }`. Each repository call gets its own try/catch with its own context message. `SubscribeUseCase`/`UnsubscribeUseCase` had existing `NewsletterSubscriberNotFoundException` catch — `DatabaseException` added as a separate catch clause.

Context messages: "Failed to load reservation.", "Failed to save reservation.", "Failed to list reservations.", "Failed to load resource.", "Failed to save resource.", "Failed to delete resource.", "Failed to list resources.", "Failed to get availability.", "Failed to save availability rule.", "Failed to save availability override.", "Failed to load subscriber.", "Failed to save subscriber.", "Failed to delete subscriber.", "Failed to load newsletter subscribers.", "Failed to seed database."

**Sub-step 5 — `@throws DatabaseException` on use case interfaces and implementations:** Added `@throws \Rez\Application\Exception\DatabaseException` (FQN) to all 18 `*UseCaseInterface` files; added `@throws DatabaseException` (short name, imported) to all 18 `execute()` implementations.

**Sub-step 6 — Document `DatabaseException → 503` mapping:** Added `DatabaseException → 503` row to the exception/HTTP mapping table in `docs/REZ-CONTEXT.md`. Marked `rez-pdo-exceptions` as COMPLETE in pending scaffold list.

334 tests (27 skipped — all integration), PHPStan max clean, CS clean. `05_rez-pdo-exceptions.md` fully complete.

---

### 61. UTC timezone fix (`06_rez-testing-fixes.md` step 1)

All `DateTimeImmutable` instances inside `rez` now use an explicit UTC timezone — never relying on server default.

**Domain:** `AvailabilityRule::openTimeForDate()` and `closeTimeForDate()` — both now construct with `new \DateTimeZone('UTC')`.

**Infrastructure:** `MysqlReservationRepository::hydrate()` — `start_at`, `end_at`, and `created_at` now all constructed with UTC. `MysqlAvailabilityRepository::hydrateOverride()` — `date` column now constructed with UTC.

**Handlers:** `CreateReservationHandler`, `ListReservationsHandler`, `GetAvailabilityHandler`, `SaveAvailabilityOverrideHandler` — all user-input date strings now parsed with explicit UTC timezone.

2 new tests: `testOpenTimeForDateReturnsUtcTimezone`, `testCloseTimeForDateReturnsUtcTimezone` in `AvailabilityRuleTest`. 2 new integration tests: `testCreatedAtIsHydratedAsUtc`, `testSlotTimestampsAreHydratedAsUtc` in `MysqlReservationRepositoryTest` (skipped locally).

338 tests (29 skipped — all integration), PHPStan max clean, CS clean.

---

### 62. Cancelled reservations freed from availability (`06_rez-testing-fixes.md` step 2)

`MysqlReservationRepository::findByTimeSlotAndResource()` now filters out cancelled reservations with `AND r.status != :cancelled_status`, where the status string is resolved via `ReservationStatusMapper::toString(ReservationStatus::Cancelled)` — the mapper remains the single source of truth. `ReservationStatus` added to imports.

1 new integration test: `testCancelledReservationsAreExcludedFromOverlapQuery` (skipped locally).

339 tests (30 skipped — all integration), PHPStan max clean, CS clean.

---

### 63. `ReservationsConfig` + `autoConfirm` on `CreateReservationUseCase` (`06_rez-testing-fixes.md` step 5)

`src/Application/Config/ReservationsConfig.php` — immutable config value object. Single field: `public readonly bool $autoConfirm = false`. No validation needed — bool cannot be invalid.

`PlatformConfig` — `ReservationsConfig $reservations` added as a required second constructor parameter (after `MailerConfig`, before all optionals). All test callsites updated to use named arguments.

`CreateReservationUseCase` — `PlatformConfig` injected via constructor. If `$config->reservations->autoConfirm` is true, calls `$reservation->confirm()` in memory before saving — always a single DB write regardless of autoConfirm value.

`CreateReservationResponse` unchanged — status already accessible via `$response->reservation->status`.

3 new tests: `ReservationsConfigTest` (2 cases), `testAutoConfirmFalseLeavesPending`, `testAutoConfirmTrueConfirmsImmediately`. Existing `PlatformConfigTest` (17 tests) and `FeatureGuardTest` (8 tests) updated to named args.

344 tests (30 skipped — all integration), PHPStan max clean, CS clean. `06_rez-testing-fixes.md` steps 1, 2, 5 complete (steps 3, 4 are rez-starter only).

---

### 64. `AvailabilityRule` date bounds + `isActiveOn()` (`07_rez-availability-bounds.md` steps 1–4)

`07_rez-availability-bounds.md` fully complete (step 5 is rez-starter only, skipped).

**Step 1 — `AvailabilityRule` bounds:** `validFrom` and `validUntil` added as optional nullable `DateTimeImmutable` constructor parameters (both default `null`). `isActiveOn(DateTimeImmutable $date): bool` added — strips time via `format('Y-m-d')` before comparing, so a rule valid on a given calendar date is active for the whole day regardless of time-of-day.

**Step 2 — `AvailabilityService` filtering:** `AvailabilityService::findRuleForDate()` now calls `$rule->isActiveOn($date)` in addition to `$rule->appliesToDate($date)`. Rules outside their bounds are invisible to the availability pipeline.

**Step 3 — Schema + `MysqlAvailabilityRepository`:** `database/seeds/000_schema.sql` — `availability_rules` table gained `valid_from DATE NULL DEFAULT NULL` and `valid_until DATE NULL DEFAULT NULL` columns. `saveRule()` persists both as `Y-m-d` strings (or `null`). `hydrateRule()` reads them back as UTC `DateTimeImmutable` at midnight.

**Step 4 — `SaveAvailabilityRuleRequest` + `SaveAvailabilityRuleUseCase`:** Request gained `?string $validFrom = null` and `?string $validUntil = null`. Use case `execute()` validates both as `Y-m-d` format (throws `\InvalidArgumentException` for bad format or `validFrom > validUntil`), then passes parsed `DateTimeImmutable` bounds to `AvailabilityRule`. Interface updated with `@throws \InvalidArgumentException`.

10 new unit tests in `AvailabilityRuleTest` (`isActiveOn` variants + UTC timezone for `openTimeForDate`/`closeTimeForDate`). 3 new tests in `AvailabilityServiceTest` (bounds filtering). 4 new tests in `SaveAvailabilityRuleUseCaseTest` (validation). 2 new integration tests in `MysqlAvailabilityRepositoryTest` (bounds round-trip with and without values). All skipped locally.

364 tests (32 skipped — all integration), PHPStan max clean, CS clean.

---

### 65. DeleteAvailabilityRule, DeleteAvailabilityOverride, BulkCancelReservations

**DeleteAvailabilityRule** — removes a single day's rule for a resource. `AvailabilityRepositoryInterface` gained `deleteRule(ResourceId, DayOfWeek): void`. Use case validates resource exists then deletes. Handler takes `{resource_id, day_of_week}` and returns `{}`.

**DeleteAvailabilityOverride** — removes a date override for a resource. `AvailabilityRepositoryInterface` gained `deleteOverride(ResourceId, DateTimeImmutable): void`. Same pattern as above. Handler takes `{resource_id, date}` and returns `{}`.

**BulkCancelReservations** — cancels a list of reservations in one call. For each `ReservationId`: `findById` (skip on `ReservationNotFoundException`), `cancel()` (skip on `InvalidReservationStateException`), `save()`. Database errors propagate immediately. Response includes `cancelledCount` and `skippedCount`. Handler converts `string[]` → `ReservationId[]`.

All three use case interfaces registered in `config/container.php`. Integration tests for `deleteRule` and `deleteOverride` added to `MysqlAvailabilityRepositoryTest`.

384 tests (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 66. PSR-3 Logging (`08_rez-psr-logging.md`)

`psr/log: ^3.0` added to `composer.json` (was already a transitive dep; now declared).

**`CreateReservationUseCase`** — gained optional `?MailerInterface $mailer = null` and `LoggerInterface $logger = new NullLogger()` constructor parameters. After saving the reservation, if a mailer is injected it sends `sendBookingConfirmation`; any `\Throwable` is caught, logged at `error` level with `reservationId` + `email` + `error` context, and swallowed (booking succeeded). In `rez-starter`, the client app wires the concrete mailer in; the library default is null.

**`BroadcastUseCase`** — gained `LoggerInterface $logger = new NullLogger()`. Per-recipient send is now wrapped in try-catch `\Throwable`; failures are logged at `error` level with `subscriberId` + `email` + `error` context and skipped (send count not incremented). Existing behaviour for successful sends is unchanged.

**All four MySQL repositories** (`MysqlReservationRepository`, `MysqlResourceRepository`, `MysqlNewsletterRepository`, `MysqlAvailabilityRepository`) — each gained `LoggerInterface $logger = new NullLogger()`. Every `catch (\PDOException)` block now calls `$this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()])` before re-throwing as `DatabaseException`.

**`CancelReservationUseCase`** — logger deferred to `rez-guest-cancellation` (step 11) when HMAC token verification and email sending are added; injecting it now would be PHPStan `property.onlyWritten`.

3 new tests: `testMailerFailureIsLoggedAndNotPropagated` (CreateReservation), `testPerRecipientFailureIsLoggedAndSkipped` (Broadcast), `testDatabaseExceptionIsLoggedAsCritical` (MysqlRepositoryLoggerTest).

381 tests (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 67. Seed schema/data split + entry point moved to rez-starter (`rez-starter-01_testing-fixes.md` step 2)

`bin/seed.php` removed from rez — CLI entry points are delivery-layer concerns and belong in the client app (`rez-starter`), not the library. This aligns with the Handler deprecation principle.

`database/seeds/` reorganised into two subdirectories:
- `database/seeds/schema/` — DDL only (`000_schema.sql`). Always safe to run against any environment.
- `database/seeds/data/` — sample INSERT data for development. Run with `--fill` only.

`MysqlDatabaseSeeder` updated:
- `seedsPath(): string` — now returns `database/seeds/schema` (was `database/seeds`)
- `dataPath(): string` — new; returns `database/seeds/data`

Client apps compose seed directories by calling these path helpers and passing the result to `SeedDatabaseRequest`. `rez-starter/bin/seed.php` is the canonical entry point.

381 tests (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 68. GetAvailabilityRulesUseCase + GetAvailabilityOverridesUseCase

Two new read use cases for the availability module, following the standard Request/Response/UseCase/Interface pattern.

**GetAvailabilityRulesUseCase** — returns all `AvailabilityRule[]` for a given `ResourceId`. Delegates to `availabilityRepository->findRulesForResource()`. Wraps `DatabaseException` with context message "Failed to get availability rules."

**GetAvailabilityOverridesUseCase** — returns all `AvailabilityOverride[]` for a given `ResourceId` within a `DateTimeImmutable $from` / `$to` date range. Delegates to `availabilityRepository->findOverridesForResource()`. Wraps `DatabaseException` with context message "Failed to get availability overrides."

Both interfaces registered in `config/container.php`.

8 new tests (4 per use case). Total: 389 unit tests passing (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 70. ListSubscribersUseCase

`ListSubscribersUseCase` follows the standard Request/Response/UseCase/Interface pattern under `src/Application/UseCase/Newsletter/ListSubscribers/`.

- `ListSubscribersRequest` — no parameters
- `ListSubscribersResponse` — `NewsletterSubscriber[] $subscribers`
- `ListSubscribersUseCase` — calls `findAll()`, wraps `DatabaseException` with message "Failed to load newsletter subscribers."
- `ListSubscribersUseCaseInterface` registered in `config/container.php`

3 new tests. Total: 400 unit tests passing (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 69. Bug fixes from API testing (`09_rez-bug-fixes.md`)

Three bugs and one design gap identified during manual API testing (see `docs/reports/testing-report.md`).

**BUG-01 — `GetAvailabilityUseCase` resource existence check:** `ResourceRepositoryInterface` injected into `GetAvailabilityUseCase`. `findById()` called at the top of `execute()` before delegating to `AvailabilityService`. Non-existent resource now returns 404 (`ResourceNotFoundException`) instead of a silent empty slot window. `@throws ResourceNotFoundException` added to interface and implementation.

**BUG-02 + BUG-03 — Capacity-aware conflict detection:** `AvailabilityService` gains `ResourceRepositoryInterface` as its first constructor parameter. `isSlotAvailable()` and `getAvailableSlots()` both accept `int $partySize = 1`. The conflict check changes from `isEmpty()` to summing `party->size` across overlapping reservations and comparing against `resource->capacity`. A slot is available when `occupied + partySize <= capacity`. This fixes both bugs simultaneously: two small parties can share a high-capacity slot (BUG-02), and a party too large for any slot is rejected immediately (BUG-03). `filterConflictingSlots()` renamed to `filterAvailableSlots()` and made capacity-aware. `sumPartySize()` private helper added. `CreateReservationUseCase::assertAvailable()` passes `$request->party->size` as the party size. `GetAvailabilityRequest` gains optional `int $partySize = 1` with validation (`< 1` throws). `GetAvailabilityHandler` reads optional `party_size` from input.

9 new tests. Total: 398 unit tests passing (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 71. `SubscriberSource::Admin` + `BroadcastRequest` field rename

`src/Domain/Newsletter/SubscriberSource.php` — added `Admin` case (subscribers added manually via rez-admin).

`src/Infrastructure/Mapper/SubscriberSourceMapper.php` — `Admin` ↔ `'admin'` added to both `toString()` and `fromString()` match expressions.

`src/Application/UseCase/Newsletter/Broadcast/BroadcastRequest.php` — `$className` renamed to `$resourceName`; `$classDate` renamed to `$resourceDate`.

`src/Application/UseCase/Newsletter/Broadcast/BroadcastUseCase.php` — `execute()` updated to read `$request->resourceName` and `$request->resourceDate`.

2 new tests in `SubscriberSourceMapperTest` (`testAdminMapsToString`, `testStringMapsToAdmin`). Total: 402 unit tests passing (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 72. Fix: optional `$mailer`/`$logger` constructor params silently ignored by PHP-DI autowiring

**Bug:** `CreateReservationUseCase` (`$mailer`, `$logger`), `BroadcastUseCase` (`$logger`), and all four MySQL repositories (`$logger`) declared these dependencies as optional constructor parameters (`?MailerInterface $mailer = null`, `LoggerInterface $logger = new NullLogger()`). PHP-DI's `ReflectionBasedAutowiring::getParametersDefinition()` skips any constructor parameter where `isOptional()` is true — it never queries the container for that parameter, regardless of what's bound to its type. Result: a client app's `MailerInterface`/`LoggerInterface` bindings (e.g. `rez-starter`'s `SymfonyMailer` and Monolog logger) were never actually injected — these classes always received `null`/`NullLogger`, so reservation confirmation emails silently never sent and no error was ever logged.

**Fix (final design):** removed the defaults entirely — `$mailer` and `$logger` are now required, non-optional constructor parameters everywhere in the library. This forces PHP-DI's autowiring to always resolve them from the container. `config/container.php` binds library-level no-op defaults, `MailerInterface::class => autowire(NullMailer::class)` and `LoggerInterface::class => autowire(\Psr\Log\NullLogger::class)` (new `src/Infrastructure/Mailer/NullMailer.php`, a trivial no-op adapter), so the library works out of the box with no client wiring. Client apps (e.g. `rez-starter`) override both bindings with concrete implementations (`SymfonyMailer`, Monolog) — since `array_merge($libraryDefs, $clientDefs)` lets the client array win, this now works correctly for every current consumer, including the four MySQL repositories, without any client-side changes. `CreateReservationUseCase::execute()` no longer needs the `if ($this->mailer !== null)` guard — `NullMailer` no-ops safely when unconfigured.

This supersedes an interim fix that used `->constructorParameter()` overrides in `config/container.php` instead — that approach worked but only fixed the two classes bound in this repo's container and would have needed the same override repeated for every future class and every client-side repository binding. Removing the defaults fixes the root cause once, for all consumers.

Touches: `CreateReservationUseCase`, `BroadcastUseCase`, `MysqlReservationRepository`, `MysqlResourceRepository`, `MysqlAvailabilityRepository`, `MysqlNewsletterRepository`, `config/container.php`, new `NullMailer`. ~10 test call sites updated to pass explicit mailer/logger (mocks or `NullLogger`) now that the parameters are required. Verified via a standalone container-resolution script (no-override → `NullMailer`/`NullLogger`; client override → real bindings) and `composer ca` (402 tests, PHPStan max, CS clean).

---

### 73. Codebase cleanup: refactors + Handler layer removal

Housekeeping pass following a codebase audit. No functional change except where noted.

**`@throws DatabaseException` backfill** — 17 use case `execute()` methods caught and rethrew `DatabaseException` without declaring it in their docblock, violating the project's own one-tag-per-exception-type convention. Docblock-only change (`SaveAvailabilityOverrideUseCase`, `SaveAvailabilityRuleUseCase`, `BroadcastUseCase`, `UpdateResourceUseCase`, `UnsubscribeUseCase`, `MarkNoShowUseCase`, `DeleteResourceUseCase`, `ListResourcesUseCase`, `SubscribeUseCase`, `ListReservationsUseCase`, `CreateResourceUseCase`, `GetResourceUseCase`, `CancelReservationUseCase`, `ConfirmReservationUseCase`, `GetReservationUseCase`, `CreateReservationUseCase`, `SeedDatabaseUseCase`).

**`UuidV4Id` trait** — `src/Domain/Shared/UuidV4Id.php` extracts the byte-identical `generate()`/`fromString()`/`toString()`/`equals()`/`__toString()` implementation shared by `ReservationId`, `ResourceId`, and `NewsletterSubscriberId`. Each class becomes `final class X { use UuidV4Id; }`. `self` return/param types inside the trait resolve to the composing class at use-site, so type safety across aggregates (`ReservationId` vs `ResourceId`) is unchanged — passing the wrong type still fails to typecheck.

**`AvailabilityService::sumOverlappingPartySize()`** — `isSlotAvailable()`'s `sumPartySize()` (summed all reservations in the pre-filtered collection unconditionally) and `getAvailableSlots()`'s inline per-candidate overlap-and-sum closure were two slightly different implementations of the same computation. Unified into one private `sumOverlappingPartySize(TimeSlot, ReservationCollection): int` that checks `overlapsWith()` before summing, used by both call sites. Behavior-identical: in `isSlotAvailable()`, the repository already filters reservations to those overlapping the query slot, so the (now redundant) overlap check is always true there.

**`Utc` helper** — `src/Domain/Shared/Utc.php` with `timezone(): DateTimeZone` and `now(): DateTimeImmutable`, both stateless static factories (no cached static property — the codebase's "no static state" rule applies to mutable state, not stateless factories, consistent with existing `generate()` methods). Replaces ad hoc `new \DateTimeZone('UTC')` construction in `AvailabilityRule`, `Reservation::create()`, `NewsletterSubscriber::create()`, `SaveAvailabilityRuleUseCase`, `MysqlReservationRepository`, `MysqlAvailabilityRepository`, `MysqlNewsletterRepository`.

**`Email` helper** — `src/Domain/Shared/Email.php` with `isValid(string): bool`, wrapping `filter_var(..., FILTER_VALIDATE_EMAIL)`. Replaces duplicated inline validation in `Party`, `NewsletterSubscriber::create()`, and `MailerConfig`. Each call site keeps its own exception type — only the boolean check is shared.

**`MysqlDatabaseSeeder` logging (behavior change, approved)** — every other MySQL repository injects `LoggerInterface` and logs `critical()` before rethrowing a `PDOException` as `DatabaseException`; the seeder didn't. Added `LoggerInterface` as a required constructor parameter (same pattern as `MysqlReservationRepository` et al.), logs on `executeFile()`'s `PDOException` catch. `config/container.php` already autowires `MysqlDatabaseSeeder`, so no container change needed — PHP-DI resolves the new param from the existing `LoggerInterface::class => autowire(NullLogger::class)` binding. Updated `tests/Integration/Persistence/Mysql/MysqlDatabaseSeederTest.php` (constructs the seeder directly) to pass a `NullLogger`. Added `tests/Infrastructure/Persistence/Mysql/MysqlDatabaseSeederLoggerTest.php` mirroring the existing `MysqlRepositoryLoggerTest` pattern.

**Handler layer removed entirely** — `src/Handler/**` (14 handler classes + `ReservationSerializer` + `ResourceSerializer`) and `tests/Handler/**` deleted. Confirmed unused: no reference anywhere outside `src/Handler`/`tests/Handler` (`config/container.php` wires use case interfaces directly; no `examples/slim` directory exists in this repo). `phpunit.xml` — removed the now-nonexistent `Handler` testsuite entry. This effectively completes `docs/instructions/18_rez-deprecate-handlers.md` by removal rather than just `@deprecated` annotation (the plan's step 2 phpstan.neon suppression was moot — no `phpstan.neon` file exists; analysis runs via CLI flags in `composer stan`). `Infrastructure/Mapper/**` (`ReservationStatusMapper`, `ResourceTypeMapper`, `DayOfWeekMapper`, `SubscriberSourceMapper`, `CurrencyMapper`) is untouched — still actively used by the MySQL repositories for enum/string persistence, unrelated to the deprecated Handler layer despite living in the same historical delivery step.

Total: 381 unit tests passing (34 skipped — all integration), PHPStan max clean, CS clean.

---

### 74. MailerInterface reservation-email restructure (`rez-email-restructure`)

Splits the single, unconditionally-fired reservation email into three lifecycle-specific,
independently-triggerable emails. Breaking rename, not an addition — the old email's content
now means "reservation confirmed," not "reservation created." Scope is the port + call-site
cleanup only; no Twig templates or `SymfonyMailer` implementation (client-repo follow-up).

**`CancellationToken` value object** (`src/Domain/Shared/CancellationToken.php`) — pulled
forward from the still-pending `rez-guest-cancellation` scaffold (step 11) because the new
`MailerInterface` shape needs the type to exist. Stateless HMAC-SHA256 over `reservationId` +
a secret (`generate()`, `fromString()`, `verify()` via `hash_equals()`, `toString()`). No call
site wires it up yet — that's still `rez-guest-cancellation`'s and the not-yet-scaffolded
`rez-lifecycle-email-integration`'s job. `tests/Domain/Shared/CancellationTokenTest.php` — 5
cases.

**`MailerInterface`** (`src/Application/Port/MailerInterface.php`) — `sendBookingConfirmation()`
and `sendBookingCancellation()` replaced with three typed methods:
`sendReservationCreatedEmail(Reservation, CancellationToken)`,
`sendReservationConfirmedEmail(Reservation, CancellationToken)` (both cancellable states need
the token), `sendReservationCancelledEmail(Reservation)` (nothing left to cancel, no token).
`sendPasswordReset()` and `sendNewClassNotification()` unchanged. `NullMailer` updated to
match. `tests/Application/Port/MailerInterfaceTest.php` — 3 cases asserting the new method
shapes via mocks (per-method, not a use-case wiring test).

**`CreateReservationUseCase`** — the unconditional `sendBookingConfirmation()` call (and the
`try/catch` + logger-error wrapping it) removed entirely, along with the now-unused
`MailerInterface $mailer` and `LoggerInterface $logger` constructor parameters. Reintroducing
a call here — gated by settings and choosing created-vs-confirmed — is deferred to
`rez-lifecycle-email-integration`, not this scaffold. `sendBookingCancellation()` was never
actually called anywhere in the codebase, so no other call site needed cleanup.
`CreateReservationUseCaseTest` — removed `testMailerFailureIsLoggedAndNotPropagated` (tested
behavior that no longer exists) and the mailer/logger mocks from all other cases.

**Instruction docs updated for conflicts** (per this scaffold's explicit request to check
`docs/instructions/` before changing config-adjacent things): `11_rez-guest-cancellation.md`
— section 1 (`CancellationToken`) marked already-built; section 6 (old
`sendReservationConfirmation(Reservation, string $cancellationUrl)` design) replaced with
notes that the port now takes the `CancellationToken` object directly and URL-building is the
concrete mailer implementation's job; test list and checklist updated to match.
`17_rez-booking.md` — the two `mailer->sendBookingConfirmation(...)` /
`mailer->sendBookingCancellation(...)` call-outs (steps 7 and 4 of `CreateBookingUseCase` /
`CancelBookingUseCase`) updated to the new method names and signatures. `10_rez-config-update.md`
has no mailer references — unaffected.

8 tests added (5 `CancellationToken`, 3 `MailerInterface`), 1 removed
(`testMailerFailureIsLoggedAndNotPropagated`). Total: 388 unit tests passing (34 skipped — all
integration), PHPStan max clean, CS clean.

---

### 75. DB-backed ReservationSettings replaces ReservationsConfig (`rez-reservation-settings`)

Moves `autoConfirm` (and adds three new lifecycle-email toggles) from deploy-time env config to
a plain per-request DB read. No caching layer — explicit decision; add one later as an
infrastructure-layer decorator only if measured to matter.

**`ReservationSettings`** (`src/Domain/Reservation/ReservationSettings.php`) — value object,
four `bool` fields: `autoConfirm`, `autoSendReservationCreated`, `autoSendReservationConfirmed`,
`autoSendReservationCancelled`. No dedicated test — trivial, no invariants, matching this
codebase's precedent for value objects with no logic.

**`ReservationSettingsRepositoryInterface`** (`src/Application/Port/`) — `get(): ReservationSettings`
(throws `DatabaseException` if the row is missing — a missing row post-seed is a deployment bug,
not a normal state, so it never defaults silently) and `update(ReservationSettings): void`
(full-row replace, no PATCH semantics at the repository level).

**`MysqlReservationSettingsRepository`** — single-row table, `id` always `1` by convention (not
a DB constraint). Same `PDOException` → `DatabaseException` + `logger->critical()` wrapping as
every other MySQL repository. `tests/Infrastructure/Persistence/Mysql/MysqlReservationSettingsRepositoryLoggerTest.php`
— 2 cases (`get()`/`update()` PDO failure). `tests/Integration/Persistence/Mysql/MysqlReservationSettingsRepositoryTest.php`
— 3 cases (seeded-defaults round-trip, update round-trip, missing-row throws), skipped locally.
`MysqlIntegrationTestCase` creates the table and — since this row is a required singleton rather
than deletable sample data — re-inserts the default row after each test's `TRUNCATE` so every
test starts from a known state.

**Schema** — `database/seeds/schema/001_reservation_settings.sql`, a new file in `rez`'s owned
number range rather than appended to `000_schema.sql`, per this scaffold's explicit instruction
(diverges from this repo's earlier practice of one growing schema file, but the data/ directory
already establishes numbered-file-per-addition as a valid pattern within the seed convention).
`CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE` for the default row — idempotent, safe to re-run.
Defaults: `autoConfirm: false` (matches the old deploy-time default), the three
`autoSend*: true` (preserves today's observable behavior — an email always went out on create —
through the migration, even though which specific email fires now depends on `autoConfirm`).

**`GetReservationSettingsUseCase`** / **`UpdateReservationSettingsUseCase`**
(`src/Application/UseCase/ReservationSettings/`) — standard Request/Response/Interface pattern.
Get is a thin read-through (2 tests). Update is PATCH semantics reusing `UpdateResourceUseCase`'s
established shape — nullable request fields, `get()` current, apply only non-null fields, `update()`
— rather than inventing a new update style (8 tests: one per field independently, one combined,
one no-fields-provided, plus `get()`/`update()` `DatabaseException` propagation). Both interfaces
registered in `config/container.php`; the repository binding itself is left to the client app,
matching every other MySQL-repository-owned-by-`rez` interface (`NewsletterRepositoryInterface`
et al.) — `rez`'s own container never binds repository interfaces, only use cases.

**`ReservationsConfig` removed entirely.** `PlatformConfig` drops the `reservations` constructor
param (no `hasReservations()` existed to remove). `CreateReservationUseCase` — its *only* use of
`PlatformConfig` was `->reservations->autoConfirm` — now depends on
`ReservationSettingsRepositoryInterface` instead of `PlatformConfig`, wrapping `get()` failures as
`DatabaseException('Failed to load reservation settings.')` matching the existing wrapping
convention for `findById`/`save`. `PlatformConfigTest` and `FeatureGuardTest` updated (both
constructed a `ReservationsConfig` per test purely to satisfy the old required param).
No live client currently exists (core tier's users/auth layer isn't built yet — see §9 in
REZ-CONTEXT.md — so nothing could be live), confirming the "no migration/backfill needed" premise
this scaffold's instructions asked to verify before merging.

**`rez-starter` follow-up (not done here):** its `PlatformConfig` DI wiring still passes a
`reservations` argument that no longer exists on the constructor and will fail to compile;
its container also needs `ReservationSettingsRepositoryInterface` bound to
`MysqlReservationSettingsRepository`. Flagged in REZ-CONTEXT.md.

**Instruction docs** — `11_rez-guest-cancellation.md` and `17_rez-booking.md` both mentioned
`autoConfirm` in passing (not `ReservationsConfig` directly); both updated with a note that the
value now comes from `ReservationSettingsRepositoryInterface::get()->autoConfirm`, not
`PlatformConfig->reservations`.

18 tests added (2 repository logger, 3 integration, 2 Get-use-case, 8 Update-use-case,
1 new Create-use-case settings-propagation test), 3 removed (`ReservationsConfigTest`'s 2 cases,
`PlatformConfigTest::testReservationsConfigIsAccessible`). Total: 401 unit tests passing
(37 skipped — all integration), PHPStan max clean, CS clean.

---

### 76. Reservation lifecycle email wiring (`rez-lifecycle-email-integration`)

Wires settings-gated auto-sending into the three core reservation use cases, plus three
standalone manual-send use cases for rez-admin's "send anyway" buttons. Depends on
`rez-email-restructure` (three-method `MailerInterface`) and `rez-reservation-settings`
(`ReservationSettings`).

**`MailerConfig::cancellationSecret`** — new required field (non-empty string). Needed because
`CreateReservationUseCase`/`ConfirmReservationUseCase` needed a real HMAC secret to generate
`CancellationToken`s, but `UsersConfig` (the invariant-12-documented home) doesn't have this
field yet and stays optional on `PlatformConfig` until `rez-config-update` runs — out of scope
here, a much larger change touching dozens of existing tests. `MailerConfig` is the one config
object always required on `PlatformConfig` today, so this works with no nullability gap.
`tests/Application/Config/MailerConfigTest.php` — 1 new case (empty secret throws), existing
cases updated for the new required param. REZ-CONTEXT.md invariant 12 updated to match;
`docs/instructions/10_rez-config-update.md` flagged with a conflict note so that scaffold
doesn't add a second, independent `cancellationSecret` to `UsersConfig` later.

**`ReservationEmailService`** (`src/Application/Service/`) — single home for the
settings-check-and-send logic. `sendCreatedIfEnabled()`, `sendConfirmedIfEnabled()`,
`sendCancelledIfEnabled()` each check the matching `ReservationSettings` flag, then call the
matching `MailerInterface` method inside a try/catch that logs at `error` level and never
re-throws (invariant 11, now explicitly covering all three lifecycle emails, not just
create/cancel). `ReservationSettings` is passed in by the caller rather than loaded internally,
so a use case needing it for another decision (`autoConfirm`) only reads it once per request.
No interface — final class injected directly, same pattern as `FeatureGuard`. 9 tests (3 per
method: skip when disabled, call mailer when enabled, log-and-swallow on mailer exception).

**`CreateReservationUseCase`** — after save, a single `if ($settings->autoConfirm) { …
sendConfirmedIfEnabled … } else { … sendCreatedIfEnabled … }` decides which email fires.
Structural, not two independent flag checks — locked in by
`testAutoConfirmTrueAndConfirmedEnabledSendsOnlyConfirmedEmailEvenWhenCreatedAlsoEnabled`, which
sets both `autoSendReservationCreated` and `autoSendReservationConfirmed` to `true` with
`autoConfirm: true` and asserts only the confirmed email fires. Gained `ReservationEmailService`
and `MailerConfig` constructor deps. 5 new tests (2 for the false/true × enabled/disabled matrix
combinations not already covered by existing autoConfirm tests, 1 token-verifies-for-saved-id
test) on top of the existing suite.

**`ConfirmReservationUseCase`** — the manual admin-confirm path. After save: generates a
`CancellationToken` and calls the same `sendConfirmedIfEnabled()` that `CreateReservationUseCase`'s
autoConfirm branch uses — exactly one place decides whether a confirmed email goes out. Gained
`ReservationSettingsRepositoryInterface`, `ReservationEmailService`, `MailerConfig` deps. 4 new
tests (settings DatabaseException propagation, sends-when-enabled, skips-when-disabled, mailer
failure doesn't abort confirmation).

**`CancelReservationUseCase`** — after save: calls `sendCancelledIfEnabled()` unconditionally,
no actor-type branching (admin vs future guest HMAC path both get the same email behavior, per
explicit instruction). No token needed. Gained `ReservationSettingsRepositoryInterface`,
`ReservationEmailService` deps — no `MailerConfig` needed here since there's no token to
generate. 4 new tests, same shape as ConfirmReservationUseCase's.
`BulkCancelReservationsUseCase` intentionally untouched — independent save-per-id loop, doesn't
route through `CancelReservationUseCase`, wasn't in scope.

**Three manual-send use cases** (`src/Application/UseCase/ReservationEmail/`) — new top-level
use case category, alongside `Reservation`, `ReservationSettings`, `Availability`, `Resource`,
`Newsletter`. `SendReservationCreatedEmailUseCase`, `SendReservationConfirmedEmailUseCase`,
`SendReservationCancelledEmailUseCase` — each: load reservation (`ReservationNotFoundException`
propagates), generate a token where needed, call the matching `MailerInterface` method
*directly* (not through `ReservationEmailService` — that class exists for the settings-gated
path only; bypassing settings is meant to be explicit and grep-able). No reservation-state
validation — they fire regardless of status, by design; restricting when a button is shown is a
rez-admin concern. Mailer failures propagate unswallowed — manual admin action, not an
unattended auto-send. 12 new tests (4 per use case: happy path, missing reservation, DB
exception, mailer exception propagates).

All six use case interfaces registered in `config/container.php`, plus
`ReservationEmailService::class => autowire()`.

**Instruction docs updated for conflicts:** `10_rez-config-update.md` — conflict note added
(see above). `11_rez-guest-cancellation.md` — section 4 (`CancelReservationUseCase`) updated to
read `MailerConfig->cancellationSecret` instead of the never-built
`UsersConfig->cancellationSecret`, and to list the `ReservationSettingsRepositoryInterface`/
`ReservationEmailService` deps it already has; section 6 rewritten from "out of scope" to
"already done" describing what actually shipped. `17_rez-booking.md` — `CreateBookingUseCase`/
`CancelBookingUseCase` no longer need a `MailerInterface` dependency or manual send step, since
`CreateReservationUseCase`/`CancelReservationUseCase` already send the right email internally
now; the old plan would have double-sent.

**Explicitly out of scope (per this scaffold's own instructions):** `rez-starter` routes for
the three manual-send endpoints and the settings `GET`/`PATCH` endpoint; rez-admin UI; resource
delete/modify cascade cancellation emails (would loop `sendCancelledIfEnabled()` per affected
reservation — separate use case, not built).

**`rez-starter` follow-up (not done here):** needs a standalone `MailerConfig::class` container
binding — previously `MailerConfig` only existed nested inside `PlatformConfig`'s factory
closure; the new direct-dependency use cases need PHP-DI to autowire it on its own.

30 tests added (9 `ReservationEmailService`, 5 `CreateReservationUseCase` new + matrix, 4
`ConfirmReservationUseCase` new, 4 `CancelReservationUseCase` new, 12 manual-send use cases,
1 `MailerConfig`), 1 fixed for a PHPStan error (typed capture instead of untyped array
destructuring). Total: 436 unit tests passing (37 skipped — all integration), PHPStan max
clean, CS clean.

---

### 77. MailerSettings extracted from MailerConfig (`rez-mailer-settings`)

Moves `fromAddress`/`fromName` from deploy-time `MailerConfig` to a DB-backed
`MailerSettings` single-row table, same shape as `rez-reservation-settings`'
`ReservationSettings`. No caching layer, same explicit decision as that scaffold.

**`MailerSettings`** (`src/Domain/Mailer/MailerSettings.php`) — new `Domain/Mailer` namespace
(mirrors `ReservationSettings` living in `Domain/Reservation`). Unlike `ReservationSettings`'
four bools, `fromAddress`/`fromName` have real invariants (email format, non-empty) — moved
as-is from `MailerConfig`'s old validation, so this one gets its own value-object test
(3 cases), matching `Party`'s precedent of validating string fields in the constructor rather
than the no-test precedent for `ReservationSettings`/`AvailabilityOverride`.

**`MailerSettingsRepositoryInterface`** / **`MysqlMailerSettingsRepository`** — identical shape
to `ReservationSettingsRepositoryInterface`/`MysqlReservationSettingsRepository`: `get()` throws
`DatabaseException` on a missing row rather than defaulting (deployment bug, not normal state),
`update()` is a full-row replace. Single-row table, `id` always `1` by convention.
`tests/Infrastructure/Persistence/Mysql/MysqlMailerSettingsRepositoryLoggerTest.php` — 2 cases.
`tests/Integration/Persistence/Mysql/MysqlMailerSettingsRepositoryTest.php` — 3 cases (seeded
defaults, update round-trip, missing-row throws), skipped locally. `MysqlIntegrationTestCase`
creates the table and re-inserts the default row after each test's `TRUNCATE`, same pattern as
`reservation_settings`.

**Schema** — `database/seeds/schema/002_mailer_settings.sql`, next number after
`001_reservation_settings.sql`. `CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`. Seeded defaults
(`noreply@example.com` / `Rez`) are explicit placeholders — every deployment must update them
via the settings endpoint before going live; there's no meaningful "current behavior" default
to preserve here since `fromAddress`/`fromName` were always required, unlike `autoConfirm`.

**`GetMailerSettingsUseCase`** / **`UpdateMailerSettingsUseCase`**
(`src/Application/UseCase/MailerSettings/`) — same Request/Response/Interface pattern as the
reservation-settings pair. Get is a thin read-through (2 tests). Update is PATCH semantics
reusing `UpdateReservationSettingsUseCase`'s/`UpdateResourceUseCase`'s established shape (8
tests: one field independently ×2, combined, no-fields-provided, `get()`/`update()`
`DatabaseException` propagation ×2, invalid email throws, empty name throws — the latter two
have no equivalent in `UpdateReservationSettingsUseCaseTest` since `ReservationSettings` had no
validation to test). No `@throws \InvalidArgumentException` on the interface/use case docblock,
matching `UpdateResourceUseCaseInterface`'s existing precedent of omitting it even though the
underlying value object validates. Both interfaces registered in `config/container.php`; the
repository binding itself is left to the client app, same as every other `rez`-owned repository
interface.

**`MailerConfig` shrinks to just `cancellationSecret`.** `fromAddress`/`fromName` removed
entirely — all six call sites across `CreateReservationUseCase`, `ConfirmReservationUseCase`,
the two `SendReservation{Created,Confirmed}EmailUseCase` tests, `PlatformConfigTest`, and
`FeatureGuardTest` updated to the single-arg constructor. `cancellationSecret` stays as
deploy-time config rather than moving to DB-backed settings too — it's a security secret, not
admin-editable branding content, so it doesn't belong in the same category as `fromAddress`/
`fromName`. `MailerConfigTest` — 2 cases now (was 4; the two `fromAddress`/`fromName`
validation tests moved to `MailerSettingsTest`).

No pending instruction docs (`docs/instructions/*.md`) reference `fromAddress`/`fromName` —
only `cancellationSecret` and `cancellationBaseUrl`, both untouched by this scaffold and still
on `MailerConfig`. Nothing to update there this time.

**`rez-starter` follow-up (not done here):** `SymfonyMailer`'s "From" header currently reads
`MailerConfig->fromAddress`/`->fromName`, which no longer exist — needs switching to
`GetMailerSettingsUseCase` (or `MailerSettingsRepositoryInterface` directly) at send time.
Container also needs `MailerSettingsRepositoryInterface` bound to
`MysqlMailerSettingsRepository`, same as `ReservationSettingsRepositoryInterface`.

16 tests added (3 `MailerSettings`, 2 repository logger, 3 integration, 2 Get-use-case,
8 Update-use-case, minus 2 removed from `MailerConfigTest`). Total: 452 unit tests passing
(40 skipped — all integration), PHPStan max clean, CS clean.

---

### 78. Custom email template infrastructure (`rez-custom-email-templates`)

Admin-composed reusable emails (subject + HTML body), stored as a real multi-row collection —
not a settings singleton like `ReservationSettings`/`MailerSettings`. Covers only the `rez`
layer: domain entity, MySQL persistence, CRUD use cases, and a send-to-a-list use case. The
rich-text editor and template list UI live in `rez-admin`; the HTTP routes and the email-layout
wrapping live in `rez-starter` — both out of scope here, separate repos.

**`EmailTemplate`** (`src/Domain/Mailer/EmailTemplate.php`) — immutable entity: `id`, `subject`,
`html`, `createdAt`. Private constructor; `create()` validates (non-empty subject/html) and sets
`createdAt`; `reconstruct()` is DB hydration (trusts the row, matching
`NewsletterSubscriber::reconstruct()`); `withContent(subject, html)` is the update path —
validates and returns a new instance preserving `id`/`createdAt`, since there's no existing
"update in place" convention to reuse (`Resource`'s constructor isn't private, so its update
path just calls `new Resource(...)` directly — `EmailTemplate` can't do that). `EmailTemplateId`
— `UuidV4Id` trait, same as every other ID class. 12 tests (7 entity, 5 id).

**`EmailTemplateNotFoundException`** — empty stub extending `DomainException`, matching
`ResourceNotFoundException` (not `NewsletterSubscriberNotFoundException`'s constructor-arg
variant — templates are looked up by id, not a business key).

**`EmailTemplateRepositoryInterface`** / **`MysqlEmailTemplateRepository`** — full CRUD, shaped
like `ResourceRepositoryInterface`/`MysqlResourceRepository` (`findById`/`findAll`/`save`/
`delete`), not the settings pair's `get`/`update`-only shape. `findAll()` orders by
`created_at DESC`. Same `PDOException` wrapping convention as every other MySQL repository.
9 tests (4 repository logger, 5 integration — save/find roundtrip, not-found, findAll,
upsert-on-save, delete — skipped locally).

**Schema** — `database/seeds/schema/003_email_templates.sql`, next number after
`002_mailer_settings.sql`. `CREATE TABLE IF NOT EXISTS` only — no seed rows, since this is a
real collection (like `resources`), not a singleton settings row. `html` column is `MEDIUMTEXT`
(email bodies can be long; `MEDIUMTEXT`'s 16MB cap is comfortably more than enough).
`MysqlIntegrationTestCase` creates the table and truncates it per test — no reseed needed,
unlike `reservation_settings`/`mailer_settings`.

**`MailerInterface::sendCustomEmail(string $recipientEmail, string $subject, string $htmlBody)`**
— new typed method, consistent with this port's existing convention (one typed method per email,
not a generic `templateName`+`params` method, per the `rez-email-restructure` decision).
`rez-starter`'s concrete mailer wraps `htmlBody` in the shared email layout before sending —
`rez` only hands over the raw content. `NullMailer` updated. 1 new test in
`MailerInterfaceTest`.

**`CreateEmailTemplateUseCase`** / **`GetEmailTemplateUseCase`** / **`ListEmailTemplatesUseCase`**
/ **`UpdateEmailTemplateUseCase`** / **`DeleteEmailTemplateUseCase`**
(`src/Application/UseCase/EmailTemplate/`) — standard Request/Response/Interface pattern,
matching `Resource`'s CRUD use cases exactly (`ListEmailTemplatesResponse` wraps a plain
`EmailTemplate[]` array like `ListSubscribersResponse`, not a collection type like
`ListResourcesResponse` — `EmailTemplateRepositoryInterface::findAll()` returns a plain array).
`DeleteEmailTemplateUseCase` follows `DeleteResourceUseCase`'s order — `findById` before
`delete`, so a missing template surfaces as `EmailTemplateNotFoundException` rather than a
silent no-op `DELETE`. 23 tests total across the five.

**`SendEmailTemplateUseCase`** — loads the template by id, validates the whole recipient list up
front (non-empty, every address well-formed via `Email::isValid()`) and fails fast before
sending anything if any entry is malformed, then loops calling
`mailer->sendCustomEmail($recipient, $template->subject, $template->html)` per recipient,
catching `\Throwable` and logging (not re-throwing) on individual failures — same
catch-log-continue pattern as `BroadcastUseCase`. Returns the count actually sent. 6 tests.

All six use case interfaces registered in `config/container.php`; the repository binding itself
is left to the client app, same as every other `rez`-owned repository interface.

No pending instruction docs reference this feature — brand new, nothing to reconcile.

**`rez-starter`/`rez-admin` follow-ups (not done here, separate repos):** HTTP routes for the
six use cases; `SymfonyMailer::sendCustomEmail()` implementation wrapping `htmlBody` in the
Twig email layout; container binding `EmailTemplateRepositoryInterface` →
`MysqlEmailTemplateRepository`; the rez-admin editor/list/send UI described in the original
request.

51 tests added. Total: 503 unit tests passing (45 skipped — all integration), PHPStan max
clean, CS clean.

---

### 79. Users promoted to required + `cancellationSecret` migrated to `UsersConfig` (`10_rez-config-update.md`)

Resolves the conflict flagged in `10_rez-config-update.md` and `rez-lifecycle-email-integration`
(`docs/CONTEXT.md` step 76): `cancellationSecret` had landed on `MailerConfig` ahead of schedule
because `UsersConfig` wasn't required yet. This scaffold makes `UsersConfig` required and — per
explicit instruction, choosing the "migrate" option over "leave on MailerConfig" — moves
`cancellationSecret` onto it, deleting it from `MailerConfig`.

**`UsersConfig`** — gained `cancellationSecret` as a second required constructor parameter
(non-empty string, validated the same way as `jwtSecret`; separate field, never shares the
`jwtSecret` value). Final signature:
`__construct(string $jwtSecret, string $cancellationSecret, int $jwtTtlSeconds = 3600, int $passwordResetTtlMinutes = 60)`.

**`MailerConfig`** — `cancellationSecret` removed. The class now has zero properties (a
temporary placeholder — `REZ-CONTEXT.md` §3.7 already documents it gaining
`cancellationBaseUrl` in the not-yet-built `rez-guest-cancellation`). `MailerConfigTest`
deleted — no logic left to test, matching this codebase's convention for trivial classes.

**Four use cases switched from `MailerConfig->cancellationSecret` to
`UsersConfig->cancellationSecret`:** `CreateReservationUseCase`, `ConfirmReservationUseCase`,
`SendReservationCreatedEmailUseCase`, `SendReservationConfirmedEmailUseCase`. Each had
`MailerConfig $mailerConfig` as its sole use of that class — replaced outright with
`UsersConfig $usersConfig`, not added alongside it.

**`PlatformConfig`** — `UsersConfig $users` moved from the third, nullable parameter to the
second, required parameter (right after `mailer`). Dependency chain simplified from
`users → payments`, `credits → payments + users`, `subscriptions → payments + users` down to
just `credits → payments` and `subscriptions → payments` — users are implicitly always present,
so no separate check is needed. `hasUsers(): bool` removed. **No `getUsersConfig()` getter
added** despite `10_rez-config-update.md` section 3 suggesting one — `CLAUDE.md`'s "never add
getters when a public readonly property suffices" rule applies directly here: `$config->users`
is already a required, non-nullable `public readonly` property, so a getter would be pure
duplication.

**`Feature` enum** — `Users` case removed (three cases remain: `Payments`, `Credits`,
`Subscriptions`). **`FeatureGuard::requireUsers()`** removed entirely — no call site referenced
it (grepped and confirmed before removal, per the instruction's own verification step).

**Container wiring** — `rez`'s own `config/container.php` binds neither `PlatformConfig` nor any
of its sub-configs (comment: "must be bound by the client app"), so section 5 of the scaffold
(container wiring) is a `rez-starter`-only change, not made here.

**`rez-starter` follow-up (not done here, breaking change on top of an earlier one):**
`rez-starter` had already been synced once for `MailerConfig::cancellationSecret` (see
`docs/CONTEXT.md` step 76/77 and the `rez-starter` status block in `REZ-CONTEXT.md` §9) —
`CANCELLATION_SECRET` env var, a standalone `MailerConfig::class` binding, and the four call
sites reading `$mailerConfig->cancellationSecret`. All of that now needs re-pointing at
`UsersConfig->cancellationSecret` instead, and `UsersConfig`'s own container construction needs
the new required parameter wired from env. `MailerConfig::class`'s binding can be simplified to
a no-arg construction once `rez-guest-cancellation`'s `cancellationBaseUrl` isn't needed on it
yet either.

Test changes: `UsersConfigTest` (+2 cases: cancellationSecret round-trips, empty throws),
`MailerConfigTest` deleted (2 cases removed), `PlatformConfigTest` (removed 4 dependency-chain
tests for the deleted `users`/`hasUsers` checks, updated remaining 11 to pass `users` as
required), `FeatureGuardTest` (removed 2 `requireUsers` cases), plus the 4 use case test files
updated to construct `UsersConfig` instead of `MailerConfig`. Total: 495 unit tests passing (45
skipped — all integration), PHPStan max clean, CS clean. `10_rez-config-update.md` fully
complete.
