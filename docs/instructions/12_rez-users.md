# Rez — Users & Auth

This document adds the users domain, authentication, and JWT to `davidrubydev/rez`.
Complete `rez-config.md` and `rez-mailer-newsletter.md` before starting this.

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

Users adds registration, login, password reset, and JWT-based stateless auth.
`firebase/php-jwt` is a hard `require` dependency — it is always pulled regardless of whether
users are enabled, but `JwtService` is only used when `UsersConfig` is present in `PlatformConfig`.

`FeatureGuard::requireUsers()` must be called at the top of every use case in this document.

---

## Composer dependency

Add to `composer.json` `require`:
```json
"firebase/php-jwt": "^6.0"
```

Run `composer update` before proceeding.

---

## New files

```
src/
  Domain/
    User/
      User.php
      UserId.php
      UserCollection.php
      HashedPassword.php
      UserRole.php
    Exception/
      UserNotFoundException.php
      EmailAlreadyRegisteredException.php
      InvalidCredentialsException.php
      InvalidTokenException.php
  Application/
    Port/
      UserRepositoryInterface.php
      PasswordResetRepositoryInterface.php
      TokenGeneratorInterface.php
    UseCase/
      Auth/
        Register/
          RegisterUseCase.php
          RegisterRequest.php
          RegisterResponse.php
          RegisterUseCaseInterface.php
        Login/
          LoginUseCase.php
          LoginRequest.php
          LoginResponse.php
          LoginUseCaseInterface.php
        RequestPasswordReset/
          RequestPasswordResetUseCase.php
          RequestPasswordResetRequest.php
          RequestPasswordResetResponse.php
          RequestPasswordResetUseCaseInterface.php
        ResetPassword/
          ResetPasswordUseCase.php
          ResetPasswordRequest.php
          ResetPasswordResponse.php
          ResetPasswordUseCaseInterface.php
      User/
        GetUser/
          GetUserUseCase.php
          GetUserRequest.php
          GetUserResponse.php
          GetUserUseCaseInterface.php
        UpdateUser/
          UpdateUserUseCase.php
          UpdateUserRequest.php
          UpdateUserResponse.php
          UpdateUserUseCaseInterface.php
        ListUsers/
          ListUsersUseCase.php
          ListUsersRequest.php
          ListUsersResponse.php
          ListUsersUseCaseInterface.php
        AdminUpdateUser/
          AdminUpdateUserUseCase.php
          AdminUpdateUserRequest.php
          AdminUpdateUserResponse.php
          AdminUpdateUserUseCaseInterface.php
    Service/
      JwtService.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlUserRepository.php
        MysqlPasswordResetRepository.php
    Token/
      RandomTokenGenerator.php

tests/
  Domain/
    User/
      UserIdTest.php
      HashedPasswordTest.php
      UserTest.php
  Application/
    Service/
      JwtServiceTest.php
    UseCase/
      Auth/
        RegisterUseCaseTest.php
        LoginUseCaseTest.php
        RequestPasswordResetUseCaseTest.php
        ResetPasswordUseCaseTest.php
      User/
        GetUserUseCaseTest.php
        UpdateUserUseCaseTest.php
        ListUsersUseCaseTest.php
        AdminUpdateUserUseCaseTest.php
  Infrastructure/
    Token/
      RandomTokenGeneratorTest.php
    Persistence/
      Mysql/
        MysqlUserRepositoryTest.php (integration)
        MysqlPasswordResetRepositoryTest.php (integration)
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. Domain exceptions

`src/Domain/Exception/UserNotFoundException.php`
Extends `DomainException`. Constructor: `string $identifier` (email or id).
Message: `"User '{$identifier}' not found."`

`src/Domain/Exception/EmailAlreadyRegisteredException.php`
Extends `DomainException`. Constructor: `string $email`.
Message: `"Email '{$email}' is already registered."`

`src/Domain/Exception/InvalidCredentialsException.php`
Extends `DomainException`. No constructor args — never reveal which field was wrong.
Message: `"Invalid credentials."`

`src/Domain/Exception/InvalidTokenException.php`
Extends `DomainException`. Constructor: `string $reason`.
Message: `"Invalid token: {$reason}"`

---

### 2. UserId + UserIdTest

Same UUID v4 pattern as `ReservationId`, `ResourceId`, `NewsletterSubscriberId`.

- `static generate(): self`
- `static fromString(string $id): self` — throws `\InvalidArgumentException` if not valid UUID
- `toString(): string`
- `equals(self $other): bool`
- `__toString(): string`

`tests/Domain/User/UserIdTest.php`:
- `generate()` produces valid UUID v4
- `fromString()` roundtrips correctly
- `fromString()` with invalid string throws `\InvalidArgumentException`
- `equals()` true for same, false for different

---

### 3. HashedPassword + HashedPasswordTest

`src/Domain/User/HashedPassword.php` — immutable value object.

- `static fromPlainText(string $plainText): self`
  Throws `\InvalidArgumentException` if empty.
  Hashes via `password_hash($plainText, PASSWORD_BCRYPT)`.
- `static fromHash(string $hash): self`
  For hydration from persistence. Throws `\InvalidArgumentException` if empty.
- `verify(string $plainText): bool` — `password_verify()`
- `toString(): string`
- `__toString(): string`

`tests/Domain/User/HashedPasswordTest.php`:
- `fromPlainText()` with empty string throws `\InvalidArgumentException`
- `fromPlainText()` produces a non-empty hash different from the plain text
- `verify()` true for correct plain text
- `verify()` false for wrong plain text
- `fromHash()` with empty string throws `\InvalidArgumentException`
- `fromHash()` roundtrips: `fromHash($hash)->toString() === $hash`

---

### 4. UserRole

`src/Domain/User/UserRole.php` — pure enum.

```php
enum UserRole
{
    case Customer;
    case Admin;
}
```

String serialization in infrastructure. No test needed.

---

### 5. User + UserTest

`src/Domain/User/User.php` — immutable entity. Static factory only.

```php
public static function create(
    UserId $id,
    string $name,
    string $email,
    HashedPassword $password,
    UserRole $role = UserRole::Customer,
    bool $newsletterOptIn = false,
    ?string $stripeCustomerId = null,
): self
```

- Throws `\InvalidArgumentException` if `$name` is empty
- Throws `\InvalidArgumentException` if `$email` is not valid (`filter_var`)

State mutation — each returns a new immutable instance:
- `withName(string $name): self` — throws `\InvalidArgumentException` if empty
- `withNewsletterOptIn(bool $optIn): self`
- `withStripeCustomerId(string $id): self`
- `withPassword(HashedPassword $password): self`
- `withRole(UserRole $role): self`

Getters:
`getId()`, `getName()`, `getEmail()`, `getPassword()`, `getRole()`,
`isNewsletterOptIn()`, `getStripeCustomerId(): ?string`, `isAdmin(): bool`, `getCreatedAt(): \DateTimeImmutable`

`getCreatedAt()` is set to UTC now on `create()`.

`tests/Domain/User/UserTest.php`:
- Valid construction stores all values
- Empty name throws `\InvalidArgumentException`
- Invalid email throws `\InvalidArgumentException`
- Default role is `Customer`
- `withName()` returns new instance, original unchanged
- `withName()` with empty string throws `\InvalidArgumentException`
- `withNewsletterOptIn()` toggles correctly, returns new instance
- `withStripeCustomerId()` returns new instance with id set
- `isAdmin()` true for Admin role, false for Customer
- `getCreatedAt()` is approximately UTC now

---

### 6. UserCollection

`src/Domain/User/UserCollection.php` — same immutable collection pattern as `ResourceCollection`.

- `static empty(): self`
- `static fromArray(User[]): self` — validates all elements
- `add(User): self` — immutable
- `isEmpty(): bool`
- `count(): int`
- `toArray(): User[]`
- `filter(callable): self`
- `findById(UserId): ?User`
- `findByEmail(string): ?User`

No separate test — tested indirectly via use case and repository tests.

---

### 7. Port interfaces

`src/Application/Port/UserRepositoryInterface.php`:
```php
public function findById(UserId $id): User;           // throws UserNotFoundException
public function findByEmail(string $email): User;     // throws UserNotFoundException
public function findAll(): UserCollection;
public function save(User $user): void;               // upsert by id
public function delete(UserId $id): void;
```

`src/Application/Port/PasswordResetRepositoryInterface.php`:
```php
public function save(string $email, string $tokenHash, \DateTimeImmutable $expiresAt): void;
// throws InvalidTokenException if not found:
public function findByTokenHash(string $tokenHash): array; // ['email' => string, 'expires_at' => DateTimeImmutable]
public function deleteByEmail(string $email): void;
```

`src/Application/Port/TokenGeneratorInterface.php`:
```php
public function generate(int $bytes = 32): string;  // hex-encoded random token
```

---

### 8. JwtService + JwtServiceTest

`src/Application/Service/JwtService.php`

Constructor: `UsersConfig $config`

Uses `Firebase\JWT\JWT` and `Firebase\JWT\Key`.
Algorithm: `HS256`.

Methods:

`generate(UserId $userId, UserRole $role): string`
- Payload: `sub` = userId string, `role` = role name (e.g. `'Customer'`), `iat` = now, `exp` = now + ttlSeconds
- Returns encoded JWT string

`validate(string $token): array`
- Decodes and validates signature and expiry
- Throws `InvalidTokenException` with reason if expired or signature invalid
- Returns decoded payload as array

`extractUserId(string $token): UserId`
- Calls `validate()`, extracts `sub`, returns `UserId::fromString(sub)`

`extractRole(string $token): UserRole`
- Calls `validate()`, extracts `role`, maps string to `UserRole` case
- Throws `InvalidTokenException` if role string is unrecognised

`tests/Application/Service/JwtServiceTest.php`:
- `generate()` returns non-empty string
- `validate()` roundtrips — token generated then validated returns correct payload
- `validate()` with tampered token throws `InvalidTokenException`
- `validate()` with expired token throws `InvalidTokenException` — use `jwtTtlSeconds: -1` in config
- `extractUserId()` returns correct `UserId`
- `extractRole()` returns correct `UserRole`
- `extractRole()` with unknown role string throws `InvalidTokenException`

---

### 9. RandomTokenGenerator + RandomTokenGeneratorTest

`src/Infrastructure/Token/RandomTokenGenerator.php` implements `TokenGeneratorInterface`.

```php
public function generate(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
```

`tests/Infrastructure/Token/RandomTokenGeneratorTest.php`:
- Returns non-empty string
- Length equals `$bytes * 2` (hex encoding doubles the length)
- Two consecutive calls return different values

---

### 10. Auth use cases

Build each in TDD order: test first (with PHPUnit mocks), then implementation.

#### Register

`RegisterRequest` — readonly:
```php
string $name,
string $email,
string $password,
bool $newsletterOptIn = false,
```

`RegisterResponse` — readonly: `User $user, string $token`

`RegisterUseCase implements RegisterUseCaseInterface`:

Constructor: `UserRepositoryInterface`, `SubscribeUseCaseInterface`, `JwtService`, `FeatureGuard`

Logic:
1. `$guard->requireUsers()`
2. Try `userRepository->findByEmail($email)` — if found throw `EmailAlreadyRegisteredException`
3. Catch `UserNotFoundException` — proceed (email is available)
4. `HashedPassword::fromPlainText($password)`
5. `User::create(UserId::generate(), name, email, password)`
6. `userRepository->save($user)`
7. If `$newsletterOptIn`: `subscribeUseCase->execute(new SubscribeRequest(email, name, SubscriberSource::Registered))`
8. `jwtService->generate($user->getId(), $user->getRole())`
9. Return response

`tests/Application/UseCase/Auth/RegisterUseCaseTest.php`:
- `requireUsers()` not called — users disabled throws `FeatureDisabledException`
- Duplicate email throws `EmailAlreadyRegisteredException`
- Success: `save()` called exactly once
- Success: returned token is a non-empty string
- Success with `newsletterOptIn: true` — `SubscribeUseCase::execute()` called once
- Success with `newsletterOptIn: false` — `SubscribeUseCase::execute()` never called
- Returned user has correct name and email

#### Login

`LoginRequest` — readonly: `string $email, string $password`
`LoginResponse` — readonly: `User $user, string $token`

`LoginUseCase implements LoginUseCaseInterface`:

Constructor: `UserRepositoryInterface`, `JwtService`, `FeatureGuard`

Logic:
1. `$guard->requireUsers()`
2. Try `userRepository->findByEmail($email)`
3. Catch `UserNotFoundException` — throw `InvalidCredentialsException` (never reveal email existence)
4. `$user->getPassword()->verify($password)` — throw `InvalidCredentialsException` if false
5. `jwtService->generate($user->getId(), $user->getRole())`
6. Return response

`tests/Application/UseCase/Auth/LoginUseCaseTest.php`:
- Users disabled throws `FeatureDisabledException`
- Unknown email throws `InvalidCredentialsException` (NOT `UserNotFoundException`)
- Wrong password throws `InvalidCredentialsException`
- Success: returns user and non-empty token

#### RequestPasswordReset

`RequestPasswordResetRequest` — readonly: `string $email, string $resetBaseUrl`
`RequestPasswordResetResponse` — readonly: `bool $sent` (always true — never reveal email existence)

`RequestPasswordResetUseCase implements RequestPasswordResetUseCaseInterface`:

Constructor: `UserRepositoryInterface`, `PasswordResetRepositoryInterface`,
`TokenGeneratorInterface`, `MailerInterface`, `FeatureGuard`, `UsersConfig`

Logic:
1. `$guard->requireUsers()`
2. Try `userRepository->findByEmail($email)` — if `UserNotFoundException`, return `sent: true` silently
3. `$token = tokenGenerator->generate()`
4. `$tokenHash = hash('sha256', $token)`
5. `$expiresAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))` + `passwordResetTtlMinutes`
6. `passwordResetRepository->save($email, $tokenHash, $expiresAt)`
7. `$resetUrl = rtrim($resetBaseUrl, '/') . '?token=' . $token`
8. `mailer->sendPasswordReset($email, $resetUrl)`
9. Return `sent: true`

`tests/Application/UseCase/Auth/RequestPasswordResetUseCaseTest.php`:
- Users disabled throws `FeatureDisabledException`
- Unknown email returns `sent: true` — mailer never called, token never saved
- Known email: token saved (assert `save()` called once)
- Known email: raw token is NOT what gets saved (hash is stored, not the token itself)
- Known email: mailer called with URL containing the raw token
- Known email: returns `sent: true`

#### ResetPassword

`ResetPasswordRequest` — readonly: `string $token, string $newPassword`
`ResetPasswordResponse` — readonly: `bool $success`

`ResetPasswordUseCase implements ResetPasswordUseCaseInterface`:

Constructor: `UserRepositoryInterface`, `PasswordResetRepositoryInterface`, `FeatureGuard`

Logic:
1. `$guard->requireUsers()`
2. `$tokenHash = hash('sha256', $request->token)`
3. `$record = passwordResetRepository->findByTokenHash($tokenHash)` — throws `InvalidTokenException` if not found
4. If `$record['expires_at'] < now UTC` — throw `InvalidTokenException('Token has expired')`
5. `$user = userRepository->findByEmail($record['email'])`
6. `$newPassword = HashedPassword::fromPlainText($request->newPassword)`
7. `userRepository->save($user->withPassword($newPassword))`
8. `passwordResetRepository->deleteByEmail($record['email'])`
9. Return `success: true`

`tests/Application/UseCase/Auth/ResetPasswordUseCaseTest.php`:
- Users disabled throws `FeatureDisabledException`
- Unknown token throws `InvalidTokenException`
- Expired token throws `InvalidTokenException`
- Success: user saved with new (different) password hash
- Success: token deleted after use (assert `deleteByEmail()` called)
- Success: returns `success: true`

---

### 11. User management use cases

Build each in TDD order.

#### GetUser

`GetUserRequest` — readonly: `UserId $userId`
`GetUserResponse` — readonly: `User $user`

`GetUserUseCase implements GetUserUseCaseInterface`:
1. `$guard->requireUsers()`
2. `userRepository->findById($userId)` — propagates `UserNotFoundException`
3. Return response

Test: users disabled throws, not found propagates, found returns correct user.

#### UpdateUser

`UpdateUserRequest` — readonly: `UserId $userId, ?string $name, ?bool $newsletterOptIn`
`UpdateUserResponse` — readonly: `User $user`

`UpdateUserUseCase implements UpdateUserUseCaseInterface`:
1. `$guard->requireUsers()`
2. `findById()`
3. Apply non-null fields via `with*()` methods
4. `save()`
5. Return response

Test: users disabled throws, not found propagates, partial update preserves unchanged fields,
updated fields reflected in response, original user unchanged (immutability).

#### ListUsers

`ListUsersRequest` — empty readonly class.
`ListUsersResponse` — readonly: `UserCollection $users`

`ListUsersUseCase implements ListUsersUseCaseInterface`:
1. `$guard->requireUsers()`
2. `findAll()`
3. Return response

Test: users disabled throws, returns all users from repository.

#### AdminUpdateUser

`AdminUpdateUserRequest` — readonly: `UserId $targetUserId, ?UserRole $role, ?bool $newsletterOptIn`
`AdminUpdateUserResponse` — readonly: `User $user`

`AdminUpdateUserUseCase implements AdminUpdateUserUseCaseInterface`:
1. `$guard->requireUsers()`
2. `findById($targetUserId)`
3. Apply non-null fields
4. `save()`
5. Return response

Note: admin role enforcement is the HTTP layer's responsibility (middleware checks JWT role),
not this use case's. The use case trusts that the caller is authorised.

Test: users disabled throws, not found propagates, role updated correctly, newsletter updated correctly.

---

### 12. Database schema — users and password_reset_tokens tables

Add to `database/seeds/000_schema.sql`:

```sql
CREATE TABLE IF NOT EXISTS users (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,
    name               VARCHAR(255) NOT NULL,
    email              VARCHAR(255) NOT NULL UNIQUE,
    password_hash      VARCHAR(255) NOT NULL,
    role               VARCHAR(20)  NOT NULL DEFAULT 'customer',
    newsletter_opt_in  TINYINT(1)   NOT NULL DEFAULT 0,
    stripe_customer_id VARCHAR(255) NULL,
    created_at         DATETIME     NOT NULL
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email      VARCHAR(255) NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    PRIMARY KEY (email)
);
```

Note: `users` table must be created before any table that references it via foreign key.
Ensure it appears before `wallet_transactions`, `subscriptions` in the schema file.

---

### 13. MysqlUserRepository + MysqlPasswordResetRepository

`src/Infrastructure/Persistence/Mysql/MysqlUserRepository.php`

Implements `UserRepositoryInterface`. Constructor injects `\PDO`.

- `findById()`: `SELECT * FROM users WHERE id = :id` — throws `UserNotFoundException` if missing
- `findByEmail()`: `SELECT * FROM users WHERE email = :email` — throws `UserNotFoundException` if missing
- `findAll()`: `SELECT * FROM users ORDER BY created_at ASC` — returns `UserCollection`
- `save()`: `INSERT INTO users (...) ON DUPLICATE KEY UPDATE name=VALUES(name), ...`
  All mutable fields included in the UPDATE clause. `id` and `created_at` never updated.
- `delete()`: `DELETE FROM users WHERE id = :id`

`UserRole` string mapping (inline or via mapper):
- `Customer` ↔ `'customer'`
- `Admin` ↔ `'admin'`

`src/Infrastructure/Persistence/Mysql/MysqlPasswordResetRepository.php`

Implements `PasswordResetRepositoryInterface`. Constructor injects `\PDO`.

- `save()`: `INSERT INTO password_reset_tokens (email, token_hash, expires_at) VALUES (...) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), expires_at=VALUES(expires_at)`
  One row per email — re-requesting a reset overwrites the previous token.
- `findByTokenHash()`: `SELECT * FROM password_reset_tokens WHERE token_hash = :hash`
  Throws `InvalidTokenException('Token not found')` if missing.
  Returns `['email' => string, 'expires_at' => DateTimeImmutable]`.
- `deleteByEmail()`: `DELETE FROM password_reset_tokens WHERE email = :email`

`tests/Integration/Persistence/Mysql/MysqlUserRepositoryTest.php`:
- `testSaveAndFindById` — roundtrip all fields
- `testFindByIdThrowsWhenNotFound` — assert `UserNotFoundException`
- `testFindByEmail` — find by email after save
- `testFindByEmailThrowsWhenNotFound` — assert `UserNotFoundException`
- `testSaveUpdatesExistingUser` — save, mutate via `with*()`, save again, find, assert updated values
- `testFindAllReturnsAllUsers` — save 3, findAll returns 3
- `testDeleteRemovesUser` — save, delete, findById throws

`tests/Integration/Persistence/Mysql/MysqlPasswordResetRepositoryTest.php`:
- `testSaveAndFindByTokenHash` — save token, find by hash, assert email and expiry match
- `testFindByTokenHashThrowsWhenNotFound` — assert `InvalidTokenException`
- `testSaveOverwritesPreviousTokenForEmail` — save twice for same email, only one row
- `testDeleteByEmailRemovesToken` — save, delete, findByTokenHash throws

---

### 14. Register in container

`config/container.php`

Add all use case interface → implementation bindings via `autowire()`.
Add `JwtService` via `autowire()`.

Client app must bind:
- `UserRepositoryInterface` → `MysqlUserRepository`
- `PasswordResetRepositoryInterface` → `MysqlPasswordResetRepository`
- `TokenGeneratorInterface` → `RandomTokenGenerator`
- `MailerInterface` → client-provided implementation

Document all client-required bindings in a comment.

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. Domain exceptions (4 classes)
- [ ] 2. UserId + UserIdTest
- [ ] 3. HashedPassword + HashedPasswordTest
- [ ] 4. UserRole enum
- [ ] 5. User + UserTest
- [ ] 6. UserCollection
- [ ] 7. Port interfaces (UserRepository, PasswordReset, TokenGenerator)
- [ ] 8. JwtService + JwtServiceTest
- [ ] 9. RandomTokenGenerator + RandomTokenGeneratorTest
- [ ] 10. Auth use cases (Register, Login, RequestPasswordReset, ResetPassword) + tests
- [ ] 11. User management use cases (GetUser, UpdateUser, ListUsers, AdminUpdateUser) + tests
- [ ] 12. Database schema (users, password_reset_tokens)
- [ ] 13. MysqlUserRepository + MysqlPasswordResetRepository + integration tests
- [ ] 14. container.php updated
