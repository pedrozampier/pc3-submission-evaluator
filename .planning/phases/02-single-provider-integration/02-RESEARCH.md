# Phase 2: Single-Provider Integration - Research

**Researched:** 2026-04-27
**Domain:** laravel/ai SDK agent wiring, PC3 prompt construction, structured output testing
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01:** System prompt uses the **expert code reviewer** role: "You are an expert TypeScript code reviewer applying the PC³ taxonomy..."

**D-02:** Taxonomy definitions are **full** — each category includes its name, a definition, and 1–2 examples. Enough context for the LLM to classify edge cases reliably.

**D-03:** Three categories to define:
  - **Predicate** — logic/condition errors (wrong comparisons, off-by-one, incorrect boolean expressions)
  - **Concept** — wrong understanding of a language feature or library API (misused method, wrong type usage, incorrect operator semantics)
  - **Context** — environment, scope, or configuration issues (wrong variable in scope, missing import, misconfigured toolchain)

**D-04:** Prompt is a **private constant** inside `DiagnosticPromptBuilder` — never in `.env`, never in `config/`. Changes only via code commit. (PROMPT-02)

**D-05:** Prompt version string is `"v1.0"` — matches the `prompt_version` column seeded in the migration.

**D-06:** User message uses **labeled sections**:
```
## Exercise Statement
{statement}

## TypeScript Code
```typescript
{code}
```
```
Clear structure — LLM can parse the two inputs as separate concerns.

**D-07:** Class name is `PrismStructuredCaller` (locked by ROADMAP success criteria: "Calling `PrismStructuredCaller::call()` in tinker").
  Note: legacy name from before SDK switch — the class uses `laravel/ai`, not prism-php.

**D-08:** Single public method: `call(string $code, string $statement, string $requestId): ProviderResult`.
  Anthropic-only in this phase; Phase 3 adds other providers.

**D-09:** Anthropic model is pinned to `claude-sonnet-4-20250514`. Pin location: a constant or config key in `config/ai.php` — **not** a hardcoded string scattered through code.

**D-10:** Phase 2 test uses **HTTP fake / mocked response** — no real Anthropic API call.
  The test fakes the `laravel/ai` HTTP layer and returns a valid `StructuredAgentResponse`. This is:
  - Deterministic (no token spend, no network dependency)
  - CI-safe (no `ANTHROPIC_API_KEY` required)
  - Sufficient to verify the wiring: schema → agent → caller → ProviderResult → repository

### Claude's Discretion

- Exact wording of the PC³ definitions in the system prompt — keep them accurate and concise; target ~150–250 tokens for the full prompt
- Namespace/location of `PrismStructuredCaller` (e.g. `App\Services\` or `App\Ai\`)
- How to construct the fake `StructuredAgentResponse` in the test (reflection, factory, or public constructor if available)

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| SETUP-02 | `laravel/ai` configured for Anthropic, model pinned to `claude-sonnet-4-20250514` | `config/ai.php` `models.text.default` key under `anthropic` provider; SDK reads this in `AnthropicProvider::defaultTextModel()` |
| PROMPT-01 | System prompt instructs the LLM to apply PC³ taxonomy classification | `DiagnosticAgent::instructions()` returns value from `DiagnosticPromptBuilder::build()`; `DiagnosticPromptBuilder` holds PC³ definitions as private const |
| PROMPT-02 | System prompt is version-locked in code (not environment config) | Private constant in `DiagnosticPromptBuilder` class — not in `.env`, not in `config/`; changes only via code commit |
</phase_requirements>

---

## Summary

Phase 2 wires the `laravel/ai` SDK for Anthropic and proves the full path: prompt → agent → `StructuredAgentResponse` → `ProviderResult` → `DiagnosticResultRepository`. Phase 1 already built all three downstream components (`ProviderResult::fromPrismResponse()`, `DiagnosticResultRepository::save()`, and the `DiagnosticAgent` skeleton with a complete `schema()`). Phase 2 adds only three new artifacts: `DiagnosticPromptBuilder` (holds the PC³ system prompt as a private constant), `PrismStructuredCaller` (invokes the agent with provider + model targeting), and one integration test that exercises the full stack with a faked gateway.

The `laravel/ai` SDK's testing API uses `Agent::fake()` — a static method on any class that uses the `Promptable` trait. When called with an array payload for a structured agent, the `FakeTextGateway` marshals it into a `StructuredTextResponse`, which the agent system then wraps into the expected response type. Crucially, the existing `makeResponse()` helper in `DiagnosticResultPersistenceTest.php` uses `ReflectionClass::newInstanceWithoutConstructor()` + assignment to the public `$structured` property — that pattern is confirmed and reusable for building fake `StructuredAgentResponse` objects in tests.

The Anthropic model pin `claude-sonnet-4-20250514` must be placed in `config/ai.php` under the `anthropic` provider's `models.text.default` key. The SDK reads this via `AnthropicProvider::defaultTextModel()` with the config path `config['models']['text']['default']` — confirmed by reading `vendor/laravel/ai/src/Providers/AnthropicProvider.php` line 61.

**Primary recommendation:** Add `models.text.default` to the `anthropic` provider config, fill `DiagnosticAgent::instructions()` from `DiagnosticPromptBuilder`, then build `PrismStructuredCaller` that calls `(new DiagnosticAgent)->prompt($userMessage, provider: 'anthropic')`. The fake-based integration test should use `DiagnosticAgent::fake(['structured_array'])` to intercept the call.

---

## Standard Stack

### Core (already installed, Phase 1 verified)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^13.0 (v13.6.0) | Core framework | Project decision; Laravel 13 on main branch |
| laravel/ai | ^0.6 (v0.6.3) | LLM agent SDK | Project decision; provides `Agent`, `HasStructuredOutput`, `Promptable`, `fake()` |
| PHP | 8.4.1 | Runtime | Installed and verified Phase 1 |
| SQLite | 3.x (bundled) | Persistence | Default in Laravel 13, already used in Phase 1 tests |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| pestphp/pest | v4.6.3 | Test framework | All tests; already installed Phase 1 |
| pestphp/pest-plugin-laravel | latest | Laravel integration | `uses(RefreshDatabase::class)` for feature tests |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `DiagnosticAgent::fake([...])` | `ReflectionClass::newInstanceWithoutConstructor()` for response stub | Both approaches work; `Agent::fake()` is the SDK-native path and handles the full stack; reflection is used when you need to hand a `StructuredAgentResponse` to `fromPrismResponse()` directly (as in Phase 1 persistence tests) |
| `config/ai.php` model pin | PHP constant in caller class | Config key is the correct pin location per D-09; putting it in the caller class would create a string literal |

**No new packages need to be installed for Phase 2.**

---

## Architecture Patterns

### Recommended Project Structure (additions for Phase 2)

```
app/
├── Ai/
│   ├── Agents/
│   │   └── DiagnosticAgent.php          # EXISTING — instructions() needs filling
│   └── PrismStructuredCaller.php        # NEW — or App\Services\PrismStructuredCaller.php
├── Services/
│   └── DiagnosticPromptBuilder.php      # NEW — private const for PC3 system prompt
├── DTOs/
│   ├── Pc3Category.php                  # Phase 1, complete
│   └── ProviderResult.php               # Phase 1, complete
└── Repositories/
    └── DiagnosticResultRepository.php   # Phase 1, complete
config/
└── ai.php                               # MODIFY — add models.text.default to anthropic provider
tests/
└── Feature/
    └── Ai/
        └── PrismStructuredCallerTest.php  # NEW — faked integration test
```

**Namespace discretion:** `PrismStructuredCaller` may live in `App\Ai\` or `App\Services\`. Either follows Laravel 13 conventions. Recommend `App\Services\` since it is a service layer orchestrator, not an agent definition — agents live in `App\Ai\Agents\`.

### Pattern 1: Filling DiagnosticAgent::instructions()

**What:** Replace the TODO placeholder with a call to `DiagnosticPromptBuilder::systemPrompt()` (or return the constant inline via the builder's static accessor).
**When to use:** This is the only integration point between PROMPT-01 and the agent.

```php
// app/Ai/Agents/DiagnosticAgent.php
public function instructions(): string
{
    return DiagnosticPromptBuilder::systemPrompt();
}
```

`DiagnosticPromptBuilder::systemPrompt()` returns the private constant string. This keeps `DiagnosticAgent` thin and makes the prompt independently testable.

### Pattern 2: DiagnosticPromptBuilder with Private Constant (PROMPT-01, PROMPT-02)

**What:** A final class that holds the full PC³ system prompt as a `private const` string and exposes it via a single public static method.
**When to use:** Any time the system prompt is needed. The private const ensures no external access — changes must be code commits.

```php
<?php
// app/Services/DiagnosticPromptBuilder.php
declare(strict_types=1);

namespace App\Services;

final class DiagnosticPromptBuilder
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert TypeScript code reviewer applying the PC³ taxonomy to classify
the root cause of a TypeScript compilation or runtime error.

The PC³ taxonomy has three categories:

**Predicate** — Logic or condition errors: wrong comparisons, off-by-one mistakes,
incorrect boolean expressions. Example: using `>` instead of `>=` in a boundary check.

**Concept** — Wrong understanding of a language feature or library API: misused method,
wrong type usage, incorrect operator semantics. Example: calling `.push()` on a readonly
array or using `==` when strict equality is required.

**Context** — Environment, scope, or configuration issues: wrong variable in scope,
missing import, misconfigured toolchain. Example: referencing a variable before its
`let` declaration or importing from the wrong module path.

Analyze the code below and return a JSON response with:
- `diagnosis`: a concise description of the error
- `pc3_category`: exactly one of "Predicate", "Concept", or "Context"
- `feedback`: actionable guidance for the student to fix the error
- `confidence`: your self-reported confidence as a float between 0.0 and 1.0
- `tokens_input`: your estimated input token count
- `tokens_output`: your estimated output token count
PROMPT;

    /**
     * Return the version-locked PC³ system prompt.
     */
    public static function systemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * Return the prompt version string matching the DB column default.
     */
    public static function promptVersion(): string
    {
        return 'v1.0';
    }

    /**
     * Build the user message with labeled sections (D-06).
     */
    public static function userMessage(string $code, string $statement): string
    {
        return <<<MSG
## Exercise Statement
{$statement}

## TypeScript Code
```typescript
{$code}
```
MSG;
    }
}
```

**Key design choices:**
- `private const` makes it impossible to read the prompt from outside without going through `systemPrompt()` — satisfies PROMPT-02
- `promptVersion()` centralizes `"v1.0"` so the caller doesn't hardcode it
- `userMessage()` encapsulates the labeled-section format (D-06)
- The heredoc `<<<'PROMPT'` (single-quoted) prevents PHP variable interpolation inside the prompt text
- Exact wording and token budget (~150–250 tokens) is Claude's discretion; the structure above targets ~200 tokens

### Pattern 3: PrismStructuredCaller (SETUP-02)

**What:** A service class with a single public `call()` method that instantiates `DiagnosticAgent`, invokes it against the `anthropic` provider with the pinned model, and wraps the response in `ProviderResult`.
**When to use:** This is the single entry point the ROADMAP tinker success criterion tests.

```php
<?php
// app/Services/PrismStructuredCaller.php  (or app/Ai/PrismStructuredCaller.php)
declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\DiagnosticAgent;
use App\DTOs\ProviderResult;
use App\Repositories\DiagnosticResultRepository;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class PrismStructuredCaller
{
    public function __construct(
        private readonly DiagnosticResultRepository $repository,
    ) {}

    /**
     * Call the Anthropic provider for a single structured diagnostic result.
     */
    public function call(string $code, string $statement, string $requestId): ProviderResult
    {
        $userMessage = DiagnosticPromptBuilder::userMessage($code, $statement);

        /** @var StructuredAgentResponse $response */
        $response = (new DiagnosticAgent)->prompt(
            $userMessage,
            provider: 'anthropic',
            model: config('ai.providers.anthropic.models.text.default'),
        );

        $result = ProviderResult::fromPrismResponse(
            response:      $response,
            provider:      'anthropic',
            model:         config('ai.providers.anthropic.models.text.default'),
            requestId:     $requestId,
            promptVersion: DiagnosticPromptBuilder::promptVersion(),
        );

        $this->repository->save($result);

        return $result;
    }
}
```

**Notes on the model string:**
- `config('ai.providers.anthropic.models.text.default')` reads the model from `config/ai.php` — satisfies D-09 (pin in config, not scattered in code)
- In tests with `DiagnosticAgent::fake()`, the provider/model arguments to `prompt()` are ignored by `FakeTextGateway` — no real HTTP call is made

### Pattern 4: Model Pin in config/ai.php (SETUP-02, D-09)

**What:** Add a `models` key to the `anthropic` provider block in `config/ai.php`.
**Why:** `AnthropicProvider::defaultTextModel()` reads `$this->config['models']['text']['default']` (confirmed in vendor source line 61). Setting this key pins the model without hardcoding a string anywhere in application code.

```php
// config/ai.php — modify the existing 'anthropic' provider entry:
'anthropic' => [
    'driver' => 'anthropic',
    'key'    => env('ANTHROPIC_API_KEY'),
    'url'    => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
    'models' => [
        'text' => [
            'default' => 'claude-sonnet-4-20250514',
        ],
    ],
],
```

This is the single location for the model string. If `prompt()` is called without an explicit `model:` argument, the SDK falls back to `defaultTextModel()` which reads this config. The caller explicitly passes it as `model: config('ai.providers.anthropic.models.text.default')` for clarity, but either approach reaches the same string.

**Verification criterion:** No other model string for Anthropic appears in any provider configuration file — ROADMAP success criterion 3 checks this.

### Pattern 5: Faked Integration Test (D-10)

**What:** A Pest feature test that:
1. Calls `DiagnosticAgent::fake([...structured_array...])` — intercepts the agent prompt at the SDK level
2. Instantiates `PrismStructuredCaller` with a real `DiagnosticResultRepository`
3. Calls `call()` with sample inputs
4. Asserts the returned `ProviderResult` has correct `pc3_category` and `confidence` in range
5. Asserts the result was persisted to the DB

**Mechanism:** `DiagnosticAgent::fake($responses)` (from `Promptable` trait, line 239) calls `Ai::fakeAgent(static::class, $responses)` which registers a `FakeTextGateway`. When `prompt()` is subsequently called, `FakeTextGateway::generateText()` is invoked instead of the real HTTP client. When `$responses` contains an array, `marshalResponse()` wraps it in a `StructuredTextResponse`. The agent infrastructure then wraps that into a `StructuredAgentResponse` before returning.

```php
<?php
// tests/Feature/Ai/PrismStructuredCallerTest.php
declare(strict_types=1);

use App\Ai\Agents\DiagnosticAgent;
use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use App\Models\DiagnosticResult;
use App\Repositories\DiagnosticResultRepository;
use App\Services\PrismStructuredCaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a schema-compliant ProviderResult and persists it via the repository', function () {
    DiagnosticAgent::fake([
        [
            'diagnosis'     => 'TS2322: Type "string" is not assignable to type "number"',
            'pc3_category'  => 'Predicate',
            'feedback'      => 'Change the variable assignment to a numeric literal.',
            'confidence'    => 0.88,
            'tokens_input'  => 200,
            'tokens_output' => 75,
        ],
    ]);

    $caller = new PrismStructuredCaller(new DiagnosticResultRepository());
    $requestId = '11111111-1111-4111-8111-111111111111';

    $result = $caller->call(
        code: 'let x: number = "hello";',
        statement: 'Assign a number to variable x.',
        requestId: $requestId,
    );

    // ProviderResult shape
    expect($result)->toBeInstanceOf(ProviderResult::class);
    expect($result->pc3Category)->toBe(Pc3Category::Predicate);
    expect($result->confidence)->toBeFloat()
        ->toBeGreaterThanOrEqual(0.0)
        ->toBeLessThanOrEqual(1.0);
    expect($result->provider)->toBe('anthropic');
    expect($result->promptVersion)->toBe('v1.0');

    // Persistence
    $row = DiagnosticResult::query()
        ->where('request_id', $requestId)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->pc3_category)->toBe(Pc3Category::Predicate);
});

it('includes the user message with both labeled sections in the prompt', function () {
    DiagnosticAgent::fake([
        [
            'diagnosis'     => 'd',
            'pc3_category'  => 'Concept',
            'feedback'      => 'f',
            'confidence'    => 0.5,
            'tokens_input'  => 10,
            'tokens_output' => 10,
        ],
    ]);

    $caller = new PrismStructuredCaller(new DiagnosticResultRepository());
    $caller->call('const x = 1;', 'Declare a constant.', '22222222-2222-4222-8222-222222222222');

    DiagnosticAgent::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, '## Exercise Statement')
            && str_contains($prompt->prompt, '## TypeScript Code');
    });
});
```

**Alternative stub approach (if `Agent::fake()` has compatibility issues):** Reuse the `makeResponse()` reflection helper from Phase 1 persistence tests — build a `StructuredAgentResponse` with `newInstanceWithoutConstructor()` and inject it via a mock/spy on the caller. However, `Agent::fake()` is the cleaner SDK-native path and is already confirmed available in the installed version.

### Anti-Patterns to Avoid

- **Putting the system prompt in `.env`:** D-04 forbids it explicitly. Env vars are not version-controlled and could be overwritten per-environment.
- **Hardcoding `'claude-sonnet-4-20250514'` directly in `PrismStructuredCaller`:** Violates D-09. The string must appear exactly once — in `config/ai.php` under `models.text.default`.
- **Using `$table->string()` or a class constant outside `DiagnosticPromptBuilder` for the prompt version:** The prompt version `"v1.0"` belongs on `DiagnosticPromptBuilder::promptVersion()` — one canonical location.
- **Calling `->prompt()` without specifying `provider: 'anthropic'`:** The `config/ai.php` `default` key is currently `'openai'`. If no provider is specified, the SDK routes to OpenAI, not Anthropic — the call would fail in production without an OpenAI key.
- **Making `DiagnosticPromptBuilder` non-final with instance state:** No instance state is needed; everything is static. A final class with only static methods and a private const prevents subclassing that could override the locked prompt.
- **Checking prompt content via source-code string inspection in tests:** Source inspection is valid for schema tests (as in Phase 1) but the integration test should use `DiagnosticAgent::assertPrompted()` with a closure — cleaner and doesn't break on whitespace changes.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Faking HTTP responses in tests | Laravel `Http::fake()` or custom mock objects | `DiagnosticAgent::fake()` from `Promptable` trait | SDK-native path; intercepts at the gateway level, not HTTP level; auto-generates valid structured responses from schema |
| Agent response validation | Custom schema validator | `DiagnosticAgent` with `HasStructuredOutput` — already done in Phase 1 | SDK enforces schema on the response |
| Prompt version tracking | Custom migration, DB column, etc. | `DiagnosticPromptBuilder::promptVersion()` constant + existing `prompt_version` column | Column already exists from Phase 1 |
| UUID generation for `requestId` | `bin2hex(random_bytes(16))` | `Str::uuid()` (Phase 3 will inject this at the service entry point; in tests pass a literal) | RFC 4122 compliant, already in Laravel |

**Key insight:** The `laravel/ai` SDK's `fake()` mechanism generates structurally valid fake data automatically when `$responses` is an empty array (via `generate_fake_data_for_json_schema_type`). Passing an explicit array overrides this — use explicit arrays in tests for predictable assertions.

---

## Runtime State Inventory

Not applicable. Phase 2 adds new files only — no renames, refactors, or migrations. No stored data, live service config, or OS-registered state is affected.

---

## Common Pitfalls

### Pitfall 1: `config/ai.php` Default Provider Mismatch

**What goes wrong:** `PrismStructuredCaller` calls `(new DiagnosticAgent)->prompt($message)` without specifying `provider:`. The `config/ai.php` `default` key is `'openai'` (the published default), so the call routes to OpenAI instead of Anthropic. The test passes (faked), but production fails without an OpenAI key.

**Why it happens:** The `Promptable::withModelFailover()` resolves provider from `config('ai.default')` when no `provider:` argument is given.

**How to avoid:** Always pass `provider: 'anthropic'` explicitly in `PrismStructuredCaller::call()`. Do not rely on the default.

**Warning signs:** `ANTHROPIC_API_KEY` is set but calls fail; logs show `openai` as the provider.

### Pitfall 2: `Agent::fake()` Returns Array — FakeTextGateway Wraps It Correctly

**What goes wrong:** Developer passes a string to `Agent::fake()` expecting structured output, but `FakeTextGateway::marshalResponse()` wraps strings in `TextResponse`, not `StructuredTextResponse`. The response cannot be accessed as `$response['diagnosis']`.

**Why it happens:** `marshalResponse()` uses `match(true)`: `is_string($response)` → `TextResponse`; `is_array($response)` → `StructuredTextResponse`. Structured agents must be faked with arrays.

**How to avoid:** Always pass an **array** (not a string) as the fake response for structured agents.
```php
DiagnosticAgent::fake([[/* valid structured array */]]);  // correct
DiagnosticAgent::fake(['Some text']);                       // WRONG — this is a string
```

**Warning signs:** `Call to a member function offsetGet() on null` or `Cannot use object of type TextResponse as array` when the caller tries `$response['diagnosis']`.

### Pitfall 3: Model Pin Not Read by SDK When `prompt()` Passes Model Explicitly

**What goes wrong:** Developer pins the model in config but then hard-codes a different string directly in the `prompt()` call, creating two sources of truth. The config pin is never read.

**Why it happens:** The `prompt()` method's `model:` argument takes precedence over `defaultTextModel()`. If both are set but disagree, the call argument wins silently.

**How to avoid:** Read the model once from config in the caller and use it in both `prompt()` and `fromPrismResponse()`:
```php
$model = config('ai.providers.anthropic.models.text.default');
$response = (new DiagnosticAgent)->prompt($msg, provider: 'anthropic', model: $model);
$result = ProviderResult::fromPrismResponse($response, 'anthropic', $model, ...);
```

**Warning signs:** ROADMAP success criterion 3 check fails — the model string appears in caller code, not only in config.

### Pitfall 4: `DiagnosticAgent::instructions()` Still Returns Placeholder in Test

**What goes wrong:** Phase 1 left a TODO placeholder in `instructions()`. If Phase 2 adds `DiagnosticPromptBuilder` but forgets to wire it into `DiagnosticAgent::instructions()`, the integration test still passes (faked) but the existing `DiagnosticAgentSchemaTest` that checks `instructions()` returns a non-empty string will still pass — the wiring is never verified.

**Why it happens:** The faked gateway never evaluates `instructions()` — it skips the real HTTP call. The ROADMAP success criterion 2 verifies by reading the class source.

**How to avoid:** Add a test that asserts `instructions()` does NOT contain `'TODO'`. The existing Phase 1 test only checks the string is non-empty — it does not check for the TODO text.

**Warning signs:** `grep -r "TODO" app/Ai/Agents/DiagnosticAgent.php` returns a match after Phase 2.

### Pitfall 5: `private const` vs `private static string $prompt`

**What goes wrong:** Developer uses `private static string $prompt = '...'` instead of `private const`. A static property can technically be modified via reflection; a constant cannot. Also, const is slightly clearer in intent.

**Why it happens:** Habit from properties-oriented coding.

**How to avoid:** Use `private const SYSTEM_PROMPT = '...'`. In PHP 8.2+, const may declare typed constants, but for strings the bare untyped const is fine.

### Pitfall 6: `StructuredAgentResponse` Constructor Requires `Usage` and `Meta` Objects

**What goes wrong:** If any test tries to use `new StructuredAgentResponse(...)` directly (instead of `DiagnosticAgent::fake()` or the reflection approach), it requires constructing `Usage` and `Meta` objects, which may have their own dependencies.

**Why it happens:** `StructuredAgentResponse::__construct` has signature `(string $invocationId, array $structured, string $text, Usage $usage, Meta $meta)`.

**How to avoid:** Two correct approaches:
1. **SDK-native:** `DiagnosticAgent::fake([['field' => 'value']])` — SDK generates the full response object without touching the constructor directly.
2. **Reflection (Phase 1 pattern):** `$reflection->newInstanceWithoutConstructor()` + `$instance->structured = $data` — skips constructor, works because `$structured` is a public property from `ProvidesStructuredResponse` trait.

Do NOT try `new StructuredAgentResponse(...)` — requires `Usage` and `Meta` instances that are hard to construct meaningfully in tests.

---

## Code Examples

Verified patterns from official sources:

### Agent fake() for Structured Output

```php
// Source: vendor/laravel/ai/src/Promptable.php line 239
// Source: vendor/laravel/ai/src/Gateway/FakeTextGateway.php marshalResponse() — array → StructuredTextResponse
DiagnosticAgent::fake([
    [
        'diagnosis'     => 'TS2322: Type mismatch',
        'pc3_category'  => 'Predicate',
        'feedback'      => 'Fix the assignment type.',
        'confidence'    => 0.9,
        'tokens_input'  => 100,
        'tokens_output' => 50,
    ],
]);
```

### Agent assertPrompted() with Closure

```php
// Source: vendor/laravel/ai/src/Promptable.php assertPrompted()
DiagnosticAgent::assertPrompted(function ($prompt) {
    return str_contains($prompt->prompt, '## Exercise Statement');
});
```

### Model Pinning in config/ai.php

```php
// Source: vendor/laravel/ai/src/Providers/AnthropicProvider.php line 61
// $this->config['models']['text']['default'] ?? 'claude-sonnet-4-6'
'anthropic' => [
    'driver' => 'anthropic',
    'key'    => env('ANTHROPIC_API_KEY'),
    'url'    => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
    'models' => [
        'text' => [
            'default' => 'claude-sonnet-4-20250514',
        ],
    ],
],
```

### Reading Model from Config in Caller

```php
// Source: D-09 pattern — single source of truth
$model = config('ai.providers.anthropic.models.text.default');
$response = (new DiagnosticAgent)->prompt($msg, provider: 'anthropic', model: $model);
$result = ProviderResult::fromPrismResponse($response, 'anthropic', $model, $requestId, 'v1.0');
```

### StructuredAgentResponse Reflection Stub (Phase 1 confirmed pattern)

```php
// Source: tests/Feature/Persistence/DiagnosticResultPersistenceTest.php makeResponse()
// ProvidesStructuredResponse trait exposes $structured as public property
$reflection = new ReflectionClass(StructuredAgentResponse::class);
$instance   = $reflection->newInstanceWithoutConstructor();
$instance->structured = ['diagnosis' => '...', 'pc3_category' => 'Predicate', ...];
```

### Private Const Heredoc Pattern

```php
// Source: PHP 8.1+ — heredoc in class const is supported
private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert TypeScript code reviewer...
PROMPT;
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Prompt strings in `.env` | Private const in code class | Project decision (D-04) | Reproducibility — prompt version is always tied to a commit |
| prism-php fluent builder pattern | `laravel/ai` Agent pattern | Feb 2026 (laravel/ai v0.1.0) | Class-based agents; `instructions()` method; `Agent::fake()` for testing |
| `Http::fake()` for AI calls | `Agent::fake()` | On adoption of laravel/ai | SDK-level interception; works with structured output schema |
| Hardcoded model string | `config/ai.php` `models.text.default` | D-09 project decision | Centralized pin; SDK reads it via `defaultTextModel()` |

**Deprecated/outdated in project artifacts:**
- `DiagnosticAgent::instructions()` TODO placeholder → must be replaced in Phase 2 (PROMPT-01)
- `SETUP-02` in REQUIREMENTS.md references "prism-php/prism" → actually `laravel/ai`; distinction documented in Phase 1 STATE.md decisions

---

## Open Questions

1. **Whether `DiagnosticAgent` should use `#[Provider(Lab::Anthropic)]` attribute or rely on caller-specified `provider:` argument**
   - What we know: `Promptable::getProvidersAndModels()` checks for a `#[Provider]` PHP attribute on the class first, then falls back to caller argument, then `config('ai.default')`. The SDK docs show both approaches.
   - What's unclear: Whether fixing the provider at the class level (via attribute) would conflict with Phase 3 where the same agent is called for all 4 providers.
   - Recommendation: Do NOT add `#[Provider]` to `DiagnosticAgent` — Phase 3 must dispatch it to multiple providers. The caller (`PrismStructuredCaller`) should pass `provider: 'anthropic'` explicitly.

2. **Exact token count self-reporting by Claude Sonnet 4 via `tokens_input`/`tokens_output` schema fields**
   - What we know: The schema defines `tokens_input`/`tokens_output` as LLM-self-reported integers. Anthropic models comply well with structured output schema. The `StructuredAgentResponse` also has `->usage` on `AgentResponse` parent, but field access pattern is array-based (`$response['tokens_input']`) per Phase 1 design.
   - What's unclear: Whether Claude Sonnet 4 reliably self-reports accurate token counts. For research purposes, self-reported values may systematically undercount.
   - Recommendation: Use self-reported values as designed (Phase 1 contract is locked). This is a research footnote, not a Phase 2 blocker.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | All | Yes | 8.4.1 | — |
| Composer | Package management | Yes | 2.8.3 | — |
| laravel/ai | SDK | Yes | ^0.6.3 installed | — |
| ANTHROPIC_API_KEY | Live call in tinker | Required for live test | Not verified in .env | Test uses fake() — CI-safe without key |
| SQLite | Persistence | Yes | bundled with PHP 8.4 | — |

**Missing dependencies with no fallback:** `ANTHROPIC_API_KEY` is required for the tinker success criterion (live call). Not needed for automated tests (faked). Must be in `.env` before running tinker verification.

**Missing dependencies with fallback:** None.

---

## Sources

### Primary (HIGH confidence)
- `vendor/laravel/ai/src/Providers/AnthropicProvider.php` — `defaultTextModel()` reads `config['models']['text']['default']` (line 61)
- `vendor/laravel/ai/src/Promptable.php` — `fake()` static method (line 239), `assertPrompted()`, `prompt()` with `provider:` + `model:` named args
- `vendor/laravel/ai/src/Gateway/FakeTextGateway.php` — `marshalResponse()` shows array → `StructuredTextResponse` (confirmed behavior for structured agents)
- `vendor/laravel/ai/src/Responses/StructuredAgentResponse.php` — constructor signature, `$structured` public property via `ProvidesStructuredResponse` trait
- `vendor/laravel/ai/src/Concerns/InteractsWithFakeAgents.php` — `fakeAgent()`, `assertAgentWasPrompted()` implementations
- `tests/Feature/Persistence/DiagnosticResultPersistenceTest.php` — confirmed `makeResponse()` reflection approach for `StructuredAgentResponse` stubs
- `app/Ai/Agents/DiagnosticAgent.php` — Phase 1 artifact: instructions() placeholder, schema() complete
- `app/DTOs/ProviderResult.php` — Phase 1 artifact: `fromPrismResponse()` factory signature confirmed
- `app/Repositories/DiagnosticResultRepository.php` — Phase 1 artifact: `save(ProviderResult)` confirmed
- `config/ai.php` — Anthropic provider block confirmed; `models` key absent (needs to be added)
- `SKD.md` — Official laravel/ai documentation bundled in project

### Secondary (MEDIUM confidence)
- `vendor/laravel/ai/src/Providers/Provider.php` — `formatProviderAndModelList()` confirms string `'anthropic'` is valid for `provider:` argument
- `.planning/STATE.md` — SDK decisions, FQN confirmations from Phase 1 execution

### Tertiary (LOW confidence)
- None — all critical claims verified through primary vendor source inspection.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all packages installed and verified in Phase 1
- Agent fake() testing pattern: HIGH — confirmed via direct vendor source read
- Model pin via config key: HIGH — `AnthropicProvider::defaultTextModel()` reads exact config path, confirmed in vendor
- DiagnosticPromptBuilder design: HIGH — private const pattern is pure PHP, no external dependency
- PC³ prompt wording: MEDIUM — exact token budget and wording is Claude's discretion; structure confirmed correct

**Research date:** 2026-04-27
**Valid until:** 2026-05-27 (laravel/ai is actively developed — re-verify if > 30 days)
