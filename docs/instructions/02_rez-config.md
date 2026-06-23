# Rez — Config & FeatureGuard

This document adds the platform configuration system to `davidrubydev/rez`.
It must be completed before any other platform feature scaffold (users, payments, credits, subscriptions).

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

`PlatformConfig` is the single configuration root passed at boot time by the client app.
It validates the feature dependency chain at construction time, failing fast rather than at runtime.
`FeatureGuard` is injected into every use case that requires a gated feature and throws
`FeatureDisabledException` if that feature is not configured.

The six supported profiles are a strict linear dependency chain:
```
reservations → email → payments → users → credits → subscriptions
```
Email (mailer) is always required. Every other feature is optional but has hard prerequisites.

---

## New files

```
src/
  Application/
    Config/
      PlatformConfig.php
      MailerConfig.php
      PaymentsConfig.php
      UsersConfig.php
      CreditsConfig.php
      SubscriptionsConfig.php
    Service/
      FeatureGuard.php
  Domain/
    Exception/
      FeatureDisabledException.php

tests/
  Application/
    Config/
      PlatformConfigTest.php
    Service/
      FeatureGuardTest.php
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. FeatureDisabledException

`src/Domain/Exception/FeatureDisabledException.php`

Extends existing `DomainException`. Constructor accepts a `string $feature` name.
Message format: `"Feature '{$feature}' is not enabled in PlatformConfig."`

No test needed — trivial constructor.

---

### 2. MailerConfig

`src/Application/Config/MailerConfig.php`

Immutable. Readonly constructor properties:

```php
public function __construct(
    public readonly string $fromAddress,
    public readonly string $fromName,
)
```

- Throws `\InvalidArgumentException` if `$fromAddress` is not a valid email (`filter_var`)
- Throws `\InvalidArgumentException` if `$fromName` is empty

No DSN here — the concrete mailer implementation lives in the client repo and handles
its own transport config. `MailerConfig` only carries what `rez` itself needs to know:
who emails appear to come from.

---

### 3. PaymentsConfig

`src/Application/Config/PaymentsConfig.php`

Immutable. Readonly constructor properties:

```php
public function __construct(
    public readonly string $currency,           // e.g. 'czk', 'eur'
    public readonly string $webhookSecret,      // Stripe webhook signing secret
)
```

- Throws `\InvalidArgumentException` if `$currency` is empty
- Throws `\InvalidArgumentException` if `$webhookSecret` is empty

Note: Stripe publishable/secret keys are NOT stored here — they live in the client repo's
concrete `StripeGateway` implementation and are not needed by `rez` itself.

---

### 4. UsersConfig

`src/Application/Config/UsersConfig.php`

Immutable. Readonly constructor properties:

```php
public function __construct(
    public readonly string $jwtSecret,
    public readonly int $jwtTtlSeconds = 3600,
    public readonly int $passwordResetTtlMinutes = 60,
)
```

- Throws `\InvalidArgumentException` if `$jwtSecret` is empty
- Throws `\InvalidArgumentException` if `$jwtTtlSeconds < 1`
- Throws `\InvalidArgumentException` if `$passwordResetTtlMinutes < 1`

---

### 5. CreditsConfig

`src/Application/Config/CreditsConfig.php`

Immutable. Readonly constructor properties:

```php
public function __construct(
    public readonly int $minimumTopUpAmount,    // in smallest currency unit (haléře/cents)
    public readonly string $currency,           // must match PaymentsConfig::$currency
)
```

- Throws `\InvalidArgumentException` if `$minimumTopUpAmount < 1`
- Throws `\InvalidArgumentException` if `$currency` is empty

---

### 6. Plan

`src/Application/Config/Plan.php`

Immutable value object representing a subscription plan. Lives in Config because plans
are defined at boot time by the client app, not stored in the database.

```php
public function __construct(
    public readonly string $id,             // slug, e.g. 'monthly'
    public readonly string $name,           // e.g. 'Měsíční členství'
    public readonly int $priceAmount,       // in smallest currency unit
    public readonly string $currency,
    public readonly int $intervalDays,      // e.g. 30
)
```

- Throws `\InvalidArgumentException` if `$id` is empty
- Throws `\InvalidArgumentException` if `$name` is empty
- Throws `\InvalidArgumentException` if `$priceAmount < 0` (zero allowed — free plan)
- Throws `\InvalidArgumentException` if `$currency` is empty
- Throws `\InvalidArgumentException` if `$intervalDays < 1`

---

### 7. SubscriptionsConfig

`src/Application/Config/SubscriptionsConfig.php`

Immutable. Readonly constructor properties:

```php
public function __construct(
    public readonly array $plans,   // Plan[]
)
```

- Throws `\InvalidArgumentException` if `$plans` is empty
- Throws `\InvalidArgumentException` if any element is not a `Plan` instance
- `getPlanById(string $id): Plan` — throws `\InvalidArgumentException` if not found
- `getPlans(): Plan[]`

---

### 8. PlatformConfig + PlatformConfigTest

`src/Application/Config/PlatformConfig.php`

Immutable configuration root. Constructor:

```php
public function __construct(
    public readonly MailerConfig $mailer,
    public readonly ?PaymentsConfig $payments = null,
    public readonly ?UsersConfig $users = null,
    public readonly ?CreditsConfig $credits = null,
    public readonly ?SubscriptionsConfig $subscriptions = null,
)
```

Validate dependency chain in constructor — throws `\InvalidArgumentException` with a clear
message for each violation:

| If this is set | Requires |
|---|---|
| `users` | `payments` |
| `credits` | `payments` AND `users` |
| `subscriptions` | `payments` AND `users` |

Feature check methods:
- `hasMailer(): bool` — always true (mailer is always required, method for consistency)
- `hasPayments(): bool`
- `hasUsers(): bool`
- `hasCredits(): bool`
- `hasSubscriptions(): bool`

`tests/Application/Config/PlatformConfigTest.php`:

- Valid construction with mailer only
- Valid construction with all features enabled
- `users` without `payments` throws `\InvalidArgumentException`
- `credits` without `payments` throws `\InvalidArgumentException`
- `credits` without `users` throws `\InvalidArgumentException`
- `subscriptions` without `payments` throws `\InvalidArgumentException`
- `subscriptions` without `users` throws `\InvalidArgumentException`
- `hasPayments()` false when null, true when set
- `hasUsers()` false when null, true when set
- `hasCredits()` false when null, true when set
- `hasSubscriptions()` false when null, true when set

---

### 9. FeatureGuard + FeatureGuardTest

`src/Application/Service/FeatureGuard.php`

Constructor: `PlatformConfig $config`

Methods — each throws `FeatureDisabledException` with the feature name if not configured:
- `requirePayments(): void`
- `requireUsers(): void`
- `requireCredits(): void`
- `requireSubscriptions(): void`

`FeatureGuard` does not have a `requireMailer()` — mailer is always present.

`tests/Application/Service/FeatureGuardTest.php`:

- `requirePayments()` passes silently when payments configured
- `requirePayments()` throws `FeatureDisabledException` when payments null
- `requireUsers()` passes silently when users configured
- `requireUsers()` throws `FeatureDisabledException` when users null
- `requireCredits()` passes silently when credits configured
- `requireCredits()` throws `FeatureDisabledException` when credits null
- `requireSubscriptions()` passes silently when subscriptions configured
- `requireSubscriptions()` throws `FeatureDisabledException` when subscriptions null

---

### 10. Register PlatformConfig and FeatureGuard in container

`config/container.php`

Add:
```php
// PlatformConfig must be bound by the client app — not defined here.
// FeatureGuard is autowired — PHP-DI resolves PlatformConfig from client binding.
\Rez\Application\Service\FeatureGuard::class => \DI\autowire(),
```

Document clearly in a comment that `PlatformConfig` is the client app's responsibility.

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must continue to pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. FeatureDisabledException
- [ ] 2. MailerConfig
- [ ] 3. PaymentsConfig
- [ ] 4. UsersConfig
- [ ] 5. CreditsConfig
- [ ] 6. Plan
- [ ] 7. SubscriptionsConfig
- [ ] 8. PlatformConfig + PlatformConfigTest
- [ ] 9. FeatureGuard + FeatureGuardTest
- [ ] 10. container.php updated
