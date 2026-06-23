# Rez — Subscriptions

This document adds the subscription domain, repository, and use cases to `davidrubydev/rez`.
Complete `rez-payments.md` and `rez-users.md` before starting this.

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

Subscriptions are monthly (or configurable interval) plans that allow users to attend
any class for free while the subscription is active. Plans are defined in `SubscriptionsConfig`
at boot time — not stored in the database. Stripe manages billing; the application tracks
the subscription status and period via webhooks.

`FeatureGuard::requireSubscriptions()` must be called at the top of every use case here.

---

## New files

```
src/
  Domain/
    Subscription/
      Subscription.php
      SubscriptionId.php
      SubscriptionStatus.php
  Application/
    Port/
      SubscriptionRepositoryInterface.php
    UseCase/
      Subscription/
        GetSubscription/
          GetSubscriptionUseCase.php
          GetSubscriptionRequest.php
          GetSubscriptionResponse.php
          GetSubscriptionUseCaseInterface.php
        CreateSubscriptionCheckoutSession/
          CreateSubscriptionCheckoutSessionUseCase.php
          CreateSubscriptionCheckoutSessionRequest.php
          CreateSubscriptionCheckoutSessionResponse.php
          CreateSubscriptionCheckoutSessionUseCaseInterface.php
        CancelSubscription/
          CancelSubscriptionUseCase.php
          CancelSubscriptionRequest.php
          CancelSubscriptionResponse.php
          CancelSubscriptionUseCaseInterface.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlSubscriptionRepository.php

tests/
  Domain/
    Subscription/
      SubscriptionIdTest.php
      SubscriptionTest.php
  Application/
    UseCase/
      Subscription/
        GetSubscriptionUseCaseTest.php
        CreateSubscriptionCheckoutSessionUseCaseTest.php
        CancelSubscriptionUseCaseTest.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlSubscriptionRepositoryTest.php (integration)
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. SubscriptionNotFoundException

`src/Domain/Exception/SubscriptionNotFoundException.php`

Extends `DomainException`. Constructor: `UserId $userId`.
Message: `"No active subscription found for user '{$userId->toString()}'."`

---

### 2. SubscriptionId + SubscriptionIdTest

Same UUID v4 pattern as all other ID value objects.

- `static generate(): self`
- `static fromString(string $id): self` — throws `\InvalidArgumentException` if not valid UUID
- `toString(): string`
- `equals(self $other): bool`
- `__toString(): string`

`tests/Domain/Subscription/SubscriptionIdTest.php`:
- `generate()` produces valid UUID v4
- `fromString()` roundtrips correctly
- `fromString()` with invalid string throws `\InvalidArgumentException`
- `equals()` true for same, false for different

---

### 3. SubscriptionStatus

`src/Domain/Subscription/SubscriptionStatus.php` — pure enum.

```php
enum SubscriptionStatus
{
    case Active;
    case Cancelled;
    case PastDue;
    case Incomplete;
}
```

String serialization in infrastructure:
- `Active` ↔ `'active'`
- `Cancelled` ↔ `'cancelled'`
- `PastDue` ↔ `'past_due'`
- `Incomplete` ↔ `'incomplete'`

No test needed.

---

### 4. Subscription + SubscriptionTest

`src/Domain/Subscription/Subscription.php` — immutable entity. Static factory only.

```php
public static function create(
    SubscriptionId $id,
    UserId $userId,
    string $planId,
    string $stripeSubscriptionId,
    \DateTimeImmutable $currentPeriodEnd,
): self
```

- Throws `\InvalidArgumentException` if `$planId` is empty
- Throws `\InvalidArgumentException` if `$stripeSubscriptionId` is empty
- Sets status to `SubscriptionStatus::Active`
- Sets `createdAt` to UTC now

State transition methods — each returns new immutable instance:
- `cancel(): self` — sets status to `Cancelled`
- `markPastDue(): self` — sets status to `PastDue`
- `renew(\DateTimeImmutable $newPeriodEnd): self` — sets status to `Active`, updates period end

`isActive(): bool`:
- Returns `true` ONLY when status is `Active` AND `currentPeriodEnd > now UTC`
- `PastDue` subscriptions are NOT active (payment failed)
- Expired `Active` subscriptions are NOT active (period ended, webhook not yet received)

Getters: `getId()`, `getUserId()`, `getPlanId()`, `getStripeSubscriptionId()`,
`getStatus()`, `getCurrentPeriodEnd()`, `getCreatedAt()`

`tests/Domain/Subscription/SubscriptionTest.php`:
- `create()` sets Active status
- `create()` with empty planId throws `\InvalidArgumentException`
- `create()` with empty stripeSubscriptionId throws `\InvalidArgumentException`
- `cancel()` sets Cancelled status, original unchanged
- `markPastDue()` sets PastDue status, original unchanged
- `renew()` sets Active status and updates period end, original unchanged
- `isActive()` true when Active and period end in future
- `isActive()` false when Active but period end in past (expired)
- `isActive()` false when Cancelled (even with future period end)
- `isActive()` false when PastDue
- All transitions return new instances (immutability)

---

### 5. SubscriptionRepositoryInterface

`src/Application/Port/SubscriptionRepositoryInterface.php`

```php
interface SubscriptionRepositoryInterface
{
    public function findByUserId(UserId $userId): Subscription;                         // throws SubscriptionNotFoundException
    public function findByStripeSubscriptionId(string $stripeSubscriptionId): Subscription; // throws SubscriptionNotFoundException
    public function save(Subscription $subscription): void;                             // upsert by id
}
```

---

### 6. Subscription use cases

Build each in TDD order: test first, then implementation.

#### GetSubscription

`GetSubscriptionRequest` — readonly: `UserId $userId`
`GetSubscriptionResponse` — readonly: `Subscription $subscription`

`GetSubscriptionUseCase implements GetSubscriptionUseCaseInterface`:

Constructor: `SubscriptionRepositoryInterface`, `FeatureGuard`

1. `$guard->requireSubscriptions()`
2. `subscriptionRepository->findByUserId($userId)` — propagates `SubscriptionNotFoundException`
3. Return response

`tests/Application/UseCase/Subscription/GetSubscriptionUseCaseTest.php`:
- Subscriptions disabled throws `FeatureDisabledException`
- User with no subscription propagates `SubscriptionNotFoundException`
- Found subscription returned in response

#### CreateSubscriptionCheckoutSession

`CreateSubscriptionCheckoutSessionRequest` — readonly:
```php
UserId $userId,
string $planId,
string $successUrl,
string $cancelUrl,
```

`CreateSubscriptionCheckoutSessionResponse` — readonly: `string $checkoutUrl`

`CreateSubscriptionCheckoutSessionUseCase implements CreateSubscriptionCheckoutSessionUseCaseInterface`:

Constructor: `UserRepositoryInterface`, `StripeGatewayInterface`, `FeatureGuard`, `SubscriptionsConfig`

Logic:
1. `$guard->requireSubscriptions()`
2. `$plan = subscriptionsConfig->getPlanById($request->planId)` — throws `\InvalidArgumentException` if not found
3. `$user = userRepository->findById($request->userId)` — propagates `UserNotFoundException`
4. `$stripeCustomerId = $user->getStripeCustomerId()`
   Throw `\RuntimeException('User has no Stripe customer ID')` if null
5. `$url = stripeGateway->createSubscriptionCheckoutSession($stripeCustomerId, $plan->getStripePriceId(), $successUrl, $cancelUrl)`
6. Return response

Note: `Plan` needs a `getStripePriceId(): string` getter — add this to `Plan` in `rez-config.md`'s
`Plan` value object. The Stripe price ID is set in the client config alongside the plan definition.

Update `Plan` constructor to include:
```php
public readonly string $stripePriceId,  // e.g. 'price_1ABC...'
```

`tests/Application/UseCase/Subscription/CreateSubscriptionCheckoutSessionUseCaseTest.php`:
- Subscriptions disabled throws `FeatureDisabledException`
- Unknown plan ID throws `\InvalidArgumentException`
- User not found propagates `UserNotFoundException`
- User with no Stripe customer ID throws `\RuntimeException`
- Success: `createSubscriptionCheckoutSession()` called with correct customer ID and price ID
- Success: returns URL from gateway

#### CancelSubscription

`CancelSubscriptionRequest` — readonly: `UserId $userId`
`CancelSubscriptionResponse` — readonly: `Subscription $subscription`

`CancelSubscriptionUseCase implements CancelSubscriptionUseCaseInterface`:

Constructor: `SubscriptionRepositoryInterface`, `FeatureGuard`

Logic:
1. `$guard->requireSubscriptions()`
2. `$subscription = subscriptionRepository->findByUserId($userId)` — propagates `SubscriptionNotFoundException`
3. `$cancelled = $subscription->cancel()`
4. `subscriptionRepository->save($cancelled)`
5. Return response

Note: this marks the subscription as cancelled in our database. Stripe cancellation
is handled separately — the client repo's Slim route calls this use case AND calls
`StripeGateway::cancelSubscription()`. Keep those concerns separate.

`tests/Application/UseCase/Subscription/CancelSubscriptionUseCaseTest.php`:
- Subscriptions disabled throws `FeatureDisabledException`
- User with no subscription propagates `SubscriptionNotFoundException`
- Success: `save()` called with cancelled subscription
- Success: returned subscription has `Cancelled` status
- Original subscription unchanged (immutability)

---

### 7. Database schema — subscriptions table

Add to `database/seeds/000_schema.sql` (after `users` table):

```sql
CREATE TABLE IF NOT EXISTS subscriptions (
    id                     CHAR(36)     NOT NULL PRIMARY KEY,
    user_id                CHAR(36)     NOT NULL UNIQUE,
    plan_id                VARCHAR(100) NOT NULL,
    status                 VARCHAR(20)  NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL UNIQUE,
    current_period_end     DATETIME     NOT NULL,
    created_at             DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

`user_id UNIQUE` — one active subscription per user. If a user cancels and resubscribes,
the existing row is overwritten via upsert (save by `id` as primary key, but `user_id`
unique constraint means old row is replaced).

Actually: `UNIQUE (user_id)` with upsert by `id` causes a conflict. Use upsert by `user_id`
instead, or remove the unique constraint and handle "one active sub per user" at the
application layer in `findByUserId()` (return the most recent one).

Preferred solution: keep `UNIQUE (user_id)` and use `INSERT ... ON DUPLICATE KEY UPDATE`
keyed on `user_id` in `save()`. The `id` field is the Rez UUID, `user_id` is the upsert key.

---

### 8. MysqlSubscriptionRepository

`src/Infrastructure/Persistence/Mysql/MysqlSubscriptionRepository.php`

Implements `SubscriptionRepositoryInterface`. Constructor injects `\PDO`.

- `findByUserId()`:
  `SELECT * FROM subscriptions WHERE user_id = :userId`
  Throws `SubscriptionNotFoundException` if no row.

- `findByStripeSubscriptionId()`:
  `SELECT * FROM subscriptions WHERE stripe_subscription_id = :stripeId`
  Throws `SubscriptionNotFoundException` if no row.

- `save()`:
  ```sql
  INSERT INTO subscriptions (id, user_id, plan_id, status, stripe_subscription_id, current_period_end, created_at)
  VALUES (...)
  ON DUPLICATE KEY UPDATE
      plan_id = VALUES(plan_id),
      status = VALUES(status),
      stripe_subscription_id = VALUES(stripe_subscription_id),
      current_period_end = VALUES(current_period_end)
  ```
  Keyed on `user_id` unique constraint. `id` and `created_at` never overwritten.

`SubscriptionStatus` string mapping (inline in repository):
- `'active'` → `Active`, `'cancelled'` → `Cancelled`, `'past_due'` → `PastDue`, `'incomplete'` → `Incomplete`

`tests/Integration/Persistence/Mysql/MysqlSubscriptionRepositoryTest.php`:

Requires a `users` row first (FK). Create via raw SQL in setup.

- `testSaveAndFindByUserId` — save, find, assert all fields
- `testFindByUserIdThrowsWhenNotFound` — assert `SubscriptionNotFoundException`
- `testFindByStripeSubscriptionId` — find by Stripe ID after save
- `testFindByStripeSubscriptionIdThrowsWhenNotFound` — assert `SubscriptionNotFoundException`
- `testSaveUpdatesExistingSubscription` — save, cancel, save again, find, assert Cancelled
- `testSaveIsIdempotentByUserId` — save twice for same user, only one row exists

---

### 9. Register in container

`config/container.php`

Add:
```php
\Rez\Application\UseCase\Subscription\GetSubscription\GetSubscriptionUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Subscription\GetSubscription\GetSubscriptionUseCase::class),

\Rez\Application\UseCase\Subscription\CreateSubscriptionCheckoutSession\CreateSubscriptionCheckoutSessionUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Subscription\CreateSubscriptionCheckoutSession\CreateSubscriptionCheckoutSessionUseCase::class),

\Rez\Application\UseCase\Subscription\CancelSubscription\CancelSubscriptionUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Subscription\CancelSubscription\CancelSubscriptionUseCase::class),

\Rez\Application\Port\SubscriptionRepositoryInterface::class
    => \DI\autowire(\Rez\Infrastructure\Persistence\Mysql\MysqlSubscriptionRepository::class),
```

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. SubscriptionNotFoundException
- [ ] 2. SubscriptionId + SubscriptionIdTest
- [ ] 3. SubscriptionStatus enum
- [ ] 4. Subscription + SubscriptionTest
- [ ] 5. SubscriptionRepositoryInterface
- [ ] 6. GetSubscription use case + test
- [ ] 6. CreateSubscriptionCheckoutSession use case + test
- [ ] 6. CancelSubscription use case + test
- [ ] 7. Database schema (subscriptions)
- [ ] 8. MysqlSubscriptionRepository + integration test
- [ ] 9. container.php updated
