# `rez-sessions` — Fixed-length class occurrences

## Problem this solves

`AvailabilityRule` models a *continuous* open/close window per resource per day-of-week —
correct for resources like tables where any start/end inside the window is a legal booking.
It's the wrong model for fixed-length classes (Pilates, cycling, massage) that occur at
specific, often irregular start times set by a lecturer's own schedule — and today nothing
stops a client from submitting an arbitrary `TimeSlot` to `POST /api/reservations` for such a
resource, since `AvailabilityService::isSlotAvailable()` only checks that a rule exists for the
date, that no override blocks it, and that there's no conflicting reservation. It never checks
that the submitted slot matches anything specific.

This scaffold adds `Session` — a discrete, admin-created occurrence with a fixed start time and
duration — as the unit customers actually book against for class-type resources. Continuous
resources (tables) are entirely unaffected; `AvailabilityRule`/`Override` keep doing their job.

## Scope

`davidrubydev/rez` only. `rez-starter` (HTTP routes), `rez-admin` (session management UI), and
`rez-components` (`<rez-calendar>` booking flow for class resources) are separate scaffolds —
see `21_rez-sessions.md` in those repos.

## 1. Domain

`src/Domain/Session/Session.php` — immutable entity, static factory only (matches
`Reservation`/`NewsletterSubscriber`, not `Resource`'s public-constructor style, since a
`SessionStatus` transition needs the same guard pattern as `Reservation::cancel()`).

- Constructor params (all `public readonly`, no getters — `CLAUDE.md` rule):
  `SessionId $id`, `ResourceId $resourceId`, `DateTimeImmutable $startTime`,
  `int $durationMinutes`, `int $capacity`, `SessionStatus $status`.
- `static create(SessionId, ResourceId, DateTimeImmutable $startTime, int $durationMinutes, int $capacity): self`
  — sets `status = SessionStatus::Scheduled`. Throws `\InvalidArgumentException` if
  `$durationMinutes <= 0` or `$capacity <= 0`.
- `static reconstruct(...)` — DB hydration, trusts the row (same convention as
  `NewsletterSubscriber::reconstruct()`).
- `cancel(): self` — only from `Scheduled`, throws `InvalidSessionStateException` if already
  `Cancelled` (mirrors `Reservation::cancel()`'s guard against re-cancelling).
- `toTimeSlot(): TimeSlot` — `new TimeSlot($this->startTime, $this->startTime->modify("+{$this->durationMinutes} minutes"))`.
  This is the one place session duration becomes a `TimeSlot` — nothing else should compute it.

`src/Domain/Session/SessionId.php` — `UuidV4Id` trait, same as `ResourceId`.

`src/Domain/Session/SessionStatus.php` — pure enum, no backing values: `Scheduled`, `Cancelled`
(matches `ReservationStatus`'s shape, not `DayOfWeek`'s).

`src/Domain/Exception/SessionNotFoundException.php` — empty stub extending `DomainException`,
matching `ResourceNotFoundException`.

`src/Domain/Exception/InvalidSessionStateException.php` — matches
`InvalidReservationStateException`.

Tests: `SessionTest` (create validation, `cancel()` transitions, `toTimeSlot()`), `SessionIdTest`
(copy `ResourceIdTest`'s shape) — same coverage depth as `ReservationTest`/`ResourceCollectionTest`.

## 2. Resource gains a default duration

`src/Domain/Resource/Resource.php` — add `?int $defaultDurationMinutes = null` to the
constructor (nullable — table-type resources never set it; class-type resources do). No new
invariant enforced here; validation that class-type resources *should* have one is an
application-layer/admin-UI concern, not a domain one, per the existing pattern of `attributes`
being an untyped bag the domain doesn't interpret.

Update `ResourceTest` for the new optional param. Update `MysqlResourceRepository` (column
`default_duration_minutes INT NULL`) and its hydration/save methods — same pattern as the
`active` column added in step 83.

## 3. Reservation gains an optional session link

`src/Domain/Reservation/Reservation.php` — add `?SessionId $sessionId = null` to `create()` and
the constructor. **Do not** make `Reservation` know about `Session` beyond storing the ID —
same boundary `Party::externalRef` uses ("the library never interprets it" — copy that comment
onto this field too).

`MysqlReservationRepository` — column `session_id CHAR(36) NULL`, no FK (same reasoning as
`wallet_transactions.reservation_id` — don't couple deletion behavior across tables you don't
need to). Hydrate/save both ways.

`ReservationRepositoryInterface` — add `findBySessionId(SessionId): ReservationCollection`.
Needed by `CancelSessionUseCase` (step 5) to find what to bulk-cancel, and useful later for a
"list this session's reservations" admin view (`rez-starter`'s job to expose).

## 4. Port + MySQL repository

`src/Application/Port/SessionRepositoryInterface.php`:
- `findById(SessionId): Session` — throws `SessionNotFoundException`
- `findForResource(ResourceId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): SessionCollection`
- `save(Session): void`

`src/Domain/Session/SessionCollection.php` — immutable collection, same shape as
`ResourceCollection` (`empty()`, `fromArray()`, `add()`, `isEmpty()`, `count()`, `toArray()`,
`filter()`, `findById()`).

`src/Infrastructure/Persistence/Mysql/MysqlSessionRepository.php` — same
`PDOException → DatabaseException` + `logger->critical()` wrapping as every other MySQL
repository (`rez-pdo-exceptions` convention, non-negotiable — don't skip it because this is new
code).

Schema: `database/seeds/schema/005_sessions.sql` (new numbered file, `000–099` range is
`davidrubydev/rez`'s per the seed directory convention) —
```sql
CREATE TABLE IF NOT EXISTS sessions (
    id CHAR(36) PRIMARY KEY,
    resource_id CHAR(36) NOT NULL,
    start_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL,
    capacity INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    FOREIGN KEY (resource_id) REFERENCES resources(id)
);
```
No `ON DELETE CASCADE` on the FK — resources are soft-deleted (invariant 13), so this never
fires, but be explicit rather than silent about that being the reason.

Tests: `MysqlSessionRepositoryTest` (integration, skipped locally, same shape as
`MysqlResourceRepositoryTest`) + a logger-failure unit test matching
`MysqlReservationSettingsRepositoryLoggerTest`.

## 5. Use cases

All under `src/Application/UseCase/Session/`, Request/Response/UseCase pattern.

**`CreateSessionUseCase`** — request: `resourceId`, `startTime` (string, `Y-m-d H:i` — parse and
validate format, throw `\InvalidArgumentException` on bad input, same as
`SaveAvailabilityRuleUseCase` does for its date bounds), optional `durationMinutes`/`capacity`
overrides. Loads the `Resource` (`ResourceNotFoundException` propagates), defaults duration from
`Resource::defaultDurationMinutes` if the request didn't override it — throw
`\InvalidArgumentException` if neither the request nor the resource supplies one (a class
resource with no duration anywhere is a configuration error, not a silent 0). Defaults capacity
from `Resource::capacity`. Creates and saves.

**`CancelSessionUseCase`** — loads session, calls `cancel()`, saves, then calls
`findBySessionId()` and bulk-cancels every non-cancelled reservation on it — reuse
`BulkCancelReservationsUseCase` here rather than reimplementing the skip-on-invalid-state loop
it already has.

**`GetSessionUseCase`** — loads session, wraps in response. Trivial, matches `GetResourceUseCase`.

**`ListSessionsUseCase`** — `findForResource(resourceId, from, to)`, thin wrapper matching
`GetAvailability`'s "delegates entirely" shape.

**`CreateReservationUseCase` change** — `CreateReservationRequest` gains an optional
`?string $sessionId`. When present: load the `Session` (`SessionNotFoundException` propagates,
maps to 404 same as everything else), throw `InvalidSessionStateException` if
`status !== Scheduled`, derive `TimeSlot` via `Session::toTimeSlot()` and `resourceId` from the
session — **ignore any resourceId/timeslot the request body also carries for this path**; the
session is the source of truth, full stop, that's the entire point of this scaffold. Capacity
check: sum `Party::size` across `findBySessionId()`'s non-cancelled results, reject if
`+ incoming > session.capacity` (same arithmetic as the existing BUG-02/03 capacity check, just
keyed by session instead of by date+resource conflict scan). Skip the `AvailabilityService` call
entirely on this path — rules/overrides don't apply to session bookings, the session's existence
and status *are* the availability check.

Tests: `CreateSessionUseCaseTest`, `CancelSessionUseCaseTest` (include the cascade-cancel
assertion), `GetSessionUseCaseTest`, `ListSessionsUseCaseTest` — same coverage shape as their
Resource/Reservation equivalents. `CreateReservationUseCaseTest` gains: session-path happy case,
session not found, session cancelled rejects, capacity exceeded rejects, session path ignores
body-supplied resourceId/timeslot (regression test for the exact vulnerability this scaffold
closes — write this one first).

## 6. Container

Register `SessionRepositoryInterface → MysqlSessionRepository`, all five new use case
interfaces, in `config/container.php`.

## Explicitly out of scope

`rez-starter` routes, `rez-admin` UI, `rez-components` booking-flow changes for class resources.
See the matching instruction docs in those repos.

## Checklist
- [ ] `Session`, `SessionId`, `SessionStatus`, `SessionCollection`
- [ ] `Resource::defaultDurationMinutes` + schema column
- [ ] `Reservation::sessionId` + schema column (no FK)
- [ ] `SessionRepositoryInterface` + `MysqlSessionRepository` + schema
- [ ] `ReservationRepositoryInterface::findBySessionId()`
- [ ] `CreateSessionUseCase`, `CancelSessionUseCase`, `GetSessionUseCase`, `ListSessionsUseCase`
- [ ] `CreateReservationUseCase` session path (with the regression test above)
- [ ] Container bindings
- [ ] PHPStan max clean, CS clean, full test suite green
