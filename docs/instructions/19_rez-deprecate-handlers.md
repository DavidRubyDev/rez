# Rez — Deprecate Handler Layer

This document marks the existing handler layer as deprecated in `davidrubydev/rez`.
Handlers are not removed — they remain for backwards compatibility — but are flagged
so IDEs and static analysis warn on usage.

Complete all other scaffold documents before running this one.
Handlers should not be used by new code — all new functionality calls use cases directly.

Run `composer test` and `vendor/bin/phpstan analyse` after completing before proceeding.

---

## Context

The handler layer (`src/Handler/`) was originally built as an array-in/array-out translation
layer between HTTP and use cases. Now that client apps call use cases directly via Slim routes,
handlers are redundant. They are kept to avoid a breaking change but should not be used
in new client app code.

PHP 8.3 has no built-in `#[Deprecated]` attribute (that lands in 8.4). Use a PHPDoc
`@deprecated` tag on each class. PHPStan and most IDEs respect this and will emit warnings
when deprecated classes are instantiated or type-hinted.

---

## Steps

### 1. Add `@deprecated` to all handler classes

Add the following PHPDoc block to every class in `src/Handler/` and its subdirectories.
Place it immediately before the `class` keyword, after any existing docblock or in place of it.

Format:
```php
/**
 * @deprecated Handlers are deprecated. Call the use case directly instead.
 *             This class will be removed in a future major version.
 */
```

Files to update — every `.php` file under `src/Handler/`:

**Reservation handlers:**
- `src/Handler/Reservation/CreateReservationHandler.php`
- `src/Handler/Reservation/CancelReservationHandler.php`
- `src/Handler/Reservation/GetReservationHandler.php`
- `src/Handler/Reservation/ListReservationsHandler.php`
- `src/Handler/Reservation/ConfirmReservationHandler.php`
- `src/Handler/Reservation/MarkNoShowHandler.php`

**Resource handlers:**
- `src/Handler/Resource/CreateResourceHandler.php`
- `src/Handler/Resource/GetResourceHandler.php`
- `src/Handler/Resource/ListResourcesHandler.php`
- `src/Handler/Resource/UpdateResourceHandler.php`
- `src/Handler/Resource/DeleteResourceHandler.php`

**Availability handlers:**
- `src/Handler/Availability/GetAvailabilityHandler.php`
- `src/Handler/Availability/SaveAvailabilityRuleHandler.php`
- `src/Handler/Availability/SaveAvailabilityOverrideHandler.php`

**Serializers** — these remain non-deprecated. They may be useful in client app route layers:
- `src/Handler/ReservationSerializer.php` — keep as-is
- `src/Handler/ResourceSerializer.php` — keep as-is

---

### 2. Update PHPStan config to allow deprecated usage in handler tests

The existing handler test classes instantiate handler classes directly. Since handlers are now
deprecated, PHPStan will flag the tests themselves as using deprecated code.

Two options:
1. Add `ignoreErrors` to `phpstan.neon` for the `tests/Handler/` path — cleaner
2. Add `@deprecated` suppression annotations in each test — noisier

Preferred: option 1. Add to `phpstan.neon` (or create it if absent):

```neon
parameters:
    ignoreErrors:
        -
            message: '#deprecated#'
            path: tests/Handler/*
```

This suppresses deprecation warnings only in the handler test directory, keeping the rest
of the codebase clean.

---

### 3. Update `examples/slim/` to call use cases directly

`examples/slim/routes.php` currently wires handlers to routes. Update it to call use cases
directly, building request objects inline.

Example — before:
```php
$app->post('/resources', function (Request $request, Response $response) use ($container) {
    $handler = $container->get(CreateResourceHandler::class);
    $data = $handler->handle((array)$request->getParsedBody());
    return jsonResponse($response, $data, 201);
});
```

After:
```php
$app->post('/resources', function (Request $request, Response $response) use ($container) {
    $useCase = $container->get(CreateResourceUseCaseInterface::class);
    $body = (array)$request->getParsedBody();
    $result = $useCase->execute(new CreateResourceRequest(
        type: $body['type'],
        name: $body['name'],
        capacity: (int)$body['capacity'],
        attributes: $body['attributes'] ?? [],
    ));
    $data = ResourceSerializer::serialize($result->getResource());
    return jsonResponse($response, $data, 201);
});
```

Update all routes in `examples/slim/routes.php` this way.
Serializers (`ReservationSerializer`, `ResourceSerializer`) are still used — they are not deprecated.

This step demonstrates the intended usage pattern for new client app repos.

---

### 4. Verify

Run the full test suite — all existing tests must continue to pass.
Handler tests still pass because the handler classes still exist and work — they are only deprecated,
not removed.

```bash
composer test
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run
```

PHPStan will now emit `deprecated` notices for any code outside `tests/Handler/` that
instantiates a handler class. There should be none after step 3.

---

## Checklist

- [ ] 1. `@deprecated` added to all 14 handler classes (not serializers)
- [ ] 2. PHPStan config updated to suppress deprecation in `tests/Handler/`
- [ ] 3. `examples/slim/routes.php` updated to call use cases directly
- [ ] 4. Full test suite passes, PHPStan clean
