# Scaffold: rez-guest-cancellation

## Prerequisites
- `rez-config-update` complete (`UsersConfig` required, `cancellationSecret` present)
- `rez-mailer-newsletter` complete (`MailerInterface` wired, confirmation email exists)

## Goal

Allow a guest who made a reservation to cancel it by clicking a link in their confirmation
email. The link carries a stateless HMAC-SHA256 token — no new DB column, no stored token.
Verification is pure computation. Admin cancellation is unchanged and requires no token.

---

## 1. `CancellationToken` value object

`src/Domain/Shared/CancellationToken.php`

A stateless value object. Never stored. Generated from a `ReservationId` and the
`cancellationSecret` from `UsersConfig`.

```
HMAC = hash_hmac('sha256', reservationId, cancellationSecret)
```

Public interface:

```php
final class CancellationToken
{
    private function __construct(private readonly string $value) {}

    public static function generate(ReservationId $id, string $secret): self;
    public static function fromString(string $token): self;
    public function verify(ReservationId $id, string $secret): bool;
    public function toString(): string;
}
```

`generate()` produces a new token using `hash_hmac('sha256', $id->toString(), $secret)`.

`fromString()` wraps a raw string (received from the HTTP layer) without validation — the
value is untrusted until `verify()` is called.

`verify()` recomputes the expected HMAC and compares using `hash_equals()` (timing-safe).
Returns `bool` — never throws.

No other logic lives here. The token does not know about expiry, HTTP, or databases.

---

## 2. `InvalidTokenException`

`src/Domain/Shared/Exception/InvalidTokenException.php`

A domain exception (extends `\DomainException`). Thrown by `CancelReservationUseCase` when
a guest provides a token that fails HMAC verification.

Already mapped to HTTP 401 in `rez-starter` error middleware (documented in REZ-CONTEXT.md
exception table). No changes needed in `rez-starter` for the mapping — it already exists.

---

## 3. Update `CancelReservationRequest`

`src/Application/UseCase/CancelReservation/CancelReservationRequest.php`

Add an optional `?string $cancellationToken` field. The field is nullable — null means admin
cancellation (no token required). A non-null value triggers guest verification.

```php
public function __construct(
    public readonly string $reservationId,
    public readonly ?string $cancellationToken = null,
)
```

---

## 4. Update `CancelReservationUseCase`

`src/Application/UseCase/CancelReservation/CancelReservationUseCase.php`

The use case gains a dependency on `UsersConfig` (to read `cancellationSecret`).

Two paths inside `execute()`:

**Admin path** (`$request->cancellationToken === null`):
- Load reservation by id
- Cancel it
- No token check

**Guest path** (`$request->cancellationToken !== null`):
- Load reservation by id
- Construct `CancellationToken::fromString($request->cancellationToken)`
- Call `$token->verify($reservation->id(), $this->usersConfig->cancellationSecret)`
- If `false` → throw `InvalidTokenException`
- Cancel it

Both paths call the same internal cancellation logic after their respective auth checks.
Extract that logic into a private method to avoid duplication.

The use case must not know whether the caller is a browser, CLI, or test. It only sees
the request object.

---

## 5. Update `CreateReservationUseCase` — emit cancellation URL

`src/Application/UseCase/CreateReservation/CreateReservationUseCase.php`

After saving the reservation, generate a `CancellationToken` and include the cancellation
URL in the confirmation email.

The URL is built from a `cancellationBaseUrl` string that must come from outside the library.
Add it to `MailerConfig` as a required field:

`src/Application/Config/MailerConfig.php` — add:
```php
public readonly string $cancellationBaseUrl,
```

Validated as a non-empty string. No URL format validation — keep it simple.

The use case builds the cancellation URL as:
```
{cancellationBaseUrl}?reservation={reservationId}&token={hmacToken}
```

Pass this URL to whatever mailer method sends the confirmation email. The mailer
implementation in `rez-starter` is responsible for embedding it in the email body — the
library only provides the string.

---

## 6. Update `MailerInterface`

`src/Application/Port/MailerInterface.php`

The confirmation email method signature must accept the cancellation URL. Update it to:

```php
public function sendReservationConfirmation(
    Reservation $reservation,
    string $cancellationUrl,
): void;
```

Update the `SymfonyMailer` stub in `rez-starter` to accept and embed the `$cancellationUrl`
in the email body (plain text is fine for now — proper HTML template comes later).

---

## 7. Update `MailerConfig` in `rez-starter` container

`config/container.php`

Wire `cancellationBaseUrl` from an env var (e.g. `CANCELLATION_BASE_URL`). Example value
for local development: `http://localhost/zrusit-rezervaci`.

---

## 8. Tests

### Unit tests for `CancellationToken`
- `generate()` returns a token whose `verify()` returns true with the same id and secret
- `verify()` returns false with a different id
- `verify()` returns false with a different secret
- `verify()` returns false with a tampered token string
- `fromString()` + `verify()` round-trips correctly

### Unit tests for `CancelReservationUseCase`
- Admin path (no token) cancels successfully
- Guest path with valid token cancels successfully
- Guest path with invalid token throws `InvalidTokenException`
- Guest path with tampered token throws `InvalidTokenException`
- Existing admin-path tests still pass

### Unit tests for `CreateReservationUseCase`
- Mailer is called with a non-empty `cancellationUrl` string
- The URL contains the `reservationId` and a non-empty token segment

---

## Checklist

- [ ] 1. `CancellationToken` value object created
- [ ] 2. `InvalidTokenException` created
- [ ] 3. `CancelReservationRequest` gains optional `cancellationToken`
- [ ] 4. `CancelReservationUseCase` updated — two paths, shared cancellation logic
- [ ] 5. `MailerConfig` gains `cancellationBaseUrl`
- [ ] 6. `MailerInterface::sendReservationConfirmation()` updated to accept `cancellationUrl`
- [ ] 7. `CreateReservationUseCase` generates token and passes URL to mailer
- [ ] 8. `rez-starter` `SymfonyMailer` stub updated to accept and embed the URL
- [ ] 9. `rez-starter` container wires `cancellationBaseUrl` from env
- [ ] 10. All new and existing tests pass
