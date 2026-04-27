# Pitfalls Research: Laravel AI Broker

**Domain:** Multi-LLM broker — Laravel 12, prism-php, Anthropic + OpenAI + Gemini + DeepSeek
**Researched:** 2026-04-27
**Overall confidence:** HIGH (GitHub issues + official docs + prism-php source verified)

---

## Critical Pitfalls (will break the project)

---

### CRITICAL-1: DeepSeek Structured Output Is Not Natively Supported in prism-php

**What goes wrong:**
prism-php does not implement a native structured output path for DeepSeek. Issue #136 in the prism-php repo ("DeepSeek structured output") was filed as an enhancement request and closed as "planned / help wanted" — not implemented. If you call `Prism::structured()` with the DeepSeek provider, you will get either a silent JSON-mode fallback or a decoding failure. A separate streaming bug (issue #936) shows DeepSeek's tool-call arguments coming back with control characters that cause `JsonException` in prism's `ToolCall::arguments()`.

**Warning signs:**
- `PrismStructuredDecodingException` on DeepSeek calls
- `JsonException: Control character error` when using tool calls with DeepSeek
- Structured response returns `null` or raw text instead of a PHP array

**Prevention:**
1. Use DeepSeek's JSON mode explicitly: set `response_format` to `json_object` via `withProviderOptions(['response_format' => ['type' => 'json_object']])`.
2. The word "json" **must appear in your system prompt** — DeepSeek's API returns an error if it doesn't ("Prompt must contain the word 'json'").
3. Parse the raw text response manually with `json_decode($response->text, true)` and validate it instead of relying on `$response->structured`.
4. Add a `json_validate()` guard (PHP 8.3+) before persisting.

**Phase:** Setup / provider integration phase (day 1 blocker).

---

### CRITICAL-2: Anthropic Silently Strips `minimum`/`maximum` from `NumberSchema` — The `confidence` Field Will Not Be Range-Validated

**What goes wrong:**
Anthropic's native structured outputs do not support JSON Schema numeric constraints (`minimum`, `maximum`, `multipleOf`). When prism-php sends an `ObjectSchema` that includes a `NumberSchema` with `minimum: 0.0` and `maximum: 1.0` for the `confidence` field, Anthropic's PHP SDK (and prism by extension) silently removes those constraints from the schema sent to the API. The model still generates a number, but nothing enforces it is in the 0–1 range. A model responding `1.5` or `99` will pass the API call and be persisted without error.

**Warning signs:**
- `confidence` values outside [0, 1] appearing in `DiagnosticResult` rows
- No API error is raised — the call succeeds

**Prevention:**
1. After `$response->structured` is returned, add an explicit PHP validation:
   ```php
   $confidence = $result['confidence'] ?? null;
   if (!is_float($confidence) && !is_int($confidence)) { /* reject */ }
   $confidence = max(0.0, min(1.0, (float) $confidence));
   ```
2. Add the constraint to the schema description text: `new NumberSchema('confidence', 'Self-reported confidence 0.0–1.0. MUST be between 0 and 1.')` — the Anthropic SDK moves unsupported constraints into the description automatically.
3. Do not rely on schema-level enforcement for numeric ranges across any provider.

**Phase:** Schema design phase. Enforce in the response-parsing layer.

---

### CRITICAL-3: Claude Sonnet 4.5 Structured Decoding Bug in prism-php

**What goes wrong:**
prism-php issue #645 documents that `claude-sonnet-4-5-20250929` causes `"Structured object could not be decoded"` even when the API returns valid JSON matching the schema. The bug is a compatibility issue between prism's decoding layer and how that specific model version wraps output in markdown code fences. The older `claude-sonnet-4-20250514` works correctly.

**Warning signs:**
- `PrismStructuredDecodingException: Structured object could not be decoded. Received: ```json...`
- The raw `$response->text` contains valid JSON wrapped in triple-backtick code fences

**Prevention:**
1. Pin the Anthropic model to a version known to work. At time of research, `claude-sonnet-4-20250514` (Sonnet 4) is the safe choice. Check prism-php releases before upgrading.
2. If you must use a newer model, add a fallback parser that strips markdown code fences from `$response->text` before `json_decode`.
3. Track prism-php releases; this is the kind of bug that gets patched quickly.

**Phase:** Setup / model selection. Verify with an integration test on actual API before committing to a model string.

---

### CRITICAL-4: Laravel `Concurrency::run` Process Driver Cannot Serialize Closures That Capture `$this`

**What goes wrong:**
The default `process` driver for `Concurrency::run` (Laravel 12) achieves concurrency by serializing closures to child Artisan processes. Closures that capture `$this` (i.e., defined inside a controller or service method as `fn() => $this->callProvider(...)`) fail with a `SerializableClosure` type error. Framework issue #55219 confirms this is a known bug as of Laravel 12.4.1.

Additionally, the `fork` driver (the faster alternative) only works in CLI context — it will fail during HTTP request handling because PHP does not support `pcntl_fork` in FPM/Apache contexts.

**Warning signs:**
- `Cannot assign Laravel\SerializableClosure\Serializers\Native to property ... of type Closure|array`
- The `fork` driver working in `artisan tinker` but crashing in `POST /api/diagnose`

**Prevention:**
- **Recommended approach**: Use Laravel's HTTP client pool (`Http::pool()`) instead of `Concurrency::run` for parallelising outbound API calls. The pool approach uses Guzzle promises under the hood, works in all PHP contexts, and does not rely on process serialization:
  ```php
  $responses = Http::pool(fn (Pool $pool) => [
      $pool->as('anthropic')->post(...),
      $pool->as('openai')->post(...),
      $pool->as('gemini')->post(...),
      $pool->as('deepseek')->post(...),
  ]);
  ```
- If `Concurrency::run` is preferred, use only static/free closures (no `$this`), or extract provider calls into static helper methods.

**Phase:** Parallelism implementation — the project requirement of "all 4 providers concurrent" depends on getting this right.

---

### CRITICAL-5: Anthropic Does Not Support `AnyOfSchema` — Cross-Provider Schema Must Avoid It

**What goes wrong:**
If the `pc3_category` field uses `AnyOfSchema` (e.g., allowing either an enum value or null), the call will work for OpenAI and Gemini (both support AnyOf) but will silently or loudly fail for Anthropic. prism-php documents this explicitly: Anthropic does not support `AnyOfSchema`.

**Warning signs:**
- Anthropic provider returns a 400 or decoding error while the other three providers succeed
- Null/optional union fields work on OpenAI but not on Anthropic

**Prevention:**
- Use `EnumSchema` (not `AnyOfSchema`) for `pc3_category` with fixed values `['Predicate', 'Concept', 'Context']`.
- Make all schema fields required (`requiredFields: [...]`) — do not rely on optional/nullable via AnyOf.
- This is consistent with OpenAI strict mode which also requires all fields to be required (with nullable rather than optional).

**Phase:** Schema design. Must be resolved before writing the shared schema class.

---

## Common Mistakes (reduce quality)

---

### MISTAKE-1: OpenAI Strict Mode Rejects Non-Object Root Schema

**What goes wrong:** If the structured output root is not an `ObjectSchema` (e.g., an `ArraySchema` or a bare `StringSchema`), OpenAI in strict mode returns a validation error. The project's schema is correctly an object, but wrapping multiple provider results in an array at the top level would break OpenAI.

**Prevention:** Always root the schema in `ObjectSchema`. Return an object `{ provider, model, diagnosis, pc3_category, feedback, confidence, tokens_input, tokens_output }` per call — one call per provider, not one call returning all four.

---

### MISTAKE-2: Missing `maxSteps` When Mixing Tools and Structured Output

**What goes wrong:** If any future prompt enhancement adds tool calling alongside structured output (e.g., for retrieval), `maxSteps` must be set to at least 2 or the structured response step never executes. The default is 1.

**Prevention:** Always set `->withMaxSteps(2)` if tools are added. This is a silent failure — the call succeeds but `$response->structured` is null.

---

### MISTAKE-3: Token Usage Fields May Be Zero or Null for DeepSeek

**What goes wrong:** The `tokens_input` and `tokens_output` fields are populated from `$response->usage->promptTokens` and `$response->usage->completionTokens`. For DeepSeek, if structured output is falling back to raw text parsing, the usage object may not be populated by prism's DeepSeek adapter. Persisting zero tokens for a DeepSeek result corrupts the research corpus.

**Prevention:** Always null-check before persisting: `$response->usage?->promptTokens ?? 0`. Log a warning if both are 0 on a non-error response.

---

### MISTAKE-4: Assuming `$response->structured` Is Always a PHP Array

**What goes wrong:** Prism throws `PrismStructuredDecodingException` on parse failure, but if you catch the exception at the wrong level (per-provider vs. global), you may persist `null` or a partial object to `DiagnosticResult`. Since partial results are the correct behavior for failed providers, catching too broadly can silence real structured-output bugs.

**Prevention:** Catch at the per-provider level inside each concurrent closure. Log the exception and the raw `$response->text` at `warning` level. Return `null` for that provider slot, not an empty array.

---

### MISTAKE-5: `config:cache` in Production Breaks `env()` Calls Outside Config Files

**What goes wrong:** Laravel caches config at deploy time. Any `env('ANTHROPIC_API_KEY')` call made directly in service code (rather than in `config/prism.php` or `config/ai.php`) returns `null` after caching. This is a standard Laravel trap but is common when copying provider setup code from tutorials.

**Prevention:** All API keys must be read in config files only. Service code reads `config('prism.providers.anthropic.api_key')`, never `env()`.

---

### MISTAKE-6: Gemini Thinking Mode Breaks on Non-2.5 Models

**What goes wrong:** If the prompt asks Gemini for reasoning steps or `thinkingBudget` is set on Gemini 2.0 or earlier models, the request fails. Since the project uses Gemini for structured output only, this is a risk if the model string is misconfigured.

**Prevention:** Use `gemini-2.0-flash` or `gemini-2.5-flash` and never set `thinkingBudget` unless intentional. Do not use `gemini-1.x` series.

---

### MISTAKE-7: Forgetting to Set `additionalProperties: false` for Anthropic

**What goes wrong:** Anthropic's native structured output requires `additionalProperties: false` on every object schema. prism-php's `ObjectSchema` should handle this automatically, but if you manually construct raw JSON schemas or use `withProviderOptions` to pass a raw schema, forgetting this causes a 400.

**Prevention:** Always use prism's schema classes rather than raw JSON. If you must use raw schemas, add `additionalProperties: false` to every object.

---

## Provider-Specific Quirks

---

### Anthropic (Claude)

| Quirk | Detail |
|-------|--------|
| Native structured output model minimum | Requires Claude Sonnet 4.5+ (`claude-sonnet-4-20250514` or later). Older models fall back to tool-call emulation. |
| Numeric constraints silently dropped | `minimum`, `maximum`, `minLength`, `maxLength` are removed from the schema; constraints must be enforced in PHP post-processing. |
| Recursive schemas trigger 400 | "Too many recursive definitions in schema" — keep the schema flat. |
| Optional parameters hard cap | Maximum 24 optional parameters total across all schemas in a request. The 6-field diagnostic schema is well within limit. |
| Property output order | Required fields always output first regardless of definition order — plan JSON parsing accordingly. |
| `stop_reason: "refusal"` returns HTTP 200 | A refusal is not an exception; the response is a 200 with `$response->structured` potentially not matching the schema. Check `$response->finishReason`. |
| System prompt API | Anthropic uses a separate `system` property, not a message with `role: system`. Always use `->withSystemPrompt()` in prism, never inject a system message into `withMessages()`. |

---

### OpenAI (GPT-4o)

| Quirk | Detail |
|-------|--------|
| Root schema must be ObjectSchema | Strict mode rejects any other root type. |
| All fields required in strict mode | Optional fields must be marked `required` with `nullable: true`, not omitted from `requiredFields`. |
| AnyOfSchema supported | Safe to use if needed, but Anthropic won't accept the same schema — keep a unified schema that avoids AnyOf. |
| Reasoning tokens count discrepancy | Azure-hosted OpenAI returns `usage.output_tokens_details.reasoning_tokens` (plural) vs. standard OpenAI's singular — not relevant for direct API use but worth knowing if deployment changes. |

---

### Google Gemini

| Quirk | Detail |
|-------|--------|
| AnyOfSchema requires Gemini 2.5+ | Don't use AnyOf with Gemini 2.0 Flash or earlier. |
| `response_schema` is Gemini's parameter | prism abstracts this, but if you ever inspect the raw request, it differs from OpenAI's `response_format`. |
| Nullable required fields | Fields that can be null must still be listed in `requiredFields` — include them, just allow null values in the schema type. |
| Thinking budget incompatibility | Do not set `thinkingBudget` on Gemini 2.0 or prior series — request will fail. |
| Empty response on policy violation | If the prompt triggers Gemini's person generation policy, the response is empty and prism raises an exception rather than returning a parseable error. |
| `confidence` float schema | Gemini supports `minimum`/`maximum` numeric constraints (unlike Anthropic) since November 2025 JSON Schema update — you can enforce the range schema-side for Gemini. |

---

### DeepSeek

| Quirk | Detail |
|-------|--------|
| No native structured output in prism-php | Issue #136 is planned but not shipped. Use JSON mode + manual parsing as described in CRITICAL-1. |
| Prompt must contain the word "json" | Hard requirement for `response_format: {type: json_object}`. Include it in the system prompt string. |
| Control characters in streaming tool calls | Issue #936: DeepSeek's streaming tool-call arguments may contain unescaped control characters causing `JsonException`. Avoid streaming; use non-streaming calls. |
| OpenAI compatibility is shallow | The chat completions endpoint is compatible, but: (a) strict `tool_choice` schemas cause 422 errors, (b) reasoning/thinking tokens are in a separate field, (c) model names `deepseek-chat` and `deepseek-reasoner` are deprecated as of 2026-07-24 — use `deepseek-v4-flash` or `deepseek-v4-pro`. |
| Occasional empty responses | DeepSeek's own docs acknowledge "the API may occasionally return empty content." Build a retry or fallback for this. |
| `response_format` must match prompt | Setting `json_object` without "json" in the prompt causes a 422 error, not a parsing failure. |
| API reliability during peak hours | DeepSeek's API has had documented reliability issues during high-traffic periods — for research use this is tolerable, but timeouts should be set generously. |

---

## Git / Setup Traps

---

### GIT-1: Force-Push Orphan `main` Destroys Remote History Permanently

**What goes wrong:** The plan calls for `git checkout --orphan main && git push --force origin main` to reset the repo. This permanently rewrites the remote `main` branch. If a collaborator has cloned it, their local `main` will diverge with no common ancestor. If the `legacy/v1` branch was not pushed before the force-push, the old code is gone.

**Prevention order:**
1. Push the old code to `legacy/v1` first and verify it exists on the remote (`git push origin HEAD:legacy/v1`).
2. Confirm `legacy/v1` is visible on the remote before touching `main`.
3. Only then create the orphan branch and force-push `main`.
4. Never force-push `main` again after the fresh install commit.

---

### GIT-2: `vendor/` and `composer.lock` Left Behind When Switching Between Old and New Branch

**What goes wrong:** The old codebase and the new Laravel 12 install have incompatible `vendor/` trees. If you switch branches without clearing `vendor/`, PHP will autoload the wrong classes, causing cryptic class-not-found errors or silent method mismatches. Composer will also refuse to install the new dependencies if `composer.lock` conflicts.

**Prevention:**
```bash
rm -rf vendor/ composer.lock
composer install
```
Run this after every branch switch between the legacy code and the fresh install branch.

---

### GIT-3: `.env` Not Copied After Fresh Laravel Install — `APP_KEY` Missing

**What goes wrong:** A fresh `composer create-project laravel/laravel` or orphan branch setup does not include `.env`. Running any artisan command before `cp .env.example .env && php artisan key:generate` causes `No application encryption key` panic and prevents the app from booting. Under `Concurrency::run` (which spawns child artisan processes), a missing key in the child's environment causes the child to fail silently.

**Prevention:**
1. First artisan command after any fresh install must be `php artisan key:generate`.
2. Verify `.env` exists before running any test or server command.
3. Add an early boot check: `abort_if(empty(config('app.key')), 500, 'APP_KEY not set')`.

---

### GIT-4: Config Key Naming Conflicts Between prism-php and Laravel's Native AI Package

**What goes wrong:** Laravel 12 ships with a new first-party `laravel/ai` package (separate from prism-php). Both packages publish config files. `laravel/ai` publishes `config/ai.php` and uses env vars like `OPENAI_API_KEY`. prism-php publishes `config/prism.php` and uses `PRISM_ANTHROPIC_API_KEY` (or similar provider-prefixed names depending on version). If both packages are installed, or if tutorials for one are applied to the other, the keys will be read from the wrong config.

**Prevention:**
1. Use only prism-php as the AI SDK (as planned). Do not install `laravel/ai` alongside it.
2. Check the exact env var names prism-php expects by running `php artisan vendor:publish --tag=prism-config` and inspecting the published file.
3. Never use `env('OPENAI_API_KEY')` directly in code — always use `config('prism.providers.openai.api_key')` or equivalent.

---

### GIT-5: prism-php v0.100 Breaking Change — Structured Output Uses Native Mode by Default

**What goes wrong:** Projects built on prism-php before v0.100 (released March 2025) used prompt-based JSON mode as the default for structured output. After v0.100, native structured output is the default for providers that support it. Any schema that was being converted to a JSON prompt instruction silently is now being sent as a native schema, which can trigger API errors if the schema uses unsupported features (e.g., Anthropic and `minimum`/`maximum` constraints, or recursive references).

**Warning signs:** Structured output calls that worked before upgrading prism-php now return 400 errors from the provider.

**Prevention:**
1. Pin prism-php to a specific version in `composer.json` (`"prism-php/prism": "^0.100"`) and read the release notes before `composer update`.
2. After any prism-php version bump, run a full integration test against all four providers before deploying.

---

### GIT-6: Laravel Concurrency Child Processes Spawn New Artisan Instances — Config Cache Must Match

**What goes wrong:** `Concurrency::run` with the `process` driver spawns child `artisan` processes. If `php artisan config:cache` has been run and then `.env` is modified without re-caching, the child processes read the stale cache while the parent reads the live `.env`. This creates a split-brain: parent says provider X is configured, child says it isn't.

**Prevention:**
- In local development: do not use `config:cache`. Use `php artisan config:clear` if it was ever cached.
- In production: after any `.env` change, immediately run `php artisan config:cache` before the next request.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|----------------|------------|
| Git reset to orphan `main` | GIT-1: force-push destroys remote history before `legacy/v1` is saved | Push `legacy/v1` first, verify, then force-push |
| prism-php installation | GIT-5: v0.100 native structured output default; version compatibility | Pin version; read release notes |
| Schema design | CRITICAL-2: Anthropic strips numeric constraints; CRITICAL-5: AnyOf unsupported | Flat ObjectSchema only, all fields required, no AnyOf |
| DeepSeek integration | CRITICAL-1: no native structured output; JSON mode quirks | JSON mode + manual parse + "json" in prompt |
| Parallelism | CRITICAL-4: Concurrency process driver fails with `$this` closures | Use `Http::pool()` or static closures |
| Claude model selection | CRITICAL-3: Sonnet 4.5 decoding bug | Pin to `claude-sonnet-4-20250514` until prism patch |
| Response parsing | MISTAKE-4: exception scope too broad | Catch per-provider; log raw text on failure |
| Config / env | GIT-3: missing APP_KEY; MISTAKE-5: env() in service code | key:generate first; config() calls only |
| Token persistence | MISTAKE-3: DeepSeek usage fields may be zero | Null-check usage; log zero-token warnings |

---

## Sources

- prism-php structured output docs: https://prismphp.com/core-concepts/structured-output/
- prism-php schema docs: https://prismphp.com/core-concepts/schemas/
- prism-php Anthropic provider docs: https://prismphp.com/providers/anthropic/
- prism-php Gemini provider docs: https://prismphp.com/providers/gemini/
- prism-php releases (v0.100 breaking change): https://github.com/prism-php/prism/releases
- prism-php issue #136 (DeepSeek structured output): https://github.com/prism-php/prism/issues/136
- prism-php issue #645 (Claude Sonnet 4.5 decoding bug): https://github.com/prism-php/prism/issues/645
- prism-php issue #936 (DeepSeek control character JsonException): https://github.com/prism-php/prism/issues/936
- prism-php issue #500 (withProviderTools incompatibility): https://github.com/prism-php/prism/issues/500
- Laravel Concurrency docs (Laravel 12): https://laravel.com/docs/12.x/concurrency
- Laravel framework issue #55219 (closure binding serialization bug): https://github.com/laravel/framework/issues/55219
- Anthropic structured outputs official docs: https://platform.claude.com/docs/en/build-with-claude/structured-outputs
- DeepSeek JSON mode docs: https://api-docs.deepseek.com/guides/json_mode
- DeepSeek V3 structured output issue (LangChain): https://github.com/deepseek-ai/DeepSeek-V3/issues/302
- Structured output cross-provider comparison (Oct 2025): https://www.glukhov.org/post/2025/10/structured-output-comparison-popular-llm-providers
