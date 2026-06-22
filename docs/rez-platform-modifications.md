# Rez — Core Changes for Platform Readiness

These changes prepare `davidrubydev/rez` to support `rez-platform`.
All changes are backwards-compatible except where noted.
Complete all steps in order. Run `vendor/bin/phpunit` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

`rez-platform` needs to link reservations back to authenticated users.
The solution is an opaque `externalRef` field on `Party` — a nullable string the library stores and returns but never interprets.
`rez-platform` will populate it with a `UserId`. Guest bookings leave it null.

The seeder also needs a small interface change to support multiple seed directories
so `rez-platform` and client repos can contribute their own SQL files.

---

## Changes

---

### 1. Party — add `externalRef` + PartyTest

`src/Domain/Reservation/Party.php`

Add a fifth constructor parameter:

```
?string $externalRef = null
```

- Nullable, no validation — any non-empty string is valid, null is valid
- Position: after `?string $phone`

`tests/Domain/Reservation/PartyTest.php`

Add two new cases:

- `testNullExternalRefIsAccepted` — construct with explicit `externalRef: null`, assert `$externalRef` returns null
- `testExternalRefIsStoredAndReturned` — construct with `externalRef: 'some-uuid'`, assert `$externalRef` returns `'some-uuid'`

Existing 6 cases must continue to pass unchanged (all existing callers pass no fifth argument, so default null applies).

---

### 2. Database schema — add `external_ref` column

`database/seeds/000_schema.sql`

Add column to `reservations` table definition:

```sql
external_ref VARCHAR(255) NULL
```

Position: after `party_phone VARCHAR(50) NULL`.

---

### 3. MysqlReservationRepository — persist and hydrate `external_ref`

`src/Infrastructure/Persistence/Mysql/MysqlReservationRepository.php`

**save():**
Add `external_ref` to the INSERT/ON DUPLICATE KEY UPDATE statement.
Value: `$reservation->party->externalRef` — may be null, pass as PDO param directly.

**hydration (reconstruct from row):**
Pass `$row['external_ref']` (nullable string) as the `externalRef` argument when constructing `Party`.
Use `$this->nullStr($row['external_ref'])` via the existing base class helper.

**findByTimeSlotAndResource() and findAll():**
No query changes needed — `external_ref` is returned by `SELECT *` already once the column exists.

`tests/Integration/Persistence/Mysql/MysqlReservationRepositoryTest.php`

Add one new integration test:

- `testExternalRefIsPersistedAndHydrated` — create a `Party` with `externalRef: 'test-user-id'`, save a reservation, reload via `findById()`, assert `party->externalRef === 'test-user-id'`
- Also assert that a reservation with null `externalRef` roundtrips correctly (null in, null out)

---

### 4. ReservationSerializer — include `external_ref` in output

`src/Handler/ReservationSerializer.php`

Add `external_ref` to the serialized array:

```php
'external_ref' => $reservation->party->externalRef,
```

Type: `?string`. Position: after `party` block or alongside other party fields — be consistent with existing shape.

Update `docs/openapi.yaml`:

Add `external_ref` to the `Party` schema component:

```yaml
external_ref:
  type: string
  nullable: true
  description: Opaque reference set by the caller. Used by rez-platform to link reservations to user accounts.
```

No handler tests need updating — serializer output is already covered by existing handler tests; add `external_ref: null` assertion to one existing handler test to lock the shape.

---

### 5. SeedDatabaseRequest — accept multiple directories

`src/Application/UseCase/Seed/SeedDatabase/SeedDatabaseRequest.php`

Change constructor from:

```php
public function __construct(
    public readonly string $seedsDirectory,
)
```

To:

```php
public function __construct(
    public readonly array $seedsDirectories,  // string[]
)
```

**Breaking change** — all callers must be updated (see steps 6 and 7).

---

### 6. SeedDatabaseUseCase — iterate multiple directories

`src/Application/UseCase/Seed/SeedDatabase/SeedDatabaseUseCase.php`

Current logic: glob `*.sql` from one directory, sort, execute each.

New logic:
1. Iterate `$request->seedsDirectories` in order
2. For each directory, glob `*.sql` files
3. Sort files within each directory by filename
4. Execute all files across all directories in that order
5. Return total count of files executed across all directories

Ordering guarantee: files within a single directory are sorted by filename. Directories are processed in the order they appear in the array. This means cross-directory ordering is controlled by the caller — the client repo puts rez seeds first, platform seeds second.

`tests/Application/UseCase/Seed/SeedDatabase/SeedDatabaseUseCaseTest.php`

Update all existing tests to pass `seedsDirectories: ['/some/path']` (array wrapping the previous string).

Add two new cases:

- `testExecutesMultipleDirectoriesInOrder` — two directories, each with one SQL file; assert both files executed, directory-1 file before directory-2 file
- `testEmptyDirectoryInListIsSkipped` — one valid directory, one empty directory; assert only valid files executed, no error thrown

---

### 7. bin/seed.php — update to array syntax

`bin/seed.php`

Update `SeedDatabaseRequest` instantiation from:

```php
new SeedDatabaseRequest(seedsDirectory: __DIR__ . '/../database/seeds')
```

To:

```php
new SeedDatabaseRequest(seedsDirectories: [__DIR__ . '/../database/seeds'])
```

No other changes to `bin/seed.php`.

---

### 8. Seed directory naming convention — document and enforce

`database/seeds/` filenames must follow this convention going forward:

| Range | Owner |
|-------|-------|
| `000` – `099` | `davidrubydev/rez` |
| `100` – `199` | `davidrubydev/rez-platform` |
| `200`+ | Client repo |

Rename existing seed files to confirm to this:

- `000_schema.sql` → stays (already in range)
- `001_resources.sql` → stays
- `002_availability_rules.sql` → stays
- `003_availability_overrides.sql` → stays
- `004_reservations.sql` → stays

No renames needed — all existing files are already in the `000`–`099` range.

Document this convention in `database/seeds/README.md`:

```markdown
# Seed directory conventions

Files are executed in filename order within each directory.
To avoid conflicts across packages, use the following numeric prefix ranges:

- 000–099: davidrubydev/rez
- 100–199: davidrubydev/rez-platform
- 200+:    client repo

Run seeds via `bin/seed.php` or by calling `SeedDatabaseUseCase`
with an ordered array of seed directories.
```

---

### 9. Expose seeds path via static method

`src/Infrastructure/Persistence/Mysql/MysqlDatabaseSeeder.php`

Add one static method:

```php
public static function seedsPath(): string
{
    return dirname(__DIR__, 4) . '/database/seeds';
}
```

This allows `rez-platform` and client repos to reference the path without hardcoding vendor paths:

```php
// In client repo bin/seed.php
$seedsDirectories = [
    \Rez\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder::seedsPath(),
    \RezPlatform\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder::seedsPath(),
    __DIR__ . '/../database/seeds',
];
```

No test needed — trivial path computation.

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all 206 existing tests must continue to pass
- Run `vendor/bin/phpstan analyse` after each step — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` after each step — zero violations
- Do not modify any file outside `rez/` (no changes to `examples/slim/`)
- The `examples/slim/` Slim adapter does not need updating — it passes a single directory to the seeder and that still works once step 6 accepts arrays

---

## Checklist

- [ ] 1. Party `externalRef` field + 2 new tests
- [ ] 2. Schema `external_ref` column + migration file
- [ ] 3. MysqlReservationRepository persist + hydrate + 2 integration tests
- [ ] 4. ReservationSerializer output + OpenAPI update
- [ ] 5. SeedDatabaseRequest array change
- [ ] 6. SeedDatabaseUseCase multi-directory + 2 new tests + existing tests updated
- [ ] 7. bin/seed.php updated
- [ ] 8. README.md naming convention documented
- [ ] 9. MysqlDatabaseSeeder::seedsPath() static method

Total new tests: 8 (2 Party, 2 integration, 2 seeder use case, existing seeder tests updated)
Expected final count: ~214 unit tests (206 + 8), 17+ integration tests