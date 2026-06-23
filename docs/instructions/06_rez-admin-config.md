# Rez — Admin Config Use Case

Implements the `GetAdminConfigUseCase` — a pure read from `PlatformConfig` that tells
`rez-admin` which feature modules are enabled and what config values the UI needs to render
correctly. The endpoint `GET /api/admin/config` is one of the first things `rez-admin`
calls after login.

No repository dependency. No database calls. Depends only on `PlatformConfig`.

Complete `02_rez-config.md` before starting this.

Run `composer ca` after completing all changes and fix any issues before committing.

---

## Response shape

```json
{
  "features": {
    "payments":      true,
    "users":         true,
    "credits":       true,
    "subscriptions": false
  },
  "currency": "CZK",
  "plans": []
}
```

- `features` — one bool per gated feature; always present
- `currency` — value of `PaymentsConfig::$currency` if payments enabled, `null` otherwise
- `plans` — array of plan summaries if subscriptions enabled, empty array otherwise

---

## New files

```
src/
  Application/
    UseCase/
      AdminConfig/
        GetAdminConfig/
          GetAdminConfigRequest.php
          GetAdminConfigResponse.php
          GetAdminConfigUseCaseInterface.php
          GetAdminConfigUseCase.php

tests/
  Application/
    UseCase/
      AdminConfig/
        GetAdminConfigUseCaseTest.php
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. GetAdminConfigRequest

`src/Application/UseCase/AdminConfig/GetAdminConfig/GetAdminConfigRequest.php`

Empty request — no parameters needed.

```php
final class GetAdminConfigRequest
{
}
```

---

### 2. GetAdminConfigResponse

`src/Application/UseCase/AdminConfig/GetAdminConfig/GetAdminConfigResponse.php`

```php
final class GetAdminConfigResponse
{
    public function __construct(
        public readonly bool $hasPayments,
        public readonly bool $hasUsers,
        public readonly bool $hasCredits,
        public readonly bool $hasSubscriptions,
        public readonly ?string $currency,
        /** @var array<int, array{id: string, name: string, priceAmount: int, currency: string, intervalDays: int}> */
        public readonly array $plans,
    ) {
    }
}
```

`$currency` is null when payments are not enabled.
`$plans` is an empty array when subscriptions are not enabled or no plans are configured.
Each plan entry contains only the fields the UI needs — not `stripePriceId` (internal Stripe detail).

---

### 3. GetAdminConfigUseCaseInterface

`src/Application/UseCase/AdminConfig/GetAdminConfig/GetAdminConfigUseCaseInterface.php`

```php
interface GetAdminConfigUseCaseInterface
{
    public function execute(GetAdminConfigRequest $request): GetAdminConfigResponse;
}
```

---

### 4. GetAdminConfigUseCase + GetAdminConfigUseCaseTest

`src/Application/UseCase/AdminConfig/GetAdminConfig/GetAdminConfigUseCase.php`

Constructor: `PlatformConfig $config`

Logic:
1. Read `$config->hasPayments()`, `hasUsers()`, `hasCredits()`, `hasSubscriptions()`
2. `$currency` = `$config->payments->currency` if payments enabled, else `null`
3. `$plans` = map `$config->subscriptions->plans` to the response shape if subscriptions
   enabled, else `[]`
4. Return `GetAdminConfigResponse`

Plan mapping (strip `stripePriceId`, keep the rest):
```php
array_map(
    fn (PlanConfig $p) => [
        'id'          => $p->id,
        'name'        => $p->name,
        'priceAmount' => $p->priceAmount,
        'currency'    => $p->currency,
        'intervalDays' => $p->intervalDays,
    ],
    $config->subscriptions->plans,
)
```

`tests/Application/UseCase/AdminConfig/GetAdminConfigUseCaseTest.php`:

- `testMinimalConfigReturnsAllFeaturesDisabled` — only mailer configured; all has* false, currency null, plans empty
- `testPaymentsEnabledReturnsCurrency` — payments configured; hasPayments true, currency matches config
- `testUsersEnabledReturnsHasUsersTrue`
- `testCreditsEnabledReturnsHasCreditsTrue`
- `testSubscriptionsEnabledReturnsPlanSummaries` — one plan configured; plans array has one entry with correct fields
- `testSubscriptionsPlanDoesNotExposeStripePriceId` — assert `stripePriceId` is not present in any plan entry
- `testAllFeaturesEnabled` — full config; all has* true, currency set, plans present

---

### 5. Register in container

`config/container.php` — add:

```php
GetAdminConfigUseCaseInterface::class => autowire(GetAdminConfigUseCase::class),
```

Note: `PlatformConfig` is already bound by the client app (no extra wiring needed).

---

## General rules

- `stripePriceId` must never appear in the response — it is an internal Stripe detail
- No `@throws` needed — this use case performs no I/O and throws no exceptions
- All `has*` flags derive from `PlatformConfig` — do not re-implement the logic, call the existing methods
