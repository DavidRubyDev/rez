You are building "Rez" — a full PHP reservation engine.
Domain, application, infrastructure, and handler layers.
No HTTP framework. Pure PHP 8.3, strict types, immutable value objects, hexagonal architecture, DDD, TDD.

---

## Repository

Single directory: rez/
This will become a standalone Composer package.

---

## rez/composer.json

{
  "name": "davidrubydev/rez",
  "description": "Rez reservation engine",
  "type": "library",
  "autoload": {
    "psr-4": {
      "Rez\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Rez\\Tests\\": "tests/"
    }
  },
  "require": {
    "php": "^8.3"
  },
  "require-dev": {
    "phpunit/phpunit": "^11",
    "phpstan/phpstan": "^2",
    "squizlabs/php_codesniffer": "^3",
    "friendsofphp/php-cs-fixer": "^3"
  }
}

---

## rez/phpunit.xml

Standard PHPUnit config. testsuites:
- Domain: tests/Domain/
- Application: tests/Application/
- Infrastructure: tests/Infrastructure/
- Handler: tests/Handler/

Bootstrap: vendor/autoload.php
Colors enabled.

---

## Directory structure

src/
  Domain/
    Resource/
      Resource.php
      ResourceType.php
      ResourceId.php
      ResourceCollection.php
    Reservation/
      Reservation.php
      ReservationId.php
      ReservationStatus.php
      TimeSlot.php
      Party.php
      ReservationCollection.php
    Availability/
      AvailabilityRule.php
      AvailabilityOverride.php
      AvailabilityWindow.php
    Shared/
      DateTimeRange.php
    Exception/
      DomainException.php
      ConflictException.php
      ResourceNotFoundException.php
      ReservationNotFoundException.php
      InvalidTimeSlotException.php
      InvalidPartyException.php

  Application/
    UseCase/
      Reservation/
        CreateReservation/
          CreateReservationUseCase.php
          CreateReservationRequest.php
          CreateReservationResponse.php
        CancelReservation/
          CancelReservationUseCase.php
          CancelReservationRequest.php
          CancelReservationResponse.php
        GetReservation/
          GetReservationUseCase.php
          GetReservationRequest.php
          GetReservationResponse.php
        ListReservations/
          ListReservationsUseCase.php
          ListReservationsRequest.php
          ListReservationsResponse.php
      Resource/
        CreateResource/
          CreateResourceUseCase.php
          CreateResourceRequest.php
          CreateResourceResponse.php
        ListResources/
          ListResourcesUseCase.php
          ListResourcesRequest.php
          ListResourcesResponse.php
      Availability/
        GetAvailability/
          GetAvailabilityUseCase.php
          GetAvailabilityRequest.php
          GetAvailabilityResponse.php
    Port/
      ReservationRepositoryInterface.php
      ResourceRepositoryInterface.php
      AvailabilityRepositoryInterface.php

  Infrastructure/
    Persistence/
      Mysql/
        MysqlReservationRepository.php
        MysqlResourceRepository.php
        MysqlAvailabilityRepository.php
    Mapper/
      ReservationStatusMapper.php
      ResourceTypeMapper.php

  Handler/
    Reservation/
      CreateReservationHandler.php
      CancelReservationHandler.php
      GetReservationHandler.php
      ListReservationsHandler.php
    Resource/
      CreateResourceHandler.php
      ListResourcesHandler.php
    Availability/
      GetAvailabilityHandler.php

tests/
  Domain/
    Resource/
      ResourceIdTest.php
      ResourceTest.php
    Reservation/
      ReservationIdTest.php
      TimeSlotTest.php
      PartyTest.php
      ReservationTest.php
      ReservationCollectionTest.php
    Availability/
      AvailabilityRuleTest.php
  Application/
    UseCase/
      Reservation/
        CreateReservation/
          CreateReservationUseCaseTest.php
        CancelReservation/
          CancelReservationUseCaseTest.php
        GetReservation/
          GetReservationUseCaseTest.php
        ListReservations/
          ListReservationsUseCaseTest.php
      Availability/
        GetAvailability/
          GetAvailabilityUseCaseTest.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlReservationRepositoryTest.php
        MysqlResourceRepositoryTest.php
    Mapper/
      ReservationStatusMapperTest.php
      ResourceTypeMapperTest.php
  Handler/
    Reservation/
      CreateReservationHandlerTest.php
      CancelReservationHandlerTest.php
      GetReservationHandlerTest.php
      ListReservationsHandlerTest.php
    Resource/
      CreateResourceHandlerTest.php
      ListResourcesHandlerTest.php
    Availability/
      GetAvailabilityHandlerTest.php

---

## IMPLEMENT IN THIS EXACT ORDER
## Write the test first, then the implementation. All tests must pass before moving on.

---

### 1. DomainException.php

<?php declare(strict_types=1);
namespace Rez\Domain\Exception;

abstract class DomainException extends \RuntimeException {}

All other exceptions extend this base. Implement them all now as empty classes
extending DomainException. They will gain constructors as needed during implementation.

---

### 2. TimeSlot.php + TimeSlotTest.php

TimeSlot is an immutable value object.

Constructor: DateTimeImmutable $start, DateTimeImmutable $end
Throws InvalidTimeSlotException if $end <= $start.

Methods:
- start(): DateTimeImmutable
- end(): DateTimeImmutable
- overlapsWith(TimeSlot $other): bool
  Logic: $this->start < $other->end && $this->end > $other->start
  Adjacent slots where end === other->start do NOT overlap.
- duration(): \DateInterval
- equals(TimeSlot $other): bool — compares start and end timestamps
- __toString(): string — 'Y-m-d H:i:s / Y-m-d H:i:s'

TimeSlotTest must cover:
- Valid construction succeeds
- end === start throws InvalidTimeSlotException
- end before start throws InvalidTimeSlotException
- overlapsWith: complete overlap → true
- overlapsWith: partial overlap at start → true
- overlapsWith: partial overlap at end → true
- overlapsWith: adjacent slots (A end === B start) → FALSE — critical edge case
- overlapsWith: no overlap → false
- overlapsWith: identical slots → true
- duration() returns correct DateInterval
- equals() true for same values, false for different

---

### 3. ReservationId.php + ResourceId.php + ReservationIdTest.php + ResourceIdTest.php

Both are identical in structure. Immutable value objects wrapping a UUID string.

Methods:
- static generate(): self — generates UUID v4 using random_bytes
- static fromString(string $id): self — validates format, throws \InvalidArgumentException if not valid UUID
- toString(): string
- equals(self $other): bool
- __toString(): string

UUID v4 generation without ext-uuid:
$data = random_bytes(16);
$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

Each class has its own test file. Test:
- generate() produces valid UUID v4 format
- fromString() roundtrips correctly
- fromString() with invalid string throws \InvalidArgumentException
- equals() true for same id, false for different

---

### 4. ReservationStatus.php

Pure enum — no backing values. Serialization is handled by infrastructure mappers.

enum ReservationStatus
{
    case Pending;
    case Confirmed;
    case Cancelled;
    case NoShow;
}

No test needed. Used in Reservation tests.

---

### 5. ResourceType.php

Immutable value object wrapping a string slug.

Constructor: private, use fromString()
static fromString(string $slug): self
  - Validate: lowercase, alphanumeric + hyphens only, non-empty
  - Throws \InvalidArgumentException if invalid
toString(): string
equals(self $other): bool
__toString(): string

No test needed at this stage — tested indirectly via Resource tests.

---

### 6. Party.php + PartyTest.php

Immutable value object.

Constructor:
  string $name    — non-empty
  string $email   — valid format via filter_var FILTER_VALIDATE_EMAIL
  int $size       — minimum 1
  ?string $phone  — optional, no validation

Throws InvalidPartyException for any invalid field.
Getters: name(), email(), size(), phone()

PartyTest must cover:
- Valid construction stores all values
- Empty name throws InvalidPartyException
- Invalid email throws InvalidPartyException
- Size zero throws InvalidPartyException
- Negative size throws InvalidPartyException
- Null phone is accepted

---

### 7. Resource.php + ResourceTest.php

Immutable entity.

Constructor:
  ResourceId $id
  ResourceType $type
  string $name         — non-empty, throws \InvalidArgumentException if empty
  int $capacity        — minimum 1, throws \InvalidArgumentException if < 1
  array $attributes    — key-value, default []

All fields immutable. Getters for all.
withAttributes(array $attributes): self — returns new instance with merged attributes

ResourceTest must cover:
- Valid construction
- Empty name throws \InvalidArgumentException
- Capacity zero throws \InvalidArgumentException
- withAttributes returns new instance, original unchanged

---

### 8. ResourceCollection.php + ResourceCollectionTest.php

Wraps Resource[].

static empty(): self
static fromArray(array $resources): self — validates all elements are Resource instances
add(Resource $resource): self — immutable, returns new instance
isEmpty(): bool
count(): int
toArray(): array
filter(callable $fn): self
findById(ResourceId $id): ?Resource

Test:
- empty() creates empty collection
- add() returns new instance with element added
- original unchanged after add() — immutability
- isEmpty() true when empty, false when not
- filter() returns matching subset
- findById() returns correct resource or null

---

### 9. Reservation.php + ReservationTest.php

Immutable entity.

Static factory only — no public constructor:
static create(
  ReservationId $id,
  ResourceId[] $resourceIds,
  TimeSlot $slot,
  Party $party
): self
Sets status to ReservationStatus::Pending.
Throws \InvalidArgumentException if $resourceIds is empty.

State transition methods — each returns new immutable instance:
- confirm(): self — only from Pending, throws DomainException otherwise
- cancel(): self — from Pending or Confirmed, throws DomainException if already Cancelled
- markNoShow(): self — only from Confirmed, throws DomainException otherwise

Getters: id(), resourceIds(), slot(), party(), status(), createdAt()
resourceIds() returns ResourceId[].
createdAt() is set to current UTC time on create().

ReservationTest must cover:
- create() sets Pending status
- create() sets createdAt to approximately now
- confirm() from Pending → Confirmed
- confirm() from Confirmed throws DomainException
- cancel() from Pending → Cancelled
- cancel() from Confirmed → Cancelled
- cancel() from Cancelled throws DomainException
- markNoShow() from Confirmed → NoShow
- markNoShow() from Pending throws DomainException
- All state transitions return new instances, original unchanged

---

### 10. ReservationCollection.php + ReservationCollectionTest.php

Same pattern as ResourceCollection but for Reservation.

static empty(): self
static fromArray(array $reservations): self
add(Reservation $reservation): self — immutable
isEmpty(): bool
count(): int
toArray(): array
filter(callable $fn): self
findById(ReservationId $id): ?Reservation
filterByStatus(ReservationStatus $status): self

Test:
- empty(), add(), immutability, filter(), filterByStatus()
- findById() returns correct or null

---

### 11. AvailabilityRule.php + AvailabilityRuleTest.php

Immutable value object.

Constructor:
  ResourceId $resourceId
  int $dayOfWeek     — 0=Sunday through 6=Saturday, throws \InvalidArgumentException outside range
  string $openTime   — 'HH:MM' format
  string $closeTime  — 'HH:MM' format, must be after openTime, throws \InvalidArgumentException

Methods:
- resourceId(): ResourceId
- dayOfWeek(): int
- openTime(): string
- closeTime(): string
- appliesToDate(DateTimeImmutable $date): bool
  — compares $date->format('w') to dayOfWeek
- openTimeForDate(DateTimeImmutable $date): DateTimeImmutable
  — returns DateTimeImmutable for open time on given date
- closeTimeForDate(DateTimeImmutable $date): DateTimeImmutable
  — returns DateTimeImmutable for close time on given date

Test:
- Valid construction
- dayOfWeek -1 throws \InvalidArgumentException
- dayOfWeek 7 throws \InvalidArgumentException
- closeTime before openTime throws \InvalidArgumentException
- appliesToDate true for matching day
- appliesToDate false for non-matching day
- openTimeForDate and closeTimeForDate return correct DateTimeImmutable

---

### 12. AvailabilityOverride.php

Immutable value object.

Constructor:
  ResourceId $resourceId
  DateTimeImmutable $date
  bool $available

Getters: resourceId(), date(), isAvailable()
No test needed beyond construction — logic is trivial.

---

### 13. AvailabilityWindow.php

Represents resolved available TimeSlots for a resource on a given date.

Constructor:
  ResourceId $resourceId
  DateTimeImmutable $date
  TimeSlot[] $availableSlots

static empty(ResourceId $resourceId, DateTimeImmutable $date): self
slots(): array — returns TimeSlot[]
isEmpty(): bool
count(): int

---

### 14. DateTimeRange.php

Shared utility. Not a domain concept — used internally.

Constructor: DateTimeImmutable $start, DateTimeImmutable $end
Throws \InvalidArgumentException if end < start (equal is allowed — zero duration range)

contains(DateTimeImmutable $point): bool
overlapsWith(DateTimeRange $other): bool
toTimeSlot(): TimeSlot — throws InvalidTimeSlotException if start === end

---

### 15. Port interfaces

ReservationRepositoryInterface.php:

interface ReservationRepositoryInterface
{
    public function findById(ReservationId $id): Reservation;
    public function findByTimeSlotAndResource(
        TimeSlot $slot,
        ResourceId $resourceId
    ): ReservationCollection;
    public function findAll(
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null
    ): ReservationCollection;
    public function save(Reservation $reservation): void;
}

ResourceRepositoryInterface.php:

interface ResourceRepositoryInterface
{
    public function findById(ResourceId $id): Resource;
    public function findAll(): ResourceCollection;
    public function save(Resource $resource): void;
}

AvailabilityRepositoryInterface.php:

interface AvailabilityRepositoryInterface
{
    /** @return AvailabilityRule[] */
    public function findRulesForResource(ResourceId $resourceId): array;

    /** @return AvailabilityOverride[] */
    public function findOverridesForResource(
        ResourceId $resourceId,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array;
}

---

### 16. Application Use Cases

Build each use case group in this order.
For every use case: write the test first using PHPUnit mocks, then implement.

#### CreateReservation

CreateReservationRequest — readonly constructor properties:
  ResourceId[] $resourceIds
  DateTimeImmutable $start
  DateTimeImmutable $end
  Party $party

CreateReservationResponse — readonly constructor property:
  Reservation $reservation

CreateReservationUseCase:
  Constructor injects ReservationRepositoryInterface, ResourceRepositoryInterface
  execute(CreateReservationRequest): CreateReservationResponse

  Logic:
  1. findById resourceId — throws ResourceNotFoundException if missing
  2. Build TimeSlot($request->start, $request->end) — exception propagates
  3. findByTimeSlotAndResource — if not empty throw ConflictException
  4. Reservation::create(ReservationId::generate(), resourceId, slot, party)
  5. save()
  6. Return response

  ConflictException constructor: TimeSlot $slot, Resource $resource
  Store both, expose via slot() and resource() getters.

CreateReservationUseCaseTest:
  - Resource not found throws ResourceNotFoundException
  - Invalid time slot throws InvalidTimeSlotException
  - Conflicting reservation throws ConflictException
  - Success: save() called exactly once
  - Success: returned reservation has Pending status
  - Success: reservation has correct resourceId

#### CancelReservation

CancelReservationRequest — readonly: ReservationId $reservationId
CancelReservationResponse — readonly: Reservation $reservation

CancelReservationUseCase:
  1. findById — throws ReservationNotFoundException if missing
  2. $reservation->cancel() — DomainException propagates if already cancelled
  3. save()
  4. Return response

Test:
  - Not found throws ReservationNotFoundException
  - Already cancelled throws DomainException
  - Success: save() called with cancelled reservation
  - Success: response reservation has Cancelled status

#### GetReservation

GetReservationRequest — readonly: ReservationId $reservationId
GetReservationResponse — readonly: Reservation $reservation

GetReservationUseCase:
  findById, wrap in response. Throws ReservationNotFoundException if missing.

Test:
  - Not found throws ReservationNotFoundException
  - Found returns correct reservation in response

#### ListReservations

ListReservationsRequest — readonly:
  ?DateTimeImmutable $from = null
  ?DateTimeImmutable $to = null
  ?ResourceId $resourceId = null

ListReservationsResponse — readonly: ReservationCollection $reservations

ListReservationsUseCase:
  findAll(from, to) — then filter by resourceId in memory if provided
  Return response.

Test:
  - Returns all when no filters
  - Filters by resourceId correctly in returned collection

#### GetAvailability

GetAvailabilityRequest — readonly:
  ResourceId $resourceId
  DateTimeImmutable $date
  int $slotDurationMinutes   — must be > 0

GetAvailabilityResponse — readonly: AvailabilityWindow $window

GetAvailabilityUseCase:
  Constructor injects AvailabilityRepositoryInterface, ReservationRepositoryInterface

  execute(GetAvailabilityRequest): GetAvailabilityResponse

  Logic:
  1. Load rules via findRulesForResource
  2. Find rule where appliesToDate($request->date) — if none, return empty window
  3. Load overrides for date range (date to date+1day)
  4. If any override exists for the date and isAvailable() === false, return empty window
  5. Generate candidate slots:
     - Start from openTimeForDate($date)
     - Each slot: start = previous end, duration = slotDurationMinutes
     - Stop when slot end would exceed closeTimeForDate($date)
  6. Load existing reservations: findByTimeSlotAndResource for full day range + resourceId
  7. Filter candidates: keep slots where no existing reservation overlaps
  8. Return AvailabilityWindow with remaining slots

GetAvailabilityUseCaseTest:
  - No rule for date returns empty window
  - Override with available=false returns empty window
  - All slots taken returns empty window
  - No reservations returns all slots
  - One reservation removes only overlapping slots
  - Adjacent reservations do not block each other
  - slotDurationMinutes correctly divides the window
  - Slot that would exceed close time is not included

---

### 17. Infrastructure — Mappers

#### ReservationStatusMapper + ReservationStatusMapperTest

Maps between ReservationStatus (pure enum) and string for persistence.

Methods:
- toString(ReservationStatus $status): string
- fromString(string $value): ReservationStatus — throws \InvalidArgumentException for unknown values

Mapping:
  Pending   → 'pending'
  Confirmed → 'confirmed'
  Cancelled → 'cancelled'
  NoShow    → 'no_show'

Test:
- Each case maps to the correct string
- Each string maps back to the correct case
- Unknown string throws \InvalidArgumentException

#### ResourceTypeMapper + ResourceTypeMapperTest

Maps between ResourceType value object and string for persistence.

Methods:
- toString(ResourceType $type): string
- fromString(string $value): ResourceType

Test:
- Roundtrip: toString → fromString returns equivalent ResourceType
- Invalid string throws \InvalidArgumentException

---

### 18. Infrastructure — MySQL Repositories

Implement all three MySQL repositories. These are the driven adapters.
Constructor injects a \PDO instance. All methods must satisfy the port interface contracts.
Use mappers for enum/value object serialization.

#### MysqlReservationRepository + MysqlReservationRepositoryTest

Implements ReservationRepositoryInterface.

- save(): INSERT ... ON DUPLICATE KEY UPDATE, keyed by id
- findById(): throws ReservationNotFoundException if missing
- findByTimeSlotAndResource(): query by resourceId and overlapping slot range
- findAll(): optional from/to filtering via WHERE clause

Test uses a real MySQL connection (integration test). Requires a test database.
- save and findById roundtrip
- findById missing throws ReservationNotFoundException
- findByTimeSlotAndResource returns overlapping reservations only
- findAll with no filters returns all
- findAll with from/to filters correctly

#### MysqlResourceRepository + MysqlResourceRepositoryTest

Implements ResourceRepositoryInterface.

- save(): INSERT ... ON DUPLICATE KEY UPDATE
- findById(): throws ResourceNotFoundException if missing
- findAll(): returns ResourceCollection of all stored

Test:
- save and findById roundtrip
- findById missing throws ResourceNotFoundException
- findAll returns all saved resources

---

### 19. Handlers

Handlers are the driving adapters. They receive input from the outside world,
delegate to a use case, and return output. No HTTP, no framework — pure PHP.

Each handler wraps exactly one use case. Constructor injects the use case.
The handle() method accepts the use case Request object and returns the Response object.

#### Reservation Handlers

CreateReservationHandler:
  Constructor: CreateReservationUseCase
  handle(CreateReservationRequest): CreateReservationResponse

CancelReservationHandler:
  Constructor: CancelReservationUseCase
  handle(CancelReservationRequest): CancelReservationResponse

GetReservationHandler:
  Constructor: GetReservationUseCase
  handle(GetReservationRequest): GetReservationResponse

ListReservationsHandler:
  Constructor: ListReservationsUseCase
  handle(ListReservationsRequest): ListReservationsResponse

#### Resource Handlers

CreateResourceHandler:
  Constructor: CreateResourceUseCase
  handle(CreateResourceRequest): CreateResourceResponse

ListResourcesHandler:
  Constructor: ListResourcesUseCase
  handle(ListResourcesRequest): ListResourcesResponse

#### Availability Handler

GetAvailabilityHandler:
  Constructor: GetAvailabilityUseCase
  handle(GetAvailabilityRequest): GetAvailabilityResponse

Handler tests use PHPUnit mocks for the use case.
Each test verifies that handle() delegates to the use case and returns its response.

---

## General rules throughout

- Every file starts with: <?php declare(strict_types=1);
- Every class, method, and property has correct visibility
- No public mutable properties anywhere
- Use readonly where appropriate (PHP 8.1+ readonly properties)
- All DateTimeImmutable values stored and compared in UTC
- No static state except in the ID generate() methods
- Enums are pure (no backing values) — string mapping lives in infrastructure mappers
- PHPUnit mocks only — no test doubles library
- Each test class has one responsibility — do not combine domain tests with use case tests
- Run composer install and vendor/bin/phpunit after completing each numbered step above
  Fix any failures before proceeding to the next step
- Do not create any files outside rez/

---

### 20. Resource Use Cases + Handlers

Build these in TDD order: test → commit → confirm red → implement → amend.

#### CreateResource

`CreateResourceRequest` — readonly constructor properties:
  `string $type` — ResourceType slug
  `string $name`
  `int $capacity`
  `array $attributes = []`

`CreateResourceResponse` — readonly constructor property: `Resource $resource`

`CreateResourceUseCase implements CreateResourceUseCaseInterface`:
  Constructor injects `ResourceRepositoryInterface`.
  Logic:
  1. `Resource::create(ResourceId::generate(), ResourceType::fromString($request->type), $request->name, $request->capacity, $request->attributes)`
  2. `save()`
  3. Return response

`CreateResourceUseCaseInterface` — same pattern as reservation interfaces.

Test:
  - Success: `save()` called once, response contains resource with correct type/name/capacity
  - Invalid type slug throws `\InvalidArgumentException`
  - Capacity < 1 throws `\InvalidArgumentException`

#### GetResource

`GetResourceRequest` — readonly: `ResourceId $resourceId`
`GetResourceResponse` — readonly: `Resource $resource`

`GetResourceUseCase implements GetResourceUseCaseInterface`:
  `findById()` — throws `ResourceNotFoundException` if missing.

Test:
  - Not found throws `ResourceNotFoundException`
  - Found returns correct resource in response

#### ListResources

`ListResourcesRequest` — empty (no filters).
`ListResourcesResponse` — readonly: `ResourceCollection $resources`

`ListResourcesUseCase implements ListResourcesUseCaseInterface`:
  `findAll()` — wrap in response.

Test:
  - Returns all resources from repository

#### CreateResourceHandler / GetResourceHandler / ListResourcesHandler

`src/Handler/ResourceSerializer.php` — shared serialization of `Resource` to array:

```php
/**
 * @return array{
 *     id: string,
 *     type: string,
 *     name: string,
 *     capacity: int,
 *     attributes: array<string, mixed>
 * }
 */
public static function serialize(Resource $resource): array
```

`CreateResourceHandler::handle(array): array`
  Input shape: `array{type: string, name: string, capacity: int, attributes?: array<string, mixed>}`
  Returns serialized resource.

`GetResourceHandler::handle(array): array`
  Input shape: `array{id: string}`
  Returns serialized resource.

`ListResourcesHandler::handle(array): array`
  Input shape: `array{}` (no input needed)
  Returns `list<array{id: string, type: string, name: string, capacity: int, attributes: array<string, mixed>}>`

Add all three use case interfaces to `config/container.php` bindings.

---

### 21. Database Setup

Create a standalone SQL file at `database/schema.sql` containing the full DDL.
This file is the authoritative schema definition — keep it in sync with `MysqlIntegrationTestCase::createSchema()`.

```sql
CREATE TABLE IF NOT EXISTS resources (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    type       VARCHAR(100) NOT NULL,
    name       VARCHAR(255) NOT NULL,
    capacity   INT          NOT NULL,
    attributes JSON         NOT NULL
);

CREATE TABLE IF NOT EXISTS reservations (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    status      VARCHAR(20)  NOT NULL,
    start_at    DATETIME     NOT NULL,
    end_at      DATETIME     NOT NULL,
    party_name  VARCHAR(255) NOT NULL,
    party_email VARCHAR(255) NOT NULL,
    party_size  INT          NOT NULL,
    party_phone VARCHAR(50)  NULL,
    created_at  DATETIME     NOT NULL
);

CREATE TABLE IF NOT EXISTS reservation_resources (
    reservation_id CHAR(36) NOT NULL,
    resource_id    CHAR(36) NOT NULL,
    PRIMARY KEY (reservation_id, resource_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)  ON DELETE CASCADE,
    FOREIGN KEY (resource_id)    REFERENCES resources(id)     ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS availability_rules (
    resource_id CHAR(36)    NOT NULL,
    day_of_week VARCHAR(10) NOT NULL,
    open_time   CHAR(5)     NOT NULL,
    close_time  CHAR(5)     NOT NULL,
    PRIMARY KEY (resource_id, day_of_week),
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS availability_overrides (
    resource_id CHAR(36)   NOT NULL,
    date        DATE       NOT NULL,
    available   TINYINT(1) NOT NULL,
    PRIMARY KEY (resource_id, date),
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE
);
```

Column notes:
- `open_time` / `close_time`: `CHAR(5)` stores `'HH:MM'` exactly as used in `AvailabilityRule`
- `available`: `1` = available, `0` = blocked
- `attributes`: `JSON` — MySQL 5.7.8+
- All timestamps in UTC, stored without timezone
- No auto-increment IDs — all PKs are UUID v4 strings generated in PHP

---

### 22. OpenAPI / Swagger

Create `docs/openapi.yaml`. This describes the HTTP API surface that a client app exposes by wiring the handlers to routes. The library is framework-agnostic — this spec is the contract, not the implementation.

```yaml
openapi: 3.0.3
info:
  title: Rez Reservation API
  version: 1.0.0

paths:

  /resources:
    post:
      summary: Create a resource
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [type, name, capacity]
              properties:
                type:        { type: string, example: table }
                name:        { type: string, example: "Table 1" }
                capacity:    { type: integer, minimum: 1, example: 4 }
                attributes:  { type: object, additionalProperties: true }
      responses:
        '201':
          description: Resource created
          content:
            application/json:
              schema: { $ref: '#/components/schemas/Resource' }
    get:
      summary: List all resources
      responses:
        '200':
          description: List of resources
          content:
            application/json:
              schema:
                type: array
                items: { $ref: '#/components/schemas/Resource' }

  /resources/{id}:
    get:
      summary: Get a resource by ID
      parameters:
        - { name: id, in: path, required: true, schema: { type: string, format: uuid } }
      responses:
        '200':
          description: Resource found
          content:
            application/json:
              schema: { $ref: '#/components/schemas/Resource' }
        '404': { description: Resource not found }

  /reservations:
    post:
      summary: Create a reservation
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [resource_ids, start, end, party]
              properties:
                resource_ids:
                  type: array
                  items: { type: string, format: uuid }
                  minItems: 1
                start:  { type: string, format: date-time, example: "2024-06-01 10:00:00" }
                end:    { type: string, format: date-time, example: "2024-06-01 11:00:00" }
                party:
                  type: object
                  required: [name, email, size]
                  properties:
                    name:   { type: string }
                    email:  { type: string, format: email }
                    size:   { type: integer, minimum: 1 }
                    phone:  { type: string, nullable: true }
      responses:
        '201':
          description: Reservation created
          content:
            application/json:
              schema: { $ref: '#/components/schemas/Reservation' }
        '409': { description: Conflict — slot already taken }

    get:
      summary: List reservations
      parameters:
        - { name: from,        in: query, schema: { type: string, format: date-time } }
        - { name: to,          in: query, schema: { type: string, format: date-time } }
        - { name: resource_id, in: query, schema: { type: string, format: uuid } }
      responses:
        '200':
          description: List of reservations
          content:
            application/json:
              schema:
                type: array
                items: { $ref: '#/components/schemas/Reservation' }

  /reservations/{id}:
    get:
      summary: Get a reservation by ID
      parameters:
        - { name: id, in: path, required: true, schema: { type: string, format: uuid } }
      responses:
        '200':
          description: Reservation found
          content:
            application/json:
              schema: { $ref: '#/components/schemas/Reservation' }
        '404': { description: Reservation not found }

  /reservations/{id}/cancel:
    post:
      summary: Cancel a reservation
      parameters:
        - { name: id, in: path, required: true, schema: { type: string, format: uuid } }
      responses:
        '200':
          description: Reservation cancelled
          content:
            application/json:
              schema: { $ref: '#/components/schemas/Reservation' }
        '404': { description: Reservation not found }
        '422': { description: Cannot cancel (already cancelled) }

  /availability:
    get:
      summary: Get available slots for a resource on a date
      parameters:
        - { name: resource_id,           in: query, required: true,  schema: { type: string, format: uuid } }
        - { name: date,                  in: query, required: true,  schema: { type: string, format: date, example: "2024-06-01" } }
        - { name: slot_duration_minutes, in: query, required: false, schema: { type: integer, minimum: 1, default: 60 } }
      responses:
        '200':
          description: Availability window
          content:
            application/json:
              schema: { $ref: '#/components/schemas/AvailabilityWindow' }

components:
  schemas:

    Resource:
      type: object
      properties:
        id:         { type: string, format: uuid }
        type:       { type: string }
        name:       { type: string }
        capacity:   { type: integer }
        attributes: { type: object, additionalProperties: true }

    Party:
      type: object
      properties:
        name:  { type: string }
        email: { type: string, format: email }
        size:  { type: integer }
        phone: { type: string, nullable: true }

    Reservation:
      type: object
      properties:
        id:           { type: string, format: uuid }
        status:       { type: string, enum: [pending, confirmed, cancelled, no_show] }
        start:        { type: string, format: date-time }
        end:          { type: string, format: date-time }
        resource_ids: { type: array, items: { type: string, format: uuid } }
        party:        { $ref: '#/components/schemas/Party' }
        created_at:   { type: string, format: date-time }

    TimeSlot:
      type: object
      properties:
        start: { type: string, format: date-time }
        end:   { type: string, format: date-time }

    AvailabilityWindow:
      type: object
      properties:
        resource_id: { type: string, format: uuid }
        date:        { type: string, format: date }
        slots:
          type: array
          items: { $ref: '#/components/schemas/TimeSlot' }
```

---

### 23. CLI Seed Command

Create `bin/seed.php` — a standalone PHP script to populate the database with realistic sample data.

The script must:
- Read `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` from environment or `.env` file at project root (parse manually, no library required)
- Bootstrap via `vendor/autoload.php`
- Wire the container using `config/container.php` plus the infrastructure bindings
- Use the use case handlers (not repositories directly) to insert data
- Be idempotent where possible (re-running does not duplicate data)
- Print progress to stdout

Seed data to create:

1. **3 resources** of type `table` with names Table 1/2/3, capacity 4
2. **Availability rules** for each resource: Monday–Friday 09:00–17:00, Saturday 10:00–14:00
3. **1 availability override** — mark the Saturday of the current week as unavailable for Table 1
4. **3 reservations** across different resources and time slots in the current week, all in the 09:00–17:00 window

Script structure:

```php
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
use function DI\autowire;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;
use Rez\Infrastructure\Persistence\Mysql\MysqlAvailabilityRepository;

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
        PDO::class                             => fn () => $pdo,
        ReservationRepositoryInterface::class  => autowire(MysqlReservationRepository::class),
        ResourceRepositoryInterface::class     => autowire(MysqlResourceRepository::class),
        AvailabilityRepositoryInterface::class => autowire(MysqlAvailabilityRepository::class),
    ])
    ->build();

// Instantiate handlers and run seed logic here
// ...

echo "Seed complete.\n";
```

The availability repository's `saveRule()` and `saveOverride()` methods are on `MysqlAvailabilityRepository` directly (not on the port interface). Call them via the concrete class or extend the port interface to include write methods before this step.

Note: `AvailabilityRepositoryInterface` currently only defines read methods. Decide in this step whether to add `saveRule()` and `saveOverride()` to the port interface or keep write methods on the concrete class only (preferred if only the seed script needs them).
