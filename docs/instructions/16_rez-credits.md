# Rez — Credits & Wallet

This document adds the wallet domain, transaction log, and credit use cases to `davidrubydev/rez`.
Complete `rez-payments.md` before starting this (depends on `Money` and `InsufficientFundsException`).

Run `composer test` and `vendor/bin/phpstan analyse` after each step before proceeding.

---

## Context

The wallet is a transaction log — balance is always computed as `SUM` of all transactions,
never stored as a mutable column. This gives a full audit trail for free and eliminates
balance inconsistency bugs. The trade-off (a SUM query per balance check) is acceptable
at this scale and can be optimised with a materialized column later if needed.

Credits and wallet are scoped to registered users. `FeatureGuard::requireCredits()` must
be called at the top of every use case here.

---

## New files

```
src/
  Domain/
    Wallet/
      Wallet.php
      WalletTransaction.php
      WalletTransactionId.php
      WalletTransactionType.php
  Application/
    Port/
      WalletRepositoryInterface.php
    UseCase/
      Wallet/
        GetWallet/
          GetWalletUseCase.php
          GetWalletRequest.php
          GetWalletResponse.php
          GetWalletUseCaseInterface.php
        CreditWallet/
          CreditWalletUseCase.php
          CreditWalletRequest.php
          CreditWalletResponse.php
          CreditWalletUseCaseInterface.php
        DebitWallet/
          DebitWalletUseCase.php
          DebitWalletRequest.php
          DebitWalletResponse.php
          DebitWalletUseCaseInterface.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlWalletRepository.php

tests/
  Domain/
    Wallet/
      WalletTransactionIdTest.php
      WalletTest.php
  Application/
    UseCase/
      Wallet/
        GetWalletUseCaseTest.php
        CreditWalletUseCaseTest.php
        DebitWalletUseCaseTest.php
  Infrastructure/
    Persistence/
      Mysql/
        MysqlWalletRepositoryTest.php (integration)
```

---

## IMPLEMENT IN THIS EXACT ORDER

---

### 1. WalletTransactionType

`src/Domain/Wallet/WalletTransactionType.php` — pure enum.

```php
enum WalletTransactionType
{
    case Credit;
    case Debit;
}
```

String serialization in infrastructure:
- `Credit` ↔ `'credit'`
- `Debit` ↔ `'debit'`

No test needed.

---

### 2. WalletTransactionId + WalletTransactionIdTest

Same UUID v4 pattern as all other ID value objects in `rez`.

- `static generate(): self`
- `static fromString(string $id): self` — throws `\InvalidArgumentException` if not valid UUID
- `toString(): string`
- `equals(self $other): bool`
- `__toString(): string`

`tests/Domain/Wallet/WalletTransactionIdTest.php`:
- `generate()` produces valid UUID v4
- `fromString()` roundtrips correctly
- `fromString()` with invalid string throws `\InvalidArgumentException`
- `equals()` true for same, false for different

---

### 3. WalletTransaction

`src/Domain/Wallet/WalletTransaction.php` — immutable value object.

```php
public function __construct(
    private readonly WalletTransactionId $id,
    private readonly UserId $userId,
    private readonly Money $amount,         // always positive — sign encoded in type
    private readonly WalletTransactionType $type,
    private readonly string $description,
    private readonly ?string $reservationId, // nullable — set when debit is for a booking
    private readonly \DateTimeImmutable $createdAt,
)
```

- Throws `\InvalidArgumentException` if `$amount->getAmount() <= 0`
  (zero-amount transactions are meaningless)
- Throws `\InvalidArgumentException` if `$description` is empty

Getters for all fields. No mutation methods.

Static factory:
```php
public static function create(
    WalletTransactionId $id,
    UserId $userId,
    Money $amount,
    WalletTransactionType $type,
    string $description,
    ?string $reservationId = null,
): self
```
Sets `createdAt` to UTC now.

No separate test — tested indirectly via `WalletTest` and repository tests.

---

### 4. Wallet + WalletTest

`src/Domain/Wallet/Wallet.php` — immutable aggregate computed from transaction log.

```php
public function __construct(
    private readonly UserId $userId,
    private readonly array $transactions, // WalletTransaction[]
)
```

Methods:
- `getUserId(): UserId`
- `getTransactions(): WalletTransaction[]`
- `getBalance(): Money`
  Computes balance from transactions:
  - Start at zero (currency from first transaction, or `new Money(0, 'CZK')` default if empty)
  - For each `Credit` transaction: add amount
  - For each `Debit` transaction: subtract amount
  - Throws `\InvalidArgumentException` if transactions contain mixed currencies
- `canAfford(Money $amount): bool`
  `$this->getBalance()->getAmount() >= $amount->getAmount()`
  Also validates currencies match — throws `\InvalidArgumentException` if not

`tests/Domain/Wallet/WalletTest.php`:
- Empty transaction list yields zero balance
- Single credit transaction yields positive balance
- Single debit transaction yields negative... wait — debit with no prior credit.
  This is only valid computationally (balance goes negative). In practice, `DebitWalletUseCase`
  prevents this via `canAfford()` check. The domain itself allows it — test reflects that.
- Credit then debit yields correct net balance
- Multiple credits and debits yield correct net
- `canAfford()` true when balance >= amount
- `canAfford()` false when balance < amount
- `canAfford()` with mismatched currency throws `\InvalidArgumentException`
- Mixed currency transactions throws `\InvalidArgumentException`

---

### 5. WalletRepositoryInterface

`src/Application/Port/WalletRepositoryInterface.php`

```php
interface WalletRepositoryInterface
{
    /** @return WalletTransaction[] */
    public function findTransactionsByUserId(UserId $userId): array;

    public function saveTransaction(WalletTransaction $transaction): void;
}
```

Note: no `save(Wallet)` — the wallet is a read model computed from transactions.
Only individual transactions are persisted.

---

### 6. Wallet use cases

Build each in TDD order: test first, then implementation.

#### GetWallet

`GetWalletRequest` — readonly: `UserId $userId`
`GetWalletResponse` — readonly: `Wallet $wallet`

`GetWalletUseCase implements GetWalletUseCaseInterface`:

Constructor: `WalletRepositoryInterface`, `FeatureGuard`

1. `$guard->requireCredits()`
2. `$transactions = walletRepository->findTransactionsByUserId($userId)`
3. Return `new GetWalletResponse(new Wallet($userId, $transactions))`

`tests/Application/UseCase/Wallet/GetWalletUseCaseTest.php`:
- Credits disabled throws `FeatureDisabledException`
- Empty transaction list returns wallet with zero balance
- Transactions returned from repository are passed to wallet correctly

#### CreditWallet

`CreditWalletRequest` — readonly:
```php
UserId $userId,
Money $amount,
string $description,
```

`CreditWalletResponse` — readonly: `Wallet $wallet`

`CreditWalletUseCase implements CreditWalletUseCaseInterface`:

Constructor: `WalletRepositoryInterface`, `FeatureGuard`

1. `$guard->requireCredits()`
2. `$transaction = WalletTransaction::create(WalletTransactionId::generate(), $userId, $amount, WalletTransactionType::Credit, $description)`
3. `walletRepository->saveTransaction($transaction)`
4. Reload transactions and return updated wallet

`tests/Application/UseCase/Wallet/CreditWalletUseCaseTest.php`:
- Credits disabled throws `FeatureDisabledException`
- Success: `saveTransaction()` called with Credit type transaction
- Success: returned wallet reflects new balance
- Description stored correctly

#### DebitWallet

`DebitWalletRequest` — readonly:
```php
UserId $userId,
Money $amount,
string $description,
?string $reservationId = null,
```

`DebitWalletResponse` — readonly: `Wallet $wallet`

`DebitWalletUseCase implements DebitWalletUseCaseInterface`:

Constructor: `WalletRepositoryInterface`, `FeatureGuard`

1. `$guard->requireCredits()`
2. Load current wallet via `findTransactionsByUserId()`
3. `$wallet->canAfford($amount)` — throw `InsufficientFundsException` if false
4. `$transaction = WalletTransaction::create(..., WalletTransactionType::Debit, $description, $reservationId)`
5. `walletRepository->saveTransaction($transaction)`
6. Return updated wallet

**Critical ordering:** check `canAfford()` BEFORE saving the transaction. If the check fails,
no transaction is written. Never save a transaction and then check.

`tests/Application/UseCase/Wallet/DebitWalletUseCaseTest.php`:
- Credits disabled throws `FeatureDisabledException`
- Insufficient balance throws `InsufficientFundsException` — `saveTransaction()` never called
- Exact balance (spend everything) succeeds — zero remaining balance
- Success: `saveTransaction()` called with Debit type transaction
- Success: `reservationId` stored on transaction when provided
- Success: returned wallet reflects deducted balance

---

### 7. Database schema — wallet_transactions table

Add to `database/seeds/000_schema.sql` (after `users` table — requires FK):

```sql
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id             CHAR(36)     NOT NULL PRIMARY KEY,
    user_id        CHAR(36)     NOT NULL,
    amount         INT          NOT NULL,
    currency       VARCHAR(10)  NOT NULL,
    type           VARCHAR(10)  NOT NULL,
    description    VARCHAR(255) NOT NULL,
    reservation_id CHAR(36)     NULL,
    created_at     DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Note: `reservation_id` is NOT a foreign key to `reservations` — a wallet transaction may reference
a reservation that has since been deleted, and the audit trail should survive. Stored as plain
`CHAR(36)`, no FK constraint.

---

### 8. MysqlWalletRepository

`src/Infrastructure/Persistence/Mysql/MysqlWalletRepository.php`

Implements `WalletRepositoryInterface`. Constructor injects `\PDO`.

- `findTransactionsByUserId()`:
  `SELECT * FROM wallet_transactions WHERE user_id = :userId ORDER BY created_at ASC`
  Returns `WalletTransaction[]`.

- `saveTransaction()`:
  `INSERT INTO wallet_transactions (id, user_id, amount, currency, type, description, reservation_id, created_at) VALUES (...)`
  Plain INSERT — transactions are immutable, never updated.

Hydration — private `hydrate(array $row): WalletTransaction`:
- `WalletTransactionId::fromString($row['id'])`
- `UserId::fromString($row['user_id'])`
- `new Money((int)$row['amount'], $row['currency'])`
- `WalletTransactionType` from string (`'credit'` → `Credit`, `'debit'` → `Debit`)
- `new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC'))`

`tests/Integration/Persistence/Mysql/MysqlWalletRepositoryTest.php`:

Extends `MysqlIntegrationTestCase`. Requires a user row first (FK). Create one via raw SQL
in the test setup rather than depending on `MysqlUserRepository`.

- `testSaveTransactionAndFindByUserId` — save credit transaction, find by user, assert fields
- `testFindTransactionsByUserIdReturnsEmptyForNewUser` — no transactions → empty array
- `testMultipleTransactionsReturnedInChronologicalOrder` — save 3 transactions, assert order
- `testDebitTransactionStoredCorrectly` — save debit, assert type is Debit
- `testReservationIdIsNullableAndStored` — save with null and non-null reservationId

---

### 9. Register in container

`config/container.php`

Add:
```php
\Rez\Application\UseCase\Wallet\GetWallet\GetWalletUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Wallet\GetWallet\GetWalletUseCase::class),

\Rez\Application\UseCase\Wallet\CreditWallet\CreditWalletUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Wallet\CreditWallet\CreditWalletUseCase::class),

\Rez\Application\UseCase\Wallet\DebitWallet\DebitWalletUseCaseInterface::class
    => \DI\autowire(\Rez\Application\UseCase\Wallet\DebitWallet\DebitWalletUseCase::class),

\Rez\Application\Port\WalletRepositoryInterface::class
    => \DI\autowire(\Rez\Infrastructure\Persistence\Mysql\MysqlWalletRepository::class),
```

---

## General rules

- Every file starts with: `<?php declare(strict_types=1);`
- Run `composer test` after each step — all existing tests must pass
- Run `vendor/bin/phpstan analyse` — max level, zero errors
- Run `vendor/bin/php-cs-fixer fix --dry-run` — zero violations

---

## Checklist

- [ ] 1. WalletTransactionType enum
- [ ] 2. WalletTransactionId + WalletTransactionIdTest
- [ ] 3. WalletTransaction
- [ ] 4. Wallet + WalletTest
- [ ] 5. WalletRepositoryInterface
- [ ] 6. GetWallet use case + test
- [ ] 6. CreditWallet use case + test
- [ ] 6. DebitWallet use case + test
- [ ] 7. Database schema (wallet_transactions)
- [ ] 8. MysqlWalletRepository + integration test
- [ ] 9. container.php updated
