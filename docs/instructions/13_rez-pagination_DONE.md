# Rez — Pagination, Filtering & Sorting

Adds `offset`/`limit` pagination, per-resource filtering, and sorting to all four listing use
cases (Reservations, Users, Newsletter Subscribers, Resources). A new `findPage()` +
`countPage()` pair is added to each repository interface **alongside** the existing `findAll()`,
which is left completely untouched — `findAll()` has other callers (e.g. `BroadcastUseCase` needs
every subscriber, unfiltered) that must keep working exactly as today.

When a List*Request's `offset`, `limit`, and `sortBy` are all omitted, `findPage()` must return
exactly what `findAll()` returns today (same rows, same order) — every step below calls this out
explicitly where it matters.

Run `composer ca` after completing all changes and fix any issues before committing.

---

## Context

Filtering, sorting, and pagination must happen in a single SQL query per list, in the order
**filter → sort → paginate** (`WHERE` → `ORDER BY` → `LIMIT`/`OFFSET`), plus a matching
`COUNT(*)` query (same `WHERE`, no `ORDER BY`/`LIMIT`) for the total match count. Sorting a page
in isolation instead of the full filtered set would produce internally-inconsistent pages.

`ListReservationsUseCase` currently filters `resourceId` **in memory** after `findAll()` already
returned everything (`ListReservationsUseCase.php`). That must move into the SQL `WHERE`/`JOIN`
inside `findPage()`/`countPage()` — an in-memory filter applied after SQL `LIMIT`/`OFFSET` already
truncated the result set would silently return wrong pages.

---

## 1. Shared validation helper

New file: `src/Application/Validation/ListParamsValidator.php`

A single stateless helper used by all four use cases, avoiding four near-identical validation
blocks (same pattern as `FeatureGuard`/`AvailabilityService` — a concrete class, no interface,
injected where it's a service; here it's simple enough to be static, no state or dependencies):

```php
<?php

declare(strict_types=1);

namespace Rez\Application\Validation;

final class ListParamsValidator
{
    public const MAX_LIMIT = 100;

    /**
     * @param string[] $allowedSortColumns
     * @throws \InvalidArgumentException
     */
    public static function validate(
        ?int $offset,
        ?int $limit,
        ?string $sortBy,
        ?string $sortDir,
        array $allowedSortColumns,
    ): void {
        if ($offset !== null && $offset < 0) {
            throw new \InvalidArgumentException('offset must be >= 0.');
        }

        if ($limit !== null && ($limit < 1 || $limit > self::MAX_LIMIT)) {
            throw new \InvalidArgumentException(sprintf('limit must be between 1 and %d.', self::MAX_LIMIT));
        }

        if ($sortBy !== null && !in_array($sortBy, $allowedSortColumns, true)) {
            throw new \InvalidArgumentException(sprintf('sortBy must be one of: %s.', implode(', ', $allowedSortColumns)));
        }

        if ($sortDir !== null && !in_array($sortDir, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('sortDir must be "asc" or "desc".');
        }
    }
}
```

Each use case's `execute()` calls this first, before touching the repository, and gains an
`@throws \InvalidArgumentException` PHPDoc tag (per `CLAUDE.md`'s one-tag-per-exception-type
convention). `rez-starter`'s global error middleware already maps `\InvalidArgumentException` to
HTTP 422 — no new exception class or mapping needed there.

---

## 2. Reservations

### 2.1 `ReservationRepositoryInterface` (`src/Application/Port/ReservationRepositoryInterface.php`)

Add, keeping `findAll()` exactly as-is:

```php
use Rez\Domain\Reservation\ReservationStatus;

/** @throws \Rez\Application\Exception\DatabaseException */
public function findPage(
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
    ?ResourceId $resourceId = null,
    ?ReservationStatus $status = null,
    ?string $search = null,
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): ReservationCollection;

/** @throws \Rez\Application\Exception\DatabaseException */
public function countPage(
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
    ?ResourceId $resourceId = null,
    ?ReservationStatus $status = null,
    ?string $search = null,
): int;
```

`search` matches a substring against `party_name`, `party_email`, or `party_phone` (not resource
name — that stays an exact `resourceId` filter, no fuzzy resource-name search).

### 2.2 `MysqlReservationRepository`

Extract a private helper shared by `findPage()`/`countPage()` so the `WHERE` logic isn't
duplicated:

```php
/** @return array{0: string, 1: array<string, mixed>} */
private function buildPageCriteria(
    ?DateTimeImmutable $from,
    ?DateTimeImmutable $to,
    ?ResourceId $resourceId,
    ?ReservationStatus $status,
    ?string $search,
): array {
    $where  = [];
    $params = [];

    if ($from !== null) {
        $where[]         = 'r.start_at >= :from';
        $params[':from'] = $from->format('Y-m-d H:i:s');
    }
    if ($to !== null) {
        $where[]       = 'r.end_at <= :to';
        $params[':to'] = $to->format('Y-m-d H:i:s');
    }
    if ($status !== null) {
        $where[]           = 'r.status = :status';
        $params[':status'] = $this->statusMapper->toString($status);
    }
    if ($search !== null) {
        $where[]           = '(r.party_name LIKE :search OR r.party_email LIKE :search OR r.party_phone LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($resourceId !== null) {
        $where[]                = 'rr.resource_id = :resource_id';
        $params[':resource_id'] = $resourceId->toString();
    }

    return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}

private const SORT_COLUMNS = [
    'start'      => 'r.start_at',
    'end'        => 'r.end_at',
    'status'     => 'r.status',
    'party_name' => 'r.party_name',
    'created_at' => 'r.created_at',
];
```

`findPage()` — note the existing table alias needs to become `r` (it isn't aliased today) since
the `resourceId` filter needs a join:

```php
public function findPage(
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
    ?ResourceId $resourceId = null,
    ?ReservationStatus $status = null,
    ?string $search = null,
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): ReservationCollection {
    [$whereSql, $params] = $this->buildPageCriteria($from, $to, $resourceId, $status, $search);

    $join = $resourceId !== null
        ? ' INNER JOIN reservation_resources rr ON rr.reservation_id = r.id'
        : '';

    $sql = 'SELECT DISTINCT r.* FROM reservations r' . $join . $whereSql;

    if ($sortBy !== null) {
        $column = self::SORT_COLUMNS[$sortBy]
            ?? throw new \InvalidArgumentException(sprintf('Unknown sort column: "%s".', $sortBy));
        $sql .= ' ORDER BY ' . $column . ' ' . ($sortDir === 'desc' ? 'DESC' : 'ASC');
    }

    if ($limit !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    try {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
    } catch (\PDOException $e) {
        $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }

    /** @var array<int, array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ReservationCollection::fromArray(array_values(array_map($this->hydrate(...), $rows)));
}
```

`:limit`/`:offset` **must** be bound with `PDO::PARAM_INT` explicitly (`bindValue`, not a plain
`execute($params)` array) — MySQL rejects a string-typed `LIMIT`/`OFFSET` under emulated prepares.

`countPage()` reuses the same helper, no `ORDER BY`/`LIMIT`:

```php
public function countPage(
    ?DateTimeImmutable $from = null,
    ?DateTimeImmutable $to = null,
    ?ResourceId $resourceId = null,
    ?ReservationStatus $status = null,
    ?string $search = null,
): int {
    [$whereSql, $params] = $this->buildPageCriteria($from, $to, $resourceId, $status, $search);

    $join = $resourceId !== null
        ? ' INNER JOIN reservation_resources rr ON rr.reservation_id = r.id'
        : '';

    $sql = 'SELECT COUNT(DISTINCT r.id) FROM reservations r' . $join . $whereSql;

    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    } catch (\PDOException $e) {
        $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }

    return (int) $stmt->fetchColumn();
}
```

`findAll()` and `findByTimeSlotAndResource()` stay byte-for-byte unchanged.

### 2.3 `ListReservationsRequest`/`Response`

`ListReservationsRequest` — extend the existing class with the new optional fields:

```php
public readonly ?ReservationStatus $status = null,
public readonly ?string $search = null,
public readonly ?int $offset = null,
public readonly ?int $limit = null,
public readonly ?string $sortBy = null,
public readonly ?string $sortDir = null,
```

`ListReservationsResponse` — add `public readonly int $total,` alongside the existing
`$reservations` field.

### 2.4 `ListReservationsUseCase`

Replace the `findAll()` + in-memory `resourceId` filter with `findPage()`/`countPage()`, and
validate params first:

```php
private const SORTABLE = ['start', 'end', 'status', 'party_name', 'created_at'];

/**
 * @throws DatabaseException
 * @throws \InvalidArgumentException
 */
public function execute(ListReservationsRequest $request): ListReservationsResponse
{
    ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

    try {
        $reservations = $this->reservationRepository->findPage(
            from: $request->from,
            to: $request->to,
            resourceId: $request->resourceId,
            status: $request->status,
            search: $request->search,
            offset: $request->offset,
            limit: $request->limit,
            sortBy: $request->sortBy,
            sortDir: $request->sortDir,
        );
        $total = $this->reservationRepository->countPage(
            from: $request->from,
            to: $request->to,
            resourceId: $request->resourceId,
            status: $request->status,
            search: $request->search,
        );
    } catch (DatabaseException $e) {
        throw new DatabaseException('Failed to list reservations.', 0, $e);
    }

    return new ListReservationsResponse($reservations, $total);
}
```

The old in-memory `->filter(fn (Reservation $r) => ...)` block is removed entirely — the SQL join
now does this filtering.

---

## 3. Users

Same pattern as Reservations, simpler (no join).

### 3.1 `UserRepositoryInterface` — add, keep `findAll()` unchanged:

```php
use Rez\Domain\User\UserRole;

/** @throws \Rez\Application\Exception\DatabaseException */
public function findPage(
    ?string $search = null,
    ?UserRole $role = null,
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): UserCollection;

/** @throws \Rez\Application\Exception\DatabaseException */
public function countPage(?string $search = null, ?UserRole $role = null): int;
```

`search` matches a substring against `name` or `email`.

### 3.2 `MysqlUserRepository`

```php
private const SORT_COLUMNS = [
    'name'       => 'name',
    'email'      => 'email',
    'role'       => 'role',
    'created_at' => 'created_at',
];

/** @return array{0: string, 1: array<string, mixed>} */
private function buildPageCriteria(?string $search, ?UserRole $role): array
{
    $where  = [];
    $params = [];

    if ($search !== null) {
        $where[]           = '(name LIKE :search OR email LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($role !== null) {
        $where[]         = 'role = :role';
        $params[':role'] = $this->roleMapper->toString($role);
    }

    return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}
```

`findPage()` — **default sort must stay `created_at ASC`** when `sortBy` is null, matching
today's hardcoded `ORDER BY created_at ASC` exactly:

```php
public function findPage(
    ?string $search = null,
    ?UserRole $role = null,
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): UserCollection {
    [$whereSql, $params] = $this->buildPageCriteria($search, $role);

    $column = $sortBy !== null
        ? (self::SORT_COLUMNS[$sortBy] ?? throw new \InvalidArgumentException(sprintf('Unknown sort column: "%s".', $sortBy)))
        : 'created_at';
    $dir = $sortDir === 'desc' ? 'DESC' : 'ASC';

    $sql = 'SELECT * FROM users' . $whereSql . ' ORDER BY ' . $column . ' ' . $dir;

    if ($limit !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    try {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
    } catch (\PDOException $e) {
        $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }

    /** @var array<int, array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return UserCollection::fromArray(array_map($this->hydrate(...), $rows));
}

public function countPage(?string $search = null, ?UserRole $role = null): int
{
    [$whereSql, $params] = $this->buildPageCriteria($search, $role);

    try {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
        $stmt->execute($params);
    } catch (\PDOException $e) {
        $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }

    return (int) $stmt->fetchColumn();
}
```

### 3.3 `ListUsersRequest`/`Response`

`ListUsersRequest` is currently an **empty class** — first Request in the codebase to gain
pagination fields:

```php
final class ListUsersRequest
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?UserRole $role = null,
        public readonly ?int $offset = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
    ) {
    }
}
```

`ListUsersResponse` — add `public readonly int $total,`.

### 3.4 `ListUsersUseCase`

```php
private const SORTABLE = ['name', 'email', 'role', 'created_at'];

/**
 * @throws DatabaseException
 * @throws \InvalidArgumentException
 */
public function execute(ListUsersRequest $request): ListUsersResponse
{
    ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

    try {
        $users = $this->userRepository->findPage(
            search: $request->search,
            role: $request->role,
            offset: $request->offset,
            limit: $request->limit,
            sortBy: $request->sortBy,
            sortDir: $request->sortDir,
        );
        $total = $this->userRepository->countPage($request->search, $request->role);
    } catch (DatabaseException $e) {
        throw new DatabaseException('Failed to list users.', 0, $e);
    }

    return new ListUsersResponse($users, $total);
}
```

---

## 4. Newsletter Subscribers

Same pattern; this module has no Collection type (`findAll()` already returns a plain array) —
`findPage()` follows suit, no `NewsletterSubscriberCollection` introduced.

### 4.1 `NewsletterRepositoryInterface` — add, keep `findAll()` unchanged (used by `BroadcastUseCase`):

```php
use Rez\Domain\Newsletter\SubscriberSource;

/**
 * @return NewsletterSubscriber[]
 * @throws \Rez\Application\Exception\DatabaseException
 */
public function findPage(
    ?string $search = null,
    ?SubscriberSource $source = null,
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): array;

/** @throws \Rez\Application\Exception\DatabaseException */
public function countPage(?string $search = null, ?SubscriberSource $source = null): int;
```

`search` matches a substring against `email` or `name`.

### 4.2 `MysqlNewsletterRepository`

Same shape as `MysqlUserRepository` §3.2. `SORT_COLUMNS`:

```php
private const SORT_COLUMNS = [
    'email'       => 'email',
    'name'        => 'name',
    'source'      => 'source',
    'opted_in_at' => 'opted_in_at',
];
```

`buildPageCriteria(?string $search, ?SubscriberSource $source)` — `search` against
`(email LIKE :search OR name LIKE :search)`, `source` via `$this->sourceMapper->toString($source)`.

**Default sort must stay `opted_in_at ASC`** when `sortBy` is null (today's hardcoded
`ORDER BY opted_in_at ASC`) — same `$column = $sortBy !== null ? (...) : 'opted_in_at'` pattern as
§3.2. `findPage()` returns `array_map($this->hydrate(...), $rows)` (no `ReservationCollection`-style
wrapper, matching the existing `findAll()`). `countPage()` — `SELECT COUNT(*) FROM
newsletter_subscribers` + the same `$whereSql`.

### 4.3 `ListSubscribersRequest`/`Response`

`ListSubscribersRequest` is currently empty — gains the same 6 fields as `ListUsersRequest` but
with `?SubscriberSource $source` instead of `?UserRole $role`.

`ListSubscribersResponse` — add `public readonly int $total,` alongside the existing
`array $subscribers`.

### 4.4 `ListSubscribersUseCase`

Same shape as §3.4: `SORTABLE = ['email', 'name', 'source', 'opted_in_at']`, calls
`findPage()`/`countPage()` with `search`/`source` instead of `search`/`role`.

---

## 5. Resources

No filters (nothing to filter on today — no search/status UI exists for this list), only
sort + paginate. Must preserve the existing hardcoded `active = 1` filter.

### 5.1 `ResourceRepositoryInterface` — add, keep `findAll()` unchanged:

```php
/** @throws \Rez\Application\Exception\DatabaseException */
public function findPage(
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): ResourceCollection;

/** @throws \Rez\Application\Exception\DatabaseException */
public function countPage(): int;
```

### 5.2 `MysqlResourceRepository`

```php
private const SORT_COLUMNS = [
    'type'     => 'type',
    'name'     => 'name',
    'capacity' => 'capacity',
];

public function findPage(
    ?int $offset = null,
    ?int $limit = null,
    ?string $sortBy = null,
    ?string $sortDir = null,
): ResourceCollection {
    $sql = 'SELECT * FROM resources WHERE active = 1';

    if ($sortBy !== null) {
        $column = self::SORT_COLUMNS[$sortBy]
            ?? throw new \InvalidArgumentException(sprintf('Unknown sort column: "%s".', $sortBy));
        $sql .= ' ORDER BY ' . $column . ' ' . ($sortDir === 'desc' ? 'DESC' : 'ASC');
    }
    // no ORDER BY at all when $sortBy is null — matches findAll() exactly

    if ($limit !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    try {
        $stmt = $this->pdo->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
    } catch (\PDOException $e) {
        $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }

    /** @var array<int, array<string, mixed>> $rows */
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ResourceCollection::fromArray(array_map($this->hydrate(...), $rows));
}

public function countPage(): int
{
    try {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM resources WHERE active = 1');
        $stmt->execute();
    } catch (\PDOException $e) {
        $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
        throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
    }

    return (int) $stmt->fetchColumn();
}
```

### 5.3 `ListResourcesRequest`/`Response`

`ListResourcesRequest` gains only the 4 pagination/sort fields (no filters):

```php
final class ListResourcesRequest
{
    public function __construct(
        public readonly ?int $offset = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
    ) {
    }
}
```

`ListResourcesResponse` — add `public readonly int $total,`.

### 5.4 `ListResourcesUseCase`

```php
private const SORTABLE = ['type', 'name', 'capacity'];

/**
 * @throws DatabaseException
 * @throws \InvalidArgumentException
 */
public function execute(ListResourcesRequest $request): ListResourcesResponse
{
    ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

    try {
        $resources = $this->resourceRepository->findPage($request->offset, $request->limit, $request->sortBy, $request->sortDir);
        $total     = $this->resourceRepository->countPage();
    } catch (DatabaseException $e) {
        throw new DatabaseException('Failed to list resources.', 0, $e);
    }

    return new ListResourcesResponse($resources, $total);
}
```

---

## 6. Before starting

Grep for other callers of each repo's `findAll()` before touching anything (expect
`BroadcastUseCase` for `NewsletterRepositoryInterface::findAll()`; confirm no other use case
depends on any of the four `findAll()` methods) — this confirms `findAll()` genuinely needs zero
changes and only new methods are being added alongside it.

---

## 7. Tests

Per module (Reservation, User, Newsletter, Resource):

- **Use case unit tests**: default params (`offset`/`limit`/`sortBy` all null) return identical
  data to the old `findAll()`-based fixtures, plus the new `total`; explicit
  offset/limit/sort/filter combinations; invalid `sortBy` (not in the allowlist), out-of-range
  `limit` (0, 101), negative `offset`, and invalid `sortDir` each throw `\InvalidArgumentException`.
- **`ListParamsValidator` unit tests**: one test per validation rule above, independent of any
  specific module.
- **Mysql*Repository integration tests**: `findPage`/`countPage` against a seeded table —
  filter+sort+paginate combined; for Reservations specifically, a reservation with multiple
  resources and a resource matching more rows than the page size (the resourceId-JOIN correctness
  fix) — confirm no duplicate rows and confirm `countPage` matches the true distinct count; confirm
  `findAll()` itself is unaffected (still returns everything, unchanged SQL).

---

## Checklist

- [ ] 1. `ListParamsValidator` added (`src/Application/Validation/ListParamsValidator.php`)
- [ ] 2. Reservations: `findPage`/`countPage` on interface + Mysql repo (JOIN-based resourceId
      fix), `ListReservationsRequest`/`Response` extended, `ListReservationsUseCase` rewritten
- [ ] 3. Users: `findPage`/`countPage` on interface + Mysql repo (default sort preserved),
      `ListUsersRequest`/`Response` extended, `ListUsersUseCase` rewritten
- [ ] 4. Newsletter: `findPage`/`countPage` on interface + Mysql repo (default sort preserved),
      `ListSubscribersRequest`/`Response` extended, `ListSubscribersUseCase` rewritten
- [ ] 5. Resources: `findPage`/`countPage` on interface + Mysql repo (no filters, no default sort),
      `ListResourcesRequest`/`Response` extended, `ListResourcesUseCase` rewritten
- [ ] 6. Confirmed no other caller depends on any of the four `findAll()` methods being changed —
      they're all untouched
- [ ] 7. All new/changed unit and integration tests pass, `composer ca` clean
