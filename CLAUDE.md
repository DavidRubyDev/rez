# Rez — Claude Instructions

These instructions apply to every task in this repository. Follow them without being reminded.

---

## Stack Overview

- **rez** (this package) — the full reservation engine, including all core and platform features. Everything lives here.
- **rez-starter** — a client app template that consumes `rez` as a Composer dependency.
- **rez-demo / rez-app** — instances of `rez-starter`. They require `rez` and add no engine logic.

---

## Architecture

This is a full PHP reservation engine built with **Domain-Driven Design (DDD)** and **Hexagonal Architecture** (Ports & Adapters). The layers are:

```
Domain        — Pure business logic. No dependencies on any other layer.
               Entities, value objects, domain exceptions, pure enums.
               No framework, no persistence, no I/O.

Application   — Use cases and port interfaces (contracts).
               Orchestrates domain objects. Depends only on Domain.
               Port interfaces define what the application needs from the outside world.

Infrastructure — Driven adapters. Implements port interfaces.
                MySQL repositories, mappers (enum/value object ↔ string).
                Depends on Application ports and Domain.

Handler       — Driving adapters. Entry points that call use cases.
               Receives input, delegates to a use case, returns output.
               No HTTP framework — pure PHP.
```

**Rules:**
- Domain must never depend on Application, Infrastructure, or Handler
- Application must never depend on Infrastructure or Handler
- Enums are pure (no backing values) — string serialization lives in Infrastructure mappers
- All entities and value objects are immutable
- No public mutable properties anywhere
- Use `readonly` where appropriate
- All `DateTimeImmutable` values in UTC

---

## TDD Workflow

Follow strict red-green TDD for every piece of code:

1. **Write the test first** — test must reference the class/method that does not exist yet
2. **Commit the test**
3. **Run tests** — confirm they fail (red)
4. **Implement the minimum code** to make the fewest tests pass
5. **Amend the commit** with the implementation
6. **Repeat** — add the next smallest increment of implementation until all tests pass
7. Never skip ahead — do not write implementation before the test is committed and confirmed failing

**PHPUnit mocks only** — no third-party test double libraries.
Each test class has one responsibility. Do not combine domain tests with use case tests.

---

## Code Style

PSR-12 as configured in `.php-cs-fixer.php`. Always write code in this style from the start.

Every file starts with:
```php
<?php

declare(strict_types=1);
```

Write self-commenting code — well-named classes, methods, and variables should make the intent obvious. Add comments only where the *why* cannot be expressed in code (e.g. non-obvious business rules, edge cases that would surprise a reader). Never add comments that just restate what the code does.

---

## Before Every Commit

Run the full code analysis suite and fix any issues before committing:

```bash
composer ca
```

This runs `cs-fix`, `test`, and `stan` in sequence. Do not commit if any of these fail.

---

## Branch Workflow

- **Before starting any new task:** checkout a new branch with a descriptive prefix:
  - `feature/` for new functionality
  - `docs/` for documentation-only changes
  - `fix/` for bug fixes
  ```bash
  git checkout -b feature/your-task-name
  ```
- **After the task is complete:** the user handles `git push` and `git pull` — never run these
- **After the user confirms the branch is merged:** checkout `main`
  ```bash
  git checkout main
  ```

**Never run `git push` or `git pull`** — these are always done by the user.

---

## Multi-Step Implementation Workflow

When working through a numbered plan (e.g. `docs/rez-platform-modifications.md`):

- **Do one step at a time** — complete the step, commit it, then stop
- **Wait for the user** to push, review, merge to main, and pull before starting the next step
- **Never start the next step** until the user confirms main is pulled

---

## Commit Messages

- Write concise, descriptive commit messages focused on *why*, not *what*
- Do **not** include `Co-Authored-By: Claude` or any mention of Claude Code
- Do not amend commits on `main` — only on feature branches

---

## General Rules

- Every class, method, and property has correct visibility
- No static state except in ID `generate()` methods
- Do not create files outside the `rez/` directory
- Keep `docs/context.md` up to date after completing each step
- Keep `docs/instructions.md` up to date if architecture or scope changes
