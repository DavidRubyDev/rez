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
> Reservations pages built. Guest self-cancellation is wired end-to-end (`rez`'s
> `CancelReservationUseCase` token verification, `rez-starter`'s public `DELETE
> /api/reservations/{id}?token=` route and the real cancellation link in reservation emails) —
> only the guest-facing `<rez-cancel>` confirmation page (`rez-components`) is still missing.
> Users are core (always enabled) and now complete end-to-end too, including `rez-admin`: `rez`
> (`rez-users` — domain, use cases, `JwtService`), `rez-starter` (auth routes, `/api/users/me`,
> admin user routes, `AuthMiddleware`/`AdminMiddleware` now enforced on every admin route), and
> `rez-admin` (`LoginPage`, an in-memory-only Zustand auth store, `Authorization: Bearer` on every
> request, route gating via `RequireAuth`, and a Users page — list, admin invite, admin
> role/newsletter edit, and self profile edit — honoring the asymmetric API surface: an admin can
> only change another user's `role`/`newsletter_opt_in`, never `name`/`email`, and a user editing
> themselves can only change `name`/`newsletter_opt_in`, never `role`/`email`). Resource deletion
> is now a soft delete (`Resource.active`, invariant 13) rather than a hard delete, after a bug
> where a resource's `ON DELETE CASCADE` join rows could orphan a reservation's resource
> references and throw on later hydration. `13_rez-pagination.md` added offset/limit pagination,
> per-resource filtering, and sorting to all four listing use cases (Reservations, Users,
> Newsletter Subscribers, Resources) — `findPage()`/`countPage()` on each repository, filter →
> sort → paginate in one SQL query, `findAll()` left untouched for its other callers (e.g.
> `BroadcastUseCase`). `rez-starter`'s HTTP query-param wiring for this
> (`rez-starter-04_pagination_DONE.md`) is done too: all four list routes (`GET /api/reservations`,
> `/api/users`, `/api/newsletter/subscribers`, `/api/resources`) parse `offset`/`limit`/`sort`/`dir`
> + their per-resource filter and return `{"items": [...], "total": N}` instead of a bare array.
> `rez-admin` (`14_pagination-filtering-sorting.md`) consumes this end-to-end too: Reservations,
> Users, Subscribers, and Resources pages moved from fetch-everything-then-filter/sort
> client-side to server-driven pagination/filtering/sorting, via a `Page<T>` API type,
> `usePagination`/`useDebouncedValue` hooks, and a shared `Pagination` component (rendered above
> the list, with a "Per page" size selector) — closing out the pagination feature across all
> three repos. Platform extensions (payments, credits, subscriptions) not yet built.

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
- `Resource` — entity. Fields: `id`, `type`, `name`, `capacity`, `attributes`, `active` (bool, default
  `true`). `deactivate(): self` — immutable updater. Deletion is soft (invariant 13): `resources` rows
  are never removed, only deactivated, so the `reservation_resources`/`availability_*` `ON DELETE
  CASCADE` FKs stay harmless and existing reservations never lose their resource references.
- `ResourceId` — UUID v4 value object
- `ResourceType` — value object wrapping a lowercase slug string
- `ResourceCollection` — immutable collection

#### Availability (COMPLETE)
- `AvailabilityRule` — value object. Per-resource, per-day-of-week open/close times. Optional `validFrom` and `validUntil` date bounds (both nullable `DateTimeImmutable`). A null bound means unbounded in that direction — null `validUntil` means the rule recurs forever.
- `AvailabilityOverride` — value object. Per-resource, per-date available/blocked.
- `AvailabilityWindow` — value object. Resolved available `TimeSlot[]` for a resource on a date.
- `DayOfWeek` — pure enum. Monday-first (ISO-8601). String mapping in `DayOfWeekMapper`.

#### Users (CORE — COMPLETE, `rez-users`)

Users are always present regardless of which optional features are enabled. Every client
deployment requires at least one Admin user to operate rez-admin. `UsersConfig` is a
required (not optional) part of `PlatformConfig`. No `FeatureGuard`/`requireUsers()` call
anywhere in this module — users are never gated (invariant 10); the instruction doc predated
`rez-config-update` and still described a `requireUsers()` guard that no longer exists by the
time this module was built.

- `User` — immutable entity, static factory only (`create()`/`reconstruct()`, matching
  `Reservation`/`NewsletterSubscriber`). Fields: `id`, `name`, `email`, `password`
  (`HashedPassword`), `role`, `newsletterOptIn`, `stripeCustomerId` (nullable), `createdAt` — all
  `public readonly`, **no getter methods**. The instruction doc specified `getId()`/`getName()`/
  etc., which was this codebase's convention before the step-73 cleanup; `CLAUDE.md` now
  explicitly forbids getters when a public readonly property suffices, so this module follows
  the current rule (and every other recently-built entity) instead of the stale doc. Immutable
  updaters: `withName()`, `withNewsletterOptIn()`, `withStripeCustomerId()`, `withPassword()`,
  `withRole()` (same pattern as `EmailTemplate::withContent()`). `isAdmin(): bool`.
- `UserId` — UUID v4 value object, `UuidV4Id` trait (same as `ResourceId`/`NewsletterSubscriberId`).
- `HashedPassword` — value object wrapping a bcrypt hash. `fromPlainText()` (hashes via
  `password_hash(..., PASSWORD_BCRYPT)`), `fromHash()` (DB hydration), `verify()`
  (`password_verify()`).
- `UserRole` — pure enum: `Customer`, `Admin`. String mapping in `UserRoleMapper`.
- `UserCollection` — immutable collection, same pattern as `ResourceCollection`, plus
  `findByEmail(string): ?User`.

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
| `UserRepositoryInterface` | `MysqlUserRepository` | COMPLETE (same binding note as above) |
| `PasswordResetRepositoryInterface` | `MysqlPasswordResetRepository` | COMPLETE (same binding note as above) |

#### Implemented in client repo (NOT in `rez`)

| Interface | Where implementation lives | Why |
|---|---|---|
| `MailerInterface` ⚠️ | `client-*/src/Infrastructure/Mailer/SymfonyMailer.php` | `symfony/mailer` must not be a hard dep on `rez`. **Breaking change (`rez-email-restructure`):** the port dropped `sendBookingConfirmation()`/`sendBookingCancellation()` for `sendReservationCreatedEmail()`/`sendReservationConfirmedEmail()`/`sendReservationCancelledEmail()` (see §3.5, §3.9). `rez-starter`'s `SymfonyMailer` still implements the old two-method shape and needs updating to compile against the new interface — that update is out of scope for `rez` and lives in the client repo. |
| `StripeGatewayInterface` | `client-*/src/Infrastructure/Stripe/StripeGateway.php` | `stripe/stripe-php` must not be a hard dep on `rez` |

#### Implemented in `rez` application layer

| Interface | Implementation |
|---|---|
| `TokenGeneratorInterface` | `RandomTokenGenerator` (Infrastructure/Token/) — bound directly in `config/container.php`, not left to the client, since it has no external dependency to override |

### 3.4 Application services

| Service | Purpose | Status |
|---|---|---|
| `AvailabilityService` | Capacity-aware slot availability logic used by CreateReservation + GetAvailability. Injects `ResourceRepositoryInterface`. `isSlotAvailable(ResourceId, TimeSlot, int $partySize = 1)` sums existing party sizes and checks against `resource->capacity`. `getAvailableSlots()` accepts `int $partySize = 1` and filters candidates by the same capacity rule. Both return unavailable (`false` / empty `AvailabilityWindow`) immediately for a deactivated resource (invariant 13) — this is the single place that check lives, so neither `CreateReservationUseCase` nor `GetAvailabilityUseCase` duplicates it. | COMPLETE |
| `FeatureGuard` | Throws `FeatureDisabledException` if a gated feature is not configured | COMPLETE |
| `ReservationEmailService` | Settings-gated send/log/swallow for all three reservation-lifecycle emails (`sendCreatedIfEnabled`, `sendConfirmedIfEnabled`, `sendCancelledIfEnabled`). Takes `ReservationSettings` from the caller rather than loading it — avoids a second DB read per request. Single home for invariant 11. No interface, injected as a concrete class (same pattern as `FeatureGuard`) | COMPLETE (`rez-lifecycle-email-integration`) |
| `JwtService` | JWT generation and validation using `firebase/php-jwt` v7 (HS256). `generate(UserId, UserRole): string`, `validate(string): array` (throws `InvalidTokenException` on bad signature or expiry), `extractUserId()`, `extractRole()`. No interface — injected as a concrete class (same pattern as `FeatureGuard`) | COMPLETE (`rez-users`) |
| `PartyResolver` | Resolves `Party` from either a `UserId` (authenticated) or guest fields | NOT YET BUILT |
| `PaymentResolver` | Determines payment method validity and returns `PaymentResolution` | NOT YET BUILT |
| `LoggerInterface` (PSR-3) | Injected via container. `NullLogger` default. Concrete implementation (Monolog) wired in `rez-starter`. | COMPLETE |
| `ListParamsValidator` | `Application/Validation/` (not `Application/Service/` — stateless, no dependencies, all-static, so it lives in its own namespace rather than beside the injectable services above). `static validate(?int $offset, ?int $limit, ?string $sortBy, ?string $sortDir, string[] $allowedSortColumns): void`, throws `\InvalidArgumentException`. Called as the first line of all four `List*UseCase`s below (`13_rez-pagination.md`) so validation isn't duplicated per module. | COMPLETE (`13_rez-pagination.md`) |

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
| `ListReservationsUseCase` | `ListReservationsRequest` | `ListReservationsResponse(reservations, total)` | `13_rez-pagination.md`: filters (`from`, `to`, `resourceId`, `status`, `search` against party name/email/phone) + `offset`/`limit`/`sortBy`/`sortDir` (`start`\|`end`\|`status`\|`party_name`\|`created_at`), validated via `ListParamsValidator`, executed in one SQL query via `ReservationRepositoryInterface::findPage()`/`countPage()` — the old in-memory `resourceId` filter after `findAll()` is gone. `findAll()` itself is untouched, still used nowhere else in this repo |
| `CreateResourceUseCase` | `CreateResourceRequest` | `CreateResourceResponse` | |
| `GetResourceUseCase` | `GetResourceRequest` | `GetResourceResponse` | |
| `UpdateResourceUseCase` | `UpdateResourceRequest` | `UpdateResourceResponse` | PATCH semantics — all fields nullable. No `active` field — carries the existing resource's `active` forward unchanged; there's no reactivate path anywhere in the API yet |
| `DeleteResourceUseCase` | `DeleteResourceRequest` | `DeleteResourceResponse` | Soft delete — repository's `delete()` deactivates rather than removes (invariant 13) |
| `ListResourcesUseCase` | `ListResourcesRequest` | `ListResourcesResponse(resources, total)` | `13_rez-pagination.md`: no filters (nothing to filter on yet), `offset`/`limit`/`sortBy`/`sortDir` (`type`\|`name`\|`capacity`) via `ResourceRepositoryInterface::findPage()`/`countPage()`, both preserving the `active = 1` filter (invariant 13). `findAll()` untouched — still reachable via `GetResourceUseCase` for historical lookups |
| `GetAvailabilityUseCase` | `GetAvailabilityRequest` | `GetAvailabilityResponse` | Validates resource exists (throws `ResourceNotFoundException`) then delegates to AvailabilityService, which returns an empty window for a deactivated resource (invariant 13). `GetAvailabilityRequest` accepts optional `int $partySize = 1`. |
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
| `ListSubscribersUseCase` | `ListSubscribersRequest` | `ListSubscribersResponse(subscribers, total)` | `13_rez-pagination.md`: filters (`search` against email/name, `source`) + `offset`/`limit`/`sortBy`/`sortDir` (`email`\|`name`\|`source`\|`opted_in_at`, default sort `opted_in_at ASC` preserved) via `NewsletterRepositoryInterface::findPage()`/`countPage()`. `findAll()` untouched — still `BroadcastUseCase`'s only caller |
| `RegisterUseCase` | `RegisterRequest` | `RegisterResponse(User, string $token)` | No `FeatureGuard` — users are never gated. `findByEmail()` first (catches `UserNotFoundException` to confirm availability, throws `EmailAlreadyRegisteredException` if found); saves via `UserRepositoryInterface`; if `newsletterOptIn`, also calls `SubscribeUseCaseInterface` with `SubscriberSource::Registered`; generates a JWT via `JwtService` (`rez-users`) |
| `LoginUseCase` | `LoginRequest` | `LoginResponse(User, string $token)` | Unknown email → `InvalidCredentialsException`, never `UserNotFoundException` (invariant 6). Wrong password (checked via `HashedPassword::verify()`) → same exception, same message — never reveals which check failed (`rez-users`) |
| `RequestPasswordResetUseCase` | `RequestPasswordResetRequest(email, resetBaseUrl)` | `RequestPasswordResetResponse(bool $sent)` | Unknown email → `sent: true` silently, no token generated, no email sent (never reveals existence). Known email: generates a raw token via `TokenGeneratorInterface`, stores only `SHA-256(rawToken)` via `PasswordResetRepositoryInterface` (invariant 5), emails the raw token in a URL via `MailerInterface::sendPasswordReset()` (`rez-users`) |
| `ResetPasswordUseCase` | `ResetPasswordRequest(token, newPassword)` | `ResetPasswordResponse(bool $success)` | Hashes the incoming token and looks it up (never by raw token — invariant 5); `InvalidTokenException` if not found or expired; on success, re-hashes the new password, saves the user, and deletes the reset token row (`rez-users`) |
| `GetUserUseCase` | `GetUserRequest(UserId)` | `GetUserResponse(User)` | (`rez-users`) |
| `UpdateUserUseCase` | `UpdateUserRequest(UserId, ?name, ?newsletterOptIn)` | `UpdateUserResponse(User)` | PATCH semantics via `User`'s `with*()` methods — self-service profile update, no role field (`rez-users`) |
| `ListUsersUseCase` | `ListUsersRequest` | `ListUsersResponse(users, total)` | Admin-only by convention — auth enforcement is the HTTP layer's job, not this use case's (`rez-users`). `13_rez-pagination.md`: `ListUsersRequest` gained filters (`search` against name/email, `role`) + `offset`/`limit`/`sortBy`/`sortDir` (`name`\|`email`\|`role`\|`created_at`, default sort `created_at ASC` preserved) via `UserRepositoryInterface::findPage()`/`countPage()` — first `Request` in the codebase to go from empty to populated. `findAll()` untouched |
| `AdminUpdateUserUseCase` | `AdminUpdateUserRequest(UserId, ?UserRole, ?newsletterOptIn)` | `AdminUpdateUserResponse(User)` | Role/newsletter override, PATCH semantics. Admin-only by convention — same auth-enforcement note as `ListUsersUseCase` (`rez-users`) |
| `AdminCreateUserUseCase` | `AdminCreateUserRequest(name, email, resetBaseUrl, UserRole = Customer, newsletterOptIn = false)` | `AdminCreateUserResponse(User)` | No password field — generates and hashes a random one nobody is ever told, saves the user, then delegates to `RequestPasswordResetUseCaseInterface` (reused, not duplicated) to email a real reset link. No JWT in the response — the admin isn't logging in as the new user. `newsletterOptIn: true` subscribes via `SubscriberSource::Admin`, not `Registered` |

#### Not yet built

| Use case | Module | Notes |
|---|---|---|
| `GetAdminConfigUseCase` | AdminConfig | Pure read from PlatformConfig — no DB. Returns feature flags + currency + plan summaries for rez-admin |
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

13. **Resource deletion is soft — `resources` rows are never removed.** `MysqlResourceRepository::delete()`
    runs `UPDATE resources SET active = 0`, not `DELETE`. This is load-bearing: `reservation_resources`,
    `availability_rules`, and `availability_overrides` all have `resource_id` FKs with `ON DELETE CASCADE`.
    A hard delete on `resources` would cascade-delete those child rows, and for `reservation_resources`
    specifically that orphans any reservation still referencing the resource — `Reservation`'s
    `ResourceIdCollection` requires at least one element, so `MysqlReservationRepository::loadResourceIds()`
    throws on the next hydration of that reservation (e.g. `GET /api/reservations`). Never change
    `delete()` back to an actual `DELETE`, and never drop the `ON DELETE CASCADE` FKs as a workaround —
    they're fine precisely because resource rows are permanent. `findById()` intentionally does not
    filter on `active` (deactivated resources must still resolve for historical reservations and
    `GetResourceUseCase`); `findAll()` does — `WHERE active = 1` lives in the SQL query itself, not in
    `ListResourcesUseCase`, since listing is `findAll()`'s only caller. `AvailabilityService` treats a
    deactivated resource as unbookable (see `isSlotAvailable`/`getAvailableSlots` in §3.4).

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
| `resources` | id, type, name, capacity, attributes (JSON), active (TINYINT(1), default 1 — soft-delete flag, invariant 13) |
| `reservations` | id, status, start_at, end_at, party_name, party_email, party_size, party_phone, party_external_ref, created_at |
| `reservation_resources` | reservation_id, resource_id (many-to-many join) |
| `availability_rules` | resource_id, day_of_week, open_time (CHAR 5), close_time (CHAR 5), valid_from (DATE nullable), valid_until (DATE nullable) |
| `availability_overrides` | resource_id, date, available (TINYINT) |
| `reservation_settings` | id (always 1, single row by convention), auto_confirm, auto_send_reservation_created, auto_send_reservation_confirmed, auto_send_reservation_cancelled, updated_at | Seeded via `database/seeds/schema/001_reservation_settings.sql` (`CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`) — a new numbered file rather than appended to `000_schema.sql`, per explicit instruction on this scaffold |
| `mailer_settings` | id (always 1, single row by convention), from_address, from_name, updated_at | Seeded via `database/seeds/schema/002_mailer_settings.sql`, same pattern as `reservation_settings`. Seeded defaults (`noreply@example.com` / `Rez`) are placeholders — every deployment must update them before going live |
| `email_templates` | id, subject, html (MEDIUMTEXT), created_at | Seeded via `database/seeds/schema/003_email_templates.sql` — `CREATE TABLE IF NOT EXISTS` only, no seed rows (a real collection, not a singleton settings table) |
| `users` | id, name, email, password_hash, role, newsletter_opt_in, stripe_customer_id, created_at | Seeded via `database/seeds/schema/004_users.sql` (`rez-users`). Unlike the rest of this table, ships two seed rows: a default Admin (`admin@example.com`) and a default Customer (`customer@example.com`), both with placeholder password `ChangeMe123!` — must change before going live, same convention as `mailer_settings`' defaults — each `INSERT IGNORE` on a fixed UUID, safe to re-run. Every deployment needs at least one Admin to log into rez-admin and there's no use case to bootstrap one; the Customer row exists for exercising customer-facing flows out of the box. Must exist before the not-yet-built `wallet_transactions`/`subscriptions` tables |
| `password_reset_tokens` | email (PK), token_hash (CHAR 64), expires_at | One token per email — re-request overwrites. Same file as `users` (`rez-users`) |

#### Not yet built

| Table | Purpose | Notes |
|---|---|---|
| `wallet_transactions` | id, user_id, amount (INT), currency, type, description, reservation_id (nullable, no FK), created_at | FK to users. No FK to reservations — audit trail must survive reservation deletion |
| `subscriptions` | id, user_id (UNIQUE), plan_id, status, stripe_subscription_id (UNIQUE), current_period_end, created_at | FK to users. One subscription per user — upsert by user_id |
| `stripe_events` | stripe_event_id (PK), type, payload (JSON), processed_at | PK is the Stripe event ID — provides idempotency |
| `newsletter_subscribers` ✅ | id, email (UNIQUE), name, source, opted_in_at | Upsert by email |

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
| `EmailAlreadyRegisteredException` | 409 |
| `FeatureDisabledException` | 501 |
| `InvalidCredentialsException` | 401 |
| `InvalidTokenException` | 401 |
| `ForbiddenException` (`App\Http\Middleware\ForbiddenException` — `rez-starter`'s own, not `rez`'s) | 403 |
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
GET    /api/resources                                         ← offset/limit/sort/dir, see note below
GET    /api/resources/{id}
GET    /api/resources/{id}/availability/rules
GET    /api/resources/{id}/availability/overrides
GET    /api/availability
POST   /api/newsletter/subscribe
DELETE /api/newsletter/unsubscribe
DELETE /api/reservations/{id}?token={hmac}                    ← guest self-cancellation
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/password-reset/request
POST   /api/auth/password-reset/confirm
```

Guest-facing reservation creation (via `<rez-calendar>`, not yet built) is still a design target,
not a currently-available route. Guest self-cancellation, however, is now wired end-to-end:
`CancelReservationUseCase` accepts an optional `cancellationToken` (`rez-guest-cancellation`), and
`rez-starter` exposes it as `DELETE /api/reservations/{id}?token=` — a public route, distinct from
the admin `POST /api/reservations/{id}/cancel` below, verified inside the use case rather than by
middleware. `SymfonyMailer` builds the actual link (`CANCELLATION_BASE_URL` + reservation id +
token) into the reservation-created/confirmed emails; the guest-facing confirmation page itself
(`<rez-cancel>`) is still not built in `rez-components`. See §3.2 `CancellationToken` and the
`CancelReservationUseCase` row in §3.5. There is no `/api/bookings` route: **booking** (a
`CreateBookingUseCase` orchestrator layered on top of reservations to add payment/credit/
subscription resolution before creating the reservation) is a distinct, separately-scoped concept
from **reservation** and is not yet built — whether it's needed at all is still undecided pending
the payments profile. Do not conflate the two: every route below operates on `Reservation`, not on
a `Booking` entity.

The four auth routes above are genuinely public and unauthenticated (`RegisterUseCase`/
`LoginUseCase`/`RequestPasswordResetUseCase`/`ResetPasswordUseCase`) — that's the point, they're
how a caller obtains a JWT or resets a forgotten password in the first place.

#### Always available — any authenticated user (JWT required, any role)

```
GET    /api/users/me
PATCH  /api/users/me                                          ← name + newsletter_opt_in only, never role/email
```

`AuthMiddleware` (`rez-starter`, not `rez`) verifies the JWT via `rez`'s `JwtService` and attaches
`UserId`/`UserRole` request attributes; missing/invalid token → `InvalidTokenException` → 401.

#### Always available — admin JWT required

`AdminMiddleware` (`rez-starter`) requires `UserRole::Admin` on the attribute `AuthMiddleware` set
(so it always runs after `AuthMiddleware` in the pipeline); non-admin → `ForbiddenException` → 403.

```
POST   /api/resources
PATCH  /api/resources/{id}                                    ← no active field — cannot reactivate a deleted resource
DELETE /api/resources/{id}                                    ← soft delete (active = false); 204, same as before
PUT    /api/resources/{id}/availability/rules
DELETE /api/resources/{id}/availability/rules/{day_of_week}
PUT    /api/resources/{id}/availability/overrides/{date}
DELETE /api/resources/{id}/availability/overrides/{date}
POST   /api/reservations                                      ← create; today only exercised by rez-admin's manual booking modal
GET    /api/reservations                                      ← offset/limit/sort/dir + from/to/resource_id/status/search, see note below
GET    /api/reservations/{id}
POST   /api/reservations/{id}/confirm
POST   /api/reservations/{id}/no-show
POST   /api/reservations/{id}/cancel                          ← admin cancellation (no token)
POST   /api/reservations/{id}/send-created-email              ← manual resend; bypasses ReservationSettings, unswallowed mailer failures
POST   /api/reservations/{id}/send-confirmed-email             ← same
POST   /api/reservations/{id}/send-cancelled-email             ← same
GET    /api/newsletter/subscribers                           ← offset/limit/sort/dir + search/source, see note below
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
GET    /api/users                                             ← offset/limit/sort/dir + search/role, see note below
PATCH  /api/users/{id}                                        ← role + newsletter_opt_in only, never name/email
POST   /api/admin/users                                      ← AdminCreateUserUseCase; no password field, forces a reset link via RequestPasswordResetUseCase
GET    /api/admin/config                                     ❌ still not wired — blocked on GetAdminConfigUseCase (rez-admin-config)
```

`rez-starter-04_pagination_DONE.md` wired `13_rez-pagination.md`'s library-side capability (see §3.5) onto
all four list routes above (`GET /api/reservations`, `/api/resources`, `/api/users`,
`/api/newsletter/subscribers`): each now has a `RequestFactory` parsing `offset`/`limit`/`sort`/`dir`
plus its per-resource filter (`status`/`search` on reservations, `role`/`search` on users,
`source`/`search` on subscribers, no filters on resources beyond pagination/sort) and each now
returns `{"items": [...], "total": N}` instead of a bare array — a breaking response-shape change
for any existing caller. Range/allowlist validation stays entirely in `rez`'s `ListParamsValidator`;
`rez-starter` only does type coercion (`App\Http\Scalar`) and maps the resulting
`\InvalidArgumentException` to 422 like every other domain exception.

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

**Target design — not implemented yet.** `GET /api/admin/config` doesn't exist in `rez-starter`
(blocked on `GetAdminConfigUseCase`/`rez-admin-config`, see §9). `LoginPage` does not fetch it
today; it just logs in and navigates to `/`. The Sidebar's nav items are a static list, not
config-driven. This section describes the intended shape once that endpoint is built:

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
| Users (core) | ✅ | ✅ (9 use cases + JwtService) | ✅ | ✅ (`rez-users`) |
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
13. `rez-users` — **COMPLETE** (see `docs/CONTEXT.md` step 81). User domain (`User`, `UserId`,
    `HashedPassword`, `UserRole`, `UserCollection`), `JwtService` (firebase/php-jwt v7, HS256),
    `RandomTokenGenerator`, four auth use cases (Register, Login, RequestPasswordReset,
    ResetPassword) and four user-management use cases (GetUser, UpdateUser, ListUsers,
    AdminUpdateUser), `MysqlUserRepository` + `MysqlPasswordResetRepository`. No `FeatureGuard`
    anywhere in this module — the instruction doc predated `rez-config-update` and still
    specified `$guard->requireUsers()` at the top of every use case, a method that no longer
    exists (users are never gated). `firebase/php-jwt` pinned to `^7.0`, not the doc's `^6.0` —
    the entire 6.x branch is flagged by `composer audit` (advisory `PKSA-y2cr-5h3j-g3ys`); 7.x
    resolves clean with no API changes needed. `User` uses `public readonly` properties with
    `with*()` immutable updaters, not the doc's `getId()`/`getName()`-style getters — matches
    `CLAUDE.md`'s getter rule and every other entity built since the step-73 cleanup.
    **Ad hoc follow-up** (see `docs/CONTEXT.md` step 82): `AdminCreateUserUseCase` — admin
    creates a user without ever setting a password; a random one is generated and immediately
    discarded (never told to anyone), and the new user is forced through
    `RequestPasswordResetUseCaseInterface` (reused, not duplicated) to set their own via emailed
    link. `newsletterOptIn: true` subscribes via `SubscriberSource::Admin`. Also discussed but
    **not built**: registration confirmation email — deferred; full double opt-in (verification
    required before login) is the agreed target for a future separate branch, not the simpler
    "welcome email only" variant, whenever this is picked back up.
    **`rez-starter` follow-up (not done here, separate repo):** auth routes
    (`/api/auth/register`, `/login`, `/password-reset/request`, `/password-reset/confirm`),
    an admin `POST /api/admin/users` route for `AdminCreateUserUseCase`, JWT + admin middleware,
    `/api/users/*` routes, `CANCELLATION_BASE_URL`/`JWT_SECRET`/`CANCELLATION_SECRET` env wiring,
    container bindings for `UserRepositoryInterface` → `MysqlUserRepository` and
    `PasswordResetRepositoryInterface` → `MysqlPasswordResetRepository`.
- `rez-resource-soft-delete` — **COMPLETE**, ad hoc (no `docs/instructions/NN_*` file). Bug found
  via `rez-admin`: hard-deleting a `Resource` cascade-deleted its `reservation_resources` rows
  (`ON DELETE CASCADE`), orphaning any reservation still referencing it —
  `MysqlReservationRepository::loadResourceIds()` then threw on that reservation's next hydration
  (e.g. a plain `GET /api/reservations`), independent of any `resource_id` filter and deterministic
  the moment such a resource was deleted, not a race condition. Fixed by making resource deletion
  soft — see invariant 13 (§3.6) for the full mechanism. `rez-starter`: `ResourceSerializer` now
  includes `active`; `DELETE /api/resources/{id}` keeps its existing `204` no-body contract, soft
  delete is transparent at the HTTP layer. `rez-admin`: `Resource` type gained `active: boolean`,
  omitted from `create`/`update` request bodies (the API rejects/ignores it there); delete confirm
  copy softened since deletion no longer destroys reservation history.
  `docs/instructions/11_delete-resource-notifications.md` (warn about affected parties before
  deleting) was **still needed** despite this fix — it solves a data-integrity problem, not the
  separate operational one of a resource having real upcoming reservations at the moment it's
  deactivated. Since built (steps 11-13, `rez-admin` only) — see `docs/CONTEXT.md` for the
  cancel-based approach actually shipped, which needed no new `rez-starter` endpoint.
14. `rez-payments` — StripeGatewayInterface, StripeEventRepository, webhook use case
15. `rez-admin-config` — GetAdminConfigUseCase (pure read from PlatformConfig, no DB; features map excludes users)
16. `rez-credits` — Wallet, WalletTransaction, wallet use cases
17. `rez-subscriptions` — Subscription, Plan, subscription use cases
18. `rez-booking` — CreateBookingUseCase, CancelBookingUseCase, PartyResolver, PaymentResolver
19. `rez-deprecate-handlers` — **SUPERSEDED** (Handler layer removed entirely rather than deprecated — see step 73 in `docs/CONTEXT.md`)

### `rez-starter`
- ✅ Docker stack (PHP-FPM + Nginx + MySQL + Mailpit)
- ✅ Slim bootstrap, PHP-DI wiring, full route surface
- ✅ Complete exception → HTTP status map (now also `EmailTemplateNotFoundException` → 404)
- ✅ `PlatformConfig`/`MailerConfig`/`UsersConfig` construction and container wiring fully synced
  against `rez`'s `rez-config-update` (`fix/sync-platformconfig-userssettings`): `MailerConfig`
  now only takes `cancellationBaseUrl`, `UsersConfig` (required — `jwtSecret`, `cancellationSecret`)
  is bound standalone alongside it, `reservations` argument long gone, and
  `ReservationSettingsRepositoryInterface` / `MailerSettingsRepositoryInterface` /
  `EmailTemplateRepositoryInterface` / `UserRepositoryInterface` /
  `PasswordResetRepositoryInterface` are all bound to their Mysql implementations. Verified via
  `composer test-api` (was silently 422-ing on every reservation create before this fix, since
  the container was still constructing the old, no-longer-existent `MailerConfig` shape)
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
- ✅ Guest self-cancellation route — `DELETE /api/reservations/{id}?token=` (public,
  `feat/guest-cancellation-route`); `SymfonyMailer` now injects `MailerConfig` and builds the
  actual cancellation link (`cancellationBaseUrl` + reservation id + token) into the
  reservation-created/confirmed emails; `InvalidTokenException` newly mapped to 401 (was
  documented before this but not actually wired in `bootstrap/app.php`). The `<rez-cancel>`
  confirmation page the link points to still isn't built in `rez-components`
- ✅ Auth + user routes (`feat/wire-user-usecases`) — `POST /api/auth/{register,login,
  password-reset/request,password-reset/confirm}`, `GET`/`PATCH /api/users/me`,
  admin `GET /api/users` / `PATCH /api/users/{id}` / `POST /api/admin/users`
- ✅ Auth middleware (`AuthMiddleware`) / admin middleware (`AdminMiddleware`) — built as part of
  the same PR, since `/api/users/me` needs to know who's calling. Retrofitted onto every
  previously-unprotected admin route (resources writes, reservations, settings, email templates,
  newsletter admin ops), not just the new user routes — every one of those now returns 401
  without a JWT and 403 for a non-admin JWT
- ✅ Resource soft delete (`rez-resource-soft-delete`, see the `davidrubydev/rez` list above) —
  `ResourceSerializer` includes `active`; `DELETE /api/resources/{id}` keeps its existing `204`
  no-body contract, soft delete is transparent at the HTTP layer
- ❌ `StripeGateway` implementation
- ❌ Booking routes, feature-gated routes (payments/credits/subscriptions) — booking's
  orchestrator use cases (`CreateBookingUseCase`/`CancelBookingUseCase`) don't exist in `rez`
  yet; payments/credits/subscriptions aren't built in `rez` or `rez-starter` at all

### `rez-demo`
- ❌ Not initialised (init from rez-starter, local Docker only, for API testing)

### `rez-components`
- ❌ `<rez-calendar>` — not started
- ❌ `<rez-cancel>` — not started
- ❌ `<rez-checkout>` — not started
- ❌ `<rez-account>` — not started

### `rez-admin`
- ✅ Project scaffold (Vite + React + TypeScript + Tailwind, Vitest, Dockerfile + nginx)
- ✅ AppLayout + Sidebar. Feature-gating via `/api/admin/config` is still just a target design,
  not implemented — that endpoint doesn't exist yet (`GetAdminConfigUseCase`/`rez-admin-config` is
  still ❌, see the `davidrubydev/rez` list above), so `useConfig()` 404s against a real backend
  and the Sidebar's nav items are currently just a static list, not actually config-driven yet.
  Users is always shown (core, never gated); a future Payments/Subscriptions entry is what would
  need the real feature-gating once `/api/admin/config` exists
- ✅ Resources page — list, create, edit, delete; availability rules panel + rule modal; overrides panel + override modal; sorting (type/name/capacity/attributes)
- ✅ Reservations page — list with date-range filter, resource name lookup, server-side search
  (party name/email/phone) + resource/status filters, server-side sorting (date/status/party
  name/created — resource is display-only, not sortable server-side), server-driven pagination;
  detail modal with confirm/no-show/cancel actions; manual booking modal
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
- ✅ Shared UI: SortHeader, ConfirmDialog, DateRangeFilter, ErrorBanner, ExportModal, Modal, PageHeader, Pagination, StatusBadge, SlotPicker, EditableListPanel (now also takes an optional `renderRowExtra` slot for per-row actions beyond edit/delete), Button, TextInput, TimeInput, Select, FormField, FormActions, RowActions, SearchInput, EmptyTableRow, Toggle, RichTextEditor
- ✅ Shared hooks: useAsyncData, useConfig, useSortable, useConfirmDelete, useSyncedList, useDebouncedValue, usePagination
- ✅ Component/hook dedup pass — merged the near-duplicate AvailabilityRulesPanel/AvailabilityOverridesPanel into EditableListPanel, consolidated day-of-week data into lib/days.ts, removed duplicated UTC time-formatting and empty-state table markup
- ✅ API client modules: resources, reservations, availability, newsletter, config, reservationSettings, mailerSettings, emailTemplates, auth, users
- ✅ Reservation detail modal — resend-lifecycle-email buttons (`send-{created,confirmed,cancelled}-email`), one shown at a time keyed off status
- ✅ Auth (`14_auth-login.md`) — `LoginPage` (standalone, no `AppLayout`), a Zustand auth store
  that's in-memory only (`token`, `user`, `setAuth`, `setUser`, `clearAuth` — never
  `localStorage`/`sessionStorage`, so a page refresh always requires re-login, by design). `api/
  client.ts` attaches `Authorization: Bearer <token>` from the store to every request and calls
  `clearAuth()` uniformly on any `401`. `router/RequireAuth.tsx` wraps every route except
  `/login`; clearing auth (401 or explicit logout) alone is enough to trigger the redirect on next
  render, no manual `navigate()` needed outside `LoginPage` itself.
- ✅ Users page (`15_users-page.md`) — `UsersPage` (list, server-side search/sort/pagination —
  see `14_pagination-filtering-sorting.md` below), `AddUserModal` (admin invite via `POST
  /api/admin/users`, no password field since the backend always force-emails a reset link),
  `EditUserModal` (admin editing another user — name/email shown read-only, only
  `role`/`newsletter_opt_in` are actually editable, matching `PATCH /api/users/{id}`'s real
  contract), `EditProfileModal` (self-edit via `PATCH /api/users/me` — only `name`/
  `newsletter_opt_in`, role/email untouchable; reads/writes `useAuthStore` directly rather than
  taking a user prop, and calls `setUser()` on save so the Sidebar reflects the change without a
  reload). Wired to a click on the Sidebar's user-info block. Users nav entry is always visible
  (core, never feature-gated).
- ✅ Non-admin login gate (`fix/non-admin-login-gate`) — `POST /api/auth/login` is public for any
  role, so `LoginPage` checks `user.role` right after a successful login and shows a clear "no
  admin access" message instead of `setAuth`/`navigate`-ing into a session that would just hit a
  wall of `403`s on every admin-only page (`client.ts` only clears auth on `401`, not `403`). UX
  check only — `rez-starter`'s `AdminMiddleware` remains the actual security boundary.
- ✅ Server-driven pagination, filtering, sorting (`14_pagination-filtering-sorting.md`) —
  Reservations, Users, Subscribers, and Resources pages moved from fetch-everything-then-filter/
  sort client-side to server-driven `offset`/`limit`/`sort`/`dir` (+ per-resource filters), via a
  new `Page<T>` API type, a `usePagination`/`useDebouncedValue` hook pair, and a shared
  `Pagination` component. Mutating actions on these four pages now call `reload()` instead of
  patching a local array, so a row correctly drops off the current page when it no longer matches
  the active filter. Reservations folded its separate name/email filters into one server-side
  `search` box and dropped resource-name sorting (not in the API's sortable set); Users gained a
  new role filter. `AffectedReservationsGate`/`AvailabilityRuleModal`/`AvailabilityOverrideModal`/
  `BroadcastPanel`/`ManualBookingModal`/`SendEmailTemplateModal` and the Users/Subscribers
  `ExportModal` callbacks still fetch the full collection (no `offset`/`limit`), relying on "no
  limit param → everything" as the documented backward-compatible default. Verified end-to-end
  against `rez-starter-04_pagination_DONE.md`. Follow-up UX tweak: `Pagination` moved above the
  table/list on all four pages (was below), and gained a "Per page" size selector (10/20/50/100)
  wired to `usePagination`'s `setLimit` + `reset()`.
