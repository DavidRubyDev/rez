# Rez — Booking (Platform Layer)

This document adds the platform-level booking use cases to `davidrubydev/rez`.
These wrap the core `CreateReservationUseCase` and `CancelReservationUseCase` with
payment resolution, party resolution, and post-action email.

Complete ALL previous scaffold documents before starting this one:
- rez-config.md
- rez-mailer-newsletter.md
- rez-users.md
- rez-payments.md
- rez-credits.md
- rez-subscriptions.md

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

The core `CreateReservationUseCase` knows nothing about users, payments, or email.
`CreateBookingUseCase` is the platform orchestrator that:
1. Resolves WHO is booking (guest vs registered user) → produces a `Party`
2. Resolves HOW they are paying → validates or charges
3. Calls the core reservation use case
4. Handles post-booking side effects (email, newsletter opt-in)

The critical ordering rule: **payment checks happen BEFORE the reservation is created.**
A failed credit debit must never leave behind a created reservation.

`CancelBookingUseCase` wraps cancellation with ownership verification and email notification.

---

## New files

```
src/
  Application/
    Service/
      PartyResolver.php
      PaymentResolver.php
      PaymentResolution.php
    UseCase/
      Booking/
        CreateBooking/
          CreateBookingUseCase.php
          CreateBookingRequest.php
          CreateBookingResponse.php
          CreateBookingUseCaseInterface.php
        CancelBooking/
          CancelBookingUseCase.php
          CancelBookingRequest.php
          CancelBookingResponse.php
          CancelBookingUseCaseInterface.php

tests/
  Application/
    Service/
      PartyResolverTest.php
      PaymentResolverTest.php
    UseCase/
      Booking/
        CreateBookingUseCaseTest.php
        CancelBookingUseCaseTest.php
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. PartyResolver + PartyResolverTest

`src/Application/Service/PartyResolver.php`

Constructor: `UserRepositoryInterface $userRepository`

```php
public function resolve(
    ?UserId $userId,
    ?string $guestName,
    ?string $guestEmail,
    int $size = 1,
    ?string $guestPhone = null,
): \Rez\Domain\Reservation\Party
```

Logic:
- If `$userId !== null`:
  Load `User` via `userRepository->findById($userId)`.
  Build `Party` using user's name and email.
  Set `externalRef` to `$userId->toString()`.
  `$size` always comes from the request (not the user record).
- If `$userId === null`:
  Guest path. Throw `\InvalidArgumentException` if `$guestName` is null or empty.
  Throw `\InvalidArgumentException` if `$guestEmail` is null or empty.
  Build `Party` from guest fields. `externalRef` stays null.

`tests/Application/Service/PartyResolverTest.php`:
- Authenticated user → Party uses user name/email, externalRef equals userId string
- Authenticated user → size comes from request, not user
- Guest → Party uses provided name/email, externalRef is null
- Guest with null name → throws `\InvalidArgumentException`
- Guest with empty name → throws `\InvalidArgumentException`
- Guest with null email → throws `\InvalidArgumentException`
- User not found → propagates `UserNotFoundException`

---

### 2. PaymentResolution

`src/Application/Service/PaymentResolution.php` — immutable value object.

```php
public function __construct(
    public readonly string $method,           // 'online' | 'credits' | 'on_site' | 'subscription'
    public readonly bool $requiresStripe,     // true when method is 'online'
    public readonly bool $isFree,             // true when subscription or zero price
    public readonly bool $useCredits,         // true when method is 'credits'
)
```

No validation — constructed only by `PaymentResolver`. No test needed.

---

### 3. PaymentResolver + PaymentResolverTest

`src/Application/Service/PaymentResolver.php`

Constructor:
```php
WalletRepositoryInterface $walletRepository,
SubscriptionRepositoryInterface $subscriptionRepository,
FeatureGuard $guard,
```

```php
public function resolve(
    ?UserId $userId,
    Money $price,
    string $paymentMethod,  // 'online' | 'credits' | 'on_site' | 'subscription'
): PaymentResolution
```

Logic per payment method:

**`'subscription'`**:
1. `$guard->requireSubscriptions()`
2. If `$userId === null` throw `\InvalidArgumentException('Subscription payment requires a logged-in user')`
3. `$subscription = subscriptionRepository->findByUserId($userId)` — propagates `SubscriptionNotFoundException`
4. If `!$subscription->isActive()` throw `SubscriptionNotFoundException` (treat expired as not found)
5. Return `new PaymentResolution('subscription', false, true, false)`

**`'credits'`**:
1. `$guard->requireCredits()`
2. If `$userId === null` throw `\InvalidArgumentException('Credit payment requires a logged-in user')`
3. `$transactions = walletRepository->findTransactionsByUserId($userId)`
4. `$wallet = new Wallet($userId, $transactions)`
5. If `!$wallet->canAfford($price)` throw `InsufficientFundsException`
6. Return `new PaymentResolution('credits', false, false, true)`

**`'online'`**:
1. `$guard->requirePayments()`
2. Return `new PaymentResolution('online', true, false, false)`

**`'on_site'`**:
1. No feature guard — always allowed
2. Return `new PaymentResolution('on_site', false, false, false)`

**Unknown method**:
Throw `\InvalidArgumentException("Unknown payment method: {$paymentMethod}")`

`tests/Application/Service/PaymentResolverTest.php`:
- `'subscription'` with active subscription returns free resolution
- `'subscription'` with no subscription throws `SubscriptionNotFoundException`
- `'subscription'` with expired subscription throws `SubscriptionNotFoundException`
- `'subscription'` with null userId throws `\InvalidArgumentException`
- `'subscription'` with subscriptions disabled throws `FeatureDisabledException`
- `'credits'` with sufficient balance returns credit resolution
- `'credits'` with insufficient balance throws `InsufficientFundsException`
- `'credits'` with null userId throws `\InvalidArgumentException`
- `'credits'` with credits disabled throws `FeatureDisabledException`
- `'online'` returns Stripe resolution
- `'online'` with payments disabled throws `FeatureDisabledException`
- `'on_site'` always succeeds regardless of config
- Unknown method throws `\InvalidArgumentException`

---

### 4. CreateBooking use case

`CreateBookingRequest` — readonly:
```php
/** @var \Rez\Domain\Resource\ResourceId[] */
array $resourceIds,
\DateTimeImmutable $start,
\DateTimeImmutable $end,
int $size,
string $paymentMethod,          // 'online' | 'credits' | 'on_site' | 'subscription'
Money $price,                   // used for credit/online validation
?UserId $userId = null,
?string $guestName = null,
?string $guestEmail = null,
?string $guestPhone = null,
bool $newsletterOptIn = false,
```

`CreateBookingResponse` — readonly: `\Rez\Domain\Reservation\Reservation $reservation`

`CreateBookingUseCase implements CreateBookingUseCaseInterface`:

Constructor:
```php
PartyResolver $partyResolver,
PaymentResolver $paymentResolver,
\Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCaseInterface $createReservationUseCase,
DebitWalletUseCaseInterface $debitWalletUseCase,
SubscribeUseCaseInterface $subscribeUseCase,
MailerInterface $mailer,
```

Logic — **strict ordering, do not change**:

1. `$resolution = paymentResolver->resolve($userId, $price, $paymentMethod)`
   Throws on invalid payment method or insufficient credits. If this throws, nothing else happens.

2. `$party = partyResolver->resolve($userId, $guestName, $guestEmail, $size, $guestPhone)`
   If this throws, nothing has been written yet.

3. Build `\Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationRequest`:
   ```php
   new CreateReservationRequest(
       resourceIds: $request->resourceIds,
       start: $request->start,
       end: $request->end,
       party: $party,
   )
   ```

4. `$reservation = createReservationUseCase->execute($createReservationRequest)->getReservation()`
   Throws `ConflictException` if slot taken. If it throws, no payment has been taken yet.

5. If `$resolution->useCredits`:
   ```php
   debitWalletUseCase->execute(new DebitWalletRequest(
       userId: $userId,
       amount: $price,
       description: 'Booking ' . $reservation->getId()->toString(),
       reservationId: $reservation->getId()->toString(),
   ))
   ```
   **If debit fails here (should not happen — PaymentResolver pre-checked),
   the reservation exists but is unpaid. Log this as a critical error.
   Do NOT cancel the reservation automatically — handle manually.**

6. If `$request->newsletterOptIn && $request->userId === null`:
   ```php
   subscribeUseCase->execute(new SubscribeRequest(
       email: $request->guestEmail,
       name: $request->guestName,
       source: SubscriberSource::Guest,
   ))
   ```
   Newsletter failure must NOT throw — catch any exception and log silently.
   A failed newsletter subscription must never roll back a completed booking.

7. **Superseded by `rez-email-restructure`** — `MailerInterface` no longer has a single
   `sendBookingConfirmation(string $email, string $name, Reservation $reservation)` method.
   It now exposes `sendReservationCreatedEmail(Reservation $reservation, CancellationToken
   $cancellationToken)` and `sendReservationConfirmedEmail(Reservation $reservation,
   CancellationToken $cancellationToken)` — pick created vs confirmed based on
   `$reservation->status` (confirmed if `autoConfirm` fired — read via
   `ReservationSettingsRepositoryInterface::get()->autoConfirm` since `rez-reservation-settings`,
   not `PlatformConfig->reservations`, which no longer exists). Generate the token with
   `CancellationToken::generate($reservation->id, $usersConfig->cancellationSecret)`
   (`CreateBookingUseCase` needs a `UsersConfig` dependency for this). Recipient email/name
   come from `$reservation->party` — do not pass them as separate string params, they're no
   longer part of the signature. Mailer failure must still NOT throw — catch any exception
   and log silently. A failed email must never roll back a completed booking.

8. Return `new CreateBookingResponse($reservation)`

`tests/Application/UseCase/Booking/CreateBookingUseCaseTest.php`:

- Payment resolution failure (e.g. insufficient credits) — `createReservationUseCase` never called
- Party resolution failure (missing guest email) — `createReservationUseCase` never called
- Core `ConflictException` propagates — no debit, no email
- `'on_site'` payment — `debitWalletUseCase` never called
- `'credits'` payment — `debitWalletUseCase` called with correct amount and reservationId
- `'online'` payment — `debitWalletUseCase` never called (Stripe handles async via webhook)
- Guest with `newsletterOptIn: true` — `subscribeUseCase` called
- Guest with `newsletterOptIn: false` — `subscribeUseCase` never called
- Authenticated user with `newsletterOptIn: true` — `subscribeUseCase` NOT called
  (registered users manage newsletter preference via account settings)
- Mailer failure (throws) — exception caught, booking still returned successfully
- Newsletter failure (throws) — exception caught, booking still returned successfully
- Success: returned response contains the reservation

---

### 5. CancelBooking use case

`CancelBookingRequest` — readonly:
```php
\Rez\Domain\Reservation\ReservationId $reservationId,
?UserId $requestingUserId,    // null = guest (cannot cancel — no ownership proof)
bool $isAdmin = false,        // set by HTTP middleware after verifying JWT role
```

`CancelBookingResponse` — readonly: `\Rez\Domain\Reservation\Reservation $reservation`

`CancelBookingUseCase implements CancelBookingUseCaseInterface`:

Constructor:
```php
\Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCaseInterface $getReservationUseCase,
\Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCaseInterface $cancelReservationUseCase,
MailerInterface $mailer,
```

Logic:
1. `$reservation = getReservationUseCase->execute(new GetReservationRequest($reservationId))->getReservation()`
   Propagates `ReservationNotFoundException`.

2. Ownership check — throw `\DomainException('Not authorised to cancel this reservation')` if:
   - `$request->isAdmin === false` AND
   - (`$request->requestingUserId === null` OR
     `$reservation->getParty()->getExternalRef() !== $request->requestingUserId->toString()`)

3. `$cancelled = cancelReservationUseCase->execute(new CancelReservationRequest($reservationId))->getReservation()`
   Propagates `\DomainException` if already cancelled.

4. **Superseded by `rez-email-restructure`** — call `mailer->sendReservationCancelledEmail($cancelled)`.
   No recipient email/name params and no cancellation token — the method only takes the
   reservation (nothing left to cancel once it's cancelled).
   Catch and log silently — email failure must not throw.

5. Return `new CancelBookingResponse($cancelled)`

`tests/Application/UseCase/Booking/CancelBookingUseCaseTest.php`:
- Reservation not found propagates `ReservationNotFoundException`
- Guest (null userId, not admin) throws `\DomainException`
- Authenticated user who does NOT own the reservation throws `\DomainException`
- Authenticated user who OWNS the reservation (externalRef matches) can cancel
- Admin (`isAdmin: true`) can cancel any reservation regardless of ownership
- Already cancelled reservation propagates `\DomainException` from core use case
- Cancellation email sent on success
- Mailer failure caught silently — cancellation still returned

---

### 6. Register in container

`config/container.php`

Add:
```php
\Rez\Application\Service\PartyResolver::class
    => \DI\autowire(),

\Rez\Application\Service\PaymentResolver::class
    => \DI\autowire(),

\Rez\Application\UseCase\Booking\CreateBooking\CreateBookingUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Booking\CreateBooking\CreateBookingUseCase::class),

\Rez\Application\UseCase\Booking\CancelBooking\CancelBookingUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Booking\CancelBooking\CancelBookingUseCase::class),
```

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. PartyResolver + PartyResolverTest
- [ ] 2. PaymentResolution value object
- [ ] 3. PaymentResolver + PaymentResolverTest
- [ ] 4. CreateBooking use case + test
- [ ] 5. CancelBooking use case + test
- [ ] 6. container.php updated
