# Rez — Payments & Stripe

This document adds the Money value object, Stripe gateway port, checkout session use cases,
and webhook handling to `davidrubydev/rez`.
Complete `rez-config.md` and `rez-users.md` before starting this.

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

`rez` defines `StripeGatewayInterface` as a port. The concrete `StripeGateway` implementation
lives in the client repo — `rez` has zero dependency on `stripe/stripe-php`.

Stripe uses Checkout Sessions (redirect model). The user is sent to Stripe's hosted page,
completes payment, and is redirected back. The `stripe_events` table provides webhook
idempotency — duplicate webhook deliveries are safely ignored.

`FeatureGuard::requirePayments()` must be called at the top of every use case here.

---

## New files

```
src/
  Domain/
    Shared/
      Money.php
    Exception/
      InsufficientFundsException.php
  Application/
    Port/
      StripeGatewayInterface.php
    UseCase/
      Payment/
        CreateTopUpCheckoutSession/
          CreateTopUpCheckoutSessionUseCase.php
          CreateTopUpCheckoutSessionRequest.php
          CreateTopUpCheckoutSessionResponse.php
          CreateTopUpCheckoutSessionUseCaseInterface.php
        HandleWebhook/
          HandleWebhookUseCase.php
          HandleWebhookRequest.php
          HandleWebhookResponse.php
          HandleWebhookUseCaseInterface.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlStripeEventRepository.php

tests/
  Domain/
    Shared/
      MoneyTest.php
  Application/
    UseCase/
      Payment/
        CreateTopUpCheckoutSessionUseCaseTest.php
        HandleWebhookUseCaseTest.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlStripeEventRepositoryTest.php (integration)
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. InsufficientFundsException

`src/Domain/Exception/InsufficientFundsException.php`

Extends `DomainException`. Constructor: `int $required, int $available, string $currency`.
Message: `"Insufficient funds: required {$required} {$currency}, available {$available} {$currency}."`

---

### 2. Money + MoneyTest

`src/Domain/Shared/Money.php` — immutable value object. All amounts in smallest currency unit
(haléře for CZK, cents for EUR/USD). Never floats.

Constructor:
```php
public function __construct(
    public readonly int $amount,
    public readonly string $currency,
)
```

- Throws `\InvalidArgumentException` if `$amount < 0`
- Throws `\InvalidArgumentException` if `$currency` is empty
- Currency stored uppercase: `strtoupper($currency)`

Methods:
- `getAmount(): int`
- `getCurrency(): string`
- `add(Money $other): self` — throws `\InvalidArgumentException` if currencies differ
- `subtract(Money $other): self` — throws `InsufficientFundsException` if result would be negative
- `isZero(): bool`
- `equals(Money $other): bool` — amount AND currency must match
- `isGreaterThan(Money $other): bool` — throws `\InvalidArgumentException` if currencies differ
- `__toString(): string` — e.g. `'150000 CZK'`

`tests/Domain/Shared/MoneyTest.php`:
- Valid construction stores amount and uppercased currency
- Negative amount throws `\InvalidArgumentException`
- Empty currency throws `\InvalidArgumentException`
- `add()` with same currency returns correct sum
- `add()` with different currencies throws `\InvalidArgumentException`
- `subtract()` with sufficient amount returns correct result
- `subtract()` resulting in zero is valid (zero Money)
- `subtract()` below zero throws `InsufficientFundsException`
- `isZero()` true when amount is 0, false otherwise
- `equals()` true for identical amount and currency
- `equals()` false for different amount
- `equals()` false for different currency
- `isGreaterThan()` true when this amount exceeds other
- `isGreaterThan()` false when equal or less
- `isGreaterThan()` with different currencies throws `\InvalidArgumentException`
- `__toString()` returns correct string

---

### 3. StripeGatewayInterface

`src/Application/Port/StripeGatewayInterface.php`

```php
interface StripeGatewayInterface
{
    /**
     * Create a Stripe Checkout Session for a one-time payment (credit top-up).
     * Returns the Stripe-hosted checkout URL.
     *
     * @param array<string, string> $metadata
     */
    public function createCheckoutSession(
        string $stripeCustomerId,
        Money $amount,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): string;

    /**
     * Create a Stripe Checkout Session for a subscription.
     * Returns the Stripe-hosted checkout URL.
     */
    public function createSubscriptionCheckoutSession(
        string $stripeCustomerId,
        string $stripePriceId,
        string $successUrl,
        string $cancelUrl,
    ): string;

    /**
     * Create a Stripe Customer and return the customer ID.
     */
    public function createCustomer(string $email, string $name): string;

    /**
     * Verify a Stripe webhook signature and return the parsed event payload.
     * Throws \UnexpectedValueException if signature is invalid.
     *
     * @return array<string, mixed>
     */
    public function constructWebhookEvent(string $payload, string $signature, string $secret): array;
}
```

Note: no refund or subscription cancel methods here. Those are triggered via webhook events
(Stripe initiates), not directly by the application.

---

### 4. StripeEventRepositoryInterface

`src/Application/Port/StripeEventRepositoryInterface.php`

```php
interface StripeEventRepositoryInterface
{
    public function hasBeenProcessed(string $stripeEventId): bool;
    public function markProcessed(string $stripeEventId, string $type, array $payload): void;
}
```

---

### 5. Database schema — stripe_events table

Add to `database/seeds/000_schema.sql`:

```sql
CREATE TABLE IF NOT EXISTS stripe_events (
    stripe_event_id VARCHAR(255) NOT NULL PRIMARY KEY,
    type            VARCHAR(100) NOT NULL,
    payload         JSON         NOT NULL,
    processed_at    DATETIME     NOT NULL
);
```

`stripe_event_id` is the Stripe-generated event ID (e.g. `evt_1ABC...`). Using it as primary key
provides idempotency — inserting a duplicate ID fails with a primary key violation which is caught
and used to detect already-processed events.

---

### 6. MysqlStripeEventRepository

`src/Infrastructure/Persistence/Mysql/MysqlStripeEventRepository.php`

Implements `StripeEventRepositoryInterface`. Constructor injects `\PDO`.

- `hasBeenProcessed()`: `SELECT COUNT(*) FROM stripe_events WHERE stripe_event_id = :id`
  Returns `true` if count > 0.
- `markProcessed()`: `INSERT INTO stripe_events (stripe_event_id, type, payload, processed_at) VALUES (...)`
  `payload` encoded via `json_encode($payload)`.
  `processed_at` = UTC now.
  Uses plain `INSERT` (not upsert) — duplicate key means already processed, which `HandleWebhookUseCase`
  checks via `hasBeenProcessed()` before calling this.

`tests/Integration/Persistence/Mysql/MysqlStripeEventRepositoryTest.php`:
- `testMarkProcessedAndHasBeenProcessed` — mark an event, assert `hasBeenProcessed()` returns true
- `testHasBeenProcessedReturnsFalseForUnknown` — unknown ID returns false
- `testMarkProcessedTwiceThrowsPdoException` — inserting same ID twice throws (idempotency proof)

---

### 7. CreateTopUpCheckoutSession use case

`CreateTopUpCheckoutSessionRequest` — readonly:
```php
UserId $userId,
Money $amount,
string $successUrl,
string $cancelUrl,
```

`CreateTopUpCheckoutSessionResponse` — readonly: `string $checkoutUrl`

`CreateTopUpCheckoutSessionUseCase implements CreateTopUpCheckoutSessionUseCaseInterface`:

Constructor: `UserRepositoryInterface`, `StripeGatewayInterface`, `FeatureGuard`, `CreditsConfig`

Logic:
1. `$guard->requirePayments()`
2. `$guard->requireCredits()` — top-up only makes sense with credits enabled
3. Validate `$request->amount->getAmount() >= $creditsConfig->minimumTopUpAmount`
   Throw `\InvalidArgumentException` if below minimum
4. Validate currency matches `$creditsConfig->currency`
   Throw `\InvalidArgumentException` if mismatch
5. `$user = userRepository->findById($request->userId)` — propagates `UserNotFoundException`
6. `$stripeCustomerId = $user->getStripeCustomerId()`
   Throw `\RuntimeException('User has no Stripe customer ID')` if null
7. `$url = stripeGateway->createCheckoutSession($stripeCustomerId, $amount, $successUrl, $cancelUrl, ['type' => 'credit_topup', 'user_id' => $userId->toString()])`
8. Return response with URL

`tests/Application/UseCase/Payment/CreateTopUpCheckoutSessionUseCaseTest.php`:
- Payments disabled throws `FeatureDisabledException`
- Credits disabled throws `FeatureDisabledException`
- Amount below minimum throws `\InvalidArgumentException`
- Currency mismatch throws `\InvalidArgumentException`
- User not found propagates `UserNotFoundException`
- User with no stripe customer ID throws `\RuntimeException`
- Success: `createCheckoutSession()` called with correct arguments
- Success: returns URL from gateway

---

### 8. HandleWebhook use case

`HandleWebhookRequest` — readonly:
```php
string $payload,
string $stripeSignature,
```

`HandleWebhookResponse` — readonly: `bool $processed`

`HandleWebhookUseCase implements HandleWebhookUseCaseInterface`:

Constructor: `StripeGatewayInterface`, `StripeEventRepositoryInterface`,
`CreditWalletUseCaseInterface`, `UserRepositoryInterface`,
`SubscriptionRepositoryInterface`, `FeatureGuard`, `PaymentsConfig`

Logic:
1. `$guard->requirePayments()`
2. `$event = stripeGateway->constructWebhookEvent($payload, $signature, $config->webhookSecret)`
   Let `\UnexpectedValueException` propagate — HTTP layer maps it to 400
3. `$eventId = $event['id']`
4. If `stripeEventRepository->hasBeenProcessed($eventId)` — return `processed: false`
5. Dispatch by `$event['type']`:

   **`checkout.session.completed`**:
   - Read `$session = $event['data']['object']`
   - Read `$metadata = $session['metadata']`
   - If `$metadata['type'] === 'credit_topup'`:
     - `$userId = UserId::fromString($metadata['user_id'])`
     - `$amount = new Money((int)$session['amount_total'], strtoupper($session['currency']))`
     - `creditWalletUseCase->execute(new CreditWalletRequest($userId, $amount, 'Stripe top-up'))`
   - If `$metadata['type'] === 'subscription'`:
     - Create or update `Subscription` entity (see rez-subscriptions.md for domain)
     - If subscriptions feature disabled: skip silently (log-worthy but not an error)

   **`customer.subscription.deleted`**:
   - Load subscription by `$event['data']['object']['id']` (Stripe subscription ID)
   - Call `cancel()`, save
   - If `SubscriptionNotFoundException`: skip silently

   **`customer.subscription.updated`**:
   - Load subscription by Stripe ID
   - Update `currentPeriodEnd` and status from event data, save
   - If `SubscriptionNotFoundException`: skip silently

   **`invoice.payment_failed`**:
   - Load subscription by Stripe subscription ID from invoice
   - Call `markPastDue()`, save
   - If `SubscriptionNotFoundException`: skip silently

   **All other types**: fall through, mark processed, return `processed: true`

6. `stripeEventRepository->markProcessed($eventId, $event['type'], $event)`
7. Return `processed: true`

`tests/Application/UseCase/Payment/HandleWebhookUseCaseTest.php`:
- Payments disabled throws `FeatureDisabledException`
- Already-processed event ID returns `processed: false` without dispatching anything
- `checkout.session.completed` with `credit_topup` metadata calls `CreditWalletUseCase`
- `checkout.session.completed` with `subscription` metadata saves subscription (mock SubscriptionRepository)
- `customer.subscription.deleted` cancels subscription
- `customer.subscription.updated` updates period end
- `invoice.payment_failed` marks subscription past due
- Unknown event type marks processed and returns true
- `constructWebhookEvent()` raising `\UnexpectedValueException` propagates (no catch)

---

### 9. Register in container

`config/container.php`

Add:
```php
\Rez\Application\UseCase\Payment\CreateTopUpCheckoutSession\CreateTopUpCheckoutSessionUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Payment\CreateTopUpCheckoutSession\CreateTopUpCheckoutSessionUseCase::class),

\Rez\Application\UseCase\Payment\HandleWebhook\HandleWebhookUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Payment\HandleWebhook\HandleWebhookUseCase::class),

\Rez\Application\Port\StripeEventRepositoryInterface::class
    => \DI\autowire(\Rez\Infrastructure\Persistence\Mysql\MysqlStripeEventRepository::class),
```

Note: `StripeGatewayInterface` must be bound by the client app.
Document in a comment.

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. InsufficientFundsException
- [ ] 2. Money + MoneyTest
- [ ] 3. StripeGatewayInterface
- [ ] 4. StripeEventRepositoryInterface
- [ ] 5. Database schema (stripe_events)
- [ ] 6. MysqlStripeEventRepository + integration test
- [ ] 7. CreateTopUpCheckoutSession use case + test
- [ ] 8. HandleWebhook use case + test
- [ ] 9. container.php updated
