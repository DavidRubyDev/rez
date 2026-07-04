# Scaffold: rez-guest-cancellation

## Prerequisites
- `rez-config-update` complete (`UsersConfig` required, `cancellationSecret` present) — **note:**
  `cancellationSecret` briefly lived on `MailerConfig` (`rez-lifecycle-email-integration`, ahead
  of `rez-config-update`) but has since been migrated onto `UsersConfig` by `rez-config-update`
  itself, per its own "migrate" decision — matching this scaffold's original plan. Sections 4–6
  below have been updated to read `UsersConfig->cancellationSecret`, not `MailerConfig`.
- `rez-mailer-newsletter` complete (`MailerInterface` wired, confirmation email exists)
- `rez-email-restructure` complete (`MailerInterface` now exposes
  `sendReservationCreatedEmail(Reservation, CancellationToken)`,
  `sendReservationConfirmedEmail(Reservation, CancellationToken)`, and
  `sendReservationCancelledEmail(Reservation)` — no more single confirmation method,
  no more `string $cancellationUrl` parameter. Sections 1 and 6 below are superseded —
  read the notes inline before doing any work in this file.)
- `rez-reservation-settings` complete (DB-backed `ReservationSettings`/
  `ReservationSettingsRepositoryInterface`, replacing `ReservationsConfig`)
- `rez-lifecycle-email-integration` complete (ad hoc, no `docs/instructions/NN_*` file —
  see `docs/CONTEXT.md`). **This ran section 6's wiring already** — `ReservationEmailService`
  and settings-gated sends in `CreateReservationUseCase`/`ConfirmReservationUseCase`/
  `CancelReservationUseCase` all exist now (the secret they use for `CancellationToken`
  generation now reads from `UsersConfig::cancellationSecret`, not `MailerConfig` — see the
  `rez-config-update` note above). Section 6 below is superseded — read its inline note before
  doing any work in this file.

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

`src/Application/UseCase/Reservation/CancelReservation/CancelReservationUseCase.php`

**Updated for current reality:** as of `rez-lifecycle-email-integration`, this use case already
has constructor deps on `ReservationSettingsRepositoryInterface` and `ReservationEmailService`
(for the settings-gated `sendReservationCancelledEmail`, fired unconditionally after save —
see that scaffold). Add a `UsersConfig $usersConfig` dependency alongside those (it's a
required, always-available config object on `PlatformConfig` since `rez-config-update` — no
nullability concern). The property/method names below (`reservationId()`) are this doc's
original pre-refactor style; the codebase now uses `public readonly` properties directly
(`$reservation->id`, not `$reservation->id()`) — write it that way, not as shown here.

The use case gains a dependency on `UsersConfig` (to read `cancellationSecret` — it migrated
there from `MailerConfig` when `rez-config-update` ran; see the prerequisites note above).

Two paths inside `execute()`:

**Admin path** (`$request->cancellationToken === null`):
- Load reservation by id
- Cancel it
- No token check

**Guest path** (`$request->cancellationToken !== null`):
- Load reservation by id
- Construct `CancellationToken::fromString($request->cancellationToken)`
- Call `$token->verify($reservation->id, $this->usersConfig->cancellationSecret)`
- If `false` → throw `InvalidTokenException`
- Cancel it

Both paths call the same internal cancellation logic after their respective auth checks.
Extract that logic into a private method to avoid duplication. The existing settings-gated
`sendCancelledIfEnabled()` call (from `rez-lifecycle-email-integration`) stays in that shared
logic, after save — both paths get the same cancellation email behavior, unaffected by this
change per that scaffold's explicit "no actor-type branching for the email decision" instruction.

The use case must not know whether the caller is a browser, CLI, or test. It only sees
the request object.

---

## 5. `MailerConfig` gains `cancellationBaseUrl`

`src/Application/Config/MailerConfig.php` — currently an empty placeholder class.
`cancellationSecret` was briefly added here (`rez-lifecycle-email-integration`, ahead of this
scaffold, because `CreateReservationUseCase`/`ConfirmReservationUseCase` needed a real HMAC
secret before `rez-config-update` had made `UsersConfig` required) but has since moved to
`UsersConfig` (`rez-config-update`) — `MailerConfig` does not carry it anymore. This section
only needs to add:
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

## 6. Wiring `sendReservationCreatedEmail` / `sendReservationConfirmedEmail` — ALREADY DONE

**Superseded by `rez-lifecycle-email-integration`, done ahead of this scaffold.** Rather than
staying deferred, that scaffold wired all three lifecycle emails:

- `Application/Service/ReservationEmailService` — the single settings-gated send/log/swallow
  home for all three emails (`sendCreatedIfEnabled`, `sendConfirmedIfEnabled`,
  `sendCancelledIfEnabled`), reading `ReservationSettings` passed in by the caller.
- `CreateReservationUseCase` — single `if ($settings->autoConfirm) { …confirmed… } else {
  …created… }` after save, generating one `CancellationToken` from
  `$this->usersConfig->cancellationSecret`.
- `ConfirmReservationUseCase` — the manual admin-confirm path, also routes through
  `sendConfirmedIfEnabled()`.
- `CancelReservationUseCase` — routes through `sendCancelledIfEnabled()` unconditionally,
  no token needed.
- Three standalone manual-send use cases (`SendReservationCreatedEmailUseCase`,
  `SendReservationConfirmedEmailUseCase`, `SendReservationCancelledEmailUseCase`) that bypass
  settings entirely and call `MailerInterface` directly for rez-admin's "send anyway" buttons —
  mailer failures propagate unswallowed there, unlike the auto-send path.

This scaffold's job is now limited to guest-side cancellation token verification
(`CancelReservationUseCase`, sections 3–4 above — note section 4's dependency list already
includes `ReservationSettingsRepositoryInterface`/`ReservationEmailService` from the wiring
above; add `UsersConfig` alongside them for the token secret) and making `cancellationBaseUrl`
available (section 5 above).

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

### `CreateReservationUseCase`/`ConfirmReservationUseCase`/`CancelReservationUseCase` mailer wiring — already done
Done in `rez-lifecycle-email-integration` (see section 6 above), including the
autoConfirm/created-vs-confirmed test matrix and settings on/off toggle tests for all three
use cases. No tests for it here — just add `UsersConfig` to whatever mock setup
`CancelReservationUseCaseTest` already has from that scaffold when adding the guest-token tests.

---

## Checklist

- [x] 1. `CancellationToken` value object created (done in `rez-email-restructure`)
- [ ] 2. `InvalidTokenException` created
- [ ] 3. `CancelReservationRequest` gains optional `cancellationToken`
- [ ] 4. `CancelReservationUseCase` updated — two paths, shared cancellation logic (settings-gated
      email send already wired by `rez-lifecycle-email-integration` — don't touch that part,
      just add the token verification branch and the `MailerConfig` dependency for the secret)
- [x] 5. `UsersConfig::cancellationSecret` already present (added in `rez-lifecycle-email-integration`
      on `MailerConfig`, migrated to `UsersConfig` by `rez-config-update`); this scaffold only
      adds `cancellationBaseUrl` to `MailerConfig`
- [x] 6. `MailerInterface` reservation-email shape already updated (done in `rez-email-restructure`
      — takes `CancellationToken`, not a `cancellationUrl` string; no further change needed here)
- [x] 6b. Settings-gated wiring of all three `MailerInterface` methods into
      `CreateReservationUseCase`/`ConfirmReservationUseCase`/`CancelReservationUseCase`, plus the
      three manual-send use cases, already done (`rez-lifecycle-email-integration`)
- [ ] 7. `rez-starter` `SymfonyMailer` stub implements the three-method shape and builds the
      cancellation URL itself from `MailerConfig::cancellationBaseUrl`
- [ ] 8. `rez-starter` container wires `cancellationBaseUrl` from env
- [ ] 9. All new and existing tests pass
