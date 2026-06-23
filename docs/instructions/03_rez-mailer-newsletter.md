# Rez — Mailer & Newsletter

This document adds the mailer port and newsletter domain to `davidrubydev/rez`.
Complete `rez-config.md` before starting this.

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

`MailerInterface` is a port — `rez` defines the contract, the client repo provides the
concrete implementation (e.g. `SymfonyMailer`). This means `rez` has zero dependency on
any mailer library. Email sending always happens as a post-action side effect after a use
case succeeds — never inside the use case itself.

Newsletter covers both guest opt-ins (checkbox during booking) and registered user
preferences. It is independent of the users feature — guests can subscribe without an account.
The `Broadcast` use case sends a notification to all opted-in subscribers when a new class
is added by the admin.

---

## New files

```
src/
  Application/
    Port/
      MailerInterface.php
      NewsletterRepositoryInterface.php
  Domain/
    Newsletter/
      NewsletterSubscriber.php
      NewsletterSubscriberId.php
      SubscriberSource.php
    Exception/
      NewsletterSubscriberNotFoundException.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlNewsletterRepository.php

tests/
  Domain/
    Newsletter/
      NewsletterSubscriberTest.php
  Application/
    UseCase/
      Newsletter/
        SubscribeUseCaseTest.php
        UnsubscribeUseCaseTest.php
        BroadcastUseCaseTest.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlNewsletterRepositoryTest.php (integration, skipped without DB)
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. NewsletterSubscriberNotFoundException

`src/Domain/Exception/NewsletterSubscriberNotFoundException.php`

Extends `DomainException`. Constructor: `string $email`.
Message: `"Newsletter subscriber with email '{$email}' not found."`

---

### 2. SubscriberSource

`src/Domain/Newsletter/SubscriberSource.php` — pure enum.

```php
enum SubscriberSource
{
    case Guest;
    case Registered;
}
```

String serialization handled by infrastructure. No test needed.

---

### 3. NewsletterSubscriberId + NewsletterSubscriberIdTest

Same UUID v4 pattern as `ReservationId` and `ResourceId`.

- `static generate(): self`
- `static fromString(string $id): self` — throws `\InvalidArgumentException` if not valid UUID
- `toString(): string`
- `equals(self $other): bool`
- `__toString(): string`

`NewsletterSubscriberIdTest`:
- `generate()` produces valid UUID v4
- `fromString()` roundtrips correctly
- `fromString()` with invalid string throws `\InvalidArgumentException`
- `equals()` true for same, false for different

---

### 4. NewsletterSubscriber + NewsletterSubscriberTest

`src/Domain/Newsletter/NewsletterSubscriber.php` — immutable entity. Static factory only.

```php
public static function create(
    NewsletterSubscriberId $id,
    string $email,
    ?string $name,
    SubscriberSource $source,
): self
```

- Throws `\InvalidArgumentException` if `$email` is not valid (`filter_var`)
- `$optedInAt` set to current UTC time on `create()`

Getters: `getId()`, `getEmail()`, `getName()`, `getSource()`, `getOptedInAt()`

No mutation methods — subscribers are immutable once created.

`tests/Domain/Newsletter/NewsletterSubscriberTest.php`:

- Valid construction stores all values
- Invalid email throws `\InvalidArgumentException`
- Null name is accepted and returned as null
- `getOptedInAt()` is a `DateTimeImmutable` approximately equal to UTC now
- Guest source stored and returned correctly
- Registered source stored and returned correctly

---

### 5. MailerInterface

`src/Application/Port/MailerInterface.php`

```php
interface MailerInterface
{
    public function sendBookingConfirmation(
        string $recipientEmail,
        string $recipientName,
        \Rez\Domain\Reservation\Reservation $reservation,
    ): void;

    public function sendBookingCancellation(
        string $recipientEmail,
        string $recipientName,
        \Rez\Domain\Reservation\Reservation $reservation,
    ): void;

    public function sendPasswordReset(
        string $email,
        string $resetUrl,
    ): void;

    public function sendNewClassNotification(
        string $email,
        string $className,
        \DateTimeImmutable $classDate,
    ): void;
}
```

Note: recipient details passed as plain strings — not `User` objects — so the interface
has no dependency on the users domain. This keeps mailer usable even in profile 1
(no user accounts) where the recipient is a guest identified only by email and name.

No test needed — it is an interface.

---

### 6. NewsletterRepositoryInterface

`src/Application/Port/NewsletterRepositoryInterface.php`

```php
interface NewsletterRepositoryInterface
{
    public function findByEmail(string $email): NewsletterSubscriber;  // throws NewsletterSubscriberNotFoundException
    /** @return NewsletterSubscriber[] */
    public function findAll(): array;
    public function save(NewsletterSubscriber $subscriber): void;      // upsert by email
    public function delete(NewsletterSubscriberId $id): void;
}
```

---

### 7. Newsletter use cases

Build each in TDD order: test first, then implementation.

#### Subscribe

`src/Application/UseCase/Newsletter/Subscribe/SubscribeRequest.php` — readonly:
```php
string $email, ?string $name, SubscriberSource $source
```

`src/Application/UseCase/Newsletter/Subscribe/SubscribeResponse.php` — readonly:
```php
NewsletterSubscriber $subscriber
```

`src/Application/UseCase/Newsletter/Subscribe/SubscribeUseCaseInterface.php`:
```php
interface SubscribeUseCaseInterface
{
    public function execute(SubscribeRequest $request): SubscribeResponse;
}
```

`src/Application/UseCase/Newsletter/Subscribe/SubscribeUseCase.php`:

Constructor: `NewsletterRepositoryInterface $newsletterRepository`

Logic:
1. Try `findByEmail($request->email)`
2. If found — return existing subscriber (idempotent, no error)
3. If `NewsletterSubscriberNotFoundException` caught — create new:
   `NewsletterSubscriber::create(NewsletterSubscriberId::generate(), email, name, source)`
4. `save()`
5. Return response

`tests/Application/UseCase/Newsletter/SubscribeUseCaseTest.php`:
- Subscribing with new email creates and saves subscriber
- Subscribing with existing email returns existing subscriber without saving again
- Guest source stored correctly
- Registered source stored correctly

#### Unsubscribe

`UnsubscribeRequest` — readonly: `string $email`
`UnsubscribeResponse` — readonly: `bool $removed`

`UnsubscribeUseCaseInterface` — same pattern.

`UnsubscribeUseCase`:
Constructor: `NewsletterRepositoryInterface $newsletterRepository`

1. Try `findByEmail($request->email)`
2. If `NewsletterSubscriberNotFoundException` — return `removed: false` silently
3. If found — `delete($subscriber->getId())`, return `removed: true`

`tests/Application/UseCase/Newsletter/UnsubscribeUseCaseTest.php`:
- Unknown email returns `removed: false` without throwing
- Known email deletes subscriber and returns `removed: true`

#### Broadcast

`BroadcastRequest` — readonly:
```php
string $className,
\DateTimeImmutable $classDate,
```

`BroadcastResponse` — readonly: `int $sent`

`BroadcastUseCaseInterface` — same pattern.

`BroadcastUseCase`:
Constructor: `NewsletterRepositoryInterface $newsletterRepository, MailerInterface $mailer`

1. `findAll()` subscribers
2. For each subscriber: `mailer->sendNewClassNotification(email, className, classDate)`
3. Return count of emails sent

`tests/Application/UseCase/Newsletter/BroadcastUseCaseTest.php`:
- Empty subscriber list sends no emails, returns 0
- Three subscribers — `sendNewClassNotification` called exactly 3 times
- Returns correct sent count

---

### 8. Database schema — newsletter_subscribers table

Add to `database/seeds/000_schema.sql` (within the `000`–`099` rez range):

```sql
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    email       VARCHAR(255) NOT NULL UNIQUE,
    name        VARCHAR(255) NULL,
    source      VARCHAR(20)  NOT NULL,
    opted_in_at DATETIME     NOT NULL
);
```

---

### 9. MysqlNewsletterRepository

`src/Infrastructure/Persistence/Mysql/MysqlNewsletterRepository.php`

Implements `NewsletterRepositoryInterface`. Constructor injects `\PDO`.
Extends `MysqlRepository` base class (already exists in `rez`).

- `findByEmail()`: `SELECT * FROM newsletter_subscribers WHERE email = :email`
  Throws `NewsletterSubscriberNotFoundException` if no row.
- `findAll()`: `SELECT * FROM newsletter_subscribers ORDER BY opted_in_at ASC`
  Returns `NewsletterSubscriber[]`.
- `save()`: `INSERT INTO newsletter_subscribers (...) ON DUPLICATE KEY UPDATE name = VALUES(name), source = VALUES(source)`
  Upserts by email — re-subscribing updates name and source but keeps original `opted_in_at`.
- `delete()`: `DELETE FROM newsletter_subscribers WHERE id = :id`

Hydration: construct `NewsletterSubscriber` via a private `hydrate(array $row): NewsletterSubscriber`
method using `NewsletterSubscriberId::fromString()`, `SubscriberSource` from string mapping, and
`new \DateTimeImmutable($row['opted_in_at'], new \DateTimeZone('UTC'))`.

`SubscriberSource` string mapping (in mapper or inline in repository — your choice):
- `Guest` ↔ `'guest'`
- `Registered` ↔ `'registered'`

`tests/Integration/Persistence/Mysql/MysqlNewsletterRepositoryTest.php`:

Extends `MysqlIntegrationTestCase`. Skip gracefully when DB env vars absent.

- `testSaveAndFindByEmail` — save subscriber, find by email, assert all fields match
- `testFindByEmailThrowsWhenNotFound` — assert `NewsletterSubscriberNotFoundException`
- `testSaveIsIdempotentByEmail` — save same email twice, assert only one row exists
- `testFindAllReturnsAllSubscribers` — save 3 subscribers, `findAll()` returns 3
- `testDeleteRemovesSubscriber` — save, delete, assert `findByEmail()` throws

---

### 10. Register in container

`config/container.php`

Add:
```php
\Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCase::class),

\Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Newsletter\Unsubscribe\UnsubscribeUseCase::class),

\Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Newsletter\Broadcast\BroadcastUseCase::class),
```

Note: `MailerInterface` and `NewsletterRepositoryInterface` must be bound by the client app.
Document this clearly in a comment.

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. NewsletterSubscriberNotFoundException
- [ ] 2. SubscriberSource enum
- [ ] 3. NewsletterSubscriberId + NewsletterSubscriberIdTest
- [ ] 4. NewsletterSubscriber + NewsletterSubscriberTest
- [ ] 5. MailerInterface
- [ ] 6. NewsletterRepositoryInterface
- [ ] 7. Subscribe use case + test
- [ ] 7. Unsubscribe use case + test
- [ ] 7. Broadcast use case + test
- [ ] 8. Database schema newsletter_subscribers
- [ ] 9. MysqlNewsletterRepository + integration test
- [ ] 10. container.php updated
