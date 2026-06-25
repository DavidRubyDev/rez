# Rez — Testing Fixes

Bug fixes and small improvements discovered during local API testing. All changes are in
`davidrubydev/rez` unless a section explicitly says `rez-starter`.

Complete `rez-pdo-exceptions` before starting this.

Run `composer ca` after completing all changes and fix any issues before committing.

---

## 1. Fix `created_at` timezone — always store and return UTC

**Affected files:** any domain entity or value object that sets `created_at`, and any
MySQL repository that reads or writes timestamp columns.

All `DateTimeImmutable` instances created inside `rez` must use UTC explicitly:

```php
new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
```

Never rely on the server's default timezone. Never rely on `new \DateTimeImmutable('now')`
without an explicit timezone.

**In repositories:** when hydrating entities from DB rows, construct timestamps as:

```php
new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC'))
```

**In MySQL:** ensure timestamp columns are stored as `DATETIME` (not `TIMESTAMP` — MySQL
`TIMESTAMP` converts to server local time on write). If columns are already `DATETIME`,
no schema change needed — just ensure PHP writes UTC strings.

Verify fix: create a reservation, read it back, assert `created_at` is UTC (ends in `+00:00`
or `Z` when serialised to ISO 8601).

---

## 2. Fix cancelled reservations blocking availability

**Affected file:** `src/Infrastructure/Repository/MysqlReservationRepository.php` (or
wherever the query that counts existing reservations for a slot lives).

The availability check queries reservations to determine if a slot is full. Currently it
counts all reservations regardless of status, including `Cancelled` ones. A cancelled
reservation must not consume capacity.

Find the query used by `AvailabilityService` (via the repository) to count overlapping
reservations for a time slot. Add a `WHERE status != 'cancelled'` filter (or equivalent
using the status enum value that maps to the cancelled string).

Also check `ListReservationsUseCase` — if it accepts a status filter, verify `Cancelled`
reservations are still returned when explicitly requested. The fix is scoped to the
capacity-counting query only, not to general listing.

Write or update an integration test:
- Create a reservation for a slot on a single-capacity resource
- Cancel it
- Assert the slot is now available again (availability query returns it as bookable)

---

## 3. Fix DB-down returning 500 instead of 503

**Affected file:** `config/container.php` in `rez-starter`.

The `rez-pdo-exceptions` scaffold wrapped PDO calls inside repository methods. But if the
database is unreachable at boot time, the PDO constructor itself throws a
`\PDOException` before any repository method is called — and this is not caught.

Wrap the PDO construction in the container binding in a try/catch:

```php
PDO::class => function () {
    try {
        $pdo = new PDO(
            dsn: $_ENV['DB_DSN'],
            username: $_ENV['DB_USER'],
            password: $_ENV['DB_PASS'],
            options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        return $pdo;
    } catch (\PDOException $e) {
        throw new \Davidrubydev\Rez\Infrastructure\Exception\DatabaseException(
            'Database connection failed: ' . $e->getMessage(),
            previous: $e,
        );
    }
},
```

The existing error middleware already maps `DatabaseException` → 503. No middleware change
needed.

Verify fix: stop the MySQL container, make any API request, assert HTTP 503 is returned.

---

## 4. Seed directory split — schema-only vs full

**Goal:** running the seed script with no arguments creates tables only. Running it with
`--fill` also inserts data rows.

### 4a. Reorganise seed files in `rez-starter` / `rez-demo`

```
seeds/
  schema/     — 00_schema.sql   (CREATE TABLE statements only, no INSERT)
  data/       — 01_*.sql, 02_*.sql, ...  (INSERT statements)
```

Move `00_schema.sql` into `seeds/schema/`. Move all data SQL files into `seeds/data/`.
Update any hardcoded paths that reference the old locations.

### 4b. Update the seed CLI script

`bin/seed.php` (or equivalent entry point in `rez-starter`):

```php
$fill = in_array('--fill', $argv, true);

$directories = [MysqlDatabaseSeeder::seedsPath()]; // rez library schema seeds

// Always add client schema directory
$directories[] = __DIR__ . '/../seeds/schema';

// Add data directory only when --fill is passed
if ($fill) {
    $directories[] = __DIR__ . '/../seeds/data';
}

$useCase->execute(new SeedDatabaseRequest($directories));
```

Running `php bin/seed.php` — schema only.
Running `php bin/seed.php --fill` — schema + data.

### 4c. Update `docker-compose.yml` / Makefile targets if they reference seed commands

Ensure any `make seed` or similar target passes `--fill` explicitly so existing workflows
are not broken.

---

## 5. `ReservationsConfig` — autoConfirm flag

**Goal:** allow each client deployment to configure whether reservations are confirmed
automatically on creation, or left in `Pending` state for manual confirmation.

### 5a. Create `ReservationsConfig`

`src/Application/Config/ReservationsConfig.php`

```php
final class ReservationsConfig
{
    public function __construct(
        public readonly bool $autoConfirm = false,
    ) {}
}
```

No validation needed — `bool` cannot be invalid.

### 5b. Add to `PlatformConfig`

`src/Application/Config/PlatformConfig.php`

Add `ReservationsConfig` as a required constructor parameter alongside `MailerConfig` and
`UsersConfig`:

```php
public function __construct(
    private readonly MailerConfig $mailerConfig,
    private readonly UsersConfig $usersConfig,
    private readonly ReservationsConfig $reservationsConfig,
    private readonly ?PaymentsConfig $paymentsConfig = null,
    ...
)
```

Add accessor: `public function getReservationsConfig(): ReservationsConfig`

### 5c. Update `CreateReservationUseCase`

`src/Application/UseCase/CreateReservation/CreateReservationUseCase.php`

After saving the reservation, check `$this->config->getReservationsConfig()->autoConfirm`.
If true, immediately call the same transition logic used by `ConfirmReservationUseCase` to
move status from `Pending` to `Confirmed` before returning.

Do not call `ConfirmReservationUseCase` directly from inside `CreateReservationUseCase` —
extract the shared state transition into a private method or a domain method on `Reservation`
that both use cases call. Avoid use-case-calling-use-case patterns.

### 5d. Update `CreateReservationResponse`

Ensure `status` is included in the response so the caller knows whether the reservation
came back `Pending` or `Confirmed`.

### 5e. Wire in `rez-starter` container

`config/container.php` — construct `ReservationsConfig` from env:

```php
ReservationsConfig::class => fn() => new ReservationsConfig(
    autoConfirm: (bool) ($_ENV['RESERVATIONS_AUTO_CONFIRM'] ?? false),
),
```

Add `RESERVATIONS_AUTO_CONFIRM=false` to `.env.example`.

### 5f. Tests

- `testAutoConfirmFalseLeavesPending` — create reservation with `autoConfirm: false`, assert status is `Pending`
- `testAutoConfirmTrueConfirmsImmediately` — create reservation with `autoConfirm: true`, assert status is `Confirmed`
- Update any existing `CreateReservationUseCase` tests that construct `PlatformConfig` to pass `ReservationsConfig`

---

## Checklist

- [ ] 1. All `DateTimeImmutable` instances in `rez` use explicit UTC timezone
- [ ] 2. Availability capacity query filters out `Cancelled` reservations
- [ ] 3. Integration test: cancelled reservation frees its slot
- [ ] 4. PDO constructor wrapped in `rez-starter` container — throws `DatabaseException`
- [ ] 5. `seeds/schema/` and `seeds/data/` directories created, files moved
- [ ] 6. Seed CLI script respects `--fill` flag
- [ ] 7. `ReservationsConfig` created with `autoConfirm: bool`
- [ ] 8. `PlatformConfig` updated — `ReservationsConfig` required
- [ ] 9. `CreateReservationUseCase` respects `autoConfirm`
- [ ] 10. `CreateReservationResponse` includes `status`
- [ ] 11. `rez-starter` container wires `ReservationsConfig` from env
- [ ] 12. All new and existing tests pass, `composer ca` clean
