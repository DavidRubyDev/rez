# Rez — System Architecture Context

> **Purpose:** This document gives a complete architectural picture of the Rez ecosystem
> to any LLM or developer starting work on any part of the system. It is a living document —
> update it as implementation progresses. It intentionally avoids implementation details
> (specific SQL queries, PHP syntax) and focuses on structure, contracts, and invariants.
>
> **Last updated:** June 2026
> **Implementation status:** Core library complete. Platform extensions not yet built.

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
| `davidrubydev/rez` | PHP 8.3 | Public — Packagist | Core complete; platform extensions pending |
| `davidrubydev/rez-starter` | PHP 8.3 | Public — GitHub | Working template |
| `DavidRubyDev/client-*` | PHP 8.3 | Private | One repo per client |
| `davidrubydev/rez-components` | TypeScript + Lit | TBD | Not started |
| `davidrubydev/rez-admin` | TypeScript + React | TBD | Not started |

**Dependency direction (no cycles):**
```
rez-components ──→ client-* API (HTTP)
rez-admin      ──→ client-* API (HTTP)
client-*       ──→ davidrubydev/rez (Composer)
rez-starter    ──→ davidrubydev/rez (Composer)
```

`rez` never depends on client repos. Client repos never depend on each other.

---

## 3. `davidrubydev/rez` — Core Library

### 3.1 Layer structure

```
Domain/           Pure business logic. No I/O. No framework.
Application/      Use cases, port interfaces, config, services.
Infrastructure/   MySQL implementations of port interfaces.
Handler/          DEPRECATED. Array-in/array-out adapters. Do not use in new code.
```

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

#### Resources (COMPLETE)
- `Resource` — entity. Fields: `id`, `type`, `name`, `capacity`, `attributes`
- `ResourceId` — UUID v4 value object
- `ResourceType` — value object wrapping a lowercase slug string
- `ResourceCollection` — immutable collection

#### Availability (COMPLETE)
- `AvailabilityRule` — value object. Per-resource, per-day-of-week open/close times.
- `AvailabilityOverride` — value object. Per-resource, per-date available/blocked.
- `AvailabilityWindow` — value object. Resolved available `TimeSlot[]` for a resource on a date.
- `DayOfWeek` — pure enum. Monday-first (ISO-8601). String mapping in `DayOfWeekMapper`.

#### Users (NOT YET BUILT)
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

#### Newsletter (NOT YET BUILT)
- `NewsletterSubscriber` — entity. Fields: `id`, `email`, `name?`, `source`, `optedInAt`
- `NewsletterSubscriberId` — UUID v4 value object
- `SubscriberSource` — pure enum: `Guest`, `Registered`

#### Shared
- `Currency` — pure enum: `Czk`, `Eur`, `Usd`. `getCode()` returns uppercase ISO code — used only in Domain (exceptions, `Money::__toString()`). Infrastructure serialization goes through `CurrencyMapper::toString()`.
- `Feature` — pure enum: `Payments`, `Users`, `Credits`, `Subscriptions`. Passed to `FeatureDisabledException` so gated feature names are never raw strings in use cases.
- `Money` — immutable value object. `amount: int` (haléře/cents — NEVER floats), `currency: Currency`. Methods: `add()`, `subtract()` (throws `InsufficientFundsException`), `isZero()`, `equals()`, `isGreaterThan()`, `__toString()`.
- `DateTimeRange` — shared utility, not a domain concept

### 3.3 Port interfaces (Application/Port/)

These are the contracts the library defines. Implementations live in infrastructure or client repo.

#### Implemented in `rez` infrastructure (MySQL)

| Interface | Implementation | Status |
|---|---|---|
| `ReservationRepositoryInterface` | `MysqlReservationRepository` | COMPLETE |
| `ResourceRepositoryInterface` | `MysqlResourceRepository` | COMPLETE |
| `AvailabilityRepositoryInterface` | `MysqlAvailabilityRepository` | COMPLETE |
| `DatabaseSeederInterface` | `MysqlDatabaseSeeder` | COMPLETE |
| `StripeEventRepositoryInterface` | `MysqlStripeEventRepository` | NOT YET BUILT |
| `WalletRepositoryInterface` | `MysqlWalletRepository` | NOT YET BUILT |
| `SubscriptionRepositoryInterface` | `MysqlSubscriptionRepository` | NOT YET BUILT |
| `NewsletterRepositoryInterface` | `MysqlNewsletterRepository` | NOT YET BUILT |
| `UserRepositoryInterface` | `MysqlUserRepository` | NOT YET BUILT |
| `PasswordResetRepositoryInterface` | `MysqlPasswordResetRepository` | NOT YET BUILT |

#### Implemented in client repo (NOT in `rez`)

| Interface | Where implementation lives | Why |
|---|---|---|
| `MailerInterface` | `client-*/src/Infrastructure/Mailer/SymfonyMailer.php` | `symfony/mailer` must not be a hard dep on `rez` |
| `StripeGatewayInterface` | `client-*/src/Infrastructure/Stripe/StripeGateway.php` | `stripe/stripe-php` must not be a hard dep on `rez` |

#### Implemented in `rez` application layer

| Interface | Implementation |
|---|---|
| `TokenGeneratorInterface` | `RandomTokenGenerator` (Infrastructure/Token/) |

### 3.4 Application services

| Service | Purpose | Status |
|---|---|---|
| `AvailabilityService` | Shared slot availability logic used by CreateReservation + GetAvailability | COMPLETE |
| `FeatureGuard` | Throws `FeatureDisabledException` if a gated feature is not configured | NOT YET BUILT |
| `JwtService` | JWT generation and validation using `firebase/php-jwt` | NOT YET BUILT |
| `PartyResolver` | Resolves `Party` from either a `UserId` (authenticated) or guest fields | NOT YET BUILT |
| `PaymentResolver` | Determines payment method validity and returns `PaymentResolution` | NOT YET BUILT |

### 3.5 Use cases (Application/UseCase/)

#### Complete

| Use case | Input | Output | Notes |
|---|---|---|---|
| `CreateReservationUseCase` | `CreateReservationRequest` | `CreateReservationResponse` | Checks availability, throws `ConflictException` if slot taken |
| `CancelReservationUseCase` | `CancelReservationRequest` | `CancelReservationResponse` | |
| `ConfirmReservationUseCase` | `ConfirmReservationRequest` | `ConfirmReservationResponse` | |
| `MarkNoShowUseCase` | `MarkNoShowRequest` | `MarkNoShowResponse` | |
| `GetReservationUseCase` | `GetReservationRequest` | `GetReservationResponse` | |
| `ListReservationsUseCase` | `ListReservationsRequest` | `ListReservationsResponse` | Optional from/to/resourceId filters |
| `CreateResourceUseCase` | `CreateResourceRequest` | `CreateResourceResponse` | |
| `GetResourceUseCase` | `GetResourceRequest` | `GetResourceResponse` | |
| `UpdateResourceUseCase` | `UpdateResourceRequest` | `UpdateResourceResponse` | PATCH semantics — all fields nullable |
| `DeleteResourceUseCase` | `DeleteResourceRequest` | `DeleteResourceResponse` | |
| `ListResourcesUseCase` | `ListResourcesRequest` | `ListResourcesResponse` | |
| `GetAvailabilityUseCase` | `GetAvailabilityRequest` | `GetAvailabilityResponse` | Delegates to AvailabilityService |
| `SaveAvailabilityRuleUseCase` | `SaveAvailabilityRuleRequest` | `SaveAvailabilityRuleResponse` | |
| `SaveAvailabilityOverrideUseCase` | `SaveAvailabilityOverrideRequest` | `SaveAvailabilityOverrideResponse` | |
| `SeedDatabaseUseCase` | `SeedDatabaseRequest(string[] $seedsDirectories)` | `SeedDatabaseResponse` | Globs *.sql, executes in filename order across all directories |

#### Not yet built

| Use case | Module | Notes |
|---|---|---|
| `RegisterUseCase` | Users | Also saves newsletter subscriber if opt-in |
| `LoginUseCase` | Users | Returns JWT. Unknown email → `InvalidCredentialsException` (never reveal existence) |
| `RequestPasswordResetUseCase` | Users | Stores hashed token. Unknown email → silent success |
| `ResetPasswordUseCase` | Users | Verifies hashed token, updates password, deletes token |
| `GetUserUseCase` | Users | |
| `UpdateUserUseCase` | Users | |
| `ListUsersUseCase` | Users | Admin only |
| `AdminUpdateUserUseCase` | Users | Role/newsletter override. Auth enforcement in HTTP layer, not here |
| `GetWalletUseCase` | Credits | Returns Wallet computed from transactions |
| `CreditWalletUseCase` | Credits | Saves Credit transaction |
| `DebitWalletUseCase` | Credits | Checks canAfford() BEFORE saving. Throws InsufficientFundsException if not |
| `GetSubscriptionUseCase` | Subscriptions | |
| `CreateSubscriptionCheckoutSessionUseCase` | Subscriptions | Returns Stripe checkout URL |
| `CancelSubscriptionUseCase` | Subscriptions | Marks cancelled in DB. Stripe cancel called separately by route. |
| `SubscribeUseCase` | Newsletter | Idempotent — returns existing if email already subscribed |
| `UnsubscribeUseCase` | Newsletter | Silent success if email not found |
| `BroadcastUseCase` | Newsletter | Sends new-class email to all opted-in subscribers |
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

8. **externalRef on Party is opaque.** The `rez` library stores and returns it but never interprets or validates it. Platform layer sets it to `userId.toString()`. Never add logic to `rez` that reads or branches on `externalRef`.

9. **FeatureGuard called at top of every gated use case.** Every use case that requires a feature (payments, users, credits, subscriptions) calls `$guard->require*()` as its first line. This ensures disabled features fail immediately with `FeatureDisabledException`, not mid-operation.

10. **Email and newsletter failures never abort a booking.** In `CreateBookingUseCase` and `CancelBookingUseCase`, mailer calls are wrapped in try/catch. A failed email must never roll back a completed booking.

### 3.7 Configuration system

`PlatformConfig` is constructed by the client app and injected via PHP-DI. It is the single root of all feature configuration.

`MailerConfig` — COMPLETE. `fromAddress` (validated email), `fromName` (non-empty string).
`PaymentsConfig` — COMPLETE. `currency` (non-empty string), `webhookSecret` (non-empty string).
`UsersConfig` — COMPLETE. `jwtSecret` (non-empty string), `jwtTtlSeconds` (default 3600, min 1), `passwordResetTtlMinutes` (default 60, min 1).

```
PlatformConfig
  ├── MailerConfig          always required (fromAddress, fromName)
  ├── PaymentsConfig?       currency, webhookSecret
  ├── UsersConfig?          jwtSecret, jwtTtlSeconds, passwordResetTtlMinutes
  ├── CreditsConfig?        minimumTopUpAmount, currency
  └── SubscriptionsConfig?  Plan[]
        └── Plan            id, name, priceAmount, currency, intervalDays, stripePriceId
```

**Dependency chain enforced at construction time:**
- `users` requires `payments`
- `credits` requires `payments` + `users`
- `subscriptions` requires `payments` + `users`

**Feature profiles:**

| Profile | mailer | payments | users | credits | subscriptions |
|---|---|---|---|---|---|
| 1 | ✓ | | | | |
| 2 | ✓ | ✓ | | | |
| 3 | ✓ | ✓ | ✓ | | |
| 4 | ✓ | ✓ | ✓ | ✓ | |
| 5 | ✓ | ✓ | ✓ | | ✓ |
| 6 | ✓ | ✓ | ✓ | ✓ | ✓ |

### 3.8 Database schema

All tables in one MySQL database. `rez` owns all schema — no per-module databases.

#### Complete tables

| Table | Purpose |
|---|---|
| `resources` | id, type, name, capacity, attributes (JSON) |
| `reservations` | id, status, start_at, end_at, party_name, party_email, party_size, party_phone, party_external_ref, created_at |
| `reservation_resources` | reservation_id, resource_id (many-to-many join) |
| `availability_rules` | resource_id, day_of_week, open_time (CHAR 5), close_time (CHAR 5) |
| `availability_overrides` | resource_id, date, available (TINYINT) |

#### Not yet built

| Table | Purpose | Notes |
|---|---|---|
| `users` | id, name, email, password_hash, role, newsletter_opt_in, stripe_customer_id, created_at | Must exist before wallet_transactions, subscriptions |
| `wallet_transactions` | id, user_id, amount (INT), currency, type, description, reservation_id (nullable, no FK), created_at | FK to users. No FK to reservations — audit trail must survive reservation deletion |
| `subscriptions` | id, user_id (UNIQUE), plan_id, status, stripe_subscription_id (UNIQUE), current_period_end, created_at | FK to users. One subscription per user — upsert by user_id |
| `stripe_events` | stripe_event_id (PK), type, payload (JSON), processed_at | PK is the Stripe event ID — provides idempotency |
| `newsletter_subscribers` | id, email (UNIQUE), name, source, opted_in_at | Upsert by email |
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

### API surface

All routes prefixed `/api/`.

#### Always available (profile 1+)

```
GET    /api/resources
POST   /api/resources
GET    /api/resources/{id}
PATCH  /api/resources/{id}
DELETE /api/resources/{id}
PUT    /api/resources/{id}/availability/rules
PUT    /api/resources/{id}/availability/overrides/{date}
GET    /api/availability
POST   /api/bookings
DELETE /api/bookings/{id}
GET    /api/reservations         (admin)
GET    /api/reservations/{id}    (admin)
POST   /api/reservations/{id}/confirm    (admin)
POST   /api/reservations/{id}/no-show   (admin)
POST   /api/newsletter/subscribe
DELETE /api/newsletter/unsubscribe
POST   /api/newsletter/broadcast (admin)
GET    /api/admin/config         (admin) — returns enabled features for rez-admin
```

#### Profile 2+ (payments)
```
POST   /api/stripe/webhook
```

#### Profile 3+ (users)
```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/password-reset/request
POST   /api/auth/password-reset/confirm
GET    /api/users/me
PATCH  /api/users/me
GET    /api/users              (admin)
PATCH  /api/users/{id}         (admin)
```

#### Profile 4+ (credits)
```
GET    /api/wallet
POST   /api/wallet/topup
```

#### Profile 5+ (subscriptions)
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
| `<rez-checkout>` | Payment method selection, Stripe redirect | 2+ |
| `<rez-account>` | Register, login, booking history, credits, subscription | 3+ |

### Bundle entry points

| Bundle | Includes | Used by profile |
|---|---|---|
| `rez.calendar.js` | rez-calendar | 1–2 |
| `rez.calendar-auth.js` | rez-calendar + rez-account | 3 |
| `rez.full.js` | all three | 4–6 |

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
    "users": true,
    "credits": true,
    "subscriptions": false
  },
  "currency": "CZK",
  "plans": []
}
```

Pages appear/disappear in the sidebar based on this response. One codebase works for all clients.

### Pages per delivery slice

| Slice | Pages |
|---|---|
| 1 | Resources (add/edit/cancel classes), Reservations list, Manual booking |
| 2 | Users list, Edit user |
| 3 | Payment history, Stripe event log |
| 4 | User credit balances, Manual credit adjustment |
| 5 | Subscription management, Override subscription status |
| 6 | Newsletter broadcast, Full polish |

---

## 7. Delivery Plan

Six vertical slices. Each slice delivers backend + JS component MVP + admin page simultaneously. Client demos after each slice before the next begins.

| Slice | Backend scaffold | Delivers |
|---|---|---|
| 1 | rez-core-changes → rez-config → rez-mailer-newsletter | Reservations, guest booking, confirmation email, newsletter |
| 2 | rez-users | User accounts, auth, JWT, booking history |
| 3 | rez-payments | Stripe one-time payments, webhook |
| 4 | rez-credits | Credit wallet, top-up, debit on booking |
| 5 | rez-subscriptions | Monthly plans, Stripe billing, free booking |
| 6 | rez-booking → rez-deprecate-handlers | Full orchestration, polish |

**Penalty system:** in scope, rules TBD with client after slice 2.

**Client website** (Michaela Urbanová — branding, content pages): separate deliverable, expected 2–3 months after Rez MVP is working.

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
| Reservations | ✅ | ✅ | ✅ | ✅ 188 unit + 22 integration |
| Resources | ✅ | ✅ | ✅ | ✅ |
| Availability | ✅ | ✅ | ✅ | ✅ |
| Seeder | ✅ | ✅ | ✅ | ✅ |
| Currency + Money | ✅ | — | ✅ CurrencyMapper | ✅ |
| Config / FeatureGuard | ❌ | — | — | — |
| Mailer port | ❌ | — | — | — |
| Newsletter | ❌ | ❌ | ❌ | — |
| Users | ❌ | ❌ | ❌ | — |
| Payments / Stripe port | ❌ | ❌ | ❌ | — |
| Credits / Wallet | ❌ | ❌ | ❌ | — |
| Subscriptions | ❌ | ❌ | ❌ | — |
| Booking orchestration | ❌ | ❌ | — | — |
| Handler deprecation | ❌ | — | — | — |

### Pending scaffold documents (run in this order)
1. `rez-core-changes` — **COMPLETE** (externalRef on Party, MysqlReservationRepository + ReservationSerializer external_ref, SeedDatabaseRequest multi-directory, seed README, MysqlDatabaseSeeder::seedsPath())
2. `rez-config` — PlatformConfig, all sub-configs, FeatureGuard
3. `rez-mailer-newsletter` — MailerInterface, newsletter domain + repository + use cases
4. `rez-users` — User domain, JwtService, auth use cases, RandomTokenGenerator
5. `rez-payments` — StripeGatewayInterface, StripeEventRepository, webhook use case
6. `rez-credits` — Wallet, WalletTransaction, wallet use cases
7. `rez-subscriptions` — Subscription, Plan, subscription use cases
8. `rez-booking` — CreateBookingUseCase, CancelBookingUseCase, PartyResolver, PaymentResolver
9. `rez-deprecate-handlers` — @deprecated on all Handler classes, update examples/slim/

### `rez-starter`
- ✅ Docker stack (PHP-FPM + Nginx + MySQL + Mailpit)
- ✅ Slim bootstrap, PHP-DI wiring, basic routes
- ❌ Auth middleware, admin middleware, CORS middleware
- ❌ SymfonyMailer, StripeGateway implementations
- ❌ Full route surface (only core reservation routes exist)

### `rez-components`, `rez-admin`
- ❌ Not started
