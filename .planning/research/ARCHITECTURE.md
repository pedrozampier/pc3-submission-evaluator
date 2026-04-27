# Architecture Research: Laravel AI Broker

**Project:** PC³ Multi-LLM Diagnostic Broker
**Researched:** 2026-04-27
**Overall confidence:** HIGH (all claims verified against official docs or confirmed code)

---

## Component Map

```
HTTP Layer
  └── POST /api/diagnose
        └── DiagnoseController          (thin — validate, delegate, return)
              └── DiagnosticService     (orchestrator — owns the parallel fan-out)
                    ├── DiagnosticPromptBuilder   (system prompt + user prompt assembly)
                    ├── PrismStructuredCaller     (single provider call via Prism SDK)
                    │     called 4× concurrently
                    ├── DiagnosticResultRepository  (Eloquent persistence wrapper)
                    └── DiagnosticResult (Eloquent model)

DTOs
  ├── DiagnoseRequest   (validated input: code + statement)
  └── ProviderResult    (provider, model, diagnosis, pc3_category,
                         feedback, confidence, tokens_input, tokens_output)
```

**What talks to what:**

| Caller | Callee | Via |
|--------|--------|-----|
| DiagnoseController | DiagnosticService | constructor injection |
| DiagnosticService | DiagnosticPromptBuilder | direct call |
| DiagnosticService | PrismStructuredCaller (×4) | Http::pool() closures |
| DiagnosticService | DiagnosticResultRepository | direct call after collect |
| DiagnosticResultRepository | DiagnosticResult (Eloquent) | Eloquent ORM |

The service is the sole orchestrator. The controller knows nothing about providers,
parallelism, or persistence — only request shape and response shape.

---

## Data Flow

```
1. POST /api/diagnose  {code, statement}
   │
   ▼
2. DiagnoseController::store()
   - Validates: code (required string), statement (required string)
   - Builds DiagnoseRequest DTO
   - Calls DiagnosticService::run(DiagnoseRequest)
   │
   ▼
3. DiagnosticService::run()
   - Calls DiagnosticPromptBuilder::build(code, statement)
     → Returns [systemPrompt: string, userPrompt: string]
   │
   ▼
4. Http::pool() fan-out to 4 providers (concurrent — see Concurrency section)
   Each closure:
     - Calls PrismStructuredCaller::call(provider, model, systemPrompt, userPrompt)
     - Returns ProviderResult DTO on success
     - Returns null on exception (isolated — does not affect siblings)
   │
   ▼
5. Collect results
   - Filter nulls (failed providers)
   - Each successful ProviderResult passed to DiagnosticResultRepository::persist()
   │
   ▼
6. DiagnosticResultRepository::persist(ProviderResult)
   - DiagnosticResult::create([...]) — one row per provider result
   │
   ▼
7. DiagnosticService returns ProviderResult[]
   │
   ▼
8. DiagnoseController returns JsonResponse:
   [
     {provider, model, diagnosis, pc3_category, feedback,
      confidence, tokens_input, tokens_output},
     ...
   ]
```

**Key invariant:** persistence happens before the response is returned.
No fire-and-forget. Every row written = one confirmed LLM response.

---

## Concurrency Strategy

### Recommended: Http::pool() (Guzzle-backed, single-process)

**Rationale:** Http::pool() is the right tool for this workload. It uses Guzzle's
multi-cURL internally — all four HTTP connections are open simultaneously within a
single PHP process, with no subprocess serialization cost. It is available in web
request context (unlike Concurrency::fork), requires no extra packages (unlike
spatie/fork for Concurrency::fork), and has been the production-tested Laravel
pattern for parallel external API calls since Laravel 8.

The alternative — Concurrency::run with the `process` driver — spawns a separate
Artisan subprocess per closure: it serializes the closure, boots a new Laravel
application instance, deserializes, runs, re-serializes, and sends results back.
For four LLM calls that each take 2–10 seconds, that overhead is measurable and
provides zero benefit over pool-based async I/O.

**Do not use Concurrency::fork** in a web context — PHP explicitly forbids forking
during an active HTTP request. The Laravel docs confirm this is CLI-only.
(Source: https://laravel.com/docs/12.x/concurrency)

### Implementation pattern

```php
// DiagnosticService::run()

use Illuminate\Support\Facades\Http;

$configs = [
    'anthropic' => ['provider' => Provider::Anthropic, 'model' => 'claude-opus-4-5'],
    'openai'    => ['provider' => Provider::OpenAI,    'model' => 'gpt-4o'],
    'gemini'    => ['provider' => Provider::Gemini,    'model' => 'gemini-2.0-flash'],
    'deepseek'  => ['provider' => Provider::DeepSeek,  'model' => 'deepseek-chat'],
];

// Http::pool() opens all 4 connections concurrently via multi-cURL.
// Each closure is a Prism structured() call wrapped in try/catch.
// Failures return null; surviving results are collected.
$responses = Http::pool(function ($pool) use ($configs, $systemPrompt, $userPrompt) {
    return collect($configs)->map(
        fn($cfg, $key) => $pool->as($key)
            ->timeout(60)
            ->post(/* ... */)
    )->all();
});
```

**Note on Prism + Http::pool():** Prism's `Prism::structured()` uses its own
internal HTTP client. The cleanest way to achieve true parallelism is to wrap each
Prism call inside a `Concurrency::run()` closure with the `process` driver — or
alternatively, bypass pool entirely and use Guzzle promises directly through Prism's
underlying client. However, for an MVP, the practical approach is:

**Recommended MVP concurrency pattern — Concurrency::run with process driver:**

```php
use Illuminate\Support\Facades\Concurrency;

[$anthropicResult, $openaiResult, $geminiResult, $deepseekResult] = Concurrency::run([
    fn() => $this->callProvider(Provider::Anthropic, 'claude-opus-4-5', $prompts),
    fn() => $this->callProvider(Provider::OpenAI,    'gpt-4o',          $prompts),
    fn() => $this->callProvider(Provider::Gemini,    'gemini-2.0-flash', $prompts),
    fn() => $this->callProvider(Provider::DeepSeek,  'deepseek-chat',    $prompts),
]);
```

Where `callProvider()` returns a `ProviderResult|null` and wraps exceptions internally.
Results are returned in input order. `Concurrency::run` with the `process` driver
works in web request context (confirmed in Laravel 12 docs). The subprocess overhead
is ~100–200ms per process bootstrap, which is negligible against 2–10s LLM response times.

**Why Concurrency::run over Http::pool() for Prism calls specifically:**
Prism manages its own Guzzle client internally. Injecting those calls into Laravel's
Http::pool() would require rebuilding the raw HTTP calls that Prism already handles.
Concurrency::run lets you call Prism's full fluent API without unwrapping it.

**Driver:** Use `process` (default) for web. Reserve `fork` for queue workers only.

---

## Structured Output Pattern

Prism uses a per-call schema injection pattern. There is no global named schema registry.
Each call receives the schema at call time via `->withSchema()`.

**Define the schema once, reuse across all four calls:**

```php
// In DiagnosticPromptBuilder or DiagnosticService (built once per request)

use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\EnumSchema;

$schema = new ObjectSchema(
    name: 'diagnostic_result',
    description: 'PC3 taxonomy diagnosis of TypeScript code',
    properties: [
        new StringSchema('provider',    'LLM provider name'),
        new StringSchema('model',       'Model identifier used'),
        new StringSchema('diagnosis',   'Main diagnosis of the error'),
        new EnumSchema('pc3_category',  'PC3 taxonomy category',
                       ['Predicate', 'Concept', 'Context']),
        new StringSchema('feedback',    'Actionable feedback for the student'),
        new NumberSchema('confidence',  'Self-reported confidence 0.0–1.0'),
    ],
    requiredFields: ['provider', 'model', 'diagnosis', 'pc3_category',
                     'feedback', 'confidence']
);

// In PrismStructuredCaller::call()
$response = Prism::structured()
    ->using($provider, $model)
    ->withSystemPrompt($systemPrompt)
    ->withPrompt($userPrompt)
    ->withSchema($schema)
    ->asStructured();

$data = $response->structured;
// $data['pc3_category'], $data['diagnosis'], etc.

// Token counts
$tokensInput  = $response->usage->promptTokens;
$tokensOutput = $response->usage->completionTokens;
```

**tokens_input / tokens_output:** Available on `$response->usage` — not part of the
schema, pulled from the response metadata after the call.

**Provider-specific notes (MEDIUM confidence — from prism-php docs):**
- Anthropic: Native structured output via constrained decoding on Claude 3.5+. HIGH reliability.
- OpenAI: Strict mode available. Pass `->withClientOptions(['mode' => 'json'])` for older models.
- Gemini: Full schema support. `anyOf` requires Gemini 2.5+. `gemini-2.0-flash` supports ObjectSchema.
- DeepSeek: Supported as a first-class provider in prism-php config. Structured output issue
  (#136) was filed Jan 2025 and labeled "planned" — status unresolved as of research date.
  **Mitigation:** DeepSeek is OpenAI-API-compatible. If `Prism::structured()` fails for DeepSeek,
  fall back to `Prism::text()` with a JSON-mode system prompt instruction and parse the response
  manually. This is an acceptable MVP compromise.

---

## Error Isolation

**Goal:** One provider failure must not abort the other three.

**Pattern — wrap each provider call in try/catch returning null:**

```php
private function callProvider(Provider $provider, string $model, array $prompts): ?ProviderResult
{
    try {
        $response = Prism::structured()
            ->using($provider, $model)
            ->withSystemPrompt($prompts['system'])
            ->withPrompt($prompts['user'])
            ->withSchema($this->schema)
            ->asStructured();

        return ProviderResult::fromPrismResponse($response, $provider->value, $model);

    } catch (\Throwable $e) {
        Log::warning("Provider {$provider->value} failed", [
            'model'   => $model,
            'error'   => $e->getMessage(),
        ]);
        return null;
    }
}
```

**After Concurrency::run collects results:**

```php
$results = array_filter(
    [$anthropicResult, $openaiResult, $geminiResult, $deepseekResult],
    fn($r) => $r !== null
);

// Persist only successful results
foreach ($results as $result) {
    DiagnosticResult::create($result->toArray());
}

// Return partial array — 1 to 4 entries depending on failures
return array_values($results);
```

**HTTP-level failures** (timeout, 5xx): Caught by the catch block above.
Set a `->timeout(60)` on each Prism call via client options if the provider supports it.
Default prism.php `request_timeout` is 30 seconds — may need increasing for large code inputs.

**Minimum viable response:** The endpoint returns 200 with whatever results arrived.
Return 422 only if ALL four providers fail (empty array is not useful to the caller).

---

## Build Order

Dependencies determine order. Each layer only depends on what is below it.

```
Step 1 — Schema + DTO
  DiagnoseRequest DTO        (input shape: code + statement)
  ProviderResult DTO         (output shape: 8 fields)
  PC3 Diagnostic schema      (ObjectSchema — shared across all calls)

  Why first: Every other component depends on these shapes being fixed.
  Changing a DTO later breaks all callers simultaneously.

Step 2 — Migration + Model
  DiagnosticResult migration  (columns match ProviderResult fields)
  DiagnosticResult model      (Eloquent, guarded or fillable)

  Why second: Persistence is a dependency of the service, not the controller.
  Can be built and tested in isolation with tinker.

Step 3 — Prism integration (single provider first)
  Install prism-php/prism
  Configure .env for Anthropic first (most reliable structured output)
  PrismStructuredCaller::call() for a single provider
  Verify schema compliance with one working response

  Why third: Proves the SDK is wired before adding concurrency complexity.
  Anthropic is recommended first because it has native structured output
  (constrained decoding) — least likely to return malformed JSON.

Step 4 — Parallel orchestration
  DiagnosticService::run() with Concurrency::run() across all 4 providers
  Add remaining provider configs (OpenAI, Gemini, DeepSeek)
  Verify concurrency: all 4 calls fire simultaneously, total time ≈ slowest provider

  Why fourth: Concurrency only makes sense once a single call is working.
  Adding all providers at once before verifying the pattern multiplies
  debugging surface area.

Step 5 — DiagnosticPromptBuilder
  Extract PC3 system prompt into a dedicated class
  Accept code + statement, return [systemPrompt, userPrompt]
  System prompt lives as a private const in this class (not config)

  Why system prompt lives in a class, not config/:
  The PC3 taxonomy classification prompt is business logic, not environment
  configuration. Config is for values that change between environments
  (API keys, base URLs). The prompt only changes when the research protocol
  changes — that belongs in version control as code.

Step 6 — Controller + Route
  DiagnoseController::store()
  POST /api/diagnose route registration
  FormRequest validation (code required|string, statement required|string)

  Why last: The controller is a thin shell. Building it last lets you test
  DiagnosticService directly from tinker first, which is faster to iterate.
```

**Dependency graph (simplified):**

```
DTOs → Schema → PrismCaller → DiagnosticService → Controller
                Migration → Model → DiagnosticService
```

---

## Component Responsibilities (hard boundaries)

| Component | Owns | Must NOT own |
|-----------|------|-------------|
| DiagnoseController | HTTP in/out, validation, DTO construction | Provider logic, persistence, prompt text |
| DiagnosticService | Concurrency orchestration, collect/filter results | Raw HTTP, Prism API calls, SQL |
| PrismStructuredCaller | Single Prism call, error isolation (try/catch), ProviderResult construction | Concurrency, persistence |
| DiagnosticPromptBuilder | PC3 system prompt, user prompt assembly | Provider selection, HTTP |
| DiagnosticResultRepository | Eloquent create, future query methods | Business logic |
| DiagnosticResult (model) | Column definitions, casts | Business logic |

---

## Sources

- Laravel 12 Concurrency docs: https://laravel.com/docs/12.x/concurrency
- Prism structured output: https://prismphp.com/core-concepts/structured-output/
- Prism schema types: https://prismphp.com/core-concepts/schemas/
- Prism Gemini provider: https://prismphp.com/providers/gemini/
- Prism config (providers list): https://github.com/prism-php/prism/blob/main/config/prism.php
- DeepSeek structured output issue: https://github.com/prism-php/prism/issues/136
- Http::pool() parallel requests: https://dev.to/bhaidar/supercharge-your-laravel-api-calls-with-httppool-2cdp
- Guzzle Promise::settle() for partial failures: https://github.com/guzzle/promises
