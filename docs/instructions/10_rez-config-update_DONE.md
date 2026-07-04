# Scaffold: rez-config-update

## Prerequisites
- `rez-config` complete (`PlatformConfig`, all sub-configs, `FeatureGuard`)
- `rez-mailer-newsletter` complete

## ⚠️ Conflict: `cancellationSecret` already exists — not on `UsersConfig`

`rez-lifecycle-email-integration` (ad hoc, ran ahead of this scaffold — see `docs/CONTEXT.md`)
already added `cancellationSecret` to `MailerConfig`, not `UsersConfig`, because
`CreateReservationUseCase`/`ConfirmReservationUseCase` needed a real HMAC secret and
`UsersConfig` wasn't required yet (still isn't, until this scaffold runs). **Do not add a
second `cancellationSecret` field to `UsersConfig` per section 2 below** — that would leave two
independent secrets for the same token scheme, a real security footgun (tokens generated with
one would fail to verify against the other). Before running section 2, pick one:
- Leave it on `MailerConfig` permanently and skip adding it to `UsersConfig` — update section 2
  and the `PlatformConfig` diagram in `REZ-CONTEXT.md` §3.7 accordingly, or
- Migrate it: add to `UsersConfig` as planned, delete it from `MailerConfig`, update every call
  site that reads `$mailerConfig->cancellationSecret` (`CreateReservationUseCase`,
  `ConfirmReservationUseCase`, the three `SendReservation*EmailUseCase` classes, and
  `CancelReservationUseCase` once `rez-guest-cancellation` wires its guest path) to read
  `$usersConfig->cancellationSecret` instead.

Either way, this needs a decision before section 2 is implemented — it isn't a drop-in addition
anymore.

## Goal

Users are no longer an optional platform extension — every deployment needs at least one admin
user to operate rez-admin. This scaffold promotes `UsersConfig` from optional to required,
adds `cancellationSecret` to it, removes `Users` from the `Feature` enum, and simplifies the
`PlatformConfig` dependency chain accordingly.

---

## 1. `Feature` enum — remove `Users`

`src/Domain/Shared/Feature.php`

Remove the `Users` case. After this change the enum contains exactly three cases:
`Payments`, `Credits`, `Subscriptions`.

`FeatureGuard` has a `requireUsers()` method (or equivalent). Remove it entirely. Users are
never gated and must never have a guard method. Any call site that currently calls
`$guard->requireUsers()` must be deleted — there should be none yet, but verify.

---

## 2. `UsersConfig` — promote to required, add `cancellationSecret`

`src/Application/Config/UsersConfig.php`

Add `cancellationSecret` as a required constructor parameter (non-empty string, validated the
same way `jwtSecret` is). It must be a separate field — never share the value with `jwtSecret`.

Final constructor signature (constructor promotion):

```php
public function __construct(
    public readonly string $jwtSecret,
    public readonly string $cancellationSecret,
    public readonly int $jwtTtlSeconds = 3600,
    public readonly int $passwordResetTtlMinutes = 60,
)
```

Validation rules (throw `\InvalidArgumentException` on violation):
- `jwtSecret` — non-empty string
- `cancellationSecret` — non-empty string
- `jwtTtlSeconds` — int, min 1
- `passwordResetTtlMinutes` — int, min 1

---

## 3. `PlatformConfig` — make `UsersConfig` required

`src/Application/Config/PlatformConfig.php`

`UsersConfig` moves from a nullable optional parameter to a required constructor parameter.
Update the constructor so `UsersConfig` is always present — no null check, no `hasUsers()`.

Remove `hasUsers(): bool` entirely. Any call site that checks `hasUsers()` must be updated to
assume users are always available.

Simplify the dependency chain. The old chain was:
```
users → payments
credits → payments + users
subscriptions → payments + users
```

The new chain (enforced at construction time, throw `\InvalidArgumentException`):
```
credits → payments
subscriptions → payments
```

Users have no prerequisite. Credits and subscriptions no longer require users as a separate
check — users are implicitly always present.

Updated constructor parameter order (suggested):

```php
public function __construct(
    private readonly MailerConfig $mailerConfig,
    private readonly UsersConfig $usersConfig,
    private readonly ?PaymentsConfig $paymentsConfig = null,
    private readonly ?CreditsConfig $creditsConfig = null,
    private readonly ?SubscriptionsConfig $subscriptionsConfig = null,
)
```

Keep `hasMailer(): bool`, `hasPayments(): bool`, `hasCredits(): bool`,
`hasSubscriptions(): bool`. Add `getUsersConfig(): UsersConfig` (no null return — always
present). Remove `hasUsers(): bool`.

---

## 4. Update tests

Update all `PlatformConfig` and `UsersConfig` unit tests:
- Pass `UsersConfig` as required wherever `PlatformConfig` is constructed in tests
- Add `cancellationSecret` to all `UsersConfig` constructions
- Remove any test that exercises `hasUsers()` returning false
- Remove any test that exercises the `users → payments` dependency check
- Verify tests for `credits → payments` and `subscriptions → payments` still pass
- Add a test: constructing `PlatformConfig` without passing `UsersConfig` is a type error
  (no runtime test needed — PHP enforces required params at call time)
- Add a test: `UsersConfig` with empty `cancellationSecret` throws `\InvalidArgumentException`
- Add a test: `UsersConfig` with empty `jwtSecret` still throws (regression guard)

---

## 5. Update container wiring in `rez-starter`

`config/container.php` (or wherever PHP-DI bindings live)

`UsersConfig` is now always wired. It is no longer behind a conditional. Update the binding
so `UsersConfig` is always constructed from env vars and passed into `PlatformConfig`
unconditionally.

`PlatformConfig` construction in the container must pass `UsersConfig` as the second argument.

---

## Checklist

- [ ] 1. `Feature::Users` case removed from `Feature` enum
- [ ] 2. `FeatureGuard::requireUsers()` removed (if it existed)
- [ ] 3. `UsersConfig` gains `cancellationSecret`, validated as non-empty string
- [ ] 4. `PlatformConfig` constructor updated — `UsersConfig` required, no longer nullable
- [ ] 5. `PlatformConfig::hasUsers()` removed
- [ ] 6. `PlatformConfig::getUsersConfig()` returns `UsersConfig` (non-nullable)
- [ ] 7. Dependency chain updated — `credits/subscriptions → payments` only
- [ ] 8. All tests updated and passing
- [ ] 9. `rez-starter` container wiring updated
