# Phase 3: Parallel Fan-Out - Context

**Gathered:** 2026-04-28
**Status:** Ready for planning

<domain>
## Phase Boundary

Introduce `DiagnosticService::run()` that fans out to all 4 LLM providers concurrently using
`Concurrently::run()`, isolates per-provider failures via try/catch inside each closure, persists
all successful results synchronously, and returns an array of `ProviderResult` DTOs before the
service method returns. No HTTP layer, no controller wiring — that is Phase 4.

</domain>

<decisions>
## Implementation Decisions

### Service Structure

- **D-01:** `DiagnosticService` is a new class. `PrismStructuredCaller` is refactored to be
  provider-agnostic: its constructor accepts `provider` (string) + `model` (string) +
  `DiagnosticResultRepository`. The Phase 2 ROADMAP tinker success criterion (`PrismStructuredCaller::call()`)
  is satisfied by history — the class is kept and improved, not retired.
- **D-02:** `DiagnosticService` instantiates one `PrismStructuredCaller` per provider (4 total)
  and dispatches each as a keyed closure to `Concurrently::run()`. Return type of `run()` is
  `array` — up to 4 `ProviderResult` DTOs, null-filtered.

### Parallelism

- **D-03:** Concurrency mechanism is `Concurrently::run([...])` (Laravel 11+ fiber-based concurrency).
  Each keyed closure calls its `PrismStructuredCaller::call($code, $statement, $requestId)`. Works
  directly with `laravel/ai` agent calls — no HTTP layer bypass needed.

  ```php
  $results = Concurrently::run([
      'anthropic' => fn() => $anthropicCaller->call($code, $statement, $requestId),
      'openai'    => fn() => $openaiCaller->call($code, $statement, $requestId),
      'gemini'    => fn() => $geminiCaller->call($code, $statement, $requestId),
      'deepseek'  => fn() => $deepseekCaller->call($code, $statement, $requestId),
  ]);
  ```

### Failure Isolation

- **D-04:** Per-provider exception handling lives **inside each closure** — try/catch wrapping
  the `PrismStructuredCaller::call()` call, returning `null` on any `\Throwable`. `DiagnosticService`
  filters nulls from the `Concurrently::run()` result array. One provider failing cannot affect
  the other three.

### All-Providers-Fail Behavior

- **D-05:** If all 4 closures return `null`, `DiagnosticService::run()` returns `[]` (empty array).
  No exception is thrown. The Phase 4 controller decides how to respond (e.g., HTTP 200 with empty
  array or HTTP 503). Consistent with the partial-result principle.

### DeepSeek Structured Output

- **D-06:** DeepSeek is treated uniformly — no special-casing. If `laravel/ai` returns a malformed
  or null structured response, `ProviderResult::fromPrismResponse()` will throw (bad array access
  or `Pc3Category::from()` failure), the closure catch absorbs it, and DeepSeek is omitted from
  results. Research data will reflect which providers succeeded in practice.

### Claude's Discretion

- Model strings for OpenAI, Gemini, DeepSeek — mirror the Anthropic pattern: add `models.text.default`
  to each provider block in `config/ai.php`. Use `gpt-4o`, `gemini-2.0-flash`, `deepseek-chat` as
  model strings (consistent with CLAUDE.md Provider Support Matrix).
- Whether to emit a `Log::warning()` when a provider closure returns null — reasonable for debugging
- Return type annotation for `DiagnosticService::run()` — `array` (no wrapper needed for MVP)
- Test file location — follow Phase 2 pattern: `tests/Feature/Services/DiagnosticServiceTest.php`

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — API-02 (parallel dispatch), API-03 (partial results), PERSIST-02 (synchronous save)
- `.planning/ROADMAP.md` §Phase 3 — 3 success criteria (wall-clock time ≈ slowest provider; partial results on key failure; all results in DB before run() returns)

### Existing Phase 2 Artifacts
- `app/Services/PrismStructuredCaller.php` — will be refactored (constructor gains `provider` + `model`); current Anthropic-only call pattern is the reference
- `app/Repositories/DiagnosticResultRepository.php` — `save(ProviderResult)` called inside each closure before returning the DTO
- `app/Ai/Agents/DiagnosticAgent.php` — provider-agnostic by design; `prompt($msg, provider: X, model: Y)` pattern established

### Configuration
- `config/ai.php` — all 4 provider blocks present; `models.text.default` must be added to `openai`, `gemini`, and `deepseek` blocks (Anthropic already has it)

### No external specs
- All implementation detail is in the files above and Laravel 11+ `Concurrently` docs

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `PrismStructuredCaller` — single-provider call unit; refactor constructor to accept provider+model, rest of call logic unchanged
- `DiagnosticResultRepository::save()` — already ready; called once per provider inside closure before returning ProviderResult
- `DiagnosticAgent` — provider-agnostic, no `#[Provider]` attribute, designed for Phase 3 multi-provider dispatch

### Established Patterns
- `declare(strict_types=1)` + `final class` + Pest tests (all phases)
- HTTP fakes for tests — no real API calls in test suite
- `config('ai.providers.{provider}.models.text.default')` for model string lookup (established in PrismStructuredCaller)
- `ProviderResult::fromPrismResponse($response, provider: X, model: Y, requestId: Z, promptVersion: W)` factory signature

### Integration Points
- New: `app/Services/DiagnosticService.php` — entry point named in ROADMAP success criteria
- Refactor: `app/Services/PrismStructuredCaller.php` — constructor gains `provider` + `model`; `call()` signature stays the same
- Config: `config/ai.php` — add `models.text.default` to `openai`, `gemini`, `deepseek` blocks

</code_context>

<specifics>
## Specific Ideas

- ROADMAP success criterion 1 explicitly tests wall-clock time: "approximately equal to the slowest provider, not the sum of all four" — Concurrently::run() must be genuine concurrent I/O, not sequential
- ROADMAP success criterion 3: "All successful results are present in the `diagnostic_results` table **before** DiagnosticService::run() returns" — repository::save() inside each closure (not deferred), confirmed by querying DB immediately after the call

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-parallel-fan-out*
*Context gathered: 2026-04-28*
