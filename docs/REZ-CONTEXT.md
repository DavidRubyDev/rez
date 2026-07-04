# Rez — System Architecture Context

> **Purpose:** This document gives a complete architectural picture of the Rez ecosystem
> to any LLM or developer starting work on any part of the system. It is a living document —
> update it as implementation progresses. It intentionally avoids implementation details
> (specific SQL queries, PHP syntax) and focuses on structure, contracts, and invariants.
>
> **Last updated:** July 2026
> **Implementation status:** Core library complete. Newsletter (subscribe, unsubscribe, broadcast,
> list subscribers, admin-add) complete. Custom email templates complete end-to-end across all
> three repos: `rez` (`EmailTemplate` CRUD + `SendEmailTemplateUseCase` +
> `MailerInterface::sendCustomEmail()`), `rez-starter` (full HTTP surface under
> `/api/admin/email-templates`, including a `POST .../preview` route that renders an ad-hoc
> `{subject, html}` body through the real send-time `custom-email.html.twig` wrapper, no
> persistence), and `rez-admin` (editor with a TipTap rich-text editor, list, send-to-recipients
> with an extensible recipient-group mechanism, and preview). Reservation-lifecycle emails
> (auto-send plus three manual-resend routes) and reservation/mailer settings (DB-backed,
> `GET`/`PATCH` routes) are likewise wired end-to-end, with rez-admin resend buttons on the
> reservation detail modal and a settings popup for both. rez-admin also has Resources and
> Reservations pages built. Users are core (always enabled) but not yet built. Platform
> extensions (payments, credits, subscriptions) not yet built.

---

## 1. What is Rez

A PHP reservation engine shipped as a Composer library (`davidrubydev/rez`), published on
Packagist. It powers a suite of products: a Web Component frontend (`rez-components`) and
a React admin SPA (`rez-admin`), all wired together by a per-client application
(`rez-starter` template → `client-*` repos).

The system is designed for fitness studios, wellness centres, and similar businesses that
need a bookable class calendar with optional online payments, user accounts, credits,
and subscriptions.

---

## 2. Repository Map

| Repo | Lang | Visibility | Status |
|---|---|---|---|
| `davidrubydev/rez` | PHP 8.3 | **Private** — GitHub | Core + users complete; platform extensions pending |
| `davidrubydev/rez-starter` | PHP 8.3 | **Private** — GitHub | Working template; being updated |
| `DavidRubyDev/rez-demo` | PHP 8.3 | Private | Local Docker demo instance for testing |
| `DavidRubyDev/client-*` | PHP 8.3 | Private | One repo per client |
| `davidrubydev/rez-components` | TypeScript + Lit | TBD | In progress — `<rez-calendar>` first |
| `davidrubydev/rez-admin` | TypeScript + React | TBD | In progress — Resources, Reservations, Newsletter built |

**All repositories are private.** Deployment is managed via SSH and per-repo deploy keys.

**Composer dependency installation** (rez is not on Packagist): client repos and `rez-starter` declare `rez` as a VCS dependency using SSH:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:DavidRubyDev/rez.git" }
],
"require": {
    "davidrubydev/rez": "^0.0.1"
}
```

Each server and CI environment must have an SSH deploy key with read access to `davidrubydev/rez`.

**Dependency direction (no cycles):**
```
rez-components ──→ client-* API (HTTP)
rez-admin      ──→ client-* API (HTTP)
client-*       ──→ davidrubydev/rez (Composer, SSH VCS)
rez-starter    ──→ davidrubydev/rez (Composer, SSH VCS)
```

`rez` never depends on client repos. Client repos never depend on each other.

---

## 3. `davidrubydev/rez` — Core Library

### 3.1 Layer structure

```
Domain/           Pure business logic. No I/O. No framework.
Application/      Use cases, port interfaces, config, services.
Infrastructure/   MySQL implementations of port interfaces.
```

The Handler layer (array-in/array-out adapters) was removed entirely — client apps call
use cases directly. See step 73 in `docs/CONTEXT.md`.

### 3.2 Domain modules and their status

#### Reservations (COMPLETE)
- `Reservation` — entity. States: `Pending → Confirmed → Cancelled | NoShow`
- `ReservationId` — UUID v4 value object
- `ReservationStatus` — pure enum (no backing values)
- `ReservationCollection` — immutable collection
- `TimeSlot` — immutable value object. `start < end` enforced. Adjacent slots do NOT overlap.
- `Party` — immutable value object. Fields: `name`, `email`, `size`, `phone`, `externalRef`.
  `externalRef` is a nullable opaque string — platform layer sets it to `userId.toString()`
  for authenticated bookings, null for guests. The library never interprets it.
- `ResourceIdCollection` — immutable collection of `ResourceId[]`, min 1 element
- `ReservationSettings` — immutable value object, four `bool` fields: `autoConfirm`,
  `autoSendReservationCreated`, `autoSendReservationConfirmed`, `autoSendReservationCancelled`.
  DB-backed single-row settings (`ReservationSettingsRepositoryInterface`, §3.3), not deploy-time
  config — replaces the removed `ReservationsConfig`. No caching layer (explicit decision, plain
  per-request DB read — see invariant in §3.6 if one gets added later). `autoConfirm` is read by
  `CreateReservationUseCase`; the three `autoSend*` toggles aren't wired to anything yet —
  reading them to gate `sendReservationCreatedEmail`/`sendReservationConfirmedEmail`/
  `sendReservationCancelledEmail` calls is `rez-lifecycle-email-integration`'s job.

#### Resources (COMPLETE)
- `Resource` — entity. Fields: `id`, `type`, `name`, `capacity`, `attributes`
- `ResourceId` — UUID v4 value object
- `ResourceType` — value object wrapping a lowercase slug string
- `ResourceCollection` — immutable collection

#### Availability (COMPLETE)
- `AvailabilityRule` — value object. Per-resource, per-day-of-week open/close times. Optional `validFrom` and `validUntil` date bounds (both nullable `DateTimeImmutable`). A null bound means unbounded in that direction — null `validUntil` means the rule recurs forever.
- `AvailabilityOverride` — value object. Per-resource, per-date available/blocked.
- `AvailabilityWindow` — value object. Resolved available `TimeSlot[]` for a resource on a date.
- `DayOfWeek` — pure enum. Monday-first (ISO-8601). String mapping in `DayOfWeekMapper`.

#### Users (CORE — NOT YET BUILT)

Users are always present regardless of which optional features are enabled. Every client
deployment requires at least one Admin user to operate rez-admin. `UsersConfig` is a
required (not optional) part of `PlatformConfig`.

- `User` — entity. Fields: `id`, `name`, `email`, `passwordHash`, `role`, `newsletterOptIn`, `stripeCustomerId`, `createdAt`
- `UserId` — UUID v4 value object
- `HashedPassword` — value object wrapping bcrypt hash
- `UserRole` — pure enum: `Customer`, `Admin`
- `UserCollection` — immutable collection

#### Wallet / Credits (NOT YET BUILT)
- `Wallet` — aggregate computed from `WalletTransaction[]`. Balance = SUM, never stored as column.
- `WalletTransaction` — immutable value object. Fields: `id`, `userId`, `amount` (Money), `type`, `description`, `reservationId?`, `createdAt`
- `WalletTransactionType` — pure enum: `Credit`, `Debit`
- `WalletTransactionId` — UUID v4 value object

#### Subscriptions (NOT YET BUILT)
- `Subscription` — entity. States: `Active → Cancelled | PastDue | Incomplete`
- `SubscriptionId` — UUID v4 value object
- `SubscriptionStatus` — pure enum
- `isActive()` returns true ONLY when status is `Active` AND `currentPeriodEnd > now UTC`

#### Newsletter (COMPLETE — `03_rez-mailer-newsletter.md`)
- `NewsletterSubscriber` — entity. Fields: `id`, `email`, `name?`, `source`, `optedInAt` (all `public readonly`). Factories: `create()` (validates email, sets optedInAt = UTC now) and `reconstruct()` (hydration from DB). ✅
- `NewsletterSubscriberId` — UUID v4 value object ✅
- `SubscriberSource` — pure enum: `Guest`, `Registered`, `Admin` ✅ (`Admin` added for subscribers manually added via rez-admin). Serialiser lowercases via `strtolower($subscriber->source->name)`.

#### Mailer (COMPLETE — `rez-mailer-settings`, `rez-custom-email-templates`)
- `MailerSettings` — immutable value object. Fields: `fromAddress` (validated email), `fromName`
  (non-empty string). DB-backed single-row settings
  (`MailerSettingsRepositoryInterface`/`MysqlMailerSettingsRepository`, §3.3/§3.8), not
  deploy-time config — extracted from `MailerConfig` (`rez-mailer-settings`). `MailerConfig`
  itself is now an empty placeholder class — `cancellationSecret` (the other field it briefly
  carried) migrated onward to `UsersConfig` in `rez-config-update` (see §3.6 invariant 12,
  §3.7). It's expected to gain `cancellationBaseUrl` once `rez-guest-cancellation` runs. No
  caching layer, same explicit decision as `ReservationSettings`.
- `EmailTemplate` — immutable entity (not a settings value object — a real multi-row
  collection). Fields: `id`, `subject`, `html`, `createdAt`. `create()`/`reconstruct()` factories
  plus `withContent()` for validated updates preserving `id`/`createdAt`. Admin-composed
  reusable email content, sent to an arbitrary recipient list via `SendEmailTemplateUseCase`
  (§3.5) — the editor/list UI lives in rez-admin, HTTP routes and email-layout wrapping in
  rez-starter, neither built in this repo. `EmailTemplateId` — UUID v4, `UuidV4Id` trait.

#### Shared
- `Currency` — pure enum: `Czk`, `Eur`, `Usd`. `getCode()` returns uppercase ISO code — used only in Domain (exceptions, `Money::__toString()`). Infrastructure serialization goes through `CurrencyMapper::toString()`.
- `Feature` — pure enum: `Payments`, `Credits`, `Subscriptions`. Users removed — users are always present and never gated. Passed to `FeatureDisabledException` so gated feature names are never raw strings in use cases.
- `Money` — immutable value object. `amount: int` (haléře/cents — NEVER floats), `currency: Currency`. Methods: `add()`, `subtract()` (throws `InsufficientFundsException`), `isZero()`, `equals()`, `isGreaterThan()`, `__toString()`.
- `DateTimeRange` — shared utility, not a domain concept
- `CancellationToken` — value object. Stateless HMAC-SHA256 of `reservationId + secret`. Built
  in `rez-email-restructure` (ahead of schedule — `MailerInterface`'s new shape needed the
  type). **Generation now wired** (`rez-lifecycle-email-integration`): `CreateReservationUseCase`,
  `ConfirmReservationUseCase`, and the two `SendReservation{Created,Confirmed}EmailUseCase`
  manual-send use cases all generate one from `UsersConfig::$cancellationSecret` (migrated from
  `MailerConfig` in `rez-config-update`, see §3.6 invariant 12).
  **Verification now wired** (`rez-guest-cancellation`): `CancelReservationUseCase` accepts an
  optional `cancellationToken` on its request — `null` means admin cancellation (no check);
  non-null means guest cancellation, verified against `UsersConfig::$cancellationSecret` before
  cancelling, throwing `InvalidTokenException` on mismatch.

### 3.3 Port interfaces (Application/Port/)

These are the contracts the library defines. Implementations live in infrastructure or client repo.

#### Implemented in `rez` infrastructure (MySQL)

| Interface | Implementation | Status |
|---|---|---|
| `ReservationRepositoryInterface` | `MysqlReservationRepository` | COMPLETE |
| `ResourceRepositoryInterface` | `MysqlResourceRepository` | COMPLETE |
| `AvailabilityRepositoryInterface` | `MysqlAvailabilityRepository` | COMPLETE |
| `ReservationSettingsRepositoryInterface` | `MysqlReservationSettingsRepository` | COMPLETE (binding itself, like all `rez`-owned repos, must be wired by the client app — not bound in `rez`'s own `config/container.php`) |
| `MailerSettingsRepositoryInterface` | `MysqlMailerSettingsRepository` | COMPLETE (same binding note as above) |
| `EmailTemplateRepositoryInterface` | `MysqlEmailTemplateRepository` | COMPLETE (same binding note as above) |
| `DatabaseSeederInterface` | `MysqlDatabaseSeeder` | COMPLETE |
| `StripeEventRepositoryInterface` | `MysqlStripeEventRepository` | NOT YET BUILT |
| `WalletRepositoryInterface` | `MysqlWalletRepository` | NOT YET BUILT |
| `SubscriptionRepositoryInterface` | `MysqlSubscriptionRepository` | NOT YET BUILT |
| `NewsletterRepositoryInterface` ✅ | `MysqlNewsletterRepository` ✅ | COMPLETE |
| `UserRepositoryInterface` | `MysqlUserRepository` | NOT YET BUILT |
| `PasswordResetRepositoryInterface` | `MysqlPasswordResetRepository` | NOT YET BUILT |

#### Implemented in client repo (NOT in `rez`)

| Interface | Where implementation lives | Why |
|---|---|---|
| `MailerInterface` ⚠️ | `client-*/src/Infrastructure/Mailer/SymfonyMailer.php` | `symfony/mailer` must not be a hard dep on `rez`. **Breaking change (`rez-email-restructure`):** the port dropped `sendBookingConfirmation()`/`sendBookingCancellation()` for `sendReservationCreatedEmail()`/`sendReservationConfirmedEmail()`/`sendReservationCancelledEmail()` (see §3.5, §3.9). `rez-starter`'s `SymfonyMailer` still implements the old two-method shape and needs updating to compile against the new interface — that update is out of scope for `rez` and lives in the client repo. |
| `StripeGatewayInterface` | `client-*/src/Infrastructure/Stripe/StripeGateway.php` | `stripe/stripe-php` must not be a hard dep on `rez` |

#### Implemented in `rez` application layer

| Interface | Implementation |
|---|---|
| `TokenGeneratorInterface` | `RandomTokenGenerator` (Infrastructure/Token/) |

### 3.4 Application services

| Service | Purpose | Status |
|---|---|---|
| `AvailabilityService` | Capacity-aware slot availability logic used by CreateReservation + GetAvailability. Injects `ResourceRepositoryInterface`. `isSlotAvailable(ResourceId, TimeSlot, int $partySize = 1)` sums existing party sizes and checks against `resource->capacity`. `getAvailableSlots()` accepts `int $partySize = 1` and filters candidates by the same capacity rule. | COMPLETE |
| `FeatureGuard` | Throws `FeatureDisabledException` if a gated feature is not configured | COMPLETE |
| `ReservationEmailService` | Settings-gated send/log/swallow for all three reservation-lifecycle emails (`sendCreatedIfEnabled`, `sendConfirmedIfEnabled`, `sendCancelledIfEnabled`). Takes `ReservationSettings` from the caller rather than loading it — avoids a second DB read per request. Single home for invariant 11. No interface, injected as a concrete class (same pattern as `FeatureGuard`) | COMPLETE (`rez-lifecycle-email-integration`) |
| `JwtService` | JWT generation and validation using `firebase/php-jwt` | NOT YET BUILT |
| `PartyResolver` | Resolves `Party` from either a `UserId` (authenticated) or guest fields | NOT YET BUILT |
| `PaymentResolver` | Determines payment method validity and returns `PaymentResolution` | NOT YET BUILT |
| `LoggerInterface` (PSR-3) | Injected via container. `NullLogger` default. Concrete implementation (Monolog) wired in `rez-starter`. | COMPLETE |

### 3.5 Use cases (Application/UseCase/)

#### Complete

| Use case | Input | Output | Notes |
|---|---|---|---|
| `CreateReservationUseCase` | `CreateReservationRequest` | `CreateReservationResponse` | Checks availability, throws `ConflictException` if slot taken. After save: single `if ($settings->autoConfirm) { …confirmed… } else { …created… }` — generates one `CancellationToken` and sends exactly one of the two emails via `ReservationEmailService`, gated by `ReservationSettings` (`rez-lifecycle-email-integration`) |
| `CancelReservationUseCase` | `CancelReservationRequest` | `CancelReservationResponse` | Two paths, same underlying cancellation logic: admin (`cancellationToken === null`, no check) and guest (`cancellationToken !== null`, verified via `CancellationToken::verify()` against `UsersConfig::cancellationSecret`, throws `InvalidTokenException` on mismatch before any state change — `rez-guest-cancellation`). After save: sends the cancelled email via `ReservationEmailService::sendCancelledIfEnabled()` unconditionally, no actor-type branching (`rez-lifecycle-email-integration`) |
| `ConfirmReservationUseCase` | `ConfirmReservationRequest` | `ConfirmReservationResponse` | Manual admin-confirm path. After save: generates a `CancellationToken` and sends the confirmed email via the same `ReservationEmailService::sendConfirmedIfEnabled()` that `CreateReservationUseCase`'s autoConfirm branch uses — exactly one place decides whether a confirmed email goes out (`rez-lifecycle-email-integration`) |
| `SendReservationCreatedEmailUseCase` | `SendReservationCreatedEmailRequest(ReservationId)` | `SendReservationCreatedEmailResponse` | Manual escape hatch for rez-admin's "send anyway" button. Ignores `ReservationSettings` and reservation state entirely; calls `MailerInterface::sendReservationCreatedEmail()` directly, not through `ReservationEmailService`. Mailer failures propagate unswallowed |
| `SendReservationConfirmedEmailUseCase` | `SendReservationConfirmedEmailRequest(ReservationId)` | `SendReservationConfirmedEmailResponse` | Same pattern as above, for the confirmed email |
| `SendReservationCancelledEmailUseCase` | `SendReservationCancelledEmailRequest(ReservationId)` | `SendReservationCancelledEmailResponse` | Same pattern as above, for the cancelled email — no token needed |
| `MarkNoShowUseCase` | `MarkNoShowRequest` | `MarkNoShowResponse` | |
| `GetReservationUseCase` | `GetReservationRequest` | `GetReservationResponse` | |
| `ListReservationsUseCase` | `ListReservationsRequest` | `ListReservationsResponse` | Optional from/to/resourceId filters |
| `CreateResourceUseCase` | `CreateResourceRequest` | `CreateResourceResponse` | |
| `GetResourceUseCase` | `GetResourceRequest` | `GetResourceResponse` | |
| `UpdateResourceUseCase` | `UpdateResourceRequest` | `UpdateResourceResponse` | PATCH semantics — all fields nullable |
| `DeleteResourceUseCase` | `DeleteResourceRequest` | `DeleteResourceResponse` | |
| `ListResourcesUseCase` | `ListResourcesRequest` | `ListResourcesResponse` | |
| `GetAvailabilityUseCase` | `GetAvailabilityRequest` | `GetAvailabilityResponse` | Validates resource exists (throws `ResourceNotFoundException`) then delegates to AvailabilityService. `GetAvailabilityRequest` accepts optional `int $partySize = 1`. |
| `GetAvailabilityRulesUseCase` | `GetAvailabilityRulesRequest` | `GetAvailabilityRulesResponse` | Returns all rules for a resource |
| `GetAvailabilityOverridesUseCase` | `GetAvailabilityOverridesRequest` | `GetAvailabilityOverridesResponse` | Returns overrides for a resource in a date range |
| `SaveAvailabilityRuleUseCase` | `SaveAvailabilityRuleRequest` | `SaveAvailabilityRuleResponse` | |
| `SaveAvailabilityOverrideUseCase` | `SaveAvailabilityOverrideRequest` | `SaveAvailabilityOverrideResponse` | |
| `GetReservationSettingsUseCase` | `GetReservationSettingsRequest` (empty) | `GetReservationSettingsResponse` | Thin read-through to `ReservationSettingsRepositoryInterface` |
| `UpdateReservationSettingsUseCase` | `UpdateReservationSettingsRequest` | `UpdateReservationSettingsResponse` | PATCH semantics — all fields nullable, same shape as `UpdateResourceUseCase` |
| `GetMailerSettingsUseCase` | `GetMailerSettingsRequest` (empty) | `GetMailerSettingsResponse` | Thin read-through to `MailerSettingsRepositoryInterface` |
| `UpdateMailerSettingsUseCase` | `UpdateMailerSettingsRequest` | `UpdateMailerSettingsResponse` | PATCH semantics, same shape as `UpdateReservationSettingsUseCase` |
| `CreateEmailTemplateUseCase` | `CreateEmailTemplateRequest` | `CreateEmailTemplateResponse` | |
| `GetEmailTemplateUseCase` | `GetEmailTemplateRequest` | `GetEmailTemplateResponse` | |
| `ListEmailTemplatesUseCase` | `ListEmailTemplatesRequest` (empty) | `ListEmailTemplatesResponse` | Returns a plain `EmailTemplate[]` array, like `ListSubscribersUseCase` |
| `UpdateEmailTemplateUseCase` | `UpdateEmailTemplateRequest` | `UpdateEmailTemplateResponse` | PATCH semantics via `EmailTemplate::withContent()` |
| `DeleteEmailTemplateUseCase` | `DeleteEmailTemplateRequest` | `DeleteEmailTemplateResponse` | `findById` before `delete`, so a missing template throws rather than silently no-opping |
| `SendEmailTemplateUseCase` | `SendEmailTemplateRequest(EmailTemplateId, string[] $recipients)` | `SendEmailTemplateResponse(int $sent)` | Validates every recipient up front, fails fast if any is malformed; per-recipient mailer failures are caught, logged, and skipped — same pattern as `BroadcastUseCase` |
| `SeedDatabaseUseCase` | `SeedDatabaseRequest(string[] $seedsDirectories)` | `SeedDatabaseResponse` | Globs *.sql, executes in filename order across all directories |
| `SubscribeUseCase` | `SubscribeRequest` | `SubscribeResponse` | Idempotent — returns existing subscriber if email already subscribed |
| `UnsubscribeUseCase` | `UnsubscribeRequest` | `UnsubscribeResponse` | Silent success (`removed: false`) if email not found |
| `BroadcastUseCase` | `BroadcastRequest` | `BroadcastResponse` | Sends new-class email to all opted-in subscribers, returns sent count. `BroadcastRequest` fields: `resourceName` (string), `resourceDate` (DateTimeImmutable). |
| `ListSubscribersUseCase` | `ListSubscribersRequest` | `ListSubscribersResponse` | Returns all newsletter subscribers |

#### Not yet built

| Use case | Module | Notes |
|---|---|---|
| `GetAdminConfigUseCase` | AdminConfig | Pure read from PlatformConfig — no DB. Returns feature flags + currency + plan summaries for rez-admin |
| `RegisterUseCase` | Users (core) | Also saves newsletter subscriber if opt-in |
| `LoginUseCase` | Users (core) | Returns JWT. Unknown email → `InvalidCredentialsException` (never reveal existence) |
| `RequestPasswordResetUseCase` | Users (core) | Stores hashed token. Unknown email → silent success |
| `ResetPasswordUseCase` | Users (core) | Verifies hashed token, updates password, deletes token |
| `GetUserUseCase` | Users (core) | |
| `UpdateUserUseCase` | Users (core) | |
| `ListUsersUseCase` | Users (core) | Admin only |
| `AdminUpdateUserUseCase` | Users (core) | Role/newsletter override. Auth enforcement in HTTP layer, not here |
| `GetWalletUseCase` | Credits | Returns Wallet computed from transactions |
| `CreditWalletUseCase` | Credits | Saves Credit transaction |
| `DebitWalletUseCase` | Credits | Checks canAfford() BEFORE saving. Throws InsufficientFundsException if not |
| `GetSubscriptionUseCase` | Subscriptions | |
| `CreateSubscriptionCheckoutSessionUseCase` | Subscriptions | Returns Stripe checkout URL |
| `CancelSubscriptionUseCase` | Subscriptions | Marks cancelled in DB. Stripe cancel called separately by route. |
| `CreateTopUpCheckoutSessionUseCase` | Payments | Returns Stripe checkout URL for credit top-up |
| `HandleWebhookUseCase` | Payments | Idempotency via stripe_events table. Dispatches by event type |
| `CreateBookingUseCase` | Booking | Platform orchestrator. See critical ordering below. |
| `CancelBookingUseCase` | Booking | Ownership check via externalRef. Admin bypasses. |

### 3.6 Critical invariants — never violate these

1. **Money is always integers.** `amount: int` in haléře/cents. Never `float`. Never `string`.

2. **Credit check before reservation creation.** In `CreateBookingUseCase`: `PaymentResolver` runs first (validates credits/subscription), then `CreateReservationUseCase`, then `DebitWalletUseCase`. If reservation creation fails, no debit has occurred. If debit fails after reservation creation, log as critical error — do NOT auto-cancel the reservation.

3. **Wallet balance is always computed.** No mutable balance column. Balance = `SUM` of all `wallet_transactions` rows for a user. `canAfford()` runs the SUM query at check time.

4. **Stripe webhook idempotency.** `stripe_events.stripe_event_id` is the primary key. Duplicate webhook delivery → duplicate key error → `hasBeenProcessed()` returns true → `processed: false` returned without re-processing.

5. **Password reset tokens stored hashed.** Raw token sent in email. `SHA-256(rawToken)` stored in DB. Lookup is by hash, never raw token.

6. **Login never reveals email existence.** `UserNotFoundException` is always caught and re-thrown as `InvalidCredentialsException`.

7. **Adjacent time slots do not overlap.** `TimeSlot::overlapsWith()`: `A.end === B.start` → false. This is intentional and tested.

8. **Availability is capacity-aware.** A slot is available when `sum(existing party sizes) + incomingPartySize <= resource.capacity`. An empty slot is still unavailable if the incoming party size alone exceeds capacity. Never use a simple `isEmpty()` check.

9. **externalRef on Party is opaque.** The `rez` library stores and returns it but never interprets or validates it. Platform layer sets it to `userId.toString()`. Never add logic to `rez` that reads or branches on `externalRef`.

10. **FeatureGuard called at top of every gated use case.** Every use case that requires an optional feature (payments, credits, subscriptions) calls `$guard->require*()` as its first line. Users are never gated — `Feature` has no `Users` case and `FeatureGuard` has no `requireUsers()` method (both removed in `rez-config-update`); never re-add either. This ensures disabled features fail immediately with `FeatureDisabledException`, not mid-operation.

11. **Email and newsletter failures never abort a booking.** All three reservation-lifecycle
    emails (created, confirmed, cancelled) go through `ReservationEmailService`, the single
    place this invariant lives — every send is wrapped in try/catch and logged, never
    re-thrown. `CreateReservationUseCase`, `ConfirmReservationUseCase`, and
    `CancelReservationUseCase` all route through it (see `rez-lifecycle-email-integration`).
    Newsletter sends follow the same pattern independently in `BroadcastUseCase`. **Exception:**
    the three standalone manual-send use cases (`SendReservationCreatedEmailUseCase` et al.)
    deliberately do NOT swallow mailer failures — they're an explicit admin action, not an
    unattended auto-send, so a failure must surface to the caller. `CreateBookingUseCase` and
    `CancelBookingUseCase` (not yet built) don't call the mailer at all — the reservation
    use cases they call already handle it.

12. **Guest cancellation token is stateless HMAC — never stored.** `CancellationToken` is
    `HMAC-SHA256(reservationId, cancellationSecret)`. No DB column on `reservations`.
    Verification is pure computation. The secret lives in `UsersConfig::$cancellationSecret` —
    it briefly lived on `MailerConfig` (`rez-lifecycle-email-integration`, because `UsersConfig`
    wasn't required yet at that point) and was migrated to `UsersConfig` once `rez-config-update`
    made it required, per that scaffold's own instructions. Guest-side token *verification* in
    `CancelReservationUseCase` is now wired (`rez-guest-cancellation`): both admin and guest paths
    call the same private cancellation logic — only the auth check differs. `null`
    `cancellationToken` on `CancelReservationRequest` means admin (no check, unchanged
    behavior); a non-null value means guest — verified via `CancellationToken::verify()` against
    `UsersConfig::$cancellationSecret` *before* calling `Reservation::cancel()`, throwing
    `InvalidTokenException` (mapped to HTTP 401) on mismatch without touching repository state.
    Token *generation* for the created/confirmed emails was already wired.

### 3.7 Configuration system

`PlatformConfig` is constructed by the client app and injected via PHP-DI. It is the single root of all feature configuration.

`PlatformConfig` — COMPLETE (`rez-config-update`). `UsersConfig` is required (second constructor
parameter, right after `mailer`) — no longer optional. Dependency chain simplified — users no
longer a prerequisite check since they are always present. `hasMailer/Payments/Credits/Subscriptions(): bool`.
`hasUsers()` removed — always true; `$config->users` is the required, non-nullable
`public readonly` property, so no `getUsersConfig()` getter was added (see `CLAUDE.md`'s getter
rule — a public readonly property already suffices).
`ReservationsConfig` — **removed** (`rez-reservation-settings`). Never had a `reservations` slot that matched this section's own diagram anyway (drift predates this removal). `autoConfirm` is no longer part of `PlatformConfig` at all — it's DB-backed now, see `ReservationSettings` in §3.2/§3.4 and the `reservation_settings` table in §3.8. **Breaking change for `rez-starter`:** its `PlatformConfig` construction still passes a `reservations` argument that no longer exists — needs updating, not done here.
`MailerConfig` — COMPLETE. `cancellationSecret` — the only field it briefly carried
(`rez-lifecycle-email-integration`) — migrated onward to `UsersConfig` (below) in
`rez-config-update`, per that scaffold's explicit "migrate" instruction, leaving it briefly
empty. `rez-guest-cancellation` gave it its permanent field: `cancellationBaseUrl` (non-empty
string, no URL-format validation — kept simple). The concrete mailer implementation (e.g.
`rez-starter`'s `SymfonyMailer`) reads this to build the cancellation link URL itself — `rez`
only hands the mailer port the `Reservation` and `CancellationToken` object, never a pre-built
URL string (see `MailerInterface` in §3.3).
`UsersConfig` — COMPLETE (`rez-config-update`). Required. Fields: `jwtSecret`,
`cancellationSecret` (non-empty string, validated the same way as `jwtSecret`, always a
separate value — never shares `jwtSecret`), `jwtTtlSeconds` (default 3600, min 1),
`passwordResetTtlMinutes` (default 60, min 1). This resolves the conflict the scaffold flagged:
`cancellationSecret` now lives here as originally planned, not on `MailerConfig`.
`PaymentsConfig` — COMPLETE. `currency` (non-empty string), `webhookSecret` (non-empty string).
`CreditsConfig` — COMPLETE. `minimumTopUpAmount` (int, min 1, haléře/cents), `currency` (non-empty string).
`PlanConfig` — COMPLETE. `id`, `name`, `priceAmount` (≥ 0), `currency`, `intervalDays` (min 1), `stripePriceId`. Named `PlanConfig` (not `Plan`) — it holds primitive Stripe-specific config, not a domain value object.
`SubscriptionsConfig` — COMPLETE. `PlanConfig[] $plans` (constructor promotion, no empty guard). `getPlanById(string): PlanConfig`.

```
PlatformConfig
  ├── MailerConfig          always required (cancellationBaseUrl)
  ├── UsersConfig           always required (jwtSecret, cancellationSecret, jwtTtlSeconds, passwordResetTtlMinutes)
  ├── PaymentsConfig?       currency, webhookSecret
  ├── CreditsConfig?        minimumTopUpAmount, currency
  └── SubscriptionsConfig?  PlanConfig[]
        └── PlanConfig      id, name, priceAmount, currency, intervalDays, stripePriceId
```

`autoConfirm` (and the three reservation-lifecycle email toggles) and `fromAddress`/`fromName`
live outside this tree entirely now — `ReservationSettings` and `MailerSettings` are single-row
DB tables read/written through `ReservationSettingsRepositoryInterface` and
`MailerSettingsRepositoryInterface` respectively, not deploy-time config. See §3.2 and §3.8.

**Dependency chain enforced at construction time:**
- `credits` requires `payments`
- `subscriptions` requires `payments`
- `users` — always present, no dependency check needed

**Feature profiles:**

| Profile | payments | credits | subscriptions |
|---|---|---|---|
| 1 | | | |
| 2 | ✓ | | |
| 3 | ✓ | ✓ | |
| 4 | ✓ | | ✓ |
| 5 | ✓ | ✓ | ✓ |

Mailer and Users are present in all profiles — they are not optional features.

### 3.8 Database schema

All tables in one MySQL database. `rez` owns all schema — no per-module databases.

#### Complete tables

| Table | Purpose |
|---|---|
| `resources` | id, type, name, capacity, attributes (JSON) |
| `reservations` | id, status, start_at, end_at, party_name, party_email, party_size, party_phone, party_external_ref, created_at |
| `reservation_resources` | reservation_id, resource_id (many-to-many join) |
| `availability_rules` | resource_id, day_of_week, open_time (CHAR 5), close_time (CHAR 5), valid_from (DATE nullable), valid_until (DATE nullable) |
| `availability_overrides` | resource_id, date, available (TINYINT) |
| `reservation_settings` | id (always 1, single row by convention), auto_confirm, auto_send_reservation_created, auto_send_reservation_confirmed, auto_send_reservation_cancelled, updated_at | Seeded via `database/seeds/schema/001_reservation_settings.sql` (`CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`) — a new numbered file rather than appended to `000_schema.sql`, per explicit instruction on this scaffold |
| `mailer_settings` | id (always 1, single row by convention), from_address, from_name, updated_at | Seeded via `database/seeds/schema/002_mailer_settings.sql`, same pattern as `reservation_settings`. Seeded defaults (`noreply@example.com` / `Rez`) are placeholders — every deployment must update them before going live |
| `email_templates` | id, subject, html (MEDIUMTEXT), created_at | Seeded via `database/seeds/schema/003_email_templates.sql` — `CREATE TABLE IF NOT EXISTS` only, no seed rows (a real collection, not a singleton settings table) |

#### Not yet built

| Table | Purpose | Notes |
|---|---|---|
| `users` | id, name, email, password_hash, role, newsletter_opt_in, stripe_customer_id, created_at | Must exist before wallet_transactions, subscriptions |
| `wallet_transactions` | id, user_id, amount (INT), currency, type, description, reservation_id (nullable, no FK), created_at | FK to users. No FK to reservations — audit trail must survive reservation deletion |
| `subscriptions` | id, user_id (UNIQUE), plan_id, status, stripe_subscription_id (UNIQUE), current_period_end, created_at | FK to users. One subscription per user — upsert by user_id |
| `stripe_events` | stripe_event_id (PK), type, payload (JSON), processed_at | PK is the Stripe event ID — provides idempotency |
| `newsletter_subscribers` ✅ | id, email (UNIQUE), name, source, opted_in_at | Upsert by email |
| `password_reset_tokens` | email (PK), token_hash (CHAR 64), expires_at | One token per email — re-request overwrites |

#### Seed directory convention

| Range | Owner |
|---|---|
| 000–099 | `davidrubydev/rez` |
| 200+ | Client repo |

`SeedDatabaseRequest` accepts `string[] $seedsDirectories` (multiple directories, executed in order). Each package exposes `MysqlDatabaseSeeder::seedsPath(): string`.

---

## 4. `rez-starter` — Client App Template

Thin HTTP delivery layer. Contains no business logic. All logic lives in `rez`.

### Responsibilities
- PHP-DI container wiring (PDO, interface → implementation bindings)
- Slim 4 routes (build Request objects, call use cases, serialize responses)
- Auth middleware (validate JWT, attach UserId and UserRole to request)
- Admin middleware (enforce UserRole::Admin)
- CORS middleware (allow `rez-admin` origin)
- Error middleware (map domain exceptions → HTTP status codes)
- Concrete `SymfonyMailer` implementing `MailerInterface`
- Concrete `StripeGateway` implementing `StripeGatewayInterface`
- Docker stack (PHP-FPM + Nginx + MySQL + Mailpit)
- `.env` management

### Exception → HTTP status code mapping

| Exception | HTTP |
|---|---|
| `ResourceNotFoundException` | 404 |
| `ReservationNotFoundException` | 404 |
| `UserNotFoundException` | 404 |
| `SubscriptionNotFoundException` | 404 |
| `ConflictException` | 409 |
| `FeatureDisabledException` | 501 |
| `InvalidCredentialsException` | 401 |
| `InvalidTokenException` | 401 |
| `InsufficientFundsException` | 422 |
| `\InvalidArgumentException` | 422 |
| `DomainException` | 422 |
| `\UnexpectedValueException` (Stripe sig) | 400 |
| `DatabaseException` | 503 |

### API surface

All routes prefixed `/api/`.

#### Authorization model

| Principal | Credential | How identified in rez-starter |
|---|---|---|
| Guest (public) | None | No middleware |
| Guest cancelling own reservation | HMAC token in query param | Verified in use case, not middleware |
| Authenticated user | JWT Bearer token | Auth middleware attaches UserId + UserRole |
| Admin | JWT Bearer token with `role: Admin` | Admin middleware enforces UserRole::Admin |

#### Always available — public (no auth)

```
GET    /api/resources
GET    /api/resources/{id}
GET    /api/availability
POST   /api/newsletter/subscribe
DELETE /api/newsletter/unsubscribe
```

Guest-facing reservation creation (via `<rez-calendar>`, not yet built) is a design target, not
a currently-available route. Guest self-cancellation via HMAC token is now wired at the `rez`
layer (`rez-guest-cancellation` — `CancelReservationUseCase` accepts an optional
`cancellationToken`), but there is still no public `rez-starter` HTTP route exposing it — the
only existing cancel route (`POST /api/reservations/{id}/cancel` below) is admin-only and JWT-
gated; a guest-facing route (token in query param, per the authorization model table above) is a
`rez-starter` follow-up, not built here. See §3.2 `CancellationToken` and the `CancelReservationUseCase`
row in §3.5. There is no `/api/bookings` route: **booking** (a `CreateBookingUseCase`
orchestrator layered on top of reservations to add payment/credit/subscription resolution before
creating the reservation) is a distinct, separately-scoped concept from **reservation** and is not
yet built — whether it's needed at all is still undecided pending the payments profile. Do not
conflate the two: every route below operates on `Reservation`, not on a `Booking` entity.

#### Always available — admin JWT required

```
POST   /api/resources
PATCH  /api/resources/{id}
DELETE /api/resources/{id}
PUT    /api/resources/{id}/availability/rules
PUT    /api/resources/{id}/availability/overrides/{date}
POST   /api/reservations                                      ← create; today only exercised by rez-admin's manual booking modal
GET    /api/reservations
GET    /api/reservations/{id}
POST   /api/reservations/{id}/confirm
POST   /api/reservations/{id}/no-show
POST   /api/reservations/{id}/cancel                          ← admin cancellation (no token)
POST   /api/reservations/{id}/send-created-email              ← manual resend; bypasses ReservationSettings, unswallowed mailer failures
POST   /api/reservations/{id}/send-confirmed-email             ← same
POST   /api/reservations/{id}/send-cancelled-email             ← same
GET    /api/newsletter/subscribers
POST   /api/admin/newsletter/subscribers                     ← admin-add subscriber (sets Admin source)
POST   /api/newsletter/broadcast                             ← body: { resource_name, resource_date }
GET    /api/admin/reservation-settings
PATCH  /api/admin/reservation-settings
GET    /api/admin/mailer-settings
PATCH  /api/admin/mailer-settings
POST   /api/admin/email-templates
POST   /api/admin/email-templates/preview                   ← ad-hoc {subject, html} preview, no persistence
GET    /api/admin/email-templates
GET    /api/admin/email-templates/{id}
PATCH  /api/admin/email-templates/{id}
DELETE /api/admin/email-templates/{id}
POST   /api/admin/email-templates/{id}/send
GET    /api/admin/config
```

#### Always available — auth routes (users are core)

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/password-reset/request
POST   /api/auth/password-reset/confirm
GET    /api/users/me                      (JWT required)
PATCH  /api/users/me                      (JWT required)
GET    /api/users                         (admin)
PATCH  /api/users/{id}                    (admin)
```

#### Profile 2+ (payments)
```
POST   /api/stripe/webhook
```

#### Profile 3+ (credits)
```
GET    /api/wallet
POST   /api/wallet/topup
```

#### Profile 4+ (subscriptions)
```
GET    /api/subscription
POST   /api/subscription/checkout
DELETE /api/subscription
```

---

## 5. `rez-components` — Web Components Frontend

### Stack
- TypeScript + Lit (Web Components framework, ~5KB, compiles to native Custom Elements)
- Vite (build tool, multiple IIFE entry points)
- Shadow DOM for style isolation
- CSS Custom Properties for theming (pierce Shadow DOM from host page)

### Components

| Component | Responsibility | Profile |
|---|---|---|
| `<rez-calendar>` | Slot browser, booking flow, newsletter opt-in | 1+ |
| `<rez-cancel>` | Guest cancellation confirmation (reads token from URL, calls API) | 1+ |
| `<rez-checkout>` | Payment method selection, Stripe redirect | 2+ |
| `<rez-account>` | Register, login, booking history, credits, subscription | 1+ (always, since users are core) |

### Bundle entry points

| Bundle | Includes | Notes |
|---|---|---|
| `rez.core.js` | rez-calendar + rez-cancel + rez-account | Base bundle — all clients |
| `rez.payments.js` | rez-core + rez-checkout | Clients with payments enabled |
| `rez.full.js` | all components | Convenience alias for rez.payments.js |

### Component communication

Components communicate only via Custom Events — never via shared global state or direct imports.

Key events:
- `rez:slot-selected` — fired by `rez-calendar`, listened by `rez-checkout`
- `rez:booking-complete` — fired by `rez-checkout`
- `rez:auth-changed` — fired by `rez-account` when login state changes

### Theming

```css
rez-calendar {
  --rez-primary: #1D9E75;
  --rez-font: 'Inter', sans-serif;
  --rez-border-radius: 8px;
  --rez-slot-available: #E8F5E9;
  --rez-slot-full: #FFEBEE;
}
```

All CSS custom properties have sensible defaults inside the Shadow DOM.

### HTML attributes

Each component accepts `api-base` pointing to the client app API:
```html
<rez-calendar api-base="https://studio.cz/api"></rez-calendar>
```

---

## 6. `rez-admin` — React Admin SPA

### Stack
- React + TypeScript + Vite
- JWT stored in React state / Zustand — never localStorage
- Authorization: Bearer header on every request

### Feature detection

On login, fetches `GET /api/admin/config`:
```json
{
  "features": {
    "payments": true,
    "credits": true,
    "subscriptions": false
  },
  "currency": "CZK",
  "plans": []
}
```

Users, mailer, and guest cancellation are always present — they do not appear in the features
map. Pages that depend on them are always shown. Pages appear/disappear in the sidebar only
for optional features (payments, credits, subscriptions).

### Pages per delivery slice

| Slice | Pages |
|---|---|
| Core | Login, Resources (add/edit), Reservations list, Manual booking, Users list, Edit user, Newsletter broadcast |
| Payments | Payment history, Stripe event log |
| Credits | User credit balances, Manual credit adjustment |
| Subscriptions | Subscription management, Override subscription status |
| Polish | Full polish, edge cases |

---

## 7. Delivery Plan

Two tiers. Core tier delivers a fully functional system for any client. Platform extensions add
optional monetisation on top. Client demos after each tier completes before the next begins.

### Core tier

| Step | What gets built |
|---|---|
| rez-starter update | Full route surface, SymfonyMailer, middleware stubs, CORS |
| rez-demo | Local Docker instance for end-to-end API testing (no auth yet) |
| `<rez-calendar>` | Lit component — slot browser, booking form |
| Guest cancellation | HMAC token in rez, confirmation email plumbing, `<rez-cancel>` component |
| Config restructure | UsersConfig required, cancellationSecret added, Feature enum updated, dependency chain simplified |
| rez-users | User domain, JwtService, auth use cases, password reset |
| JWT middleware | Auth + admin middleware in rez-starter, full route protection |
| rez-admin (core) | React SPA — login, resources, reservations, users, newsletter |

### Platform extension tiers

| Tier | Backend | Delivers |
|---|---|---|
| Payments | rez-payments | Stripe one-time payments, webhook, payment history in rez-admin |
| Credits | rez-credits | Credit wallet, top-up, debit on booking, credit management in rez-admin |
| Subscriptions | rez-subscriptions | Monthly plans, Stripe billing, free booking for subscribers |
| Orchestration | rez-booking → rez-deprecate-handlers | CreateBookingUseCase, CancelBookingUseCase, PartyResolver, PaymentResolver, handler deprecation |

**Penalty system:** in scope, rules TBD with client after core tier is delivered.

**Client website** (Michaela Urbanová — branding, content pages): separate deliverable, expected 2–3 months after Rez core tier is working.

---

## 8. Per-client repo (`client-*`)

Created from `rez-starter` template. Contains only:
- `.env` with real credentials (never committed with real values)
- `src/Infrastructure/Mailer/SymfonyMailer.php` — implements `MailerInterface`
- `src/Infrastructure/Stripe/StripeGateway.php` — implements `StripeGatewayInterface`
- Client-specific route overrides (if any)
- Deployment config / CI

**Requires SSH deploy key on production server** (read-only, repo-scoped):
```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_rez -N ""
# add ~/.ssh/deploy_rez.pub to GitHub repo → Settings → Deploy keys
```

---

## 9. Current implementation status

### `davidrubydev/rez`

| Module | Domain | Use cases | Infrastructure | Tests |
|---|---|---|---|---|
| Reservations | ✅ | ✅ (guest cancel-token verification wired — `rez-guest-cancellation`) | ✅ | ✅ |
| Resources | ✅ | ✅ | ✅ | ✅ |
| Availability | ✅ | ✅ | ✅ | ✅ |
| ReservationSettings | ✅ | ✅ | ✅ | ✅ (`rez-reservation-settings`) |
| MailerSettings | ✅ | ✅ | ✅ | ✅ (`rez-mailer-settings`) |
| EmailTemplate (custom emails) | ✅ | ✅ CRUD + Send | ✅ | ✅ (`rez-custom-email-templates`) |
| Reservation lifecycle emails | — | ✅ auto-send (3 use cases wired) + ✅ 3 manual-send use cases | — | ✅ (`rez-lifecycle-email-integration`) |
| Seeder | ✅ | ✅ | ✅ | ✅ |
| Currency + Money | ✅ | — | ✅ CurrencyMapper | ✅ |
| Config / FeatureGuard | ✅ | — | — | ✅ (`UsersConfig` required + `Feature`/`FeatureGuard` `Users` removed — `rez-config-update`; `ReservationsConfig` removed — see ReservationSettings row above; `MailerConfig` now an empty placeholder — `cancellationSecret` moved to `UsersConfig`, `fromAddress`/`fromName` moved to MailerSettings row above) |
| Mailer port | ⚠️ restructured, breaking (`rez-email-restructure`) | — | — | ✅ shape tests |
| Newsletter | ✅ | ✅ | ✅ | ✅ |
| CancellationToken | ✅ value object + generation wired into 5 use cases | — | — | ✅ |
| Users (core) | ❌ | ❌ | ❌ | — |
| Payments / Stripe port | ❌ | ❌ | ❌ | — |
| Credits / Wallet | ❌ | ❌ | ❌ | — |
| Subscriptions | ❌ | ❌ | ❌ | — |
| Booking orchestration | ❌ | ❌ | — | — |
| Handler layer | ✅ (removed) | — | — | — |

### Pending scaffold documents (run in this order)
1. `rez-core-changes` — **COMPLETE**
2. `rez-config` — **COMPLETE**
3. `rez-mailer-newsletter` — **COMPLETE**
4. `rez-throws-phpdoc` — **COMPLETE** (`@throws` PHPDoc backfill across all public methods)
5. `rez-pdo-exceptions` — **COMPLETE** (DatabaseException, PDO wrapping in all MySQL repositories, use case re-throw with context messages, 503 mapping documented)
6. `rez-testing-fixes` — **COMPLETE** (UTC fix, cancelled slot freed, autoConfirm in ReservationsConfig; rez-starter steps now done — PDO boot guard, ReservationsConfig wired, seed entry point in rez-starter)
7. `rez-availability-bounds` — **COMPLETE** (validFrom/validUntil on AvailabilityRule + isActiveOn(); AvailabilityService filters by bounds; schema columns + repository hydration; SaveAvailabilityRuleRequest/UseCase validates and parses bounds; step 5 rez-starter skipped)
8. `rez-psr-logging` — **COMPLETE** (LoggerInterface + NullLogger in CreateReservationUseCase, BroadcastUseCase, all MySQL repos; mailer integrated into CreateReservationUseCase with email failure logging; CancelReservationUseCase logger deferred to rez-guest-cancellation)
9. `rez-starter-logging` — **COMPLETE** (Monolog wired as PSR-3 implementation; rotating file handler; request/response middleware logger; exception middleware logs with stack trace)
10. `rez-bug-fixes` — **COMPLETE** (BUG-01: GetAvailabilityUseCase now checks resource exists before calling service; BUG-02+03: capacity-aware conflict detection — slot available when sum(existing party sizes) + incoming ≤ capacity; DESIGN-01 reverted — strict UUID v4 parsing preserved; party_size query param wired in rez-starter GET /api/availability)
- `rez-email-restructure` — **COMPLETE**, ad hoc (no `docs/instructions/NN_*` file — ran out of
  sequence, ahead of `rez-config-update`; see `docs/CONTEXT.md` step 74). `MailerInterface`
  restructured from a single unconditional confirmation email into three typed methods
  (`sendReservationCreatedEmail`, `sendReservationConfirmedEmail`, `sendReservationCancelledEmail`).
  `CancellationToken` pulled forward from step 12 below (value object only, still unwired).
  `CreateReservationUseCase`'s mailer call removed — reintroducing it is `rez-lifecycle-email-integration`'s
  job. **Breaking change for `rez-starter`:** its `SymfonyMailer` still implements the old
  two-method shape and needs updating before it will compile against the new interface.
11. `rez-config-update` — **COMPLETE** (see `docs/CONTEXT.md` step 79). `UsersConfig` promoted
    to required (second `PlatformConfig` param, after `mailer`); gained `cancellationSecret`,
    migrated from `MailerConfig` (the "migrate" option was chosen over "leave on MailerConfig" —
    resolves the conflict this scaffold flagged, see invariant 12). `Feature` enum's `Users` case
    and `FeatureGuard::requireUsers()` removed. Dependency chain simplified to
    `credits/subscriptions → payments` only. No `getUsersConfig()` getter added — `$config->users`
    is already a required `public readonly` property (`CLAUDE.md` getter rule). Did not touch
    `ReservationsConfig` — already removed rather than added; see `rez-reservation-settings`
    below. **Breaking change for `rez-starter`** (on top of the one `rez-lifecycle-email-integration`
    already caused): the `CANCELLATION_SECRET` env var, the standalone `MailerConfig::class`
    binding, and the four call sites reading `$mailerConfig->cancellationSecret` all need
    re-pointing at `UsersConfig->cancellationSecret`; not done here.
12. `rez-guest-cancellation` — **COMPLETE** (see `docs/CONTEXT.md` step 80). Added
    `InvalidTokenException` (`src/Domain/Exception/` — the instruction doc suggested
    `Domain/Shared/Exception`, but every other domain exception lives flat under `Domain/Exception/`
    extending the project's own `DomainException` base, not the built-in `\DomainException`; this
    scaffold followed the established convention over the doc's suggestion). `CancelReservationRequest`
    gained an optional `?string $cancellationToken`. `CancelReservationUseCase` gained a
    `UsersConfig` dependency and now has two paths sharing one private `cancel()` method: admin
    (`cancellationToken === null`, unchanged behavior) and guest (non-null, verified via
    `CancellationToken::fromString()->verify()` against `UsersConfig::cancellationSecret` *before*
    calling `Reservation::cancel()`, throwing `InvalidTokenException` on mismatch — no repository
    write happens on a bad token). `MailerConfig` gained `cancellationBaseUrl` (non-empty string).
    `CancellationToken` value object itself was already built — see `rez-email-restructure` above.
    **`rez-starter` follow-up (not done here, separate repo):** a public guest-facing cancel route
    (token in query param, per the authorization model in §4) that maps `InvalidTokenException` to
    401 (already documented in the exception table) and passes the token through to
    `CancelReservationRequest`; `cancellationBaseUrl` wired from an env var (e.g.
    `CANCELLATION_BASE_URL`) into the container's `MailerConfig` binding; `SymfonyMailer` building
    the actual cancellation URL string from `cancellationBaseUrl` + reservation id + token, since
    `MailerInterface` hands it the `CancellationToken` object, not a pre-built URL (see §3.7).
    `<rez-cancel>` web component (confirmation page reading the token from the URL) is a
    `rez-components` follow-up, also not built here.
- `rez-reservation-settings` — **COMPLETE**, ad hoc (no `docs/instructions/NN_*` file — see
  `docs/CONTEXT.md` step 75). Removed `ReservationsConfig` entirely; `autoConfirm` plus three new
  lifecycle-email toggles (`autoSendReservationCreated/Confirmed/Cancelled`) now live in a
  DB-backed `ReservationSettings` single-row table, read/written through
  `ReservationSettingsRepositoryInterface` / `MysqlReservationSettingsRepository`. No caching —
  explicit decision. `GetReservationSettingsUseCase` / `UpdateReservationSettingsUseCase` added
  (PATCH semantics, same shape as `UpdateResourceUseCase`). `CreateReservationUseCase` now reads
  `autoConfirm` from the repository instead of `PlatformConfig`. **Breaking change for
  `rez-starter`:** `PlatformConfig` construction needs its `reservations` argument removed, and
  `ReservationSettingsRepositoryInterface` needs binding to `MysqlReservationSettingsRepository`
  in the client container — neither done here. API surface
  (`GET`/`PATCH /api/admin/reservation-settings` or similar) is also a `rez-starter` follow-up.
- `rez-lifecycle-email-integration` — **COMPLETE**, ad hoc (no `docs/instructions/NN_*` file —
  see `docs/CONTEXT.md`). Wires settings-gated auto-sending into `CreateReservationUseCase`
  (single if/else on `autoConfirm`, never both created+confirmed), `ConfirmReservationUseCase`,
  and `CancelReservationUseCase` (unconditional, no actor branching), all through the new
  `ReservationEmailService`. Adds three standalone manual-send use cases
  (`SendReservationCreatedEmailUseCase` et al.) that bypass settings and call `MailerInterface`
  directly, unswallowed failures. Added `MailerConfig::cancellationSecret` at the time (later
  migrated to `UsersConfig::cancellationSecret` by `rez-config-update` — see invariant 12).
  `rez-starter` follow-up (not done here): needs a standalone `MailerConfig::class` container
  binding (previously only nested inside `PlatformConfig`'s factory) for the new
  direct-dependency use cases to autowire.
- `rez-mailer-settings` — **COMPLETE**, ad hoc (no `docs/instructions/NN_*` file — see
  `docs/CONTEXT.md`). Extracts `fromAddress`/`fromName` from `MailerConfig` into a DB-backed
  `MailerSettings` single-row table (`Domain/Mailer`), same shape as `ReservationSettings`.
  `MailerConfig` only carried `cancellationSecret` at the time (later migrated to `UsersConfig`
  by `rez-config-update`). `GetMailerSettingsUseCase`/
  `UpdateMailerSettingsUseCase` added. `rez-starter` follow-up (not done here): `SymfonyMailer`'s
  "From" header needs to read from `MailerSettingsRepositoryInterface` instead of the now-removed
  `MailerConfig` fields; container needs `MailerSettingsRepositoryInterface` bound to
  `MysqlMailerSettingsRepository`.
- `rez-custom-email-templates` — **COMPLETE**, ad hoc (no `docs/instructions/NN_*` file — see
  `docs/CONTEXT.md`). Adds `EmailTemplate` (real CRUD entity, not a settings singleton) with
  full CRUD use cases plus `SendEmailTemplateUseCase` (loads a template, sends to an arbitrary
  recipient list, catch-log-continue per recipient like `BroadcastUseCase`). Adds
  `MailerInterface::sendCustomEmail()`. Covers only `rez` — the rez-admin editor/list/send UI
  and rez-starter's HTTP routes + Twig layout wrapping are separate, not-yet-built follow-ups
  in those repos.
13. `rez-admin-config` — GetAdminConfigUseCase (pure read from PlatformConfig, no DB; features map excludes users)
14. `rez-users` — User domain, JwtService, auth use cases, RandomTokenGenerator
15. `rez-payments` — StripeGatewayInterface, StripeEventRepository, webhook use case
16. `rez-credits` — Wallet, WalletTransaction, wallet use cases
17. `rez-subscriptions` — Subscription, Plan, subscription use cases
18. `rez-booking` — CreateBookingUseCase, CancelBookingUseCase, PartyResolver, PaymentResolver
19. `rez-deprecate-handlers` — **SUPERSEDED** (Handler layer removed entirely rather than deprecated — see step 73 in `docs/CONTEXT.md`)

### `rez-starter`
- ✅ Docker stack (PHP-FPM + Nginx + MySQL + Mailpit)
- ✅ Slim bootstrap, PHP-DI wiring, full route surface
- ✅ Complete exception → HTTP status map (now also `EmailTemplateNotFoundException` → 404)
- ✅ `PlatformConfig`/`MailerConfig` construction and container wiring synced against `rez`'s
  config restructure (`fix/rez-breaking-changes-sync`) — `reservations` argument dropped,
  `MailerConfig` now only takes `cancellationSecret` (plus new `CANCELLATION_SECRET` env var),
  standalone `MailerConfig::class` binding added, and `ReservationSettingsRepositoryInterface` /
  `MailerSettingsRepositoryInterface` / `EmailTemplateRepositoryInterface` all bound to their
  Mysql implementations
  — ⚠️ **out of date again** as of `rez-config-update` (`rez` `docs/CONTEXT.md` step 79):
  `cancellationSecret` moved on to `UsersConfig`, `UsersConfig` is now required, and
  `MailerConfig` is now an empty placeholder. `CANCELLATION_SECRET` wiring, the standalone
  `MailerConfig::class` binding, and every call site above need re-pointing at `UsersConfig`;
  not done here (separate repo)
- ✅ `SymfonyMailer` rewritten against the three-method `MailerInterface`
  (`sendReservationCreatedEmail`/`sendReservationConfirmedEmail`/`sendReservationCancelledEmail`)
  plus `sendCustomEmail()`; "From" address/name now read live from
  `MailerSettingsRepositoryInterface` instead of static config. Templates split into
  `reservation-{created,confirmed,cancelled}.html.twig` + a `custom-email.html.twig` wrapper.
  Verified against the dev stack via Mailpit
- ✅ `GET`/`PATCH /api/admin/reservation-settings` (`feat/reservation-settings-route`)
- ✅ `GET`/`PATCH /api/admin/mailer-settings` (`feat/mailer-settings-route`)
- ✅ `EmailTemplate` CRUD + send — `POST`/`GET`/`PATCH`/`DELETE /api/admin/email-templates[/{id}]`,
  `POST /api/admin/email-templates/{id}/send` (`feat/email-template-routes`)
- ✅ `POST /api/admin/email-templates/preview` (`feat/email-template-preview-route`) — renders an
  ad-hoc `{subject, html}` body through `custom-email.html.twig` (the same wrapper
  `SymfonyMailer::sendCustomEmail()` uses) and returns `{ html }`, no persistence. Deliberately
  skips the usual RequestFactory/use-case pattern — pure Twig render in the Handler, since there's
  no domain state involved
- ✅ Manual reservation-lifecycle email send — `POST /api/reservations/{id}/send-created-email`,
  `.../send-confirmed-email`, `.../send-cancelled-email` (`feat/reservation-email-send-routes`);
  each returns the updated `Reservation` (same `ReservationSerializer` as confirm/cancel/no-show)
- ✅ Twig HTML email templates
- ✅ PDO boot guard — DB-down returns 503
- ✅ `bin/seed.php` seed entry point (`composer seed` / `composer seed:fill`) — now also applies
  `rez`'s `reservation_settings`/`mailer_settings`/`email_templates` schema files
- ✅ Monolog PSR-3 logging (rotating file handler, request/response middleware, exception middleware)
- ✅ `GET /api/availability` accepts `party_size` query param — capacity-aware slot filtering
- ✅ CORS middleware (wildcard origin, preflight handled)
- ✅ Routes split into per-route `App\Http\Handler\*` classes (`src/Http/Handler`, `RequestFactory`, `Serializer`) — unrelated to the removed `Rez\Handler\*` library layer
- ✅ PHPUnit + PHPStan (level max) + PHP-CS-Fixer (PSR-12) — mirrors `davidrubydev/rez`'s toolchain; `composer ca` now works
- ✅ `bootstrap/app.php` — app construction extracted from `public/index.php` so tests reuse the exact same wiring
- ✅ Api test suite (`tests/Api/`) — real HTTP lifecycle against a dedicated `rez_starter_test` database, run in-process (`composer test-api`)
- ❌ Auth middleware, admin middleware
- ❌ `StripeGateway` implementation
- ❌ Auth routes, booking routes, feature-gated routes — blocked on rez users module
- ❌ Guest cancellation route (`rez-guest-cancellation` complete on the `rez` side —
  `CancelReservationUseCase` accepts a `cancellationToken`, `MailerConfig.cancellationBaseUrl`
  exists; needs a public route reading the token from a query param, `CANCELLATION_BASE_URL` env
  wiring, and `SymfonyMailer` building the actual cancellation link URL from
  `cancellationBaseUrl` + reservation id + token)

### `rez-demo`
- ❌ Not initialised (init from rez-starter, local Docker only, for API testing)

### `rez-components`
- ❌ `<rez-calendar>` — not started
- ❌ `<rez-cancel>` — not started
- ❌ `<rez-checkout>` — not started
- ❌ `<rez-account>` — not started

### `rez-admin`
- ✅ Project scaffold (Vite + React + TypeScript + Tailwind, Vitest, Dockerfile + nginx)
- ✅ AppLayout + Sidebar (feature-gated entries via `/api/admin/config`)
- ✅ Resources page — list, create, edit, delete; availability rules panel + rule modal; overrides panel + override modal; sorting (type/name/capacity/attributes)
- ✅ Reservations page — list with date-range filter, resource name lookup, search + per-field filters (resource, status, name, email), sorting (date/status/name/email); detail modal with confirm/no-show/cancel actions; manual booking modal
- ✅ Newsletter page — tabbed layout: broadcast panel (resource selector, date/time, send, result); subscribers panel (list with search + sort by email/name/source/opted-in, inline add with `Admin` source, delete with ConfirmDialog); **custom emails tab** (see below)
- ✅ Custom emails tab — `EmailTemplateEditorModal` (subject + `RichTextEditor`, a TipTap
  editor: bold/italic/strikethrough/underline, headings, alignment, font family/size, color,
  lists, blockquote, links; `getHTML()` on save) creates via `POST /api/admin/email-templates`
  or updates via `PATCH .../{id}` (title/submit-label swap between "New email"/Save and
  "Edit email"/Update). `CustomEmailsPanel` lists saved templates (`EditableListPanel`, newest
  first) with edit/delete/send/preview actions. `SendEmailTemplateModal` builds a recipient list
  from manually-added addresses plus a `RecipientGroup[]` list (today just "All newsletter
  subscribers" via `newsletterApi.listSubscribers()`; built to extend — a future Users/Admins
  group is one more `useAsyncData` call + array entry, not a rewrite) and posts to
  `POST .../{id}/send`. `EmailPreviewModal` calls `POST /api/admin/email-templates/preview` with
  the current `{subject, html}` (works for both a saved template and an unsaved editor draft) and
  renders the returned fully-wrapped HTML in a `sandbox=""` iframe — deliberately not just the raw
  content, since the real send wraps it in `custom-email.html.twig` (branded header/footer) first.
- ✅ Settings popup — sidebar-pinned button opens a tabbed modal: reservation settings (4
  toggles — auto-confirm, auto-send created/confirmed/cancelled — via
  `GET`/`PATCH /api/admin/reservation-settings`) and email settings (from address/name via
  `GET`/`PATCH /api/admin/mailer-settings`)
- ✅ Export buttons (Reservations page, Subscribers panel) — shared `ExportModal` (format:
  JSON or XML; `DateRangeFilter`) takes a per-caller `fetchData(range)` callback and handles
  Blob/download entirely client-side, no new backend endpoint. Reservations exports via the
  existing `GET /api/reservations?from=&to=` (server-side filtered); subscribers has no
  server-side date filter, so it fetches everything and filters by `opted_in_at` client-side.
  XML goes through a small generic JSON-to-XML converter (`lib/xml.ts`), not a full XML library
- ✅ 24-hour time inputs — all 5 native `<input type="time">` fields (broadcast time, manual
  booking start/end, availability rule open/close) replaced with a custom `TimeInput` (two
  `<select>`s, hour 00-23 + minute 00-59, hand-written option text). Native time inputs follow
  the browser/OS locale for their picker; the `lang="en-GB"` workaround that used to force
  24-hour display no longer works in current Chrome, which ignores it and follows the OS
  locale unconditionally — the custom control sidesteps the problem entirely, no locale
  dependency at all
- ✅ Shared UI: SortHeader, ConfirmDialog, DateRangeFilter, ErrorBanner, ExportModal, Modal, PageHeader, StatusBadge, SlotPicker, EditableListPanel (now also takes an optional `renderRowExtra` slot for per-row actions beyond edit/delete), Button, TextInput, TimeInput, Select, FormField, FormActions, RowActions, SearchInput, EmptyTableRow, Toggle, RichTextEditor
- ✅ Shared hooks: useAsyncData, useConfig, useSortable, useConfirmDelete, useSyncedList
- ✅ Component/hook dedup pass — merged the near-duplicate AvailabilityRulesPanel/AvailabilityOverridesPanel into EditableListPanel, consolidated day-of-week data into lib/days.ts, removed duplicated UTC time-formatting and empty-state table markup
- ✅ API client modules: resources, reservations, availability, newsletter, config, reservationSettings, mailerSettings, emailTemplates
- ✅ Reservation detail modal — resend-lifecycle-email buttons (`send-{created,confirmed,cancelled}-email`), one shown at a time keyed off status
- ❌ Auth (login/logout, JWT, protected routes) — deferred until rez-users is built
- ❌ Users page — deferred until rez-users is built
