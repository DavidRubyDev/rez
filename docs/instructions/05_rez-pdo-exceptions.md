# Rez — PDO Exception Handling

PDO configured with `ERRMODE_EXCEPTION` throws `\PDOException` on connection failures,
query errors, and constraint violations. Currently these propagate uncaught through the
infrastructure layer — callers receive a raw `\PDOException` with no typed contract.

This step introduces `DatabaseException` to abstract the PDO dependency out of the
application surface, wraps all PDO calls in repositories, and ensures use cases
document (and where appropriate handle) the failure.

Run `composer ca` after each sub-step.

---

## Context

**Why Application layer?**
`DatabaseException` must live in `src/Application/Exception/` so that:
- Infrastructure repositories (which depend on Application) can throw it
- Use cases (also in Application) can document and catch it
- The Domain layer remains unaware of persistence concerns

**What callers should do with `DatabaseException`:**
Use cases do not attempt to recover from a database failure — there is no fallback.
They wrap the repository call in a try/catch and re-throw `DatabaseException` with
additional context (which operation failed). This makes the contract explicit and
ensures partial operations are not silently swallowed.

The HTTP layer in `rez-starter` maps `DatabaseException` → 503 Service Unavailable.

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. DatabaseException

`src/Application/Exception/DatabaseException.php`

```php
final class DatabaseException extends \RuntimeException
{
}
```

No custom constructor needed — use the standard `\RuntimeException` constructor
(`string $message = ''`, `int $code = 0`, `?\Throwable $previous = null`).

No test needed — it is a marker exception class with no logic.

---

### 2. Wrap PDO calls in MySQL repositories

For each repository, wrap every `$this->pdo->prepare()` + `$stmt->execute()` pair
(and any standalone `$pdo->exec()`) in a try/catch block:

```php
try {
    $stmt = $this->pdo->prepare('...');
    $stmt->execute([':param' => $value]);
} catch (\PDOException $e) {
    throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
}
```

Files to update:

- `src/Infrastructure/Persistence/Mysql/MysqlReservationRepository.php`
  Wrap: `findById`, `findByTimeSlotAndResource`, `findAll`, `save`

- `src/Infrastructure/Persistence/Mysql/MysqlResourceRepository.php`
  Wrap: `findById`, `findAll`, `save`, `delete`

- `src/Infrastructure/Persistence/Mysql/MysqlAvailabilityRepository.php`
  Wrap: `findRulesForResource`, `findOverridesForResource`, `saveRule`, `saveOverride`

- `src/Infrastructure/Persistence/Mysql/MysqlNewsletterRepository.php`
  Wrap: `findByEmail`, `findAll`, `save`, `delete`

- `src/Infrastructure/Persistence/Mysql/MysqlDatabaseSeeder.php`
  Wrap: the `$pdo->exec($statement)` call inside `executeFile()`

**Note:** `fetch()` and `fetchAll()` after a successful `execute()` do not need a separate
try/catch — failures at that point are hydration errors, not connection errors, and are
already guarded by the `MysqlRepository` type-narrowing helpers (`str()`, `int()`, etc.)
which throw `\UnexpectedValueException`.

---

### 3. Add @throws DatabaseException to port interfaces

Update `src/Application/Port/`:

- `ReservationRepositoryInterface` — all four methods
- `ResourceRepositoryInterface` — all four methods
- `AvailabilityRepositoryInterface` — all four methods
- `NewsletterRepositoryInterface` — all four methods
- `DatabaseSeederInterface` — `executeFile()`

---

### 4. Handle DatabaseException in use cases

Each use case that calls a repository must wrap its repository calls in try/catch,
catch `DatabaseException`, and re-throw with a context message.

**Pattern:**

```php
try {
    $reservation = $this->reservationRepository->findById($request->reservationId);
} catch (DatabaseException $e) {
    throw new DatabaseException('Failed to load reservation.', 0, $e);
}
```

Apply to every use case `execute()` method that calls at least one repository method:

- `CreateReservationUseCase`
- `CancelReservationUseCase`
- `ConfirmReservationUseCase`
- `MarkNoShowUseCase`
- `GetReservationUseCase`
- `ListReservationsUseCase`
- `GetAvailabilityUseCase`
- `SaveAvailabilityRuleUseCase`
- `SaveAvailabilityOverrideUseCase`
- `CreateResourceUseCase`
- `GetResourceUseCase`
- `UpdateResourceUseCase`
- `DeleteResourceUseCase`
- `ListResourcesUseCase`
- `SeedDatabaseUseCase`
- `SubscribeUseCase`
- `UnsubscribeUseCase`
- `BroadcastUseCase`

**Context message convention:** `"Failed to {verb} {entity}."` — e.g.:
- `"Failed to load reservation."`
- `"Failed to save reservation."`
- `"Failed to delete resource."`
- `"Failed to load newsletter subscribers."`

If a use case performs multiple repository calls, each call gets its own try/catch with
its own context message.

---

### 5. Add @throws DatabaseException to use case interfaces

Add `@throws DatabaseException` to the `execute()` signature in every `*UseCaseInterface`
that calls a repository (same list as step 4).

Add it to the concrete `execute()` implementations as well (must stay in sync).

---

### 6. Update rez-starter exception mapping

Add `DatabaseException` → 503 to the error middleware in `rez-starter`:

```php
if ($exception instanceof DatabaseException) {
    return $response->withStatus(503);
}
```

This is in `rez-starter`, not in `rez` — document in a comment in `container.php` or
`docs/REZ-CONTEXT.md` that callers should map `DatabaseException` to 503.

---

## Tests

- No new unit tests for the exception class itself (marker class, no logic)
- Existing use case tests: update mocks that call repository methods to also be capable
  of simulating `DatabaseException` — add one test per use case:
  `testRepositoryDatabaseExceptionPropagates` — mock throws `DatabaseException`,
  assert the use case re-throws `DatabaseException`
- Infrastructure tests: the existing integration tests will exercise the real PDO path;
  connection-failure scenarios are not unit-testable without PDO mocking (out of scope)
