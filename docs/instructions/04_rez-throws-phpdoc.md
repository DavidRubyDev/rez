# Rez — @throws PHPDoc

Add `@throws` PHPDoc to every public method that either directly throws or propagates an
exception without catching it. This makes the exception contract explicit for callers and
enables static analysis to verify exception handling.

**Rule:** document every exception a caller may receive — thrown directly OR propagated
from a called method. Private methods that throw are covered indirectly through the public
methods that call them.

Run `composer ca` after completing all changes and fix any issues before committing.

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. Domain value objects and entities

#### `src/Domain/Reservation/TimeSlot.php`
- `__construct()` → `@throws InvalidTimeSlotException`

#### `src/Domain/Reservation/ReservationId.php`
- `fromString(string $id): self` → `@throws \InvalidArgumentException`

#### `src/Domain/Reservation/Party.php`
- `__construct()` → `@throws InvalidPartyException`

#### `src/Domain/Reservation/Reservation.php`
- `create()` → `@throws \InvalidArgumentException` (empty resource collection)
- `confirm()` → `@throws InvalidReservationStateException`
- `cancel()` → `@throws InvalidReservationStateException`
- `markNoShow()` → `@throws InvalidReservationStateException`

#### `src/Domain/Resource/ResourceId.php`
- `fromString(string $id): self` → `@throws \InvalidArgumentException`

#### `src/Domain/Resource/ResourceType.php`
- `fromString(string $slug): self` → `@throws \InvalidArgumentException`

#### `src/Domain/Resource/ResourceIdCollection.php`
- `fromArray(array $ids): self` → `@throws \InvalidArgumentException`

#### `src/Domain/Resource/Resource.php`
- `__construct()` → `@throws \InvalidArgumentException`

#### `src/Domain/Shared/Money.php`
- `__construct()` → `@throws \InvalidArgumentException`
- `add(Money $other): self` → `@throws \InvalidArgumentException` (currency mismatch)
- `subtract(Money $other): self` → `@throws InsufficientFundsException`, `@throws \InvalidArgumentException` (currency mismatch)
- `isGreaterThan(Money $other): bool` → `@throws \InvalidArgumentException` (currency mismatch)

#### `src/Domain/Shared/DateTimeRange.php`
- `__construct()` → `@throws \InvalidArgumentException`
- `toTimeSlot(): TimeSlot` → `@throws InvalidTimeSlotException`

#### `src/Domain/Newsletter/NewsletterSubscriberId.php`
- `fromString(string $id): self` → `@throws \InvalidArgumentException`

#### `src/Domain/Newsletter/NewsletterSubscriber.php`
- `create()` → `@throws \InvalidArgumentException`

#### `src/Domain/Availability/AvailabilityRule.php`
- `__construct()` → `@throws \InvalidArgumentException`

---

### 2. Application config classes

All config class constructors throw `\InvalidArgumentException` on invalid input.

#### `src/Application/Config/MailerConfig.php`
- `__construct()` → `@throws \InvalidArgumentException`

#### `src/Application/Config/PaymentsConfig.php`
- `__construct()` → `@throws \InvalidArgumentException`

#### `src/Application/Config/UsersConfig.php`
- `__construct()` → `@throws \InvalidArgumentException`

#### `src/Application/Config/CreditsConfig.php`
- `__construct()` → `@throws \InvalidArgumentException`

#### `src/Application/Config/PlanConfig.php`
- `__construct()` → `@throws \InvalidArgumentException`

#### `src/Application/Config/SubscriptionsConfig.php`
- `getPlanById(string $id): PlanConfig` → `@throws \InvalidArgumentException`

#### `src/Application/Config/PlatformConfig.php`
- `__construct()` → `@throws \InvalidArgumentException`

---

### 3. Application request classes

#### `src/Application/UseCase/Availability/GetAvailability/GetAvailabilityRequest.php`
- `__construct()` → `@throws \InvalidArgumentException`

---

### 4. Application service

#### `src/Application/Service/FeatureGuard.php`
- `requirePayments()` → `@throws FeatureDisabledException`
- `requireUsers()` → `@throws FeatureDisabledException`
- `requireCredits()` → `@throws FeatureDisabledException`
- `requireSubscriptions()` → `@throws FeatureDisabledException`

---

### 5. Port interfaces

Document what callers may receive when calling these methods.

#### `src/Application/Port/ReservationRepositoryInterface.php`
- `findById(ReservationId $id): Reservation` → `@throws ReservationNotFoundException`

#### `src/Application/Port/ResourceRepositoryInterface.php`
- `findById(ResourceId $id): Resource` → `@throws ResourceNotFoundException`

#### `src/Application/Port/NewsletterRepositoryInterface.php`
- `findByEmail(string $email): NewsletterSubscriber` → `@throws NewsletterSubscriberNotFoundException`

---

### 6. Use case interfaces and implementations

Add identical `@throws` blocks to both the `*UseCaseInterface` and the concrete `*UseCase`
class — they must stay in sync.

#### CreateReservation
Both `CreateReservationUseCaseInterface` and `CreateReservationUseCase::execute()`:
- `@throws ResourceNotFoundException` — resource not found in repository
- `@throws InvalidTimeSlotException` — invalid start/end combination
- `@throws ConflictException` — slot is not available

#### CancelReservation
- `@throws ReservationNotFoundException`
- `@throws InvalidReservationStateException` — already cancelled

#### ConfirmReservation
- `@throws ReservationNotFoundException`
- `@throws InvalidReservationStateException` — not in Pending state

#### MarkNoShow
- `@throws ReservationNotFoundException`
- `@throws InvalidReservationStateException` — not in Confirmed state

#### GetReservation
- `@throws ReservationNotFoundException`

#### ListReservations
- No throws — `findAll()` returns an empty collection if nothing matches.

#### GetAvailability
- No throws — returns an empty window if no rules exist for the date.

#### SaveAvailabilityRule
- `@throws ResourceNotFoundException`

#### SaveAvailabilityOverride
- `@throws ResourceNotFoundException`

#### CreateResource
- `@throws \InvalidArgumentException` — invalid type slug, empty name, or capacity < 1

#### GetResource
- `@throws ResourceNotFoundException`

#### UpdateResource
- `@throws ResourceNotFoundException`

#### DeleteResource
- `@throws ResourceNotFoundException`

#### ListResources
- No throws.

#### SeedDatabase
- `@throws \RuntimeException` — seed file cannot be read

#### Subscribe (Newsletter)
- No throws — idempotent, returns existing subscriber if email already subscribed.

#### Unsubscribe (Newsletter)
- No throws — silent success if email not found.

#### Broadcast (Newsletter)
- No throws — sends to all subscribers, no failure conditions in the use case itself.

---

### 7. Infrastructure mappers

#### `src/Infrastructure/Mapper/ReservationStatusMapper.php`
- `fromString(string $value): ReservationStatus` → `@throws \InvalidArgumentException`

#### `src/Infrastructure/Mapper/DayOfWeekMapper.php`
- `fromString(string $day): DayOfWeek` → `@throws \InvalidArgumentException`

#### `src/Infrastructure/Mapper/ResourceTypeMapper.php`
- `fromString(string $value): ResourceType` → `@throws \InvalidArgumentException` (propagated from `ResourceType::fromString()`)

#### `src/Infrastructure/Mapper/CurrencyMapper.php`
- `fromString(string $currency): Currency` → `@throws \InvalidArgumentException`

#### `src/Infrastructure/Mapper/SubscriberSourceMapper.php`
- `fromString(string $value): SubscriberSource` → `@throws \InvalidArgumentException`

---

### 8. Infrastructure repositories and seeder

#### `src/Infrastructure/Persistence/Mysql/MysqlReservationRepository.php`
- `findById()` → `@throws ReservationNotFoundException`

#### `src/Infrastructure/Persistence/Mysql/MysqlResourceRepository.php`
- `findById()` → `@throws ResourceNotFoundException`

#### `src/Infrastructure/Persistence/Mysql/MysqlNewsletterRepository.php`
- `findByEmail()` → `@throws NewsletterSubscriberNotFoundException`

#### `src/Infrastructure/Persistence/Mysql/MysqlDatabaseSeeder.php`
- `executeFile()` → `@throws \RuntimeException`

---

### 9. Future: DatabaseException (step 05)

After completing `05_rez-pdo-exceptions.md`, add `@throws DatabaseException` to:
- All port interface methods (`findById`, `findAll`, `save`, `delete`, `findByEmail`, etc.)
- All use case interface and implementation `execute()` methods that call a repository
- All MySQL repository public methods

---

## General rules

- PHPDoc comments go directly above the method signature, inside the class body
- One `@throws` tag per exception type
- Always use the fully-qualified class name (or a `use` import) — not bare string names
- Do not add `@throws` to constructors of exception classes themselves
- Do not add `@throws` for exceptions that are always caught internally before returning
- No test needed — static analysis (PHPStan max level) validates the contracts
