# Phase 3: Parallel Fan-Out - Research

**Researched:** 2026-04-28
**Domain:** Laravel Concurrency facade + laravel/ai multi-provider dispatch + failure isolation
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Service Structure**
- D-01: `DiagnosticService` is a new class. `PrismStructuredCaller` is refactored to be provider-agnostic: its constructor accepts `provider` (string) + `model` (string) + `DiagnosticResultRepository`. The Phase 2 ROADMAP tinker success criterion (`PrismStructuredCaller::call()`) is satisfied by history — the class is kept and improved, not retired.
- D-02: `DiagnosticService` instantiates one `PrismStructuredCaller` per provider (4 total) and dispatches each as a keyed closure to `Concurrently::run()`. Return type of `run()` is `array` — up to 4 `ProviderResult` DTOs, null-filtered.

**Parallelism**
- D-03: Concurrency mechanism is `Concurrently::run([...])` (Laravel 11+ fiber-based concurrency). Each keyed closure calls its `PrismStructuredCaller::call($code, $statement, $requestId)`. Works directly with `laravel/ai` agent calls — no HTTP layer bypass needed.

**Failure Isolation**
- D-04: Per-provider exception handling lives **inside each closure** — try/catch wrapping the `PrismStructuredCaller::call()` call, returning `null` on any `\Throwable`. `DiagnosticService` filters nulls from the `Concurrently::run()` result array.

**All-Providers-Fail Behavior**
- D-05: If all 4 closures return `null`, `DiagnosticService::run()` returns `[]`. No exception thrown.

**DeepSeek Structured Output**
- D-06: DeepSeek treated uniformly. Failure from malformed response absorbed by closure catch.

### Claude's Discretion
- Model strings for OpenAI, Gemini, DeepSeek — use `gpt-4o`, `gemini-2.0-flash`, `deepseek-chat`; add `models.text.default` to each provider block in `config/ai.php`
- Whether to emit `Log::warning()` when a provider closure returns null
- Return type annotation for `DiagnosticService::run()` — `array`
- Test file location — `tests/Feature/Services/DiagnosticServiceTest.php`

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| API-02 | All 4 providers called in parallel per request (not sequentially) | `Concurrency::run()` (ProcessDriver) spawns 4 parallel PHP sub-processes; wall-clock time ≈ slowest, not sum |
| API-03 | Partial results returned when individual providers fail (failed providers omitted) | try/catch inside each closure returns `null`; sub-process exits successfully with null; ProcessDriver maps it correctly; `array_filter()` removes nulls |
| PERSIST-02 | Every result saved synchronously before response is returned (no fire-and-forget) | `DiagnosticResultRepository::save()` called inside each closure before returning `ProviderResult`; all saves complete before `Concurrency::run()` returns |

</phase_requirements>

---

## Summary

Phase 3 adds `DiagnosticService::run()` which fans out to all four LLM providers concurrently using Laravel's built-in `Concurrency` facade (ProcessDriver). Each provider is dispatched as a keyed closure; failures are absorbed inside the closure with try/catch returning null; successful ProviderResult DTOs are persisted via the existing `DiagnosticResultRepository::save()` before the closure returns. `PrismStructuredCaller` is refactored from Anthropic-only to provider-agnostic by parameterizing its constructor.

The critical correctness finding is: the CONTEXT.md refers to `Concurrently::run()` but the actual Laravel facade class is `Illuminate\Support\Facades\Concurrency` (not `Concurrently`). The method call `Concurrency::run([...])` is correct. The ProcessDriver spawns one child PHP process per closure via `php artisan invoke-serialized-closure`, achieving genuine parallel I/O. When a closure returns null (caught exception), the child process exits successfully with a serialized null — ProcessDriver does NOT throw. This is what makes per-provider failure isolation safe.

For tests, the `SyncDriver` must be used to keep tests deterministic and fast. The pattern is `Concurrency::setDefaultInstance('sync')` in `setUp()` or within the test body, combined with `DiagnosticAgent::fake([...])` for each of the four calls.

**Primary recommendation:** Use `Concurrency::run()` (not `Concurrently::run()`). Wrap `PrismStructuredCaller::call()` in try/catch inside each keyed closure. Persist before returning from the closure. Switch to `sync` driver in tests via `Concurrency::setDefaultInstance('sync')`.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | ^13.0 (installed) | `Concurrency` facade + ProcessDriver | Built-in since Laravel 11; zero extra installs |
| `laravel/ai` | ^0.6 (installed) | Agent calls with provider+model override | Already wired; `DiagnosticAgent::fake()` works for tests |
| `laravel/serializable-closure` | (transitive) | Serializes closures for ProcessDriver sub-processes | Required by ProcessDriver; auto-installed |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Illuminate\Support\Facades\Concurrency` | (framework) | Parallel dispatch API | Main fan-out mechanism |
| `Illuminate\Concurrency\SyncDriver` | (framework) | Sequential execution for tests | Always in test context |
| `Illuminate\Concurrency\ProcessDriver` | (framework) | Genuine parallel sub-processes | Production/tinker |

### No Additional Installs Required

All concurrency infrastructure ships with `laravel/framework ^13.0` already in composer.json. No `spatie/fork` needed (ForkDriver is process-safe on Linux but is not required here).

---

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Services/
│   ├── DiagnosticService.php       # NEW — Phase 3 entry point
│   └── PrismStructuredCaller.php   # REFACTOR — provider+model injected via constructor
config/
└── ai.php                          # ADD models.text.default to openai, gemini, deepseek blocks
```

### Pattern 1: Provider-Agnostic PrismStructuredCaller

**What:** Constructor gains `provider` (string) and `model` (string); `call()` method delegates to whichever provider/model is configured.

**When to use:** Every provider call inside `DiagnosticService`.

**Example (refactored constructor):**
```php
// Source: app/Services/PrismStructuredCaller.php (Phase 2 reference + Phase 3 refactor)
final class PrismStructuredCaller
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly DiagnosticResultRepository $repository,
    ) {}

    public function call(string $code, string $statement, string $requestId): ProviderResult
    {
        $userMessage = DiagnosticPromptBuilder::userMessage($code, $statement);

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = (new DiagnosticAgent)->prompt(
            $userMessage,
            provider: $this->provider,
            model:    $this->model,
        );

        $result = ProviderResult::fromPrismResponse(
            response:      $response,
            provider:      $this->provider,
            model:         $this->model,
            requestId:     $requestId,
            promptVersion: DiagnosticPromptBuilder::promptVersion(),
        );

        $this->repository->save($result);

        return $result;
    }
}
```

### Pattern 2: DiagnosticService Fan-Out

**What:** Instantiates one `PrismStructuredCaller` per provider, dispatches as keyed closures to `Concurrency::run()`, filters nulls.

**When to use:** This is the only fan-out pattern — all four providers every time.

**Example:**
```php
// Source: Illuminate\Support\Facades\Concurrency (verified in vendor)
use Illuminate\Support\Facades\Concurrency;

final class DiagnosticService
{
    public function run(string $code, string $statement, string $requestId): array
    {
        $repository = new DiagnosticResultRepository();

        $callers = [
            'anthropic' => new PrismStructuredCaller('anthropic', config('ai.providers.anthropic.models.text.default'), $repository),
            'openai'    => new PrismStructuredCaller('openai',    config('ai.providers.openai.models.text.default'),    $repository),
            'gemini'    => new PrismStructuredCaller('gemini',    config('ai.providers.gemini.models.text.default'),    $repository),
            'deepseek'  => new PrismStructuredCaller('deepseek',  config('ai.providers.deepseek.models.text.default'),  $repository),
        ];

        $results = Concurrency::run([
            'anthropic' => function () use ($callers, $code, $statement, $requestId) {
                try {
                    return $callers['anthropic']->call($code, $statement, $requestId);
                } catch (\Throwable) {
                    Log::warning('DiagnosticService: anthropic provider failed');
                    return null;
                }
            },
            'openai' => function () use ($callers, $code, $statement, $requestId) {
                try {
                    return $callers['openai']->call($code, $statement, $requestId);
                } catch (\Throwable) {
                    Log::warning('DiagnosticService: openai provider failed');
                    return null;
                }
            },
            // ... gemini, deepseek same pattern
        ]);

        return array_values(array_filter($results));
    }
}
```

### Pattern 3: Config Model Strings (config/ai.php additions)

**What:** Add `models.text.default` to the three provider blocks that currently lack it.

**Example (diff to config/ai.php):**
```php
'openai' => [
    'driver' => 'openai',
    'key' => env('OPENAI_API_KEY'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
    'models' => [
        'text' => ['default' => 'gpt-4o'],
    ],
],

'gemini' => [
    'driver' => 'gemini',
    'key' => env('GEMINI_API_KEY'),
    'models' => [
        'text' => ['default' => 'gemini-2.0-flash'],
    ],
],

'deepseek' => [
    'driver' => 'deepseek',
    'key' => env('DEEPSEEK_API_KEY'),
    'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com'),
    'models' => [
        'text' => ['default' => 'deepseek-chat'],
    ],
],
```

### Pattern 4: Test Setup — Sync Driver + Multi-Provider Fakes

**What:** Override concurrency driver to `sync` so tests run sequentially without spawning sub-processes. Fake all four provider responses.

**Why required:** The ProcessDriver spawns real PHP sub-processes. Inside a sub-process, `DiagnosticAgent::fake()` is not inherited — the fake is registered in the parent process's container. Tests must use the `sync` driver, which executes closures in the same process where fakes are registered.

**Example:**
```php
// Source: tests/Feature/Services/DiagnosticServiceTest.php
uses(RefreshDatabase::class);

beforeEach(function () {
    // Force sequential execution so DiagnosticAgent::fake() is visible inside closures
    Concurrency::setDefaultInstance('sync');
});

it('fans out to all four providers and persists all results', function () {
    $fakeResponse = [
        'diagnosis'     => 'Test diagnosis',
        'pc3_category'  => 'Predicate',
        'feedback'      => 'Test feedback',
        'confidence'    => 0.9,
        'tokens_input'  => 100,
        'tokens_output' => 50,
    ];

    DiagnosticAgent::fake([
        $fakeResponse, // anthropic
        $fakeResponse, // openai
        $fakeResponse, // gemini
        $fakeResponse, // deepseek
    ]);

    $requestId = '44444444-4444-4444-8444-444444444444';
    $service   = new DiagnosticService();

    $results = $service->run('let x: number = "hi";', 'Assign a number.', $requestId);

    expect($results)->toHaveCount(4);
    expect(DiagnosticResult::where('request_id', $requestId)->count())->toBe(4);
});

it('returns partial results when one provider fails', function () {
    // Provide only 3 responses — 4th call gets null from FakeTextGateway,
    // which triggers ProviderResult::fromPrismResponse() to throw on bad array access.
    // The closure catch absorbs it and returns null.
    DiagnosticAgent::fake([
        ['diagnosis' => 'd', 'pc3_category' => 'Predicate', 'feedback' => 'f', 'confidence' => 0.8, 'tokens_input' => 10, 'tokens_output' => 10],
        ['diagnosis' => 'd', 'pc3_category' => 'Concept',   'feedback' => 'f', 'confidence' => 0.8, 'tokens_input' => 10, 'tokens_output' => 10],
        ['diagnosis' => 'd', 'pc3_category' => 'Context',   'feedback' => 'f', 'confidence' => 0.8, 'tokens_input' => 10, 'tokens_output' => 10],
        // 4th response: null → FakeTextGateway auto-generates fake data (does NOT throw)
        // Use a closure-based fake or ensure 4th response causes a throw instead
    ]);
    // ... (see pitfall on FakeTextGateway auto-generation below)
});
```

### Anti-Patterns to Avoid

- **Importing `Concurrently` (does not exist):** The facade is `Illuminate\Support\Facades\Concurrency`. The CONTEXT.md uses the name `Concurrently::run()` colloquially — the actual PHP class is `Concurrency`.
- **Using ProcessDriver in tests:** Sub-processes do not inherit `DiagnosticAgent::fake()` registrations from the parent. Always swap to `sync` driver in test setup.
- **Calling `repository->save()` after `Concurrency::run()` returns:** Persistence must happen inside each closure, before returning the ProviderResult. If save happens outside, PERSIST-02 is violated.
- **`new DiagnosticResultRepository()` inside each closure for ProcessDriver:** With the real ProcessDriver, each sub-process boots Laravel fresh — constructor injection or `new` inside the closure both work, but using `new` avoids serialization concerns with injected container bindings.
- **`array_filter()` without `array_values()`:** `array_filter()` preserves keys. If callers expect a 0-indexed list, `array_values(array_filter($results))` is needed.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Parallel PHP execution | Custom fork/socket/queue dispatch | `Concurrency::run()` | Built into Laravel 11+; handles serialization, process pooling, result collection |
| Per-task result mapping | Manual indexed arrays + merge | Keyed closures in `Concurrency::run()` | Keys are preserved automatically by ProcessDriver and SyncDriver |
| Test-time concurrency stub | Custom mock/spy for concurrency | `Concurrency::setDefaultInstance('sync')` | SyncDriver runs closures sequentially in same process — no sub-process overhead |
| Provider failure signaling | Custom result envelope with status | Return `null` from closure + `array_filter()` | ProcessDriver only re-throws if the child process exits non-zero; null return is a clean success |

---

## Critical Correctness Findings

### Finding 1: `Concurrency` not `Concurrently`

The CONTEXT.md (D-03) references `Concurrently::run()`. The actual Laravel facade FQN is:

```php
use Illuminate\Support\Facades\Concurrency;
```

Verified by reading `/vendor/laravel/framework/src/Illuminate/Support/Facades/Concurrency.php` directly. There is no `Concurrently` facade or class in the installed vendor. Use `Concurrency::run([...])`.

**Confidence: HIGH** (read from installed vendor source)

### Finding 2: ProcessDriver Failure Semantics — null-safe

When a closure catches `\Throwable` and returns `null`:
1. `InvokeSerializedClosureCommand::handle()` receives the result of the closure invocation — which is `null`
2. It writes `['successful' => true, 'result' => serialize(null)]` to stdout
3. `ProcessDriver::run()` deserializes this as `null` for that key
4. No exception is thrown to the caller

The ProcessDriver only re-throws when `$result->failed()` (non-zero exit) or when the JSON has `successful: false`. A caught exception inside the closure that returns null is a successful process exit.

**Confidence: HIGH** (verified by reading `ProcessDriver.php` + `InvokeSerializedClosureCommand.php`)

### Finding 3: FakeTextGateway Auto-Generation Behavior

When `DiagnosticAgent::fake([...])` runs out of responses (nth call when only n-1 responses registered), `FakeTextGateway::marshalResponse()` does NOT throw by default — it **auto-generates** fake structured data from the schema. This means testing "one provider fails" requires a different approach:

Option A: Use a `Closure` response that throws:
```php
DiagnosticAgent::fake([
    $goodResponse1, $goodResponse2, $goodResponse3,
    fn() => throw new \RuntimeException('Simulated provider failure'),
]);
```

Option B: Use `->preventStrayPrompts(true)` and provide only 3 responses.

Option B is cleaner: `DiagnosticAgent::fake([...])->preventStrayPrompts(true)` with 3 responses. The 4th call to the fake will throw `RuntimeException: Attempted prompt [...] without a fake agent response.` The closure catch absorbs it and returns null.

**Confidence: HIGH** (read from `FakeTextGateway::marshalResponse()` source)

### Finding 4: No `concurrency.php` Config File Exists

The project has no `config/concurrency.php`. The `ConcurrencyManager::getDefaultInstance()` falls back to `'process'` when neither `concurrency.default` nor `concurrency.driver` config key is set. This means production behavior is ProcessDriver (parallel), which is correct. Tests must swap to `sync` explicitly.

**Confidence: HIGH** (verified `config/` directory listing + `ConcurrencyManager::getDefaultInstance()` source)

### Finding 5: DiagnosticAgent::fake() is Shared Across All 4 Calls

`DiagnosticAgent::fake()` registers fakes keyed by `DiagnosticAgent::class` on the `Ai` singleton. When `Concurrency::run()` uses the `sync` driver, all four closures execute in the same process and share the same `Ai` singleton, so the fake queue advances correctly across providers: call 1 = index 0, call 2 = index 1, etc.

With ProcessDriver (production), each sub-process boots a fresh Laravel application — fakes are NOT inherited. This is why `sync` is mandatory for tests.

**Confidence: HIGH** (read `InteractsWithFakeAgents.php` + `FakeTextGateway::nextResponse()`)

---

## Common Pitfalls

### Pitfall 1: Wrong Facade Name (`Concurrently` vs `Concurrency`)

**What goes wrong:** `PHP Fatal error: Class "Concurrently" not found`

**Why it happens:** CONTEXT.md uses `Concurrently::run()` as a shorthand. The actual facade is `Concurrency`.

**How to avoid:** Import `use Illuminate\Support\Facades\Concurrency;` — not `Concurrently`.

**Warning signs:** Immediate fatal error on first `php artisan tinker` invocation.

### Pitfall 2: Using ProcessDriver in Tests

**What goes wrong:** `DiagnosticAgent::fake()` registrations are invisible inside sub-processes. Tests fail with live API calls attempted (no key → exception), or fake never activates.

**Why it happens:** ProcessDriver spawns a new PHP process via `php artisan invoke-serialized-closure`. The child process boots a fresh app container with no fakes registered.

**How to avoid:** Call `Concurrency::setDefaultInstance('sync')` in test `beforeEach()` or at the start of each test that exercises `DiagnosticService`.

**Warning signs:** Tests pass in isolation but fail when fake isn't found, or tests make real HTTP calls.

### Pitfall 3: FakeTextGateway Silently Auto-Generates Data

**What goes wrong:** "One provider fails" test doesn't actually test failure — FakeTextGateway generates valid fake structured data for the nth call, so all 4 calls succeed.

**Why it happens:** `FakeTextGateway::marshalResponse()` generates data from schema when `$response` is null, unless `preventStrayPrompts(true)` is set.

**How to avoid:** Use `DiagnosticAgent::fake([...])->preventStrayPrompts(true)` with fewer responses than providers to force a throw on the extras, or use a Closure that explicitly throws.

**Warning signs:** Test for partial results passes with 4 rows persisted instead of 3.

### Pitfall 4: Persisting Outside the Closure

**What goes wrong:** PERSIST-02 violated — results may not be in DB before `run()` returns if persistence is deferred.

**Why it happens:** Calling `repository->save()` after `Concurrency::run()` returns is safe for the sync driver but conceptually wrong for the process driver (where the child process has already exited).

**How to avoid:** `repository->save($result)` inside each closure, before `return $result`. This is already the Phase 2 pattern in `PrismStructuredCaller::call()` — keep it there after refactoring.

**Warning signs:** Success criteria 3 fails in tinker (rows not in DB immediately after `run()` returns).

### Pitfall 5: array_filter Key Preservation

**What goes wrong:** `array_filter($results)` returns `['anthropic' => ..., 'gemini' => ..., 'deepseek' => ...]` (missing 'openai' key but keys not re-indexed). Downstream code expecting a 0-indexed list gets wrong indices.

**Why it happens:** `array_filter()` preserves original array keys by default.

**How to avoid:** `array_values(array_filter($results))` to produce a clean 0-indexed list.

**Warning signs:** `$results[1]` returns null when openai was the failed provider.

---

## Code Examples

### Using the Concurrency Facade (verified)

```php
// Source: vendor/laravel/framework/src/Illuminate/Support/Facades/Concurrency.php
use Illuminate\Support\Facades\Concurrency;

$results = Concurrency::run([
    'anthropic' => fn() => 'result-a',
    'openai'    => fn() => 'result-b',
]);
// $results === ['anthropic' => 'result-a', 'openai' => 'result-b']
```

### Switching to Sync Driver in Tests (verified)

```php
// Source: vendor/laravel/framework/src/Illuminate/Concurrency/ConcurrencyManager.php
// setDefaultInstance() sets both config keys
Concurrency::setDefaultInstance('sync');

// SyncDriver::run() executes closures sequentially in same process:
// Source: vendor/laravel/framework/src/Illuminate/Concurrency/SyncDriver.php
// return Collection::wrap($tasks)->map(fn($task) => $task())->all();
```

### DiagnosticAgent::fake() with Multiple Responses (verified)

```php
// Source: vendor/laravel/ai/src/Concerns/InteractsWithFakeAgents.php
// Source: vendor/laravel/ai/src/Gateway/FakeTextGateway.php
DiagnosticAgent::fake([
    ['diagnosis' => 'd1', 'pc3_category' => 'Predicate', 'feedback' => 'f', 'confidence' => 0.9, 'tokens_input' => 10, 'tokens_output' => 10],
    ['diagnosis' => 'd2', 'pc3_category' => 'Concept',   'feedback' => 'f', 'confidence' => 0.8, 'tokens_input' => 10, 'tokens_output' => 10],
    ['diagnosis' => 'd3', 'pc3_category' => 'Context',   'feedback' => 'f', 'confidence' => 0.7, 'tokens_input' => 10, 'tokens_output' => 10],
    ['diagnosis' => 'd4', 'pc3_category' => 'Predicate', 'feedback' => 'f', 'confidence' => 0.6, 'tokens_input' => 10, 'tokens_output' => 10],
]);
// Responses consumed in sequence: index 0 for first call, 1 for second, etc.
```

### Partial Failure Testing Pattern (verified)

```php
// Force 4th call to throw via preventStrayPrompts:
DiagnosticAgent::fake([
    $responseA, $responseB, $responseC,
    // No 4th entry → preventStrayPrompts throws RuntimeException
])->preventStrayPrompts(true);

// OR use explicit Closure throw for 4th:
DiagnosticAgent::fake([
    $responseA, $responseB, $responseC,
    fn() => throw new \RuntimeException('Simulated failure'),
]);
```

---

## Environment Availability

Step 2.6: SKIPPED (no external dependencies beyond already-installed composer packages; all concurrency infrastructure ships with `laravel/framework ^13.0` which is already installed).

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `Http::pool()` for parallel calls | `Concurrency::run()` | Laravel 11 | Pool was HTTP-only; Concurrency works for any PHP closure including AI agent calls |
| Manual process spawning | `Concurrency::run()` ProcessDriver | Laravel 11 | Framework handles serialization, process pool, result collection automatically |

**Deprecated/outdated:**
- `Http::pool()`: Was the pre-L11 pattern for parallel HTTP requests. Suitable only for raw HTTP calls, not for `laravel/ai` agent invocations (which are not plain HTTP requests from the caller's perspective). `Concurrency::run()` is the correct mechanism for this use case.

---

## Open Questions

1. **Closure variable capture with ProcessDriver**
   - What we know: ProcessDriver uses `SerializableClosure` to serialize closures for sub-processes. `PrismStructuredCaller` objects are captured via `use` in each closure.
   - What's unclear: Whether `PrismStructuredCaller` (which holds `DiagnosticResultRepository`) is serializable by `SerializableClosure`. Both are plain PHP objects with no unserializable resources.
   - Recommendation: Use `use ($callers, $code, $statement, $requestId)` in closures. If serialization fails at runtime, alternative is to construct the caller inside each closure (capturing only strings). The `sync` driver in tests will not expose this issue — it only appears on real `tinker` runs with the ProcessDriver.

2. **DiagnosticAgent::fake() and Request ID Isolation per Provider**
   - What we know: All four closures pass the same `$requestId` to their respective callers. Each persisted row gets the same `request_id`, linking the four results as one batch.
   - What's unclear: No ambiguity — the same `request_id` on all four rows is the intended design (PERSIST-03 and ROADMAP success criterion 1).
   - Recommendation: No action needed. Confirmed by REQUIREMENTS.md PERSIST-03.

---

## Project Constraints (from CLAUDE.md)

| Directive | Impact on Phase 3 |
|-----------|-------------------|
| **AI SDK: prism-php/prism OR laravel/ai** | Project uses `laravel/ai` (confirmed in composer.json). `DiagnosticAgent` uses `Promptable` trait from `laravel/ai`. All Phase 3 code targets this SDK. |
| **Parallelism: all provider calls must be concurrent, not sequential** | `Concurrency::run()` with ProcessDriver satisfies this. |
| **Persistence: no fire-and-forget; DiagnosticResult is the research corpus** | `repository->save()` inside each closure, before returning ProviderResult. |
| **Scope: single endpoint, no auth, no frontend — MVP only** | Phase 3 has no HTTP layer. `DiagnosticService::run()` is the entry point; no controller yet. |
| **Tech Stack: Laravel 12** | Project is actually Laravel 13.x (composer.json `^13.0`). Laravel 13 includes `Concurrency` facade. No impact. |
| **declare(strict_types=1) + final class + Pest tests** | All new files must follow this convention. |
| **HTTP fakes for tests — no real API calls in test suite** | `DiagnosticAgent::fake()` + `Concurrency::setDefaultInstance('sync')` satisfies this. |

---

## Sources

### Primary (HIGH confidence)

- `vendor/laravel/framework/src/Illuminate/Support/Facades/Concurrency.php` — confirmed facade FQN
- `vendor/laravel/framework/src/Illuminate/Concurrency/ProcessDriver.php` — failure semantics, keyed result return
- `vendor/laravel/framework/src/Illuminate/Concurrency/SyncDriver.php` — sequential execution, same process
- `vendor/laravel/framework/src/Illuminate/Concurrency/ConcurrencyManager.php` — default driver (`'process'`), `setDefaultInstance()` API
- `vendor/laravel/framework/src/Illuminate/Concurrency/Console/InvokeSerializedClosureCommand.php` — sub-process null-safe return path
- `vendor/laravel/ai/src/Gateway/FakeTextGateway.php` — auto-generation behavior, `preventStrayPrompts()`
- `vendor/laravel/ai/src/Promptable.php` — `prompt(provider:, model:)` signature
- `app/Services/PrismStructuredCaller.php` — current Phase 2 code to be refactored
- `app/Repositories/DiagnosticResultRepository.php` — `save()` is already correct
- `app/Ai/Agents/DiagnosticAgent.php` — provider-agnostic by design; `fake()` API
- `tests/Feature/Ai/PrismStructuredCallerTest.php` — established test patterns to mirror
- `config/ai.php` — current provider blocks; confirmed openai/gemini/deepseek lack `models.text.default`

### Secondary (MEDIUM confidence)

- `.planning/phases/03-parallel-fan-out/03-CONTEXT.md` — locked decisions (D-01 through D-06); note: `Concurrently::run()` in CONTEXT is a naming slip; corrected to `Concurrency::run()` above

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — all packages read from installed vendor; no version uncertainty
- Architecture: HIGH — patterns derived from reading actual source of ProcessDriver, SyncDriver, FakeTextGateway
- Pitfalls: HIGH — each pitfall derived from reading vendor source, not from training-data assumptions
- Facade name correction: HIGH — directly verified `Concurrently` does not exist; `Concurrency` does

**Research date:** 2026-04-28
**Valid until:** 2026-05-28 (stable framework internals; laravel/ai is relatively new but relevant classes read from source)
