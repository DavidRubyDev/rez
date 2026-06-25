# Rez — PSR-3 Logging (rez core)

Adds PSR-3 `LoggerInterface` support to the `rez` library. The library emits log events at
appropriate points but owns no logging infrastructure — the concrete logger (Monolog) is
wired in `rez-starter`. If no logger is provided, a `NullLogger` is used and nothing is
written.

Complete `rez-testing-fixes` before starting this.

Run `composer ca` after completing all changes and fix any issues before committing.

---

## 1. Add `psr/log` dependency

`composer.json`

```json
"require": {
    "psr/log": "^3.0"
}
```

Run `composer update`. Do not add Monolog here — it belongs in `rez-starter`.

---

## 2. Inject `LoggerInterface` into use cases and services

The logger is optional infrastructure. Inject it via constructor in every class that needs
to log, with a `NullLogger` default so existing tests require no changes.

Classes that receive the logger:

| Class | Why |
|---|---|
| `CreateReservationUseCase` | Log email send failures (mailer exception caught and swallowed) |
| `CancelReservationUseCase` | Log HMAC token verification failures; log email failures |
| `BroadcastUseCase` | Log per-recipient send failures |
| `AvailabilityService` | No logging needed |
| All MySQL repositories | Log `DatabaseException` before re-throwing (if not already logged by caller) |

Constructor pattern for all affected classes:

```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

public function __construct(
    // ... existing dependencies ...
    private readonly LoggerInterface $logger = new NullLogger(),
)
```

PHP 8.1+ supports `new` in initializers — use it. No factory or optional parameter hacks.

---

## 3. Log points — what to log and at what level

### `CreateReservationUseCase`

After the reservation is saved and the mailer call is attempted:

```php
try {
    $this->mailer->sendReservationConfirmation($reservation, $cancellationUrl);
} catch (\Throwable $e) {
    $this->logger->error('Failed to send reservation confirmation email', [
        'reservationId' => $reservation->id()->toString(),
        'email'         => $reservation->party()->email(),
        'error'         => $e->getMessage(),
    ]);
}
```

Level: `error`. The booking succeeded — the email failure is an infrastructure problem, not
a domain failure. Never rethrow.

### `CancelReservationUseCase` — HMAC verification failure

When `$token->verify()` returns false (guest path):

```php
$this->logger->warning('Guest cancellation token verification failed', [
    'reservationId' => $reservationId,
]);
```

Level: `warning`. Not an error — could be a user error, an expired link, or a probing
attempt. Do not log the token value itself.

### `CancelReservationUseCase` — email failure

Same pattern as `CreateReservationUseCase`. Level: `error`.

### `BroadcastUseCase`

Per-recipient send failure (inside the loop, already caught):

```php
$this->logger->error('Failed to send broadcast email to subscriber', [
    'subscriberId' => $subscriber->id()->toString(),
    'email'        => $subscriber->email(),
    'error'        => $e->getMessage(),
]);
```

Level: `error`.

### MySQL repositories — `DatabaseException`

Repositories already catch `\PDOException` and re-throw as `DatabaseException`. Before
re-throwing, log at `critical` level — a database failure is infrastructure-down, not
a recoverable error:

```php
} catch (\PDOException $e) {
    $this->logger->critical('Database query failed', [
        'operation' => __METHOD__,
        'error'     => $e->getMessage(),
    ]);
    throw new DatabaseException('...', previous: $e);
}
```

Do not log the full SQL query — it may contain user data.

---

## 4. Log context conventions

All log entries must include:
- A human-readable message string (first argument)
- A context array (second argument) with structured key-value pairs

Never log:
- Raw tokens, secrets, or HMAC values
- Full SQL queries
- Passwords or password hashes
- Full stack traces in the context array (the logger handler in `rez-starter` adds those)

Use consistent key names across all log points:
- `reservationId` — not `id`, not `reservation_id`
- `email` — masked if possible (`***@domain.com`), but plain is acceptable for now
- `error` — `$e->getMessage()` only, not the full exception
- `operation` — `__METHOD__` for repository failures

---

## 5. Tests

Existing tests use a `NullLogger` default — no changes needed for them to pass.

Add focused tests for each log point:

### `CreateReservationUseCase` — email failure logged

```php
$logger = $this->createMock(LoggerInterface::class);
$logger->expects($this->once())
    ->method('error')
    ->with(
        $this->stringContains('confirmation email'),
        $this->arrayHasKey('reservationId'),
    );

// wire a mailer that throws, inject $logger, execute use case
// assert reservation was still created (exception was swallowed)
```

### `CancelReservationUseCase` — bad token logged as warning

```php
$logger->expects($this->once())
    ->method('warning')
    ->with(
        $this->stringContains('token verification failed'),
        $this->arrayHasKey('reservationId'),
    );
```

### Repository — `DatabaseException` logged as critical

```php
$logger->expects($this->once())
    ->method('critical')
    ->with(
        $this->stringContains('Database query failed'),
        $this->arrayHasKey('operation'),
    );
```

---

## Checklist

- [ ] 1. `psr/log` added to `composer.json`, `composer update` run
- [ ] 2. `LoggerInterface` injected into `CreateReservationUseCase` (NullLogger default)
- [ ] 3. `LoggerInterface` injected into `CancelReservationUseCase` (NullLogger default)
- [ ] 4. `LoggerInterface` injected into `BroadcastUseCase` (NullLogger default)
- [ ] 5. `LoggerInterface` injected into all MySQL repositories (NullLogger default)
- [ ] 6. All log points implemented at correct levels with correct context keys
- [ ] 7. No secrets, tokens, or SQL queries in log context
- [ ] 8. Log point tests added and passing
- [ ] 9. All existing tests still pass without modification (`composer ca` clean)
