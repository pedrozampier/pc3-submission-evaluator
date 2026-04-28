# Phase 4: HTTP Layer - Research

**Researched:** 2026-04-28
**Domain:** Laravel 13 HTTP layer — controllers, FormRequests, routing, JSON responses
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Response body is a flat JSON array — no envelope, no wrapper object.
- **D-02:** Each object exposes all 10 DTO fields: `provider`, `model`, `diagnosis`,
  `pc3_category`, `feedback`, `confidence`, `tokens_input`, `tokens_output`,
  `request_id`, `prompt_version`.
- **D-03:** When `DiagnosticService::run()` returns `[]`, return HTTP 503 with
  `{"message": "All providers failed"}`.
- **D-04:** FormRequest rules are `required|string` only for `code` and `statement`.
  No length constraints.
- **D-05:** Feature tests use `$this->mock(DiagnosticService::class)`. Tests live in
  `tests/Feature/Http/DiagnoseControllerTest.php`. Four test cases:
  1. HTTP 200 valid inputs + mocked service returning results
  2. HTTP 422 missing `code` (service never called)
  3. HTTP 422 missing `statement` (service never called)
  4. HTTP 503 when service returns `[]`

### Claude's Discretion

- Controller name (`DiagnoseController`) and namespace (`App\Http\Controllers`)
- `request_id` generated in controller via `Str::uuid()->toString()`
- `routes/api.php` creation and registration via `bootstrap/app.php`
- FormRequest class preferred (`DiagnoseRequest`) for testability over inline `validate()`

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| API-01 | `POST /api/diagnose` accepts `code` (TypeScript string, required) and `statement` (string, required) | FormRequest validation (D-04), route registration, controller dispatch to DiagnosticService, JSON response shaping |
</phase_requirements>

---

## Summary

Phase 4 wires the single HTTP endpoint onto the already-complete service layer. The work
is entirely standard Laravel 13 HTTP plumbing: create `routes/api.php`, register it in
`bootstrap/app.php`, write a `DiagnoseRequest` FormRequest, write a `DiagnoseController`,
and return the correct JSON shape.

One non-obvious finding: `ProviderResult` is a plain PHP object with camelCase `readonly`
properties (`pc3Category`, `tokensInput`, `tokensOutput`, `requestId`, `promptVersion`).
When serialized directly by `json_encode` or `response()->json()`, those become camelCase
keys. But D-02 specifies snake_case in the response. The controller must map each
`ProviderResult` to a snake_case array explicitly; no `JsonSerializable` interface or API
Resource transformation exists on the DTO.

The `$this->mock(DiagnosticService::class)` pattern requires the controller to receive
`DiagnosticService` via constructor injection (Laravel container), not `new DiagnosticService()`.
The mock binds into the container so constructor-injected dependencies are replaced
transparently in feature tests.

**Primary recommendation:** Write the controller with constructor-injected `DiagnosticService`,
produce the snake_case array manually inside the controller action, and register `routes/api.php`
by adding the `api` parameter to `withRouting()` in `bootstrap/app.php` — no `php artisan install:api`
needed (that command adds Sanctum, which is out of scope).

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^13.0 | HTTP routing, FormRequest, response helpers | Already installed — project requirement |
| PHP | ^8.3 | Runtime | Already installed (verified: PHP 8.4.1) |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| pestphp/pest | ^4.6 | Feature tests | Already installed — project standard |
| pestphp/pest-plugin-laravel | ^4.1 | `$this->mock()`, `$this->postJson()` | Already installed |
| mockery/mockery | ^1.6 | Underlying mock engine for `$this->mock()` | Already installed |

No new packages are required for this phase.

---

## Architecture Patterns

### New Files Required

```
routes/
└── api.php                                         # Register POST /api/diagnose

app/Http/
├── Controllers/
│   └── DiagnoseController.php                      # Thin controller
└── Requests/
    └── DiagnoseRequest.php                         # FormRequest validation

tests/Feature/
└── Http/
    └── DiagnoseControllerTest.php                  # 4 test cases (D-05)
```

### Pattern 1: Register `routes/api.php` Without Sanctum

**What:** Add `api:` parameter to `withRouting()` in `bootstrap/app.php`. The `/api`
prefix is applied automatically to all routes in that file.

**When to use:** Any time an API route file is needed without running `php artisan install:api`
(which would also pull in Sanctum — unnecessary here).

```php
// bootstrap/app.php
// Source: https://laravel.com/docs/13.x/routing#api-routing
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {})
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
```

```php
// routes/api.php
<?php

declare(strict_types=1);

use App\Http\Controllers\DiagnoseController;
use Illuminate\Support\Facades\Route;

Route::post('/diagnose', DiagnoseController::class);
// Accessible at POST /api/diagnose
```

### Pattern 2: FormRequest for Validation

**What:** Extend `Illuminate\Foundation\Http\FormRequest`. Return `true` from `authorize()`
(no auth in this project). Return rules from `rules()`. Validation failure on a JSON
request automatically returns HTTP 422 with error details — no controller code needed.

**When to use:** Preferred over inline `validate()` for testability — the FormRequest
class can be unit-tested independently and the controller stays thin.

```php
// app/Http/Requests/DiagnoseRequest.php
// Source: https://laravel.com/docs/13.x/validation#form-request-validation
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DiagnoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string'],
            'statement' => ['required', 'string'],
        ];
    }
}
```

### Pattern 3: Controller With Constructor Injection + Snake-Case Mapping

**What:** Receive `DiagnosticService` via constructor injection (not `new`). Generate
`request_id` in the action. Map each `ProviderResult` to a snake_case array (critical —
see pitfall below). Return HTTP 503 when results are empty.

**When to use:** All controller actions that delegate to a service and need to be mockable
in feature tests.

```php
// app/Http/Controllers/DiagnoseController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\ProviderResult;
use App\Http\Requests\DiagnoseRequest;
use App\Services\DiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class DiagnoseController extends Controller
{
    public function __construct(private readonly DiagnosticService $service) {}

    public function __invoke(DiagnoseRequest $request): JsonResponse
    {
        $requestId = Str::uuid()->toString();

        $results = $this->service->run(
            code:      $request->string('code')->toString(),
            statement: $request->string('statement')->toString(),
            requestId: $requestId,
        );

        if (empty($results)) {
            return response()->json(['message' => 'All providers failed'], 503);
        }

        return response()->json(
            array_map(fn (ProviderResult $r) => [
                'provider'       => $r->provider,
                'model'          => $r->model,
                'diagnosis'      => $r->diagnosis,
                'pc3_category'   => $r->pc3Category->value,
                'feedback'       => $r->feedback,
                'confidence'     => $r->confidence,
                'tokens_input'   => $r->tokensInput,
                'tokens_output'  => $r->tokensOutput,
                'request_id'     => $r->requestId,
                'prompt_version' => $r->promptVersion,
            ], $results)
        );
    }
}
```

### Pattern 4: Feature Tests With Mocked Service

**What:** `$this->mock(DiagnosticService::class)` binds the mock into the service
container before the request is dispatched. The route/FormRequest execute normally.
Use `$this->postJson('/api/diagnose', [...])` to send JSON requests.

**When to use:** All HTTP layer tests. Keeps tests fast and deterministic — no LLM calls,
no Concurrency/process spawning.

```php
// tests/Feature/Http/DiagnoseControllerTest.php
// Source: https://laravel.com/docs/13.x/mocking#mocking-objects
<?php

declare(strict_types=1);

use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use App\Services\DiagnosticService;
use Mockery\MockInterface;

it('returns 200 with flat JSON array for valid inputs', function () {
    $results = [/* ProviderResult stubs */];

    $this->mock(DiagnosticService::class, function (MockInterface $mock) use ($results) {
        $mock->expects('run')
             ->once()
             ->andReturn($results);
    });

    $this->postJson('/api/diagnose', [
        'code'      => 'let x: number = "hi";',
        'statement' => 'Assign a number.',
    ])->assertStatus(200)
      ->assertJsonIsArray();
});

it('returns 422 when code is missing without calling service', function () {
    $this->mock(DiagnosticService::class, function (MockInterface $mock) {
        $mock->expects('run')->never();
    });

    $this->postJson('/api/diagnose', ['statement' => 'some text'])
         ->assertStatus(422);
});
```

### Anti-Patterns to Avoid

- **Instantiating `DiagnosticService` with `new` in the controller:** Breaks `$this->mock()`.
  The mock only replaces container bindings — `new DiagnosticService()` bypasses the container
  entirely and the mock is never used.
- **Returning `$results` directly from `response()->json()`:** Produces camelCase JSON keys
  (`pc3Category`, `tokensInput`) instead of the required snake_case. Must map explicitly.
- **Running `php artisan install:api`:** Installs Sanctum, which is out of scope. Add `api:`
  to `withRouting()` manually instead.
- **Forgetting `declare(strict_types=1)`:** All files in this project use it (established
  pattern from Phases 1–3).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 422 validation errors | Custom validation middleware | FormRequest + Laravel's built-in | Laravel automatically returns 422 JSON on failed FormRequest for JSON requests |
| UUID generation | Custom UUID function | `Str::uuid()->toString()` | Laravel ships it; verified working in this project |
| JSON response with status code | `header()` + `echo json_encode()` | `response()->json($data, $status)` | Idiomatic Laravel; handles Content-Type header automatically |

**Key insight:** The HTTP layer in Laravel is almost entirely configuration — the hard work
(validation, response serialization, routing) is built in. The only custom logic is the
snake_case mapping for `ProviderResult`.

---

## Common Pitfalls

### Pitfall 1: camelCase ProviderResult Properties Leak into JSON

**What goes wrong:** `ProviderResult` has camelCase PHP properties (`pc3Category`,
`tokensInput`, `tokensOutput`, `requestId`, `promptVersion`). If returned directly via
`response()->json($results)`, `json_encode` produces camelCase keys — breaking D-02.

**Why it happens:** PHP's `json_encode` reflects property names verbatim. `ProviderResult`
implements no `JsonSerializable` interface and extends no Laravel model with automatic
snake_case conversion.

**How to avoid:** Map each `ProviderResult` to a snake_case array with `array_map()` inside
the controller action (see Pattern 3). Also note `pc3Category` is a `Pc3Category` enum —
use `->value` to get the string.

**Warning signs:** JSON response contains `"pc3Category"` or `"tokensInput"` keys — wrong.
Correct keys are `"pc3_category"` and `"tokens_input"`.

### Pitfall 2: `$this->mock()` Has No Effect When Service Is `new`-ed

**What goes wrong:** If `DiagnoseController` does `new DiagnosticService()`, the mock
registered by `$this->mock(DiagnosticService::class, ...)` is never used. The real service
runs, spawning Concurrency processes in tests.

**Why it happens:** `$this->mock()` binds the mock into the service container via
`$this->instance()`. Constructor injection (`__construct(private DiagnosticService $service)`)
resolves from the container. Direct `new` bypasses the container entirely.

**How to avoid:** Always inject `DiagnosticService` through the constructor.

### Pitfall 3: Missing `routes/api.php` Causes 404, Not 500

**What goes wrong:** If `routes/api.php` does not exist or is not referenced in
`bootstrap/app.php`, `POST /api/diagnose` returns 404 with no helpful error message.

**Why it happens:** Laravel 13 ships without `routes/api.php` by default (unlike earlier
versions). The file must be created and registered.

**How to avoid:** Create `routes/api.php` and add `api: __DIR__.'/../routes/api.php'` to
`withRouting()` in `bootstrap/app.php`. Verify with `php artisan route:list | grep diagnose`.

### Pitfall 4: FormRequest `authorize()` Returning False Causes 403, Not 422

**What goes wrong:** Forgetting to return `true` from `authorize()` causes every request to
return HTTP 403 Forbidden, regardless of whether the inputs are valid.

**Why it happens:** `authorize()` defaults to `false` in the generated stub.

**How to avoid:** Return `true` explicitly since this endpoint has no authentication.

### Pitfall 5: `$request->input('code')` May Return Unexpected Types

**What goes wrong:** `$request->input('code')` returns `mixed`. If the FormRequest rules
pass (because `required|string` is satisfied), the value is guaranteed to be a string, but
static analysis tools may still flag it.

**How to avoid:** Use `$request->string('code')->toString()` for a type-safe string, or
cast: `(string) $request->input('code')`. `DiagnosticService::run()` requires `string` params.

---

## Code Examples

### Verified: `Str::uuid()->toString()` in Laravel 13

```php
// Verified in this project via php artisan tinker
\Illuminate\Support\Str::uuid()->toString();
// => "03970b60-104e-4df1-ba1f-37320dc8d648"  (example output)
```

### Verified: HTTP 422 on FormRequest Failure for JSON Requests

From official Laravel 13 docs (https://laravel.com/docs/13.x/validation#form-request-validation):

When a FormRequest validation fails on a JSON/XHR request, Laravel automatically returns
HTTP 422 with body:

```json
{
    "message": "The code field is required.",
    "errors": {
        "code": ["The code field is required."]
    }
}
```

No controller code is needed to produce this response.

### Verified: `withRouting()` api parameter in Laravel 13

```php
// Source: https://laravel.com/docs/13.x/routing#api-routing
->withRouting(
    web:      __DIR__.'/../routes/web.php',
    api:      __DIR__.'/../routes/api.php',  // adds /api prefix automatically
    commands: __DIR__.'/../routes/console.php',
    health:   '/up',
)
```

### Verified: `response()->json()` with status code

```php
// Source: https://laravel.com/docs/13.x/responses#json-responses
return response()->json(['message' => 'All providers failed'], 503);
return response()->json($array);  // defaults to 200
```

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|-------------|-----------|---------|----------|
| PHP | Runtime | Yes | 8.4.1 | — |
| Laravel Framework | Routing, FormRequest | Yes | 13.6.0 | — |
| Pest + pest-plugin-laravel | Feature tests | Yes | 4.6 / 4.1 | — |
| Mockery | `$this->mock()` | Yes | 1.6 | — |

No missing dependencies.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `php artisan install:api` to get `routes/api.php` | Manual `api:` parameter in `withRouting()` (Sanctum-free) | Laravel 13 | No Sanctum installed; MVP has no auth |
| `Route::apiResource()` for CRUD | Single `Route::post()` for single-action controller | N/A | One endpoint, invokable controller is cleaner |
| API Resource classes for JSON shaping | Inline `array_map` in controller | N/A | No field renaming or computed props — resource class would be overkill |

---

## Open Questions

1. **Invokable vs. named-method controller**
   - What we know: CONTEXT.md says `DiagnoseController` with no specified method name
   - What's unclear: Should it use `__invoke` (single-action) or a named `diagnose` method?
   - Recommendation: Use `__invoke` — single-action controllers are idiomatic for single-route
     controllers in Laravel 13; `Route::post('/diagnose', DiagnoseController::class)` works
     with `__invoke` automatically.

---

## Sources

### Primary (HIGH confidence)

- Laravel 13 Routing docs (https://laravel.com/docs/13.x/routing) — api parameter in withRouting, /api prefix behavior
- Laravel 13 Validation docs (https://laravel.com/docs/13.x/validation#form-request-validation) — FormRequest, 422 on JSON requests
- Laravel 13 Responses docs (https://laravel.com/docs/13.x/responses#json-responses) — response()->json(), status codes
- Laravel 13 Mocking docs (https://laravel.com/docs/13.x/mocking#mocking-objects) — $this->mock(), container binding
- Laravel 13 Controllers docs (https://laravel.com/docs/13.x/controllers#dependency-injection-and-controllers) — constructor injection

### Secondary (MEDIUM confidence)

- `php artisan tinker` in-project verification — `Str::uuid()->toString()` confirmed working
- `ReflectionClass` in-project verification — `ProviderResult` property names confirmed camelCase, no JsonSerializable

### Tertiary (LOW confidence)

None.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; all dependencies already installed and confirmed
- Architecture: HIGH — verified against official Laravel 13 docs for all patterns
- Pitfalls: HIGH — camelCase issue confirmed by reflection against live codebase; mock pattern verified against official docs

**Research date:** 2026-04-28
**Valid until:** 2026-05-28 (stable framework docs; unlikely to change in 30 days)
