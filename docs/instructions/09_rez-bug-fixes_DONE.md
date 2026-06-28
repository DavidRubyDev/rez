# Scaffold: rez-bug-fixes

Fixes three bugs and one design gap identified in API testing (see `docs/reports/testing-report.md`).
Complete in the order listed — step 2 depends on step 1 (resource loading), and step 3 is
independent but small enough to batch in the same branch.

Run `composer ca` after each step and fix any issues before committing.

---

## Step 1 — BUG-01: `GetAvailabilityUseCase` resource existence check

**Problem:** `GET /api/availability?resource_id={valid-but-nonexistent-uuid}` returns `200 {"slots":[]}`.
When no rules exist for a resource (because the resource does not exist), `AvailabilityService`
returns `AvailabilityWindow::empty()` — no existence check is ever performed.

**Fix:** Inject `ResourceRepositoryInterface` into `GetAvailabilityUseCase` and call `findById()`
at the top of `execute()`. `findById()` already throws `ResourceNotFoundException` on miss, which
maps to 404 in `rez-starter`.

### 1.1 — `GetAvailabilityUseCase`

`src/Application/UseCase/Availability/GetAvailability/GetAvailabilityUseCase.php`

Add `ResourceRepositoryInterface` constructor parameter. Call `findById()` before delegating to
the availability service. Wrap both repository calls in the existing `DatabaseException` catch.

```php
public function __construct(
    private readonly AvailabilityServiceInterface $availabilityService,
    private readonly ResourceRepositoryInterface $resourceRepository,
) {}

public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse
{
    try {
        $this->resourceRepository->findById($request->resourceId);
        $window = $this->availabilityService->getAvailableSlots(
            $request->resourceId,
            $request->date,
            $request->slotDurationMinutes,
            $request->partySize,
        );
    } catch (DatabaseException $e) {
        throw new DatabaseException('Failed to get availability.', 0, $e);
    }

    return new GetAvailabilityResponse($window);
}
```

Update `@throws` PHPDoc on `execute()` — add `@throws \Rez\Domain\Exception\ResourceNotFoundException`.

Update `GetAvailabilityUseCaseInterface` with the same `@throws` tag.

### 1.2 — Tests

`tests/Application/UseCase/Availability/GetAvailabilityUseCaseTest.php`

Add one test:

- `testThrowsResourceNotFoundExceptionForNonExistentResource` — mock `resourceRepository->findById()`
  to throw `ResourceNotFoundException`; assert the use case propagates it without wrapping.

Existing tests: add `resourceRepository` mock stub (stub `findById()` to return a `Resource`) to
the test setup so existing cases continue to pass.

---

## Step 2 — BUG-02 + BUG-03: Capacity-aware conflict detection

**BUG-02:** Any existing reservation on a slot triggers a 409, regardless of how many seats remain.
Two bookings of size 1 on a capacity-2 resource both for the same slot should both succeed.

**BUG-03:** A party of size 3 booking a capacity-2 resource succeeds. Party size is never checked
against resource capacity.

These share a root cause and are fixed together in a single capacity-aware pass.

### 2.1 — `AvailabilityServiceInterface`

`src/Application/Service/AvailabilityServiceInterface.php`

Add `int $partySize = 1` to both method signatures:

```php
public function isSlotAvailable(ResourceId $resourceId, TimeSlot $slot, int $partySize = 1): bool;

public function getAvailableSlots(
    ResourceId $resourceId,
    DateTimeImmutable $date,
    int $slotDurationMinutes,
    int $partySize = 1,
): AvailabilityWindow;
```

### 2.2 — `AvailabilityService`

`src/Application/Service/AvailabilityService.php`

Inject `ResourceRepositoryInterface` as the first constructor parameter (before the two existing
dependencies so PHPStan and auto-wiring see it clearly):

```php
public function __construct(
    private readonly ResourceRepositoryInterface $resourceRepository,
    private readonly AvailabilityRepositoryInterface $availabilityRepository,
    private readonly ReservationRepositoryInterface $reservationRepository,
) {}
```

**`isSlotAvailable()`** — add `int $partySize = 1`. Replace the `isEmpty()` check with a
capacity-aware sum. The resource is loaded here so its capacity is available:

```php
public function isSlotAvailable(ResourceId $resourceId, TimeSlot $slot, int $partySize = 1): bool
{
    $rules = $this->availabilityRepository->findRulesForResource($resourceId);
    $rule  = $this->findRuleForDate($rules, $slot->start);

    if ($rule === null) {
        return false;
    }

    $dayStart  = $slot->start->setTime(0, 0);
    $dayEnd    = $dayStart->modify('+1 day');
    $overrides = $this->availabilityRepository->findOverridesForResource($resourceId, $dayStart, $dayEnd);

    if ($this->isBlockedByOverride($overrides, $slot->start)) {
        return false;
    }

    $resource     = $this->resourceRepository->findById($resourceId);
    $reservations = $this->reservationRepository->findByTimeSlotAndResource($slot, $resourceId);
    $occupied     = $this->sumPartySize($reservations);

    return $occupied + $partySize <= $resource->capacity;
}
```

**`getAvailableSlots()`** — add `int $partySize = 1`. Load the resource for its capacity and pass
both values to `filterConflictingSlots()`:

```php
public function getAvailableSlots(
    ResourceId $resourceId,
    DateTimeImmutable $date,
    int $slotDurationMinutes,
    int $partySize = 1,
): AvailabilityWindow {
    $rules = $this->availabilityRepository->findRulesForResource($resourceId);
    $rule  = $this->findRuleForDate($rules, $date);

    if ($rule === null) {
        return AvailabilityWindow::empty($resourceId, $date);
    }

    $dayEnd    = $date->modify('+1 day');
    $overrides = $this->availabilityRepository->findOverridesForResource($resourceId, $date, $dayEnd);

    if ($this->isBlockedByOverride($overrides, $date)) {
        return AvailabilityWindow::empty($resourceId, $date);
    }

    $resource     = $this->resourceRepository->findById($resourceId);
    $candidates   = $this->generateCandidateSlots($rule, $date, $slotDurationMinutes);
    $fullDaySlot  = new TimeSlot($rule->openTimeForDate($date), $rule->closeTimeForDate($date));
    $reservations = $this->reservationRepository->findByTimeSlotAndResource($fullDaySlot, $resourceId);
    $available    = $this->filterConflictingSlots($candidates, $reservations, $resource->capacity, $partySize);

    return new AvailabilityWindow($resourceId, $date, $available);
}
```

**`filterConflictingSlots()`** — accept `int $capacity` and `int $partySize`. For each candidate
slot, sum the `party->size` of overlapping reservations. A slot is available if `occupied + partySize <= capacity`:

```php
private function filterConflictingSlots(
    array $candidates,
    ReservationCollection $reservations,
    int $capacity,
    int $partySize,
): array {
    return array_values(array_filter(
        $candidates,
        function (TimeSlot $candidate) use ($reservations, $capacity, $partySize): bool {
            $occupied = 0;
            foreach ($reservations->toArray() as $reservation) {
                if ($candidate->overlapsWith($reservation->slot)) {
                    $occupied += $reservation->party->size;
                }
            }
            return $occupied + $partySize <= $capacity;
        }
    ));
}
```

**Add `sumPartySize()` helper:**

```php
private function sumPartySize(ReservationCollection $reservations): int
{
    $total = 0;
    foreach ($reservations->toArray() as $reservation) {
        $total += $reservation->party->size;
    }
    return $total;
}
```

### 2.3 — `CreateReservationUseCase`

`src/Application/UseCase/Reservation/CreateReservation/CreateReservationUseCase.php`

Update `assertAvailable()` to pass party size through to the service:

```php
private function assertAvailable(TimeSlot $slot, Resource $resource, int $partySize): void
{
    if (!$this->availabilityService->isSlotAvailable($resource->id, $slot, $partySize)) {
        throw new ConflictException($slot, $resource);
    }
}
```

Update the call site:

```php
foreach ($resources as $resource) {
    $this->assertAvailable($slot, $resource, $request->party->size);
}
```

### 2.4 — `GetAvailabilityRequest`

`src/Application/UseCase/Availability/GetAvailability/GetAvailabilityRequest.php`

Add optional `int $partySize = 1` with validation:

```php
public function __construct(
    public readonly ResourceId $resourceId,
    public readonly DateTimeImmutable $date,
    public readonly int $slotDurationMinutes,
    public readonly int $partySize = 1,
) {
    if ($slotDurationMinutes <= 0) {
        throw new \InvalidArgumentException('Slot duration must be greater than zero.');
    }
    if ($partySize < 1) {
        throw new \InvalidArgumentException('Party size must be at least 1.');
    }
}
```

### 2.5 — `GetAvailabilityHandler`

`src/Handler/Availability/GetAvailabilityHandler.php`

Read `party_size` from the input array (optional, defaults to 1) and pass it to the request:

```php
new GetAvailabilityRequest(
    ResourceId::fromString($data['resource_id']),
    new DateTimeImmutable($data['date'], new \DateTimeZone('UTC')),
    (int) ($data['slot_duration_minutes'] ?? 60),
    (int) ($data['party_size'] ?? 1),
)
```

### 2.6 — `AvailabilityServiceTest`

`tests/Application/Service/AvailabilityServiceTest.php`

Update existing test setup to inject a `resourceRepository` mock (stub `findById()` to return a
`Resource` with sufficient capacity so all existing cases still pass).

Add new tests:

- `testSecondBookingSucceedsWhenCapacityAllowsIt` — resource capacity 2; one existing reservation
  with party size 1; `isSlotAvailable()` with partySize 1 → true (1+1=2, not exceeding capacity 2)

- `testConflictWhenCapacityExceeded` — resource capacity 1; one existing reservation party size 1;
  `isSlotAvailable()` with partySize 1 → false (1+1=2 > capacity 1)

- `testPartySizeExceedingCapacityAloneIsConflict` — resource capacity 2; no existing reservations;
  `isSlotAvailable()` with partySize 3 → false (0+3=3 > capacity 2)

- `testGetAvailableSlotsFiltersCapacityAware` — resource capacity 2; one existing reservation (size 2)
  on a slot; `getAvailableSlots()` with partySize 1 → that slot absent from window (2+1=3 > 2)

- `testGetAvailableSlotsIncludesPartiallyFilledSlot` — resource capacity 3; one existing reservation
  (size 1) on a slot; `getAvailableSlots()` with partySize 1 → slot still present (1+1=2 ≤ 3)

### 2.7 — `CreateReservationUseCaseTest`

`tests/Application/UseCase/Reservation/CreateReservationUseCaseTest.php`

Add new tests:

- `testConflictWhenPartySizeExceedsCapacity` — mock `isSlotAvailable()` to return false (party size
  3 on capacity 2 resource); assert `ConflictException` is thrown

- `testPartySizeIsPassedToAvailabilityService` — verify `isSlotAvailable()` is called with the
  correct party size from the request party

---

## Step 3 — DESIGN-01: Loosen UUID parsing to accept any UUID format

**Problem:** A nil UUID (`00000000-0000-0000-0000-000000000000`) or a non-v4 UUID returns a 422
with "is not a valid UUID v4." instead of reaching the repository and returning a 404. API consumers
should receive 404 for any UUID that doesn't match a record, regardless of variant.

**Fix:** Relax `fromString()` in all three ID value objects from the strict v4 pattern to a generic
UUID format. `generate()` stays strict (always generates v4 via `random_bytes`).

### 3.1 — `ReservationId`, `ResourceId`, `NewsletterSubscriberId`

Files:
- `src/Domain/Reservation/ReservationId.php`
- `src/Domain/Resource/ResourceId.php`
- `src/Domain/Newsletter/NewsletterSubscriberId.php`

In `fromString()` on each, change the regex and error message:

```php
// Before
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id)) {
    throw new \InvalidArgumentException(sprintf('"%s" is not a valid UUID v4.', $id));
}

// After
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
    throw new \InvalidArgumentException(sprintf('"%s" is not a valid UUID.', $id));
}
```

`generate()` is unchanged — it still produces a canonical v4.

### 3.2 — Tests

`tests/Domain/Reservation/ReservationIdTest.php`
`tests/Domain/Resource/ResourceIdTest.php`
`tests/Domain/Newsletter/NewsletterSubscriberIdTest.php`

Update any existing test that asserts on the message text "is not a valid UUID v4." — change the
expected string to "is not a valid UUID."

Add one test to each:

- `testFromStringAcceptsNilUuid` — `fromString('00000000-0000-0000-0000-000000000000')` must not
  throw; assert `toString()` returns the input unchanged.

---

## Checklist

- [ ] 1.1 `GetAvailabilityUseCase` — inject `ResourceRepositoryInterface`, call `findById()` at top of `execute()`
- [ ] 1.2 `GetAvailabilityUseCase` `@throws ResourceNotFoundException` on interface + implementation
- [ ] 1.3 `GetAvailabilityUseCaseTest` — add non-existent resource test; update setup for existing tests
- [ ] 2.1 `AvailabilityServiceInterface` — `partySize = 1` on both methods
- [ ] 2.2 `AvailabilityService` — inject `ResourceRepositoryInterface`; capacity-aware `isSlotAvailable()`, `getAvailableSlots()`, `filterConflictingSlots()`, `sumPartySize()`
- [ ] 2.3 `CreateReservationUseCase::assertAvailable()` — add `int $partySize`; pass `$request->party->size` from call site
- [ ] 2.4 `GetAvailabilityRequest` — add `int $partySize = 1` with validation
- [ ] 2.5 `GetAvailabilityHandler` — read `party_size` from input, pass to request
- [ ] 2.6 `AvailabilityServiceTest` — update setup for resource mock; add 5 capacity-aware tests
- [ ] 2.7 `CreateReservationUseCaseTest` — add 2 capacity tests
- [ ] 3.1 Loosen UUID regex + update error message in all 3 ID value objects
- [ ] 3.2 Update existing message assertions; add nil UUID acceptance test to each
- [ ] All `composer ca` clean
