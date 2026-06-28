# API Testing Report

**Date:** 2026-06-28
**Tested via:** rez-starter on Docker (local WSL2)
**Scope:** All currently wired routes — resources, availability rules/overrides, slot generation, reservations, newsletter

---

## Summary

| Severity | Count |
|----------|-------|
| Bug | 3 |
| Design gap | 1 |
| By design / test plan error | 4 |
| Fixed in rez-starter | 1 |

---

## Issues

### BUG-01 — GetAvailability silently succeeds for non-existent resource

| | |
|---|---|
| **Route** | `GET /api/availability?resource_id={valid-but-nonexistent-uuid}` |
| **Expected** | 404 `ResourceNotFoundException` |
| **Actual** | 200 with `{"slots":[]}` |
| **Root cause** | `GetAvailabilityUseCase` delegates directly to `AvailabilityService.getAvailableSlots()`. When no rules exist for the resource (because it doesn't exist), the service returns `AvailabilityWindow::empty()` — no existence check is performed. |
| **Fix** | Call `resourceRepository->findById($request->resourceId)` at the top of `GetAvailabilityUseCase::execute()` before calling the availability service. `findById()` already throws `ResourceNotFoundException` on miss. |

---

### BUG-02 — Conflict detection ignores capacity

| | |
|---|---|
| **Route** | `POST /api/reservations` |
| **Expected** | Two bookings of size 1 on a capacity-2 resource for the same slot both succeed |
| **Actual** | Second booking returns 409 `ConflictException` |
| **Root cause** | `AvailabilityService::isSlotAvailable()` checks `reservationRepository->findByTimeSlotAndResource()->isEmpty()`. Any existing reservation on the slot causes a conflict — capacity is never consulted. |
| **Fix** | Replace the `isEmpty()` check with a capacity-aware check: sum `party.size` across existing reservations on the slot and add the incoming `party.size`. If the total exceeds `resource.capacity`, throw `ConflictException`. The resource entity must be passed into `isSlotAvailable()` or a separate method added. |
| **Implication** | This also affects `getAvailableSlots()` — slots shown as available may not be truly available for a given party size. The slot filtering must also become capacity-aware. |

---

### BUG-03 — Party size not validated against resource capacity

| | |
|---|---|
| **Route** | `POST /api/reservations` |
| **Expected** | Party size 3 on a capacity-2 resource → 409 or 422 |
| **Actual** | 201 — booking succeeds |
| **Root cause** | `CreateReservationUseCase::assertAvailable()` only checks slot availability via `AvailabilityService`. It never compares `party.size` against `resource.capacity`. |
| **Fix** | In `assertAvailable()` (or directly in `execute()` after loading the resource), check `$request->party->size > $resource->capacity` and throw `ConflictException` (or a dedicated `CapacityExceededException`) if true. |
| **Note** | BUG-02 and BUG-03 are related and should be fixed together as a single capacity enforcement pass. |

---

### DESIGN-01 — UUID v4 validation rejects nil UUID, returns 422 instead of 404

| | |
|---|---|
| **Routes** | Any route with a UUID path or query param |
| **Expected** | Non-existent UUID → 404 |
| **Actual** | `00000000-0000-0000-0000-000000000000` → 422 `"is not a valid UUID v4."` |
| **Root cause** | `ResourceId::fromString()` (and equivalents) enforce strict UUID v4 bit patterns via regex (`4[0-9a-f]{3}` version nibble, `[89ab]` variant bits). The nil UUID fails this check before any DB lookup occurs. |
| **Options** | (A) Loosen to accept any UUID format (`/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i`) and rely on the repository to return 404. (B) Keep strict v4 validation — the nil UUID is genuinely not a valid v4. |
| **Recommendation** | Option A. The API consumer should receive 404 for any UUID that doesn't match a record, regardless of UUID variant. Strict v4 enforcement is an internal invariant — it should not leak as a 422 to callers using test UUIDs. Generation stays strict (v4 only); parsing loosens. |

---

## Confirmed correct behaviour (test plan errors)

| Test | Expected in plan | Actual | Verdict |
|------|-----------------|--------|---------|
| 3.E1 — Bad date on override PUT | 500 | 422 | **Better than expected.** Already handled gracefully. |
| 3.E2 — Inverted `from`/`to` on overrides GET | 200 or 422 | 200 empty | **Acceptable.** An inverted range returns no results. No fix needed. |
| 4.E1 — Bad date on availability GET | 500 | 422 | **Better than expected.** Already handled gracefully. |
| 5.1 — Reservation auto-confirmed | pending | confirmed | **rez-starter bug** — `(bool) "false"` cast. Fixed in rez-starter with `filter_var`. |

---

## Timezone

**Situation:** All `DateTimeImmutable` objects in the domain are explicitly UTC (`new DateTimeImmutable('now', new \DateTimeZone('UTC'))`). The rez library is correct.

**Fix applied in rez-starter:** Serializers now output ISO 8601 format with `Z` suffix (`2026-06-28T10:30:00Z`) so the UTC nature is unambiguous and frontends can parse and convert to local time automatically.

**Input dates:** Routes parse user-supplied datetime strings without an explicit timezone (`new DateTimeImmutable($body['start'])`). In Docker (UTC server), these are interpreted as UTC. **API consumers must send datetimes in UTC.** If a Czech user wants to book at 10:00 Prague time (CEST = UTC+2), they must send `2026-06-28T08:00:00Z` or `2026-06-28 08:00:00`. This is the correct approach — timezone conversion belongs in the client/frontend, not the API.

---

## Recommended fix order

1. **BUG-02 + BUG-03** together — capacity-aware conflict detection and party size validation. These are the most significant correctness gaps.
2. **BUG-01** — existence check in `GetAvailabilityUseCase`. Small, isolated.
3. **DESIGN-01** — loosen UUID parsing. Low risk, improves DX.
