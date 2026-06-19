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

## Pending Steps

- **5.** `ResourceType`
- **6.** `Party` + `PartyTest`
- **7.** `Resource` + `ResourceTest`
- **8.** `ResourceCollection` + `ResourceCollectionTest`
- **9.** `Reservation` + `ReservationTest`
- **10.** `ReservationCollection` + `ReservationCollectionTest`
- **11.** `AvailabilityRule` + `AvailabilityRuleTest`
- **12.** `AvailabilityOverride`
- **13.** `AvailabilityWindow`
- **14.** `DateTimeRange`
- **15.** Port interfaces (`ReservationRepositoryInterface`, `ResourceRepositoryInterface`, `AvailabilityRepositoryInterface`)
- **16.** Application use cases + tests (CreateReservation, CancelReservation, GetReservation, ListReservations, GetAvailability)
- **17.** Infrastructure mappers (`ResourceTypeMapper` — `ReservationStatusMapper` done)
- **18.** Infrastructure MySQL repositories (`MysqlReservationRepository`, `MysqlResourceRepository`)
- **19.** Handlers (Reservation, Resource, Availability)
