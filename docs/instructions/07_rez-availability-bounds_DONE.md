# Rez — Availability Bounds

Adds optional `validFrom` and `validUntil` date bounds to `AvailabilityRule`. A rule with
bounds only applies when the queried date falls within the range. Null means unbounded in
that direction — null `validUntil` means the rule recurs forever.

This enables finite course scheduling ("6 Tuesday sessions starting March 1") without
changing the recurrence model or adding per-occurrence records.

Complete `rez-testing-fixes` before starting this.

Run `composer ca` after completing all changes and fix any issues before committing.

---

## 1. Update `AvailabilityRule` value object

`src/Domain/Availability/AvailabilityRule.php`

Add two nullable fields:

```php
public readonly ?\DateTimeImmutable $validFrom,
public readonly ?\DateTimeImmutable $validUntil,
```

Both default to `null` — existing rules without bounds behave identically to before.

`validFrom` and `validUntil` are date-only — the time component is irrelevant and must be
ignored during comparison. When constructing from a date string, parse as midnight UTC:

```php
new \DateTimeImmutable($dateString . ' 00:00:00', new \DateTimeZone('UTC'))
```

Add a method:

```php
public function isActiveOn(\DateTimeImmutable $date): bool
```

Returns true when the rule has no bounds, or when `$date` falls within `[validFrom, validUntil]`
inclusive. Comparison is date-only — strip time before comparing.

Logic:

```php
public function isActiveOn(\DateTimeImmutable $date): bool
{
    $day = \DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-d'), new \DateTimeZone('UTC'));

    if ($this->validFrom !== null) {
        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $this->validFrom->format('Y-m-d'), new \DateTimeZone('UTC'));
        if ($day < $from) {
            return false;
        }
    }

    if ($this->validUntil !== null) {
        $until = \DateTimeImmutable::createFromFormat('Y-m-d', $this->validUntil->format('Y-m-d'), new \DateTimeZone('UTC'));
        if ($day > $until) {
            return false;
        }
    }

    return true;
}
```

---

## 2. Update `AvailabilityService`

`src/Application/Service/AvailabilityService.php`

When resolving available slots for a given date, filter out any `AvailabilityRule` where
`$rule->isActiveOn($date)` returns false before generating slots.

No other logic changes — the existing day-of-week matching and override application remain
unchanged.

---

## 3. Update `MysqlAvailabilityRepository`

`src/Infrastructure/Repository/MysqlAvailabilityRepository.php`

### Schema change

Add columns to `availability_rules`:

```sql
ALTER TABLE availability_rules
    ADD COLUMN valid_from DATE NULL DEFAULT NULL,
    ADD COLUMN valid_until DATE NULL DEFAULT NULL;
```

Add this migration to `seeds/schema/00_schema.sql` as part of the `CREATE TABLE` definition
(not as an `ALTER TABLE` — the schema file defines the table from scratch).

### Hydration

When reading rows, hydrate `validFrom` and `validUntil` as nullable `DateTimeImmutable`:

```php
validFrom: $row['valid_from']
    ? new \DateTimeImmutable($row['valid_from'] . ' 00:00:00', new \DateTimeZone('UTC'))
    : null,
validUntil: $row['valid_until']
    ? new \DateTimeImmutable($row['valid_until'] . ' 00:00:00', new \DateTimeZone('UTC'))
    : null,
```

### Persistence

When saving a rule, write `valid_from` and `valid_until` as `Y-m-d` date strings or `NULL`.

---

## 4. Update `SaveAvailabilityRuleRequest`

`src/Application/UseCase/SaveAvailabilityRule/SaveAvailabilityRuleRequest.php`

Add optional nullable fields:

```php
public readonly ?string $validFrom = null,   // ISO date string 'Y-m-d' or null
public readonly ?string $validUntil = null,
```

Parse and validate in `SaveAvailabilityRuleUseCase` before constructing the domain object:
- If provided, must be a valid `Y-m-d` date string — throw `\InvalidArgumentException` otherwise
- `validFrom` must not be after `validUntil` when both are provided

---

## 5. Update `rez-starter` route

`POST /api/resources/{id}/availability/rules` (the route that calls `SaveAvailabilityRuleUseCase`)

Accept optional `valid_from` and `valid_until` fields in the request body (snake_case in
JSON, mapped to camelCase in the request object). Both nullable, both omitted by default.

Serialise `validFrom` and `validUntil` in the response as ISO date strings or `null`.

---

## 6. Tests

### Unit tests for `AvailabilityRule::isActiveOn()`

- No bounds → always returns true
- `validFrom` set, date before → false
- `validFrom` set, date equal → true (inclusive)
- `validFrom` set, date after → true
- `validUntil` set, date before → true
- `validUntil` set, date equal → true (inclusive)
- `validUntil` set, date after → false
- Both bounds set, date inside range → true
- Both bounds set, date outside range → false
- Time component of queried date is ignored (same date, different times → same result)

### Unit tests for `AvailabilityService`

- Rule with `validUntil` in the past is not used when resolving slots for today
- Rule with `validFrom` in the future is not used when resolving slots for today
- Rule with bounds covering today is used normally

### Integration tests for `MysqlAvailabilityRepository`

- Save rule with bounds, read it back, assert `validFrom` and `validUntil` round-trip correctly
- Save rule without bounds, read it back, assert both are null

---

## Checklist

- [ ] 1. `AvailabilityRule` gains `validFrom`, `validUntil` (nullable), `isActiveOn()` method
- [ ] 2. `AvailabilityService` filters rules by `isActiveOn()` before resolving slots
- [ ] 3. `availability_rules` schema updated with `valid_from`, `valid_until` DATE columns
- [ ] 4. Repository hydration and persistence updated
- [ ] 5. `SaveAvailabilityRuleRequest` gains optional `validFrom`, `validUntil`
- [ ] 6. `SaveAvailabilityRuleUseCase` validates and passes bounds to domain object
- [ ] 7. `rez-starter` route accepts and serialises bounds
- [ ] 8. All unit and integration tests pass, `composer ca` clean
