# Rez — System Architecture Context

> **Purpose:** This document gives a complete architectural picture of the Rez ecosystem
> to any LLM or developer starting work on any part of the system. It is a living document —
> update it as implementation progresses. It intentionally avoids implementation details
> (specific SQL queries, PHP syntax) and focuses on structure, contracts, and invariants.
>
> **Last updated:** November 2026
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
> the guest-facing `<rez-cancel>` confirmation page (`rez-components`) is now built too — see
> §5 and `docs/CONTEXT.md` step 13, on branch `feature/rez-cancel`, not yet merged.
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
> three repos. `14_rez-sessions.md` added `Session` — a discrete, admin-created, fixed-length
> class occurrence (Pilates, cycling, massage) — as the unit customers book against for
> class-type resources, closing a gap where `AvailabilityService` never verified a submitted
> `TimeSlot` matched anything specific for such resources. `Resource.defaultDurationMinutes` and
> `Reservation.sessionId` were added alongside it, and `CreateReservationUseCase` gained a
> session-booking path (session is the sole source of truth for resourceId/TimeSlot on that
> path, body-supplied values are ignored) and `CancelSessionUseCase` cascade-cancels every
> reservation on a cancelled session via `BulkCancelReservationsUseCase`. `rez-starter`
> (`rez-starter-05_sessions.md`) wired the five HTTP routes end-to-end (`GET /api/sessions`,
> `POST /api/sessions/{id}/reservations` public; `POST /api/admin/sessions`, `GET .../{id}`,
> `POST .../{id}/cancel` admin) and `default_duration_minutes` through the resource endpoints.
> `rez-admin` (`15_sessions.md`) built the full admin UI end-to-end too: Sessions list/detail
> pages, a quick-add modal, an "+ Add reservation" flow booking directly into a session, and
> cancel-with-affected-reservations reusing the same `AffectedReservationsModal` the resource/
> rule/override delete flows use — verified live against a running Docker stack, not just
> component tests. `rez-components`' class-booking flow (a session picker in `<rez-calendar>`) is
> the only piece of this scaffold still not built. `feature/delete-user` added the ability to
> delete a user end-to-end across all three repos — hard delete (no FK anywhere references
> `users.id`), guarded against deleting yourself or the last remaining admin.
> `feature/resource-published-flag` added a `Resource.published` bool, orthogonal to the existing
> `active` soft-delete flag — public listings (`GET /api/resources` with no admin token) exclude
> unpublished resources, an authenticated admin sees everything, and admins can set it either way
> on create/update. `feature/reservation-check-in` added a `ReservationStatus::CheckedIn` case and
> a nullable `Reservation.checkedIn` timestamp — an admin-only action recording when a party
> actually showed up, reachable from `Confirmed` or `NoShow` (so a no-show can be corrected by
> checking in later), with `markNoShow()` reachable from `CheckedIn` too so the two states toggle
> back and forth, clearing `checkedIn` on the way back to `NoShow`. `feature/db-migrations`
> replaced `database/seeds/schema/` with classic up-only, timestamp-ordered SQL migrations tracked
> in a `schema_migrations` table (`MigrationRepositoryInterface`/`RunMigrationsUseCase`/
> `BaselineMigrationsUseCase`) — every prior schema change needed a manual `ALTER TABLE` on every
> deployed database since the old seed files only ever used `CREATE TABLE IF NOT EXISTS`; a new
> migration file now just runs itself. `rez-starter`'s Docker entrypoint runs `composer migrate`
> on every container start. Also fixed a real, previously-undetected bug this surfaced: the
> integration test harness's hand-duplicated schema was missing FK constraints production
> actually has, letting `MysqlReservationRepositoryTest`/`MysqlAvailabilityRepositoryTest`/
> `MysqlSessionRepositoryTest` pass without ever inserting a real `resources` row.
> `feature/upcoming-reservations-filter` added `startsBefore` (`start_at <=`, distinct from `to`'s
> `end_at <=` so a long reservation starting soon isn't excluded) and `excludeStatuses`
> (`r.status NOT IN (...)`, letting a caller exclude several statuses in one call) to
> `ListReservationsUseCase`/`ReservationRepositoryInterface` — built for a rez-admin "reservations
> starting soon" view.
> `feature/send-email-icon-users-subscribers` (`rez-admin` only, no backend change) added a mail
> icon to Users/Subscribers rows that opens the existing `SendEmailTemplateModal` pre-filled with
> that row's email, reusing `POST /api/admin/email-templates/{id}/send` — the modal's `template`
> prop became optional so it can offer its own template picker when opened outside the Custom
> Emails list. `feature/session-broadcast-button` (`rez-admin` only, no backend change) removed the
> manual "pick a resource, type a date/time" broadcast panel from the Newsletter page and replaced
> it with a "Send broadcast" action on `SessionRow`/`SessionDetailModal`, sourcing
> `POST /api/newsletter/broadcast`'s `resource_name`/`resource_date` straight from the `Session`
> being viewed instead of free-typed fields. `feature/error-codes` (cross-repo: `rez` + `rez-starter`
> + `rez-admin`) gave every exception that can reach the HTTP boundary a stable, machine-readable
> `code` — see §4's exception table below — so `rez-admin` can translate error messages via
> i18next instead of showing raw backend text. `feature/mobile-responsive` (`rez-admin` only, no
> backend change) made the admin SPA usable below tablet width: the sidebar becomes an off-canvas
> drawer behind a hamburger button under the `lg` breakpoint, and every listing table
> (Reservations, Resources, Users, Sessions, Subscribers, and the resource-scoped
> rules/overrides/custom-email panels) collapses into a stacked card layout under `md`, via a
> shared `.responsive-table` CSS pattern rather than a bespoke component per page.
> `feature/timetable-calendar-view` (cross-repo: `rez-admin` + `rez` + `rez-starter`) adds a
> day-at-a-glance timetable to the Sessions and Reservations pages, as collapsed-by-default
> sections above each page's existing list rather than a new route — sessions and continuous
> resources are the same visual problem (resource rows against an hour axis for one day), so both
> share a `DateStrip` + `HourTimeline` pair. It widened the **public** `GET /api/sessions`:
> `resource_id` is now optional (joined by a comma-separated `resource_ids`, the two combining) so
> one request covers every resource, and each session gained a `booked_count` — summed party size
> of non-cancelled reservations, the measure `CreateReservationUseCase` enforces capacity against,
> fetched in one aggregate query rather than the per-session detail lookups the old shape forced.
> The endpoint keeps the `OptionalAuthMiddleware` visibility rule `GET /api/resources` already
> established — counts are public, but sessions belonging to an unpublished resource are returned
> only to an authenticated admin, and a deactivated resource's sessions are hidden from everyone.
> `feature/newsletter-unsubscribe-token` closed the same kind of gap `rez-guest-cancellation`
> closed for reservations: `DELETE /api/newsletter/unsubscribe?email=` was public with no proof
> of ownership at all — anyone could unsubscribe any guessed email. `UnsubscribeToken` (a
> `CancellationToken`-shaped HMAC keyed on the subscriber's email) is now generated per-subscriber
> inside `BroadcastUseCase` and threaded through `MailerInterface::sendNewClassNotification()`;
> `UnsubscribeUseCase` accepts it as an optional `token` on `UnsubscribeRequest` — `null` keeps
> rez-admin's existing no-token subscriber-removal call working unchanged, non-null is verified
> before deletion. Only built in `rez` so far — `rez-starter` still needs the route/query-param
> wiring, the `MailerConfig::unsubscribeBaseUrl` env binding, and the link in
> `new-class-notification.html.twig`, and `rez-components` still needs the guest-facing
> `<rez-unsubscribe>` landing page itself (same relationship `<rez-cancel>` has to guest
> cancellation, both still open).
> `feature/newsletter-unsubscribe-confirmation-email` closed the last asymmetry between the
> subscribe and unsubscribe flows: `SubscribeUseCase` already sent a confirmation email
> (`sendSubscriptionConfirmedEmail`) but `UnsubscribeUseCase` sent nothing. It now sends
> `MailerInterface::sendUnsubscribeConfirmedEmail(string $email, ?string $name)` — no token
> parameter needed, since the recipient is already unsubscribed by the time it sends — on any
> actual deletion, whether reached via the no-token (rez-admin) or verified-token (guest) path.
> Only built in `rez` so far — `rez-starter` needs a `SymfonyMailer::
> sendUnsubscribeConfirmedEmail()` implementation and an `unsubscribe-confirmed.html.twig`
> template.
> Platform extensions (payments, credits, subscriptions) not yet built.

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
- `Reservation` — entity. States: `Pending → Confirmed → Cancelled | NoShow | CheckedIn`, with
  `NoShow ⇄ CheckedIn` toggling in both directions (`feature/reservation-check-in`).
- `ReservationId` — UUID v4 value object
- `ReservationStatus` — pure enum (no backing values)
- `ReservationCollection` — immutable collection
- `Reservation.checkedIn` — nullable `DateTimeImmutable` (`feature/reservation-check-in`).
  Timestamp of the most recent `checkIn()` call; `null` until an admin checks the reservation in.
  `checkIn()` is reachable from `Confirmed` or `NoShow` and sets it to `Utc::now()`; `markNoShow()`
  is reachable from `Confirmed` or `CheckedIn` and, when coming from `CheckedIn`, resets it to
  `null` — the column tracks "checked in right now," not a historical log of every check-in.
- `TimeSlot` — immutable value object. `start < end` enforced. Adjacent slots do NOT overlap.
- `Party` — immutable value object. Fields: `name`, `email`, `size`, `phone`, `externalRef`.
  `externalRef` is a nullable opaque string — platform layer sets it to `userId.toString()`
  for authenticated bookings, null for guests. The library never interprets it.
- `Reservation.sessionId` — nullable `SessionId` (`rez-sessions`). Same boundary as
  `Party.externalRef`: the library stores and returns it but never interprets it. Set when a
  reservation is booked against a `Session` (see below); null for reservations against
  continuous `AvailabilityRule`-governed resources (tables). No FK on the `session_id` column —
  same reasoning as `wallet_transactions.reservation_id`.
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
  `true`), `defaultDurationMinutes` (nullable int, `rez-sessions`), `published` (bool, default
  `true`, `feature/resource-published-flag`). Deletion is soft (invariant 13): `resources` rows
  are never removed, only deactivated, so the `reservation_resources`/`availability_*` `ON DELETE
  CASCADE` FKs stay harmless and existing reservations never lose their resource references.
  `defaultDurationMinutes` is a fallback session length for class-type resources — table-type
  resources leave it null; `CreateSessionUseCase` uses it when a session-creation request doesn't
  override the duration itself. No invariant enforced on it here — validation that a class-type
  resource *should* have one is an application-layer/admin-UI concern, per the existing pattern of
  `attributes` being an untyped bag the domain doesn't interpret. `published` is orthogonal to
  `active` — a resource can be active-and-unpublished (bookable/editable by admins, hidden from the
  public listing) independently of being deactivated. `withPublished(bool): self` is the immutable
  updater, same shape as `withAttributes()`. Unlike `active`, `published` is fully bidirectional —
  an admin can flip it back and forth via create/update, there is no one-way "can't undo" rule.
- `ResourceId` — UUID v4 value object
- `ResourceType` — value object wrapping a lowercase slug string
- `ResourceCollection` — immutable collection

#### Availability (COMPLETE)
- `AvailabilityRule` — value object. Per-resource, per-day-of-week open/close times. Optional `validFrom` and `validUntil` date bounds (both nullable `DateTimeImmutable`). A null bound means unbounded in that direction — null `validUntil` means the rule recurs forever.
- `AvailabilityOverride` — value object. Per-resource, per-date available/blocked.
- `AvailabilityWindow` — value object. Resolved available `TimeSlot[]` for a resource on a date.
- `DayOfWeek` — pure enum. Monday-first (ISO-8601). String mapping in `DayOfWeekMapper`.

Continuous per-day-of-week windows are the right model for resources like tables where any
start/end inside the window is a legal booking. They are the *wrong* model for fixed-length
classes with irregular start times set by a lecturer's own schedule — see `Sessions` below, a
parallel model for that case, not a replacement for this one.

#### Sessions (COMPLETE — `rez-sessions`)
- `Session` — immutable entity, static factory only (matches `Reservation`/`NewsletterSubscriber`,
  not `Resource`'s public-constructor style, since its state transition needs the same guard
  pattern as `Reservation::cancel()`). Fields: `id`, `resourceId`, `startTime`, `durationMinutes`,
  `capacity`, `status` — all `public readonly`, no getters. `create()` sets
  `status = SessionStatus::Scheduled`, throws `\InvalidArgumentException` if `durationMinutes <= 0`
  or `capacity <= 0`. `cancel()` — only from `Scheduled`, throws `InvalidSessionStateException` if
  already `Cancelled`. `toTimeSlot()` — `new TimeSlot($startTime, $startTime->modify("+{duration}
  minutes"))` — the one place session duration becomes a `TimeSlot`; nothing else computes it.
  A discrete, admin-created occurrence with a fixed start time — the unit customers actually book
  against for class-type resources (Pilates, cycling, massage), where `AvailabilityRule`'s
  continuous open/close window can't express "class starts at 09:15, not any time in [09:00,
  17:00]". Before this, nothing stopped a client submitting an arbitrary `TimeSlot` to
  `POST /api/reservations` for such a resource — `AvailabilityService::isSlotAvailable()` only
  checked a rule existed and nothing conflicted, never that the slot matched anything specific.
- `SessionId` — UUID v4 value object (`UuidV4Id` trait)
- `SessionStatus` — pure enum, no backing values: `Scheduled`, `Cancelled`. String mapping in
  `SessionStatusMapper`.
- `SessionCollection` — immutable collection, same shape as `ResourceCollection`.

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

**Refresh tokens (`feature/refresh-tokens`).** Login is two tokens: a short-lived access JWT
(15 min default, sent as `Authorization: Bearer`, never stored server-side) and a long-lived
opaque refresh token (30 days default) that is rotated on every use.
- `Domain/Auth/RefreshToken` — immutable entity: `id`, `familyId`, `userId`, `tokenHash`,
  `expiresAt`, `createdAt`, `usedAt?`, `revokedAt?`. `issue()` hashes the raw token itself
  (`hashOf()` = SHA-256, invariant 14) — the raw value is never held by the entity.
  `markUsed()` (throws `InvalidTokenException` if already used), `isExpiredAt()`, `isRevoked()`,
  `wasUsedWithin(seconds, now)`.
- `RefreshTokenId`, `RefreshTokenFamilyId` — UUID v4 value objects. A **family** is one login on
  one device: every rotation issues the successor under the same `familyId`, so revoking the
  family ends that login no matter how many times it has rotated.

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
- `SubscribeUseCase` sends `MailerInterface::sendSubscriptionConfirmedEmail()` (always carries an
  `UnsubscribeToken`, unlike `sendCustomEmail`'s nullable one) — but only from the branch that
  actually creates a new row; the existing-subscriber no-op return path never re-sends it. Send
  failures are logged and swallowed, never propagated to the subscribe response
  (`newsletter-unsubscribe-token` follow-up).
- `UnsubscribeUseCase` sends `MailerInterface::sendUnsubscribeConfirmedEmail()` — no token
  parameter, unlike the subscribe side, since the recipient is already unsubscribed by the time
  it sends. Only on an actual deletion, never on the not-found no-op or an invalid-token
  rejection; failures logged and swallowed, same convention as the subscribe side
  (`feature/newsletter-unsubscribe-confirmation-email`).

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
- `UnsubscribeToken` — value object, same stateless HMAC-SHA256 shape as `CancellationToken` but
  keyed on the subscriber's email string instead of a `ReservationId`, and reuses
  `UsersConfig::$cancellationSecret` rather than a new secret field — the HMAC input is
  namespaced as `"unsubscribe:{$email}"` (not the bare email) so a token from this generator
  can't collide with any other HMAC computed from the same secret (`feature/newsletter-unsubscribe-token`).
  `BroadcastUseCase` generates one per subscriber and passes it to
  `MailerInterface::sendNewClassNotification()`. `UnsubscribeUseCase` accepts an optional
  `token` on its request — same dual-path shape as `CancelReservationUseCase`: `null` keeps the
  existing no-check path (rez-admin's subscriber-removal button calls the route this way today
  and must keep working), non-null is verified against the request's `email` before the
  subscriber row is deleted, throwing `InvalidTokenException` on mismatch.

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
| `MigrationRepositoryInterface` | `MysqlMigrationRepository` | COMPLETE (`feature/db-migrations`) |
| `StripeEventRepositoryInterface` | `MysqlStripeEventRepository` | NOT YET BUILT |
| `WalletRepositoryInterface` | `MysqlWalletRepository` | NOT YET BUILT |
| `SubscriptionRepositoryInterface` | `MysqlSubscriptionRepository` | NOT YET BUILT |
| `NewsletterRepositoryInterface` ✅ | `MysqlNewsletterRepository` ✅ | COMPLETE |
| `UserRepositoryInterface` | `MysqlUserRepository` | COMPLETE (same binding note as above) |
| `PasswordResetRepositoryInterface` | `MysqlPasswordResetRepository` | COMPLETE (same binding note as above) |
| `RefreshTokenRepositoryInterface` | `MysqlRefreshTokenRepository` | COMPLETE (`feature/refresh-tokens`, same binding note as above). `save()` (upsert by id — persists `used_at`/`revoked_at`), `findByTokenHash()` (throws `InvalidTokenException` if absent), `revokeFamily()`, `revokeAllForUser()` (both only touch not-yet-revoked rows, so the first revocation time is kept) |
| `LoginFailureRepositoryInterface` | `MysqlLoginFailureRepository` | COMPLETE (`feature/login-throttling`, same binding note as above). `record(LoginThrottleKey, at)`, `failedSince(key, since)` (oldest first), `clear(key)` |
| `SessionRepositoryInterface` | `MysqlSessionRepository` | COMPLETE (`rez-sessions`) — **bound directly in `config/container.php`**, unlike every other `rez`-owned repository interface above. Per that scaffold's explicit instruction; not left for the client app to bind. |

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
| `JwtService` | JWT generation and validation using `firebase/php-jwt` v7 (HS256). `generate(UserId, UserRole): string`, `validate(string): array` (throws `InvalidTokenException` on bad signature or expiry), `extractUserId()`, `extractRole()`. No interface — injected as a concrete class (same pattern as `FeatureGuard`). Lifetime is `UsersConfig::$accessTokenTtlSeconds` | COMPLETE (`rez-users`) |
| `AuthTokenIssuer` | The single place an access JWT and a refresh token are minted together: `issue(User, ?RefreshTokenFamilyId): AuthTokens` (`accessToken`, `accessTokenExpiresAt`, `refreshToken` (raw), `refreshTokenExpiresAt`). Omit the family to start a new login; pass it when rotating. Saves only the hash. Used by `LoginUseCase`, `RegisterUseCase`, `RefreshUseCase`. Concrete class, no interface | COMPLETE (`feature/refresh-tokens`) |
| `PartyResolver` | Resolves `Party` from either a `UserId` (authenticated) or guest fields | NOT YET BUILT |
| `PaymentResolver` | Determines payment method validity and returns `PaymentResolution` | NOT YET BUILT |
| `LoggerInterface` (PSR-3) | Injected via container. `NullLogger` default. Concrete implementation (Monolog) wired in `rez-starter`. | COMPLETE |
| `ListParamsValidator` | `Application/Validation/` (not `Application/Service/` — stateless, no dependencies, all-static, so it lives in its own namespace rather than beside the injectable services above). `static validate(?int $offset, ?int $limit, ?string $sortBy, ?string $sortDir, string[] $allowedSortColumns): void`, throws `\InvalidArgumentException`. Called as the first line of all four `List*UseCase`s below (`13_rez-pagination.md`) so validation isn't duplicated per module. | COMPLETE (`13_rez-pagination.md`) |

### 3.5 Use cases (Application/UseCase/)

#### Complete

| Use case | Input | Output | Notes |
|---|---|---|---|
| `CreateReservationUseCase` | `CreateReservationRequest` | `CreateReservationResponse` | Checks availability, throws `ConflictException` if slot taken. After save: single `if ($settings->autoConfirm) { …confirmed… } else { …created… }` — generates one `CancellationToken` and sends exactly one of the two emails via `ReservationEmailService`, gated by `ReservationSettings` (`rez-lifecycle-email-integration`). `rez-sessions`: `CreateReservationRequest` gained optional `?string $sessionId` — when present, `resolveSessionSlot()` takes over entirely: loads the `Session` (`SessionNotFoundException` propagates), rejects non-`Scheduled` sessions (`InvalidSessionStateException`), derives `TimeSlot`/`resourceId` from the session itself — **any resourceId/timeslot the request body also carries is ignored**. Capacity check sums `Party::size` across `findBySessionId()`'s non-cancelled results, throws `ConflictException` if it would exceed `session.capacity`. `AvailabilityService` is skipped entirely on this path — the session's existence and status *are* the availability check |
| `CancelReservationUseCase` | `CancelReservationRequest` | `CancelReservationResponse` | Two paths, same underlying cancellation logic: admin (`cancellationToken === null`, no check) and guest (`cancellationToken !== null`, verified via `CancellationToken::verify()` against `UsersConfig::cancellationSecret`, throws `InvalidTokenException` on mismatch before any state change — `rez-guest-cancellation`). After save: sends the cancelled email via `ReservationEmailService::sendCancelledIfEnabled()` unconditionally, no actor-type branching (`rez-lifecycle-email-integration`) |
| `ConfirmReservationUseCase` | `ConfirmReservationRequest` | `ConfirmReservationResponse` | Manual admin-confirm path. After save: generates a `CancellationToken` and sends the confirmed email via the same `ReservationEmailService::sendConfirmedIfEnabled()` that `CreateReservationUseCase`'s autoConfirm branch uses — exactly one place decides whether a confirmed email goes out (`rez-lifecycle-email-integration`) |
| `SendReservationCreatedEmailUseCase` | `SendReservationCreatedEmailRequest(ReservationId)` | `SendReservationCreatedEmailResponse` | Manual escape hatch for rez-admin's "send anyway" button. Ignores `ReservationSettings` and reservation state entirely; calls `MailerInterface::sendReservationCreatedEmail()` directly, not through `ReservationEmailService`. Mailer failures propagate unswallowed |
| `SendReservationConfirmedEmailUseCase` | `SendReservationConfirmedEmailRequest(ReservationId)` | `SendReservationConfirmedEmailResponse` | Same pattern as above, for the confirmed email |
| `SendReservationCancelledEmailUseCase` | `SendReservationCancelledEmailRequest(ReservationId)` | `SendReservationCancelledEmailResponse` | Same pattern as above, for the cancelled email — no token needed |
| `MarkNoShowUseCase` | `MarkNoShowRequest` | `MarkNoShowResponse` | Reachable from `Confirmed` or `CheckedIn` (`feature/reservation-check-in`); clears `checkedIn` when coming from `CheckedIn` |
| `CheckInUseCase` | `CheckInRequest` | `CheckInResponse` | (`feature/reservation-check-in`) Reachable from `Confirmed` or `NoShow`; sets `checkedIn = Utc::now()`. Same shape as `MarkNoShowUseCase` |
| `GetReservationUseCase` | `GetReservationRequest` | `GetReservationResponse` | |
| `ListReservationsUseCase` | `ListReservationsRequest` | `ListReservationsResponse(reservations, total)` | `13_rez-pagination.md`: filters (`from`, `to`, `resourceId`, `status`, `search` against party name/email/phone) + `offset`/`limit`/`sortBy`/`sortDir` (`start`\|`end`\|`status`\|`party_name`\|`created_at`), validated via `ListParamsValidator`, executed in one SQL query via `ReservationRepositoryInterface::findPage()`/`countPage()` — the old in-memory `resourceId` filter after `findAll()` is gone. `findAll()` itself is untouched, still used nowhere else in this repo. **`createdFrom`/`createdTo`** (reservations-created-date-filter): a second, independent date range filtering `r.created_at`, distinct from `from`/`to` which filter `start_at`/`end_at` — lets an admin filter "reservations made in the last week" independent of what date they're booked for. **`startsBefore`/`excludeStatuses`** (`feature/upcoming-reservations-filter`): `startsBefore` is `start_at <=`, a bound distinct from `to` (`end_at <=`) — needed so "starts within this window" doesn't depend on how long a reservation runs; `excludeStatuses` is `r.status NOT IN (...)`, letting a caller exclude several statuses (e.g. cancelled + no-show) in one call where `status` only ever matches one exact value |
| `CreateResourceUseCase` | `CreateResourceRequest` | `CreateResourceResponse` | `CreateResourceRequest` includes `?int $defaultDurationMinutes = null` (`rez-sessions` follow-up fix) and `bool $published = true` (`feature/resource-published-flag`), both passed straight through to `Resource` |
| `GetResourceUseCase` | `GetResourceRequest` | `GetResourceResponse` | |
| `UpdateResourceUseCase` | `UpdateResourceRequest` | `UpdateResourceResponse` | PATCH semantics — all fields nullable. No `active` field — carries the existing resource's `active` forward unchanged; there's no reactivate path anywhere in the API yet. `defaultDurationMinutes` (`rez-sessions` follow-up fix) follows the same nullable-fallback pattern as `capacity`/`name` (`$request->x ?? $existing->x`) — null means "not provided" and preserves the existing value, same as `active`; there is no way to explicitly clear it back to null through this use case yet. `?bool $published = null` (`feature/resource-published-flag`) follows the identical nullable-fallback pattern — unlike `active`, this one genuinely is settable both ways through the API, `null` just means "leave as-is on this particular PATCH" |
| `DeleteResourceUseCase` | `DeleteResourceRequest` | `DeleteResourceResponse` | Soft delete — repository's `delete()` deactivates rather than removes (invariant 13) |
| `ListResourcesUseCase` | `ListResourcesRequest` | `ListResourcesResponse(resources, total)` | `13_rez-pagination.md`: `offset`/`limit`/`sortBy`/`sortDir` (`type`\|`name`\|`capacity`) via `ResourceRepositoryInterface::findPage()`/`countPage()`, both preserving the `active = 1` filter (invariant 13). **`includeUnpublished`** (`feature/resource-published-flag`): `bool`, default `false`, passed straight through to `findPage()`/`countPage()` — this use case has no opinion on *who* gets `true`, that's entirely the HTTP layer's call (same "auth is the HTTP layer's job" convention as `ListUsersUseCase`). `findAll()` untouched — has zero callers today |
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
| `SendEmailTemplateUseCase` | `SendEmailTemplateRequest(EmailTemplateId, string[] $recipients)` | `SendEmailTemplateResponse(int $sent)` | Validates every recipient up front, fails fast if any is malformed; per-recipient mailer failures are caught, logged, and skipped — same pattern as `BroadcastUseCase`. The recipient list can be anyone, not only subscribers, so each one is looked up via `NewsletterRepositoryInterface::findByEmail()` first — a hit generates a real `UnsubscribeToken` for `sendCustomEmail()`, a miss passes `null` (no unsubscribe link on an email to someone who was never subscribed) |
| `SeedDatabaseUseCase` | `SeedDatabaseRequest(string[] $seedsDirectories)` | `SeedDatabaseResponse` | Globs *.sql, executes in filename order across all directories. Now only ever pointed at `dataPath()` (optional demo data) — `seedsPath()` (schema) was removed |
| `RunMigrationsUseCase` | `RunMigrationsRequest(string[] $migrationsDirectories)` | `RunMigrationsResponse(string[] $applied)` | (`feature/db-migrations`) Wraps `MigrationRepositoryInterface::withLock()` around: ensure tracking table exists → resolve pending via `MigrationFileResolver` → execute + record each one |
| `BaselineMigrationsUseCase` | `BaselineMigrationsRequest(string[] $migrationsDirectories)` | `BaselineMigrationsResponse(string[] $baselined)` | (`feature/db-migrations`) Same shape as `RunMigrationsUseCase` but calls `markMigrationApplied()` instead of `applyMigration()` — records as applied without executing any SQL, for databases already at that schema state via the old seed files |
| `SubscribeUseCase` | `SubscribeRequest` | `SubscribeResponse` | Idempotent — returns existing subscriber if email already subscribed |
| `UnsubscribeUseCase` | `UnsubscribeRequest(email, ?token)` | `UnsubscribeResponse` | Silent success (`removed: false`) if email not found. `feature/newsletter-unsubscribe-token`: `token` optional, verified via `UnsubscribeToken` against `email` when present, throwing `InvalidTokenException` on mismatch before `delete()` — `null` preserves the prior no-check behavior admin callers rely on. `feature/newsletter-unsubscribe-confirmation-email`: on an actual deletion (either path), sends `MailerInterface::sendUnsubscribeConfirmedEmail()` via try/catch-and-log, same non-fatal-email pattern as `SubscribeUseCase` — never sent on the not-found no-op or an invalid-token rejection |
| `BroadcastUseCase` | `BroadcastRequest` | `BroadcastResponse` | Sends new-class email to all opted-in subscribers, returns sent count. `BroadcastRequest` fields: `resourceName` (string), `resourceDate` (DateTimeImmutable). `feature/newsletter-unsubscribe-token`: generates a per-subscriber `UnsubscribeToken` from `UsersConfig::$cancellationSecret` inside the send loop and passes it to `sendNewClassNotification()` |
| `ListSubscribersUseCase` | `ListSubscribersRequest` | `ListSubscribersResponse(subscribers, total)` | `13_rez-pagination.md`: filters (`search` against email/name, `source`) + `offset`/`limit`/`sortBy`/`sortDir` (`email`\|`name`\|`source`\|`opted_in_at`, default sort `opted_in_at ASC` preserved) via `NewsletterRepositoryInterface::findPage()`/`countPage()`. `findAll()` untouched — still `BroadcastUseCase`'s only caller |
| `RegisterUseCase` | `RegisterRequest` | `RegisterResponse(User, AuthTokens $tokens)` | No `FeatureGuard` — users are never gated. `findByEmail()` first (catches `UserNotFoundException` to confirm availability, throws `EmailAlreadyRegisteredException` if found); saves via `UserRepositoryInterface`; if `newsletterOptIn`, also calls `SubscribeUseCaseInterface` with `SubscriberSource::Registered`; issues access + refresh tokens via `AuthTokenIssuer` — only after the user is saved (`rez-users`, `feature/refresh-tokens`) |
| `LoginUseCase` | `LoginRequest(email, password, clientKey)` | `LoginResponse(User, AuthTokens $tokens)` | Unknown email → `InvalidCredentialsException`, never `UserNotFoundException` (invariant 6), and only after checking the password against a dummy bcrypt hash, so it takes as long as a wrong password. Wrong password (checked via `HashedPassword::verify()`) → same exception, same message — never reveals which check failed. Both record a failure against `LoginThrottleKey(email, clientKey)`; at `loginMaxFailedAttempts` failures within `loginFailureWindowSeconds` the key is refused with `TooManyLoginAttemptsException(retryAfterSeconds)` **before** the password is checked, and refused attempts aren't recorded (invariant 15). Success clears the key's failures and starts a new refresh token family via `AuthTokenIssuer` (`rez-users`, `feature/refresh-tokens`, `feature/login-throttling`) |
| `RefreshUseCase` | `RefreshRequest(refreshToken)` | `RefreshResponse(User, AuthTokens)` | (`feature/refresh-tokens`) Looks up by hash; revoked/expired → `InvalidTokenException`. Unused → marked used, successor issued in the same family. **Already used** → within `refreshTokenReuseGraceSeconds` (default 30 — tabs or bundles racing each other) a successor is issued anyway; outside it the token was replayed, so the whole family is revoked, a warning logged, and `InvalidTokenException` thrown (invariant 14). Reloads the user, so the new access token carries the user's *current* role; a deleted user → `InvalidTokenException` |
| `LogoutUseCase` | `LogoutRequest(refreshToken)` | `LogoutResponse` | (`feature/refresh-tokens`) Revokes the presented token's family. Unknown/already-revoked token → silent success (the caller is logged out either way) |
| `RequestPasswordResetUseCase` | `RequestPasswordResetRequest(email, resetBaseUrl)` | `RequestPasswordResetResponse(bool $sent)` | Unknown email → `sent: true` silently, no token generated, no email sent (never reveals existence). Known email: generates a raw token via `TokenGeneratorInterface`, stores only `SHA-256(rawToken)` via `PasswordResetRepositoryInterface` (invariant 5), emails the raw token in a URL via `MailerInterface::sendPasswordReset()` (`rez-users`) |
| `ResetPasswordUseCase` | `ResetPasswordRequest(token, newPassword)` | `ResetPasswordResponse(bool $success)` | Hashes the incoming token and looks it up (never by raw token — invariant 5); `InvalidTokenException` if not found or expired; on success, re-hashes the new password, saves the user, deletes the reset token row, and revokes every refresh token the user has (`revokeAllForUser`) — a reset is how someone takes their account back, so no earlier login may survive it (`rez-users`, `feature/refresh-tokens`) |
| `GetUserUseCase` | `GetUserRequest(UserId)` | `GetUserResponse(User)` | (`rez-users`) |
| `UpdateUserUseCase` | `UpdateUserRequest(UserId, ?name, ?newsletterOptIn)` | `UpdateUserResponse(User)` | PATCH semantics via `User`'s `with*()` methods — self-service profile update, no role field (`rez-users`) |
| `ListUsersUseCase` | `ListUsersRequest` | `ListUsersResponse(users, total)` | Admin-only by convention — auth enforcement is the HTTP layer's job, not this use case's (`rez-users`). `13_rez-pagination.md`: `ListUsersRequest` gained filters (`search` against name/email, `role`) + `offset`/`limit`/`sortBy`/`sortDir` (`name`\|`email`\|`role`\|`created_at`, default sort `created_at ASC` preserved) via `UserRepositoryInterface::findPage()`/`countPage()` — first `Request` in the codebase to go from empty to populated. `findAll()` untouched |
| `AdminUpdateUserUseCase` | `AdminUpdateUserRequest(UserId, ?UserRole, ?newsletterOptIn)` | `AdminUpdateUserResponse(User)` | Role/newsletter override, PATCH semantics. Admin-only by convention — same auth-enforcement note as `ListUsersUseCase` (`rez-users`) |
| `AdminCreateUserUseCase` | `AdminCreateUserRequest(name, email, resetBaseUrl, UserRole = Customer, newsletterOptIn = false)` | `AdminCreateUserResponse(User)` | No password field — generates and hashes a random one nobody is ever told, saves the user, then delegates to `RequestPasswordResetUseCaseInterface` (reused, not duplicated) to email a real reset link. No JWT in the response — the admin isn't logging in as the new user. `newsletterOptIn: true` subscribes via `SubscriberSource::Admin`, not `Registered` |
| `DeleteUserUseCase` | `DeleteUserRequest(actingUserId, targetUserId)` | `DeleteUserResponse` | `feature/delete-user`: hard delete via `UserRepositoryInterface::delete()` (already existed, just never had a use case). Two guards: self-delete (`actingUserId->equals(targetUserId)` → `CannotDeleteSelfException`) and last-admin (target `isAdmin()` and `countPage(role: Admin) <= 1` → `CannotDeleteLastAdminException`); customer deletions skip the admin-count query entirely. No FK anywhere references `users.id`, so unlike `resources` (invariant 13) a hard delete needed no soft-delete flag |
| `CreateSessionUseCase` | `CreateSessionRequest(resourceId, startTime, ?durationMinutes, ?capacity)` | `CreateSessionResponse(Session)` | (`rez-sessions`) `startTime` parsed as `Y-m-d H:i` with the same round-trip validation `SaveAvailabilityRuleUseCase` uses for its date bounds. Defaults `durationMinutes` from `Resource::defaultDurationMinutes` and `capacity` from `Resource::capacity` when the request omits them; throws `\InvalidArgumentException` if duration is missing from both — a class resource with no duration anywhere is a configuration error, not a silent 0 |
| `CancelSessionUseCase` | `CancelSessionRequest(SessionId)` | `CancelSessionResponse(Session, BulkCancelReservationsResponse)` | (`rez-sessions`) Cancels the session, saves, then calls `findBySessionId()` and reuses `BulkCancelReservationsUseCase` to bulk-cancel every reservation on it — deliberately not reimplementing its skip-on-invalid-state loop |
| `GetSessionUseCase` | `GetSessionRequest(SessionId)` | `GetSessionResponse(Session)` | (`rez-sessions`) Trivial, matches `GetResourceUseCase` |
| `ListSessionsUseCase` | `ListSessionsRequest(resourceIds, ?from, ?to, includeUnpublished)` | `ListSessionsResponse(SessionCollection, array<string, int> $bookedCounts)` | (`rez-sessions`) Validates every requested resource exists (`ResourceNotFoundException` propagates) then delegates to `SessionRepositoryInterface::findForResources()` — same "validate then delegate" shape as `GetAvailabilityUseCase`. **`feature/sessions-list-all-resources`**: `resourceIds` is a list whose *empty* value means every resource rather than none, so one request can span the whole timetable; `includeUnpublished` is passed straight through to the repository, with who gets `true` left entirely to the HTTP layer (same convention as `ListResourcesUseCase`). `bookedCounts` is summed **party size** per session (the measure `CreateReservationUseCase` enforces `session.capacity` against — not a count of reservation rows), fetched in one aggregate query via `ReservationRepositoryInterface::countBookedBySessionIds()` instead of N per-session lookups; sessions nobody booked are filled in as `0` here rather than by the repository |

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

14. **Refresh tokens are stored hashed, rotated on every use, and a replay revokes the login.**
    Only `SHA-256(rawToken)` is stored (same rule as invariant 5). Every refresh consumes the
    presented token and issues a successor in the same family. A consumed token presented again
    outside the reuse grace window means a copy exists somewhere else — `RefreshUseCase` revokes
    the entire family instead of guessing which holder is legitimate. Password reset revokes all
    of the user's families; deleting a user cascades to their rows (`ON DELETE CASCADE`). Access
    tokens themselves cannot be revoked — they expire (15 min default) — but they only prove
    identity: rez-starter's auth middlewares load the user on every request and authorize by the
    role in the database, so a role change or deletion takes effect on the very next request
    (`fix/auth-current-role`). The `role` claim inside the JWT is informational only — never
    authorize by it.

15. **Failed logins are throttled per email *and* client, never per email alone.**
    `LoginThrottleKey` is `SHA-256` of the normalised email plus the caller's client key (the
    client IP in rez-starter), so the `login_failures` table holds neither. Counting per email
    alone would let anyone lock a real user out by typing wrong passwords; per pair, an attacker
    only ever blocks their own attempts. A throttled key is refused before the password is
    checked — otherwise the one differing answer would still confirm a correct guess. The window
    slides: the next attempt opens when the oldest failure in it ages out, and that moment is the
    exception's `retryAfterSeconds`.

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
`feature/newsletter-unsubscribe-token` gave it a second required field, `unsubscribeBaseUrl`
(same non-empty-string validation, same "no pre-built URL" rule — the mailer implementation
builds the link from the subscriber's email and a token). This is the base URL for the
guest-facing `<rez-unsubscribe>` page (`rez-components`), same relationship
`cancellationBaseUrl` has to `<rez-cancel>`.
`UsersConfig` — COMPLETE (`rez-config-update`). Required. Fields: `jwtSecret`,
`cancellationSecret` (non-empty string, validated the same way as `jwtSecret`, always a
separate value — never shares `jwtSecret`), `accessTokenTtlSeconds` (default 900, min 1 —
renamed from `jwtTtlSeconds` in `feature/refresh-tokens`, when the default dropped from 3600),
`passwordResetTtlMinutes` (default 60, min 1), `refreshTokenTtlSeconds` (default 2592000 = 30
days, min 1), `refreshTokenReuseGraceSeconds` (default 30, min 0), `loginMaxFailedAttempts` (default 5, min 1)
and `loginFailureWindowSeconds` (default 900, min 1) (`feature/login-throttling`). This resolves the conflict the scaffold flagged:
`cancellationSecret` now lives here as originally planned, not on `MailerConfig`.
`PaymentsConfig` — COMPLETE. `currency` (non-empty string), `webhookSecret` (non-empty string).
`CreditsConfig` — COMPLETE. `minimumTopUpAmount` (int, min 1, haléře/cents), `currency` (non-empty string).
`PlanConfig` — COMPLETE. `id`, `name`, `priceAmount` (≥ 0), `currency`, `intervalDays` (min 1), `stripePriceId`. Named `PlanConfig` (not `Plan`) — it holds primitive Stripe-specific config, not a domain value object.
`SubscriptionsConfig` — COMPLETE. `PlanConfig[] $plans` (constructor promotion, no empty guard). `getPlanById(string): PlanConfig`.

```
PlatformConfig
  ├── MailerConfig          always required (cancellationBaseUrl, unsubscribeBaseUrl)
  ├── UsersConfig           always required (jwtSecret, cancellationSecret, accessTokenTtlSeconds, passwordResetTtlMinutes, refreshTokenTtlSeconds, refreshTokenReuseGraceSeconds, loginMaxFailedAttempts, loginFailureWindowSeconds)
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
| `resources` | id, type, name, capacity, attributes (JSON), active (TINYINT(1), default 1 — soft-delete flag, invariant 13), default_duration_minutes (INT nullable, `rez-sessions`), published (TINYINT(1), default 1 — public-visibility flag, orthogonal to `active`, `feature/resource-published-flag`) |
| `reservations` | id, status, start_at, end_at, party_name, party_email, party_size, party_phone, party_external_ref, created_at, session_id (CHAR(36) nullable, no FK, `rez-sessions`), checked_in (DATETIME nullable, `feature/reservation-check-in`) |
| `reservation_resources` | reservation_id, resource_id (many-to-many join) |
| `availability_rules` | resource_id, day_of_week, open_time (CHAR 5), close_time (CHAR 5), valid_from (DATE nullable), valid_until (DATE nullable) |
| `availability_overrides` | resource_id, date, available (TINYINT) |
| `reservation_settings` | id (always 1, single row by convention), auto_confirm, auto_send_reservation_created, auto_send_reservation_confirmed, auto_send_reservation_cancelled, updated_at | Created via `database/migrations/20260101000002_create_reservation_settings.sql` (`CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`) |
| `mailer_settings` | id (always 1, single row by convention), from_address, from_name, updated_at | Created via `database/migrations/20260101000003_create_mailer_settings.sql`, same pattern as `reservation_settings`. Seeded defaults (`noreply@example.com` / `Rez`) are placeholders — every deployment must update them before going live |
| `email_templates` | id, subject, html (MEDIUMTEXT), created_at | Created via `database/migrations/20260101000004_create_email_templates.sql` — no seed rows (a real collection, not a singleton settings table) |
| `users` | id, name, email, password_hash, role, newsletter_opt_in, stripe_customer_id, created_at | Created via `database/migrations/20260101000005_create_users.sql` (`rez-users`). Unlike the rest of this table, ships two seed rows: a default Admin (`admin@example.com`) and a default Customer (`customer@example.com`), both with placeholder password `ChangeMe123!` — must change before going live, same convention as `mailer_settings`' defaults — each `INSERT IGNORE` on a fixed UUID, safe to re-run. Every deployment needs at least one Admin to log into rez-admin and there's no use case to bootstrap one; the Customer row exists for exercising customer-facing flows out of the box. Must exist before the not-yet-built `wallet_transactions`/`subscriptions` tables |
| `password_reset_tokens` | email (PK), token_hash (CHAR 64), expires_at | One token per email — re-request overwrites. Same migration file as `users` (`rez-users`) |
| `refresh_tokens` | id (PK), family_id, user_id (FK → users, `ON DELETE CASCADE`), token_hash (CHAR 64, UNIQUE), expires_at, created_at, used_at (nullable), revoked_at (nullable) | `database/migrations/20260101000008_create_refresh_tokens.sql` (`feature/refresh-tokens`). One row per issued token — rotation inserts, never overwrites. Indexed on family_id and user_id for the two revocation paths. Uses `IF NOT EXISTS` because `MysqlMigrationRepositoryTest` replays migrations (see #101 in `docs/CONTEXT.md`). Expired rows are not purged yet |
| `login_failures` | id (BIGINT auto-increment PK), throttle_key (CHAR 64 — `LoginThrottleKey` hash), failed_at | `database/migrations/20260101000009_create_login_failures.sql` (`feature/login-throttling`). One row per failed login, indexed on (throttle_key, failed_at); a successful login deletes its key's rows. Rows of keys that never succeed are inert once outside the window and not purged yet. `IF NOT EXISTS` for the migration replay |
| `sessions` | id, resource_id (FK → resources, no CASCADE — resources are soft-deleted, invariant 13, so it never fires), start_time, duration_minutes, capacity, status | Created via `database/migrations/20260101000006_create_sessions.sql` (`rez-sessions`), no seed rows (a real collection, same pattern as `email_templates`) |
| `schema_migrations` | name (PK), applied_at | `feature/db-migrations`. Tracks which migration files have run — bootstrap table, created by `MigrationRepositoryInterface::ensureMigrationsTable()` itself, not a numbered migration |

#### Not yet built

| Table | Purpose | Notes |
|---|---|---|
| `wallet_transactions` | id, user_id, amount (INT), currency, type, description, reservation_id (nullable, no FK), created_at | FK to users. No FK to reservations — audit trail must survive reservation deletion |
| `subscriptions` | id, user_id (UNIQUE), plan_id, status, stripe_subscription_id (UNIQUE), current_period_end, created_at | FK to users. One subscription per user — upsert by user_id |
| `stripe_events` | stripe_event_id (PK), type, payload (JSON), processed_at | PK is the Stripe event ID — provides idempotency |
| `newsletter_subscribers` ✅ | id, email (UNIQUE), name, source, opted_in_at | Upsert by email |

#### Migrations (`feature/db-migrations` — replaces the old `database/seeds/schema/`)

Classic up-only SQL migrations, tracked in the `schema_migrations` table above, replacing the
old `CREATE TABLE IF NOT EXISTS`-only seed files — those never altered an already-existing table,
so every schema change needed a manual `ALTER TABLE` on every deployed database (dev, test, every
client). See `docs/CONTEXT.md` #95 for the full design writeup.

- **Ownership is directory-based, not number-range-based** (unlike the old seed convention below):
  `rez` owns `database/migrations/` in this repo; a client app (`rez-starter` or a `client-*` repo)
  owns its own `database/migrations/` directory. `RunMigrationsRequest`/`BaselineMigrationsRequest`
  both accept `string[] $migrationsDirectories` — the client app decides which directories to pass
  and in what order (see §4's `rez-starter` section for `bin/migrate.php`).
- **Filenames are timestamp-prefixed** (`20260101000001_create_resources_and_reservations.sql`),
  not sequentially numbered — avoids collisions between migrations authored on parallel branches,
  and doubles as the sort key (`MigrationFileResolver::resolvePending()` sorts by filename).
- **Up-only, no rollback tooling** — matches this codebase's existing "fix forward" convention (no
  rollback exists anywhere else in this repo either). A mistake gets corrected by a new migration,
  not by reverting an old one.
- **The six baseline migrations** (`20260101000001` through `20260101000006`) are the one exception
  to "no defensive guards" — they're converted 1:1 from the deleted seed files and kept idempotent
  (`IF NOT EXISTS`/`INSERT IGNORE`) since they still need to work for a genuinely fresh database.
  Every already-existing database gets **baselined** instead (`BaselineMigrationsUseCase` records
  them as applied without running their SQL). Every migration written after the baseline should
  **not** use defensive guards — `schema_migrations` is what guarantees "runs exactly once" now.
- `MysqlMigrationRepository::migrationsPath(): string` mirrors the old `MysqlDatabaseSeeder::seedsPath()`'s shape.

#### Seed directory convention (`database/seeds/data/` only — demo data, unaffected by the above)

| Range | Owner |
|---|---|
| 000–099 | `davidrubydev/rez` |
| 200+ | Client repo |

`SeedDatabaseRequest` accepts `string[] $seedsDirectories` (multiple directories, executed in order). `MysqlDatabaseSeeder::dataPath(): string` — `seedsPath()` (the old schema half) was removed, since `database/seeds/schema/` no longer exists.

---

## 4. `rez-starter` — Client App Template

Thin HTTP delivery layer. Contains no business logic. All logic lives in `rez`.

### Responsibilities
- PHP-DI container wiring (PDO, interface → implementation bindings)
- Slim 4 routes (build Request objects, call use cases, serialize responses)
- Auth middleware (validate JWT, attach UserId and UserRole to request)
- Admin middleware (enforce UserRole::Admin)
- Refresh token transport (`App\Http\Auth\RefreshTokenTransport` — cookie or JSON body, per
  `AUTH_MODE`; the one auth concern `rez` knows nothing about, see "Auth transport" in §4)
- CORS middleware (`CorsMiddleware` — `*` for everyone, credentials only for origins in
  `CORS_ALLOWED_ORIGINS`)
- Error middleware (map domain exceptions → HTTP status codes)
- Concrete `SymfonyMailer` implementing `MailerInterface`
- Concrete `StripeGateway` implementing `StripeGatewayInterface`
- Docker stack (PHP-FPM + Nginx + MySQL + Mailpit)
- `.env` management

### Exception → HTTP status code mapping

Every exception below implements `Rez\Domain\Exception\HasErrorCode` (`DomainException`'s
subclasses, `DatabaseException`, and `rez-starter`'s own `ForbiddenException`) or is matched by
`instanceof` in `rez-starter`'s `App\Http\ErrorMapper` (`feature/error-codes`). `ErrorMapper::map()`
turns any thrown exception into `{status, code, message, params}`; the JSON error body is
`{"error": message, "code": code}`, plus `"params": {...}` when non-empty (dynamic values like an
email or identifier, for the frontend to interpolate into a translated template — this repo's
`errors.*` i18next namespace, `src/lib/errors.ts`'s `getErrorMessage()`, maps `code → translated
message`). Two cases send a **fixed generic message instead of the real one** —
`DatabaseException` (can carry raw PDO/SQL text) and any unrecognized exception
(`INTERNAL_ERROR`) — the real message is still logged server-side, never surfaced to the browser.

| Exception | HTTP | Code | Params |
|---|---|---|---|
| `ResourceNotFoundException` | 404 | `RESOURCE_NOT_FOUND` | — |
| `ReservationNotFoundException` | 404 | `RESERVATION_NOT_FOUND` | — |
| `NewsletterSubscriberNotFoundException` | 404 | `NEWSLETTER_SUBSCRIBER_NOT_FOUND` | `email` |
| `EmailTemplateNotFoundException` | 404 | `EMAIL_TEMPLATE_NOT_FOUND` | — |
| `UserNotFoundException` | 404 | `USER_NOT_FOUND` | `identifier` |
| `SessionNotFoundException` | 404 | `SESSION_NOT_FOUND` | — |
| `ConflictException` | 409 | `RESERVATION_CONFLICT` | — |
| `EmailAlreadyRegisteredException` | 409 | `EMAIL_ALREADY_REGISTERED` | `email` |
| `CannotDeleteSelfException` | 409 | `CANNOT_DELETE_SELF` | — |
| `CannotDeleteLastAdminException` | 409 | `CANNOT_DELETE_LAST_ADMIN` | — |
| `FeatureDisabledException` | 501 | `FEATURE_DISABLED` | `feature` |
| `InvalidCredentialsException` | 401 | `INVALID_CREDENTIALS` | — |
| `TooManyLoginAttemptsException` | 429 (+ `Retry-After` header — `ErrorMapper::map()` returns a `headers` map the error handler applies) | `TOO_MANY_LOGIN_ATTEMPTS` | `retry_after_seconds` |
| `InvalidTokenException` | 401 | `INVALID_TOKEN` | — |
| `ForbiddenException` (`App\Http\Middleware\ForbiddenException` — `rez-starter`'s own, not `rez`'s) | 403 | `FORBIDDEN` | — |
| `InsufficientFundsException` | 422 | `INSUFFICIENT_FUNDS` | — |
| `InvalidPartyException` / `InvalidReservationStateException` / `InvalidSessionStateException` / `InvalidTimeSlotException` | 422 | `INVALID_PARTY` / `INVALID_RESERVATION_STATE` / `INVALID_SESSION_STATE` / `INVALID_TIME_SLOT` | — (one code + one generic message per exception *class*, not per the many free-text messages each can carry) |
| `\InvalidArgumentException` / `\DateMalformedStringException` | 422 | `VALIDATION_ERROR` | — |
| `Slim\Exception\HttpNotFoundException` | 404 | `ROUTE_NOT_FOUND` | — |
| `Slim\Exception\HttpMethodNotAllowedException` | 405 | `METHOD_NOT_ALLOWED` | — |
| `\UnexpectedValueException` (Stripe sig) | 400 | not yet implemented — Stripe webhook handling isn't built | — |
| `DatabaseException` | 503 | `DATABASE_ERROR` | — (message sanitized, see above) |
| Anything else | 500 | `INTERNAL_ERROR` | — (message sanitized, see above) |

### API surface

All routes prefixed `/api/`.

#### Authorization model

| Principal | Credential | How identified in rez-starter |
|---|---|---|
| Guest (public) | None | No middleware |
| Guest cancelling own reservation | HMAC token in query param | Verified in use case, not middleware |
| Refreshing / logging out | Refresh token (httpOnly cookie, or JSON body in `AUTH_MODE=token`) | Verified in `RefreshUseCase`/`LogoutUseCase`, not middleware |
| Authenticated user | JWT Bearer token | Auth middleware attaches UserId + UserRole |
| Admin | JWT Bearer token with `role: Admin` | Admin middleware enforces UserRole::Admin |

#### Always available — public (no auth)

```
GET    /api/resources                                         ← offset/limit/sort/dir, see note below
GET    /api/resources/{id}
GET    /api/resources/{id}/availability/rules
GET    /api/resources/{id}/availability/overrides
GET    /api/availability
GET    /api/sessions?resource_id=&from=&to=                   ← resource_id required, bare array (no pagination envelope)
POST   /api/sessions/{id}/reservations                        ← body: party fields only, see note below
POST   /api/newsletter/subscribe
DELETE /api/newsletter/unsubscribe
DELETE /api/reservations/{id}?token={hmac}                    ← guest self-cancellation
GET    /api/config                                            ← auth.mode + public feature details (null when off); never secrets
POST   /api/auth/register                                     ← body may add refresh_token_delivery: "cookie" (default) | "body"
POST   /api/auth/login                                        ← same
POST   /api/auth/refresh                                      ← credential: refresh cookie or body refresh_token; rotates it
POST   /api/auth/logout                                       ← 204; revokes the presented login, always clears the cookie
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
(`<rez-cancel>`) is now built in `rez-components` (branch `feature/rez-cancel`, not yet merged
— see §5 and `docs/CONTEXT.md` step 13). See §3.2 `CancellationToken` and the
`CancelReservationUseCase` row in §3.5. There is no `/api/bookings` route: **booking** (a
`CreateBookingUseCase` orchestrator layered on top of reservations to add payment/credit/
subscription resolution before creating the reservation) is a distinct, separately-scoped concept
from **reservation** and is not yet built — whether it's needed at all is still undecided pending
the payments profile. Do not conflate the two: every route below operates on `Reservation`, not on
a `Booking` entity.

The auth routes above are genuinely public — no Bearer header (`RegisterUseCase`/`LoginUseCase`/
`RefreshUseCase`/`LogoutUseCase`/`RequestPasswordResetUseCase`/`ResetPasswordUseCase`) — that's
the point, they're how a caller obtains tokens or resets a forgotten password in the first place.

**Auth transport (`feature/refresh-tokens`).** Register/login/refresh respond with
`{access_token, access_token_expires_at, user}` (`token` was renamed `access_token`). Where the
refresh token goes is the only per-deployment difference, set by `AUTH_MODE` in rez-starter's
`.env`:
- `cookie` (default) — only ever an httpOnly `rez_refresh_token` cookie (`SameSite=Strict`,
  `Path=/api/auth`, `Secure` unless `AUTH_COOKIE_SECURE=false`). Requires every page that logs in
  to be on the same site as the API (`studio.cz` + `api.studio.cz`, or a `studio.cz/api` reverse
  proxy). Asking for `refresh_token_delivery: "body"` is a 422.
- `token` — a client may also ask for `refresh_token_delivery: "body"`, getting `refresh_token`/
  `refresh_token_expires_at` in the JSON and presenting it back as body `refresh_token`. For
  booking pages on a site the cookie can't reach. Cookie delivery stays available to same-site
  clients — rez-admin always uses it.

A refresh answers through the same channel the token arrived on. A request authenticated by the
cookie must be `Content-Type: application/json` (else 403) — a cross-site `<form>` cannot send
that without a CORS preflight. `CORS_ALLOWED_ORIGINS` (comma-separated) lists origins that may
send the cookie cross-origin (echoed `Access-Control-Allow-Origin` + `Allow-Credentials: true`);
everyone else keeps the credential-less `*`. `ACCESS_TOKEN_TTL_SECONDS`/
`REFRESH_TOKEN_TTL_SECONDS` override the `UsersConfig` defaults.

**Login throttling (`feat/login-throttling`).** `POST /api/auth/login` passes `REMOTE_ADDR` as
rez's `clientKey`, so failures are throttled per email + client IP (invariant 15):
`LOGIN_MAX_FAILED_ATTEMPTS` (default 5) within `LOGIN_FAILURE_WINDOW_SECONDS` (default 900), then
**429** `TOO_MANY_LOGIN_ATTEMPTS`, `params.retry_after_seconds` and a matching `Retry-After`
header. `X-Forwarded-For` is ignored (the sender writes it); behind a reverse proxy all clients
share one address and throttling degrades to per email — never to blocking other users.
Cross-origin JS can't read `Retry-After` (not exposed via CORS), so browser clients use the body
param.

`POST /api/sessions/{id}/reservations` is deliberately narrow: the body carries only `party`
fields, never `resource_id` or a timeslot — the session id in the URL is the sole source of truth
for what's being booked (see `CreateReservationUseCase`'s session-booking path in §3.5). A client
sending `resource_id`/timeslot alongside it has those values silently ignored, not merged or
validated against — re-adding either field to this route's request parsing would reopen the exact
hole this scaffold closed. `GET /api/sessions` returns a bare array, not the `{items, total}`
envelope the four paginated list routes above use — it was never wired into
`13_rez-pagination.md`'s pagination work. **`feat/sessions-list-all-resources`**: it no longer
requires `resource_id` — that param is now optional, joined by a comma-separated `resource_ids`
(the two combine), and supplying neither means every resource the caller may see. Each session
carries a `booked_count` (summed party size of non-cancelled reservations, the measure capacity is
enforced against — not a count of reservation rows). The route stays public but gained
`OptionalAuthMiddleware`, exactly like `GET /api/resources`: sessions belonging to an *unpublished*
resource are returned only to an authenticated admin, and sessions of a deactivated resource are
hidden from everyone.

#### Always available — any authenticated user (JWT required, any role)

```
GET    /api/users/me
PATCH  /api/users/me                                          ← name + newsletter_opt_in only, never role/email
```

`AuthMiddleware` (`rez-starter`, not `rez`) verifies the JWT via `rez`'s `JwtService`, then loads
the user through `GetUserUseCase` (`App\Http\Auth\BearerTokenAuthenticator`, shared with
`OptionalAuthMiddleware`) and attaches `UserId`/`UserRole` request attributes **from the database,
not from the token's claims** (`fix/auth-current-role`) — one primary-key lookup per
authenticated request, in exchange for role changes and deletions taking effect immediately.
Missing/invalid token, or a token whose user no longer exists → `InvalidTokenException` → 401.
`OptionalAuthMiddleware` treats those same cases as anonymous instead.

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
POST   /api/admin/sessions                                   ← body: {resource_id, start_time, duration_minutes?, capacity?}
GET    /api/admin/sessions/{id}                              ← session + its reservations (GetSessionUseCase + ReservationRepositoryInterface::findBySessionId(), joined in the Serializer — not a new rez use case)
POST   /api/admin/sessions/{id}/cancel                       ← cascades to BulkCancelReservationsUseCase
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

The public-facing, drop-in UI for any rez deployment. Goal: a client site gets booking and
account features by adding a script tag and an element — no build step, no glue code — while
a client who wants their own design can still have it.

### Stack
- TypeScript + Lit (Web Components framework, ~5KB, compiles to native Custom Elements)
- Vite library mode — one Vite config per bundle (Rollup's iife/umd formats can't do multiple
  entry points in one config), each emitting ES + UMD builds
- Shadow DOM for style isolation
- CSS Custom Properties for theming (pierce Shadow DOM from host page)

### Components (built)

| Component | Responsibility | Bundle |
|---|---|---|
| `<rez-calendar>` | Resource list, date strip, slot grid / session list; fires `rez:slot-selected` | `rez.core.*` |
| `<rez-checkout>` | Party form, payment-method step, newsletter opt-in, submit, confirmation, 409 conflict handling | `rez.core.*` |
| `<rez-newsletter>` | Standalone newsletter signup widget | `rez.core.*` |
| `<rez-cancel>` | Guest cancellation confirmation (reads `id`/`token` from the URL) | `rez.cancel.*` |
| `<rez-unsubscribe>` | Newsletter unsubscribe confirmation (reads `email`/`token` from the URL) | `rez.unsubscribe.*` |
| `<rez-login>` | Email + password sign-in; once signed in, "Signed in as {name}". Follows sign-ins/outs from any other account component or tab | `rez.account.*` |
| `<rez-register>` | Account sign-up (name, email, password, newsletter opt-in); creating the account signs the visitor in | `rez.account.*` |
| `<rez-logout>` | Sign-out button, shown only while someone is signed in | `rez.account.*` |
| `<rez-account-menu>` | Header widget: "Sign in" → login dialog that can switch to sign-up; signed in → name → panel with email and `<rez-logout>`. Answers `rez:login-required` | `rez.account.*` |

### Bundles

A bundle groups components that appear on the same page, so no page loads code it never runs.

| Bundle | Contains | npm subpath |
|---|---|---|
| `rez.core.*` | `rez-calendar`, `rez-checkout`, `rez-newsletter` — used together on a booking page | `rez-components` |
| `rez.cancel.*` | `rez-cancel` — standalone page linked from emails | `rez-components/cancel` |
| `rez.unsubscribe.*` | `rez-unsubscribe` — standalone page linked from emails | `rez-components/unsubscribe` |
| `rez.account.*` | `rez-login`, `rez-register`, `rez-logout`, `rez-account-menu` (more account components to come — see below), plus the Layer 0 session layer and auth/config API calls | `rez-components/account` |

The original plan (`rez.core.js` = calendar + cancel + account, `rez.payments.js`,
`rez.full.js`) is superseded by the table above.

### Account & user features — architecture (decided November 2026; being built — session layer, `<rez-login>`, `<rez-register>`, `<rez-logout>` and a minimal `<rez-account-menu>` done)

Login, register, logout, profile, password and email changes, account deletion, credit
balance and top-up, subscription status and checkout, booking history — and whatever comes
later. Three options were weighed: many small components the client must assemble and design
around (maximum freedom, long setup), a few ready-made pages and popups (instant, inflexible),
or both. **Decision: both, as layers, where the ready-made pieces are built only out of the
smaller ones** — so there is one implementation, not two, and every client picks their own
level.

**Layer 0 — API and session core (no UI).** Typed wrappers over the API (`src/api/*`) plus a
session module, exported from the npm package. A developer who wants a completely custom UI
calls `login()`, `getWallet()` etc. directly. Costs nothing extra — the components need it
anyway.

**Layer 1 — feature components.** One element per API capability, each self-sufficient with
only `api-base`:
`<rez-login>`, `<rez-register>`, `<rez-logout>`, `<rez-password-reset>`, `<rez-profile>`,
`<rez-change-password>`, `<rez-change-email>`, `<rez-delete-account>`,
`<rez-booking-history>`, `<rez-wallet-balance>`, `<rez-topup>`, `<rez-subscription-status>`,
`<rez-subscribe>`. A client with their own design places these wherever they like — e.g.
`<rez-wallet-balance>` in their own navbar, `<rez-topup>` on their own pricing page.
Once signed in, `<rez-login>` shows a short "Signed in as {name}" summary instead of the form;
signing out is `<rez-logout>`'s job, not a button inside `<rez-login>`.

**`<rez-login>` (built, `feature/rez-login`).** Renders nothing until the stored login has been
checked (no form flash for a signed-in visitor). Form: email (`autocomplete="username"`) and
password (`current-password`), client checks before any request, button disabled while signing
in. Errors: 401 → "Wrong email or password." (email kept, password cleared and focused); 429 →
"Try again in N minutes" from `params.retry_after_seconds`, rounded up, plural-aware; any other
API error → the API's message; unreachable server → the generic message. Parts: `form`, `field`,
`label`, `input`, `field-error`, `error`, `submit`, `summary`; slot `footer` (client links such as
"Forgot password?"), shown only while signed out. No component-specific event — pages listen to
`rez:auth-changed`.

**Layer 2 — composites.** Pure arrangements of Layer 1, with no logic of their own:
- `<rez-account>` — a full account page (sections/tabs).
- `<rez-account-menu>` — a header widget: "Sign in" when logged out, the user's name with a
  dropdown when logged in; opens login or `<rez-account>` in a dialog/drawer. The zero-setup
  path is this one line:
  ```html
  <rez-account-menu api-base="https://api.studio.cz/api"></rez-account-menu>
  ```
Adding a feature later = one new Layer 1 component + one line in the composite.

**Built so far (`feature/rez-account-menu`).** `<rez-logout>`: a button (part `button`) rendered
only while authenticated; "Signing out…" while in flight. `<rez-account-menu>` (minimal): renders
nothing while `unknown`; signed out → part `sign-in` opens a native modal `<dialog>` (part
`dialog`; Escape, backdrop click or part `close` closes it) holding `<rez-login autofocus>` with
the menu's `api-base`/`lang`/`theme`/`translations` — `<rez-login>` uses `delegatesFocus`, so
the email field gets focus — and the menu's own `login-footer` slot forwarded into its `footer`
slot; the dialog closes itself once anyone signs in. Signed in → part `account` (the user's name,
`aria-expanded`) toggles part `panel` with part `email` and `<rez-logout>`; Escape (focus returns
to the name) or a click elsewhere closes it. `<rez-account>` comes later.

**`<rez-register>` and the sign-up switch (`feature/rez-register`).** `<rez-register>`: name
(`autocomplete="name"`), email (`username`), password and its repeat (both `new-password`;
a mismatch is caught before sending — the repeat never reaches the API), and a newsletter
checkbox that starts unticked (consent must be the visitor's own act). Client checks mirror the
API's rules only — name and password non-empty, a plausible email. Creating the account signs the
visitor in, so it then shows the same "Signed in as {name}" summary. 409 → "An account with this
email already exists." (everything typed kept, email focused); other errors as `<rez-login>`.
Parts as `<rez-login>` plus `checkbox`; slot `footer`. In `<rez-account-menu>`'s dialog, part
`switch` toggles between `<rez-login>` and `<rez-register>` (the dialog always reopens on
sign-in); its `register-footer` slot is forwarded like `login-footer`, and the `no-register`
attribute removes sign-up for sites whose users get accounts some other way.

**Granularity rule.** The smallest unit is a *feature*, never a UI primitive — no
`<rez-button>`/`<rez-input>`. Shipping primitives means building and maintaining a design
system. Clients customise instead through, in increasing depth:
1. `--rez-*` CSS custom properties and the `theme` presets (see Theming)
2. `lang` / `translations` (see Localization)
3. `::part()` on exposed structural parts, and named `<slot>`s for injecting content
4. on composites, a `sections` attribute that picks and orders sections
   (`sections="profile wallet subscription"`) — chosen over a growing set of `show-x`/`hide-y`
   boolean flags
5. Layer 0 for a fully custom UI

**Session layer** (built on the refresh-token design in §4 "Auth transport"; built in
`src/session/`, `feature/auth-session`):
- The access token lives in memory only. The refresh token lives where the deployment's
  `AUTH_MODE` says: the httpOnly cookie (`cookie` — the page's origin must be in rez-starter's
  `CORS_ALLOWED_ORIGINS`, and `/api/auth/*` calls use `credentials: 'include'`) or
  `localStorage` (`token` — for pages on a site the cookie can't reach; login asks for
  `refresh_token_delivery: "body"` and sends no credentials, since a page outside
  `CORS_ALLOWED_ORIGINS` gets `Access-Control-Allow-Origin: *`). The mode is read from
  `GET /api/config` at login, so a client site never configures it.
- A login record in `localStorage` (`rez:login:{apiBase}`) in **both** modes: the refresh token
  in token mode, a secret-free `{delivery: "cookie"}` marker in cookie mode. It lets a page load
  tell "signed in" from "anonymous" without a refresh that would 401 for every visitor (the
  httpOnly cookie is invisible to JS), and its `storage` events sync login/logout across tabs in
  both modes. Without storage (some privacy modes), the component that logged in holds the
  record in memory and refreshes for the others, so a session lasts as long as the page and no
  longer.
- **Login state cannot live in a module-level singleton** — not only because CLAUDE.md forbids
  shared module state: every UMD bundle carries its own copy of every module, so
  `rez.core.js` and `rez.account.js` on the same page would each have their own. Each root
  element holds a `SessionController` (a Lit reactive controller); controllers are peers over
  `rez:*` events on `document`, identically whether they share a bundle or not.
- One refresh per page: before refreshing, a controller dispatches the internal
  `rez:session-request`; a peer answers synchronously with its fresh session or with the refresh
  it already has in flight (every refresh rotates the token, so two refreshing at once would
  race). The session — access token included — then travels in `rez:auth-changed`; decided
  deliberately: it exposes the token only to scripts on the host page, which can already read
  every fetch the page makes. The server's 30-second reuse grace window absorbs what is left —
  several tabs refreshing at the same instant.
- A 401 on refresh clears the login record and announces sign-out; any other failure leaves the
  record for next time. Logout clears locally even if the server can't be reached, and a
  refresh that finishes after a logout (or a logout and a new login) is discarded. A component
  removed from the page and added back starts over rather than trusting what it held.

**Cross-component events** (all `bubbles: true, composed: true`, dispatched on `document`):
- `rez:auth-changed` — login state changed; detail is `{apiBase, session}`, where `session` is
  `{user, accessToken, accessTokenExpiresAt}` or `null`.
- `rez:login-required` — fired by any component that needs a signed-in user (`<rez-topup>`,
  `<rez-checkout>` when paying with credits, …). `<rez-account-menu>` listens and opens its
  login dialog, so no client glue code is needed; with no menu on the page, the component falls
  back to rendering an inline `<rez-login>` itself. Built: detail `{apiBase}`, **cancelable** —
  a component that takes it calls `preventDefault()`, so `dispatchLoginRequired(apiBase)`
  returns whether anyone did (the cue for the inline fallback). The menu takes it only for its
  own `api-base` and only while signed out. Exported from the account bundle for custom UIs.

**Feature detection.** Composites show credits and subscription sections only when the
deployment has them enabled. They read rez-starter's public, unauthenticated `GET /api/config`
(built, `feat/public-config`): `{auth: {mode}, payments, credits, subscriptions}`, each optional
section `null` when off, else public-safe details (currency, minimum top-up, plan list — never
Stripe price IDs or secrets). Declaring features in attributes was rejected — it reintroduces the
setup work this design avoids.

**Payments (credits top-up, subscriptions): Stripe Checkout redirect, never Stripe.js.**
`POST /api/wallet/topup` / `POST /api/subscription/checkout` return a Stripe Checkout URL; the
component redirects there. On return it reads the result from the URL query (the same
URL-reading pattern as `<rez-cancel>`) and re-checks the wallet/subscription for a few seconds,
because the Stripe webhook may land after the redirect does. No Stripe SDK on the client and
minimal PCI scope.

**Sensitive actions require the current password** — deleting the account and changing email or
password — so a session left open on a shared computer can't be used to take the account over or
delete it. The API side of this (and of these three actions at all) is not built yet.

**Bundle.** All account components ship as a new `rez.account.*` bundle
(`rez-components/account`, built in `feature/rez-login`), following the cancel/unsubscribe split.
It also exports Layer 0: `SessionController` (any object implementing Lit's
`ReactiveControllerHost` can host one — a non-Lit UI needs only `requestUpdate()`),
`onAuthChanged`, `getPublicConfig`, the raw `login`/`register`/`refresh`/`logout` calls,
`ApiError` and the types. A custom UI should sign in through `SessionController`, not bare
`login()`, so every other account component hears about it. It is separate from
`rez.core.*` so a booking page without accounts doesn't load them, and vice versa.

**Build order.** (1) rez-starter public config endpoint → (2) session layer + `<rez-login>`,
`<rez-register>`, `<rez-logout>` + `rez:auth-changed` → (3) minimal `<rez-account-menu>`,
proving the event/storage protocol end to end → (4) profile, password, email, delete →
(5) credits, then subscriptions, once their rez/rez-starter APIs exist. Progress: (1) done;
(2) session layer, login throttling across rez/rez-starter/rez-admin, `<rez-login>` and
`<rez-register>`, `<rez-logout>` done; (3) minimal `<rez-account-menu>` done, with a sign-up
switch. Next: (4).

### Component communication

Components communicate only via `rez:*` Custom Events on `document` — never via shared module
state or by reaching into each other. Built: `rez:slot-selected` (calendar → checkout),
`rez:selection-cleared`, `rez:booking-complete` (checkout), `rez:auth-changed` (session layer)
and `rez:login-required` (`<rez-account-menu>` answers it) — see above.

### Theming

46 `--rez-*` custom properties (colors, typography, radii — grouped by role in
`src/styles/theme.ts`), each with a default inside the Shadow DOM, plus a `theme` attribute
selecting a preset (`light`, `dark`, `barbie`). Spacing and layout are deliberately not
themable. Exposing `::part()`s is part of the account design above.

```css
rez-calendar {
  --rez-primary: #1D9E75;
  --rez-font: 'Inter', sans-serif;
  --rez-border-radius: 8px;
}
```

### Localization

Root elements take `lang` (falling back to `<html lang>`, then English) and a JS-only
`translations` property for partial overrides. Built-in dictionaries: `en`, `cs`. API error
messages are shown as the API returns them.

### HTML attributes

Each component takes `api-base`, pointing at the client's API. There is no compiled-in default:
```html
<rez-calendar api-base="https://studio.cz/api"></rez-calendar>
```

---

## 6. `rez-admin` — React Admin SPA

### Stack
- React + TypeScript + Vite
- Access token stored in Zustand memory only — never localStorage/sessionStorage
- Authorization: Bearer header on every request
- Session survives reloads via the httpOnly refresh cookie (`feature/refresh-tokens`):
  `RequireAuth` calls `refreshSession()` once per page load before deciding to redirect to
  `/login`; any 401 triggers one shared refresh and a single retry; logout revokes server-side.
  The app never sees the refresh token. A non-admin login is revoked immediately. A 429 lockout
  shows how many minutes to wait (`errors.TOO_MANY_LOGIN_ATTEMPTS`, pluralised from
  `retry_after_seconds` in `getErrorMessage()`; `feat/login-throttling`)

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
| Core | Login, Resources (add/edit), Sessions (list/detail/quick-add/book-reservation), Reservations list, Manual booking, Users list, Edit user, Newsletter broadcast |
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
| Sessions | ✅ (`14_rez-sessions.md`) — fixed-time class occurrence; `Resource.defaultDurationMinutes`, `Reservation.sessionId` | ✅ Create/Get/List/Cancel + session-booking path on `CreateReservationUseCase` (cascade-cancel via `BulkCancelReservationsUseCase`) | ✅ | ✅ |
| CancellationToken | ✅ value object + generation wired into 5 use cases | — | — | ✅ |
| Users (core) | ✅ (+ `RefreshToken`, `LoginThrottleKey`) | ✅ (11 use cases + JwtService + AuthTokenIssuer; login throttled) | ✅ (+ `MysqlRefreshTokenRepository`, `MysqlLoginFailureRepository`) | ✅ (`rez-users`, `feature/refresh-tokens`, `feature/login-throttling`) |
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
    `<rez-cancel>` web component (confirmation page reading the token from the URL) was a
    `rez-components` follow-up, also not built here — since built, see §5 and
    `docs/CONTEXT.md` step 13.
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
- ✅ `POST /api/auth/refresh` + `POST /api/auth/logout`, `AUTH_MODE` cookie/body refresh-token
  transport, `CORS_ALLOWED_ORIGINS` credentialed CORS (`feature/refresh-tokens`, see §4)
- ✅ Login throttling per email + client IP → 429 `TOO_MANY_LOGIN_ATTEMPTS` + `Retry-After`
  (`feat/login-throttling`, see §4)
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
  confirmation page the link points to is now built in `rez-components` (`feature/rez-cancel`)
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
- ✅ Session routes (`rez-starter-05_sessions.md`) — `GET /api/sessions` +
  `POST /api/sessions/{id}/reservations` (public), `POST /api/admin/sessions` +
  `GET`/`POST .../{id}` + `.../{id}/cancel` (admin); `ResourceSerializer` gained
  `default_duration_minutes` and the resource create/update request factories accept it;
  `SessionNotFoundException` → 404, `InvalidSessionStateException` → 422 (via the existing
  `DomainException` mapping)
- ❌ `StripeGateway` implementation
- ❌ Booking routes, feature-gated routes (payments/credits/subscriptions) — booking's
  orchestrator use cases (`CreateBookingUseCase`/`CancelBookingUseCase`) don't exist in `rez`
  yet; payments/credits/subscriptions aren't built in `rez` or `rez-starter` at all

### `rez-demo`
- ❌ Not initialised (init from rez-starter, local Docker only, for API testing)

### `rez-components`
- ✅ `<rez-calendar>` — built, see `docs/CONTEXT.md` steps 6-7, 11-12
- ✅ `<rez-cancel>` — built, see `docs/CONTEXT.md` step 13 (`feature/rez-cancel`, not yet merged)
- ✅ `<rez-checkout>` — built, see `docs/CONTEXT.md` steps 8-10, 12
- ❌ Account & user features (`rez.account.*`) — architecture decided, not started; see §5
  "Account & user features". The auth routes they need now exist, including refresh/logout
  (`feature/refresh-tokens`); next prerequisite is rez-starter's public config endpoint

### `rez-admin`
- ✅ Project scaffold (Vite + React + TypeScript + Tailwind, Vitest, Dockerfile + nginx)
- ✅ Refresh-cookie sessions (`feature/refresh-tokens`) — reloads keep the admin signed in, 401s
  refresh once and retry, logout revokes server-side (see §6)
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
  `ManualBookingModal`/`SendEmailTemplateModal` and the Users/Subscribers
  `ExportModal` callbacks still fetch the full collection (no `offset`/`limit`), relying on "no
  limit param → everything" as the documented backward-compatible default. Verified end-to-end
  against `rez-starter-04_pagination_DONE.md`. Follow-up UX tweak: `Pagination` moved above the
  table/list on all four pages (was below), and gained a "Per page" size selector (10/20/50/100)
  wired to `usePagination`'s `setLimit` + `reset()`.
- ✅ Sessions (`15_sessions.md`) — `SessionsPage` (required resource select — the list route has
  no "all resources" query — + `DateRangeFilter`, no pagination/sort, the API has neither),
  `SessionFormModal` (quick-add: resource + date + time + a required duration input, pre-filled
  from `Resource.default_duration_minutes` but always editable — `fix/session-duration-input`
  added this after resources with no default duration made session creation error out with no
  client-side recourse; capacity is still server-default-only),
  `SessionDetailModal` (metadata, embedded reservations reusing `ReservationRow`/
  `ReservationDetailModal`, a "+ Add reservation" button opening `SessionReservationModal` to book
  directly into the session via `POST /api/sessions/{id}/reservations`). Cancelling a session with
  active reservations reuses `AffectedReservationsModal` (the same component the resource/rule/
  override delete flows use) rather than a bespoke confirm — matches those flows per explicit
  design intent; falls back to a plain `ConfirmDialog` only when nothing is active.
  `ResourceFormModal` gained a `defaultDurationMinutes` field (always shown, optional — blank means
  a continuous resource, no sessions created against it). Section 3 of the instructions doc (a
  session-aware alternative flow inside `ManualBookingModal`) was skipped — explicitly marked
  optional there.
