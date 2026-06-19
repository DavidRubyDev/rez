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

### 11. AvailabilityRule + AvailabilityRuleTest

`src/Domain/Availability/AvailabilityRule.php` — immutable value object.

- Constructor: `ResourceId $resourceId`, `int $dayOfWeek` (0–6), `string $openTime` ('HH:MM'), `string $closeTime` ('HH:MM')
- Throws `\InvalidArgumentException` if `$dayOfWeek` outside 0–6 or `$closeTime <= $openTime`
- `resourceId(): ResourceId`
- `dayOfWeek(): int`
- `openTime(): string`
- `closeTime(): string`
- `appliesToDate(DateTimeImmutable): bool` — compares `$date->format('w')` to `dayOfWeek`
- `openTimeForDate(DateTimeImmutable): DateTimeImmutable`
- `closeTimeForDate(DateTimeImmutable): DateTimeImmutable`

`tests/Domain/Availability/AvailabilityRuleTest.php` — all 8 cases passing.

---

## Pending Steps

- **12.** `AvailabilityOverride`
- **13.** `AvailabilityWindow`
- **14.** `DateTimeRange`
- **15.** Port interfaces (`ReservationRepositoryInterface`, `ResourceRepositoryInterface`, `AvailabilityRepositoryInterface`)
- **16.** Application use cases + tests (CreateReservation, CancelReservation, GetReservation, ListReservations, GetAvailability)
- **18.** Infrastructure MySQL repositories (`MysqlReservationRepository`, `MysqlResourceRepository`)
- **19.** Handlers (Reservation, Resource, Availability)
