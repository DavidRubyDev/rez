# Scaffold: rez-guest-cancellation

## Prerequisites
- `rez-config-update` complete (`UsersConfig` required, `cancellationSecret` present)
- `rez-mailer-newsletter` complete (`MailerInterface` wired, confirmation email exists)
- `rez-email-restructure` complete (`MailerInterface` now exposes
  `sendReservationCreatedEmail(Reservation, CancellationToken)`,
  `sendReservationConfirmedEmail(Reservation, CancellationToken)`, and
  `sendReservationCancelledEmail(Reservation)` — no more single confirmation method,
  no more `string $cancellationUrl` parameter. Sections 1 and 6 below are superseded —
  read the notes inline before doing any work in this file.)

## Goal

Allow a guest who made a reservation to cancel it by clicking a link in their confirmation
email. The link carries a stateless HMAC-SHA256 token — no new DB column, no stored token.
Verification is pure computation. Admin cancellation is unchanged and requires no token.

---

## 1. `CancellationToken` value object — ALREADY BUILT, no action needed

**Superseded by `rez-email-restructure`.** `src/Domain/Shared/CancellationToken.php` was
created early because the restructured `MailerInterface` needed the type to exist for its
method signatures. It matches the spec below exactly. `tests/Domain/Shared/CancellationTokenTest.php`
already covers generate/verify/fromString round-trips. Skip this section — nothing to do here.

Public interface (as built):

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

## 5. `MailerConfig` gains `cancellationBaseUrl`

`src/Application/Config/MailerConfig.php` — add:
```php
public readonly string $cancellationBaseUrl,
```

Validated as a non-empty string. No URL format validation — keep it simple.

Under the restructured `MailerInterface` (see `rez-email-restructure`), the port takes the
`CancellationToken` object itself, not a pre-built URL string — `sendReservationCreatedEmail`
and `sendReservationConfirmedEmail` both accept `(Reservation $reservation, CancellationToken
$cancellationToken)`. Building the URL string
(`{cancellationBaseUrl}?reservation={reservationId}&token={hmacToken}`) is therefore the
concrete mailer implementation's job (e.g. `rez-starter`'s `SymfonyMailer`), which has
`MailerConfig` injected and can read `cancellationBaseUrl` directly. The library only hands
the implementation the reservation and the token — it does not build or pass a URL string.

**Do not** reintroduce a `string $cancellationUrl` parameter on `MailerInterface` — that
shape was superseded by `rez-email-restructure`.

---

## 6. Wiring `sendReservationCreatedEmail` / `sendReservationConfirmedEmail` — out of scope here

Calling the new `MailerInterface` methods from `CreateReservationUseCase` (generating a
`CancellationToken::generate($reservation->id, $usersConfig->cancellationSecret)` and
choosing created-vs-confirmed based on `$reservation->status`, driven by `autoConfirm` —
now read from `ReservationSettingsRepositoryInterface::get()`, not `PlatformConfig`; see
`rez-reservation-settings`) was deliberately removed from
`CreateReservationUseCase` by `rez-email-restructure` and is **not** this scaffold's job either.
That wiring — plus whatever settings-gating governs it — belongs to the not-yet-scaffolded
`rez-lifecycle-email-integration`. This scaffold's job is limited to guest-side cancellation
token verification (`CancelReservationUseCase`, sections 3–4 above) and making
`cancellationBaseUrl` available (section 5 above) for that future wiring to use.

---

## 7. Update `MailerConfig` in `rez-starter` container

`config/container.php`

Wire `cancellationBaseUrl` from an env var (e.g. `CANCELLATION_BASE_URL`). Example value
for local development: `http://localhost/zrusit-rezervaci`.

---

## 8. Tests

### `CancellationToken` — already covered
`tests/Domain/Shared/CancellationTokenTest.php` already exists (built with the value object
in `rez-email-restructure`). No new tests needed here.

### Unit tests for `CancelReservationUseCase`
- Admin path (no token) cancels successfully
- Guest path with valid token cancels successfully
- Guest path with invalid token throws `InvalidTokenException`
- Guest path with tampered token throws `InvalidTokenException`
- Existing admin-path tests still pass

### `CreateReservationUseCase` mailer wiring — not this scaffold
Deferred to `rez-lifecycle-email-integration` (see section 6 above). No tests for it here.

---

## Checklist

- [x] 1. `CancellationToken` value object created (done in `rez-email-restructure`)
- [ ] 2. `InvalidTokenException` created
- [ ] 3. `CancelReservationRequest` gains optional `cancellationToken`
- [ ] 4. `CancelReservationUseCase` updated — two paths, shared cancellation logic
- [ ] 5. `MailerConfig` gains `cancellationBaseUrl`
- [x] 6. `MailerInterface` reservation-email shape already updated (done in `rez-email-restructure`
      — takes `CancellationToken`, not a `cancellationUrl` string; no further change needed here)
- [ ] 7. `rez-starter` `SymfonyMailer` stub implements the three-method shape and builds the
      cancellation URL itself from `MailerConfig::cancellationBaseUrl` (wiring the calls from
      `CreateReservationUseCase` is `rez-lifecycle-email-integration`'s job, not this scaffold's)
- [ ] 8. `rez-starter` container wires `cancellationBaseUrl` from env
- [ ] 9. All new and existing tests pass
