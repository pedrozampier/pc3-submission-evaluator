# Project Research Summary

**Project:** TCC Laravel AI Broker — MVP (pc3-submission-evaluator)
**Domain:** Multi-LLM comparison REST API for academic research (PC³ taxonomy)
**Researched:** 2026-04-27
**Confidence:** HIGH

---

## Executive Summary

This project is a Laravel 12 REST API that acts as an LLM broker: it receives a buggy TypeScript code snippet and an exercise statement, fans out the same structured prompt to four providers (Claude, GPT-4o, Gemini, DeepSeek) in parallel, and returns a typed diagnostic array that is simultaneously persisted as the research corpus. Because the database rows are the thesis dataset, every architectural and feature decision is evaluated against research reproducibility — not against typical SaaS concerns like user experience or traffic scale. The recommended build approach follows a strict dependency order: define DTOs and the shared Prism schema first, wire persistence second, prove a single-provider call third, then add concurrency — keeping the debugging surface minimal at each step.

The unambiguous SDK choice is `prism-php/prism` (not `laravel/ai`). The `laravel/ai` package wraps Prism internally, optimises for the Agent class pattern rather than parallel fan-out, and does not list DeepSeek structured output in its feature matrix. Prism's fluent builder maps directly onto the per-provider, per-call pattern this broker requires. SQLite is correct for the MVP: one writer, zero infrastructure overhead, and the entire corpus ships as a single file — a reproducibility feature in itself.

The dominant risk class is provider-specific structured output incompatibility. DeepSeek has no native structured output in prism-php (issue #136, status: planned); the mitigation is JSON mode with manual parsing and the word "json" required in the system prompt. Anthropic silently strips numeric constraints (`minimum`/`maximum`) from `NumberSchema`, so the `confidence` field must be clamped in PHP after the call. A bug in prism-php (issue #645) causes `claude-sonnet-4-5-20250929` to raise a decoding exception even on valid JSON — the safe model pin is `claude-sonnet-4-20250514`. Finally, `Concurrency::run` with the `process` driver fails when closures capture `$this` (framework issue #55219); use `Http::pool()` instead.

---

## Key Findings

### Recommended Stack

`prism-php/prism ^0.100` is the only package with confirmed first-party support for all four required providers under a single fluent interface. It installs as a single composer package with no per-provider dependencies. Version `^0.100` introduced GA native structured output for Anthropic and is the minimum safe version. SQLite is the default in Laravel 12 and requires zero configuration; at research scale (sequential HTTP requests, one writer) it is strictly correct. No additional infrastructure packages are needed for the MVP.

**Core technologies:**
- `laravel/framework ^12.0`: Core framework — project requirement; PHP 8.2+, ships with SQLite default and `Http::pool()` for parallel HTTP
- `prism-php/prism ^0.100`: Unified LLM interface — only package with first-party Anthropic, OpenAI, Gemini, and DeepSeek support; fluent builder maps cleanly to per-provider parallel calls
- SQLite 3.x (native PHP ext): Persistence — zero-config, single-file corpus, trivially backed up; correct for single-writer research tool
- `Illuminate\Support\Facades\Http` (pool): Concurrency — Guzzle multi-cURL, works in web request context, no subprocess serialization overhead

**Do not install:** `laravel/ai` (wraps Prism, Agent model not suited to parallel fan-out, DeepSeek structured output absent from feature matrix), individual provider SDKs (defeats broker abstraction), MySQL (unnecessary for this workload).

### Expected Features

**Must have (table stakes) — research corpus is invalid without these:**
- Shared `ObjectSchema` passed to all four providers — cross-provider comparison requires an identical schema contract
- `provider` + exact `model` string on every result row — without a pinned model ID the corpus is not reproducible
- `tokens_input` + `tokens_output` per result — required for cost and prompt-efficiency analysis
- `pc3_category` as an `EnumSchema` with values `['Predicate', 'Concept', 'Context']` — free-text invalidates inter-provider agreement analysis
- `diagnosis`, `feedback` as TEXT columns — qualitative output; use TEXT not VARCHAR
- `confidence` as a float (0.0–1.0) — self-reported; persist as-is, clamp in PHP (Anthropic strips schema constraints)
- Parallel dispatch via `Http::pool()` — sequential dispatch introduces latency ordering bias
- Partial result on provider failure — HTTP 200 with partial array; losing 3/4 corpus for one failure is unacceptable
- Synchronous persistence before response — the DB is the thesis dataset; fire-and-forget has no failure guarantee
- `request_id` (UUID) on every row — groups all four provider results from one submission
- `prompt_version` string column — the system prompt is a controlled variable; if it changes mid-study, pre/post data are from different experiments

**Should have (differentiators — strengthen thesis):**
- `latency_ms` per result — standard LLM observability metric; directly citeable comparison dimension
- `finish_reason` field — distinguishes normal completion from max-token truncation
- `raw_response` JSON column (config-toggled) — enables re-analysis if the schema is refined later
- `exercise_hash` SHA-256 of `code + statement` — enables grouping by unique input pair
- `provider_agreement_count` (1–4) — counts providers agreeing on `pc3_category`; directly citeable in thesis
- HTTP 206 when `count(results) < 4` — standard partial-content semantics, zero extra work

**Defer (out of scope for MVP):**
- Authentication, frontend, retry logic, rate limiting, LLM response caching, streaming, cost calculation, webhook/async callbacks

### Architecture Approach

The broker follows a strict layered design: a thin controller delegates entirely to a `DiagnosticService` orchestrator, which fans out to four isolated `PrismStructuredCaller` instances concurrently, collects `ProviderResult` DTOs, persists them via `DiagnosticResultRepository`, and returns the array. No business logic lives in the controller; no persistence logic lives in the service; no concurrency logic lives in the caller. The system prompt is a private constant in `DiagnosticPromptBuilder` (business logic, not environment config). The shared Prism schema is built once per request and passed to all four provider calls.

**Major components:**
1. `DiagnoseController` — HTTP validation, DTO construction, JSON response; knows nothing about providers or persistence
2. `DiagnosticService` — concurrency orchestration (`Http::pool()`), result collection/filtering, coordinates prompt builder and repository
3. `DiagnosticPromptBuilder` — PC³ system prompt and user prompt assembly; owns the `ObjectSchema` definition
4. `PrismStructuredCaller` — single provider call via Prism SDK, full `try/catch` error isolation, returns `ProviderResult|null`
5. `DiagnosticResultRepository` — Eloquent persistence wrapper; `DiagnosticResult::create()` per successful result
6. `DiagnoseRequest` / `ProviderResult` DTOs — input/output shapes; must be fixed before any other component is written

### Critical Pitfalls

1. **DeepSeek has no native structured output in prism-php (CRITICAL-1)** — `Prism::structured()` will silently fall back or throw `JsonException`. Mitigation: use `withProviderOptions(['response_format' => ['type' => 'json_object']])`, include "json" in the system prompt, parse `$response->text` manually with `json_decode`, validate with `json_validate()` before persisting.

2. **Anthropic silently strips `minimum`/`maximum` from `NumberSchema` (CRITICAL-2)** — the `confidence` field range [0, 1] is not enforced at the API level. Mitigation: clamp in PHP after response (`max(0.0, min(1.0, (float) $result['confidence']))`); embed constraint in the schema description text.

3. **`claude-sonnet-4-5-20250929` causes `PrismStructuredDecodingException` (CRITICAL-3)** — the model wraps output in markdown code fences that prism's decoder cannot parse (issue #645). Mitigation: pin Anthropic model to `claude-sonnet-4-20250514`.

4. **`Concurrency::run` process driver fails with `$this`-capturing closures (CRITICAL-4)** — framework issue #55219; child process serialization fails on bound closures. Mitigation: use `Http::pool()` for parallel dispatch instead.

5. **`AnyOfSchema` is unsupported by Anthropic (CRITICAL-5)** — the shared schema must use `EnumSchema` for `pc3_category`. All fields must be in `requiredFields`; AnyOf breaks Anthropic silently while succeeding for others.

---

## Implications for Roadmap

Research establishes a clear dependency chain: DTOs and the schema must exist before anything else can be written or tested. Persistence can be built and verified in isolation via `tinker` before the HTTP layer exists. A single working provider call must be confirmed before concurrency is added. The controller is a thin shell and comes last.

### Phase 1: Foundation — DTOs, Schema, and Persistence

**Rationale:** Every other component depends on the shape of `DiagnoseRequest`, `ProviderResult`, and the Prism `ObjectSchema` being fixed. The migration and model can be built immediately from the DTO fields and tested with `tinker` before any provider is called.

**Delivers:** `DiagnoseRequest` DTO, `ProviderResult` DTO, shared `ObjectSchema` (with `EnumSchema` for `pc3_category`, all fields required), `DiagnosticResult` migration and Eloquent model, `DiagnosticResultRepository`.

**Addresses:** All table-stakes schema fields (`provider`, `model`, `pc3_category`, `diagnosis`, `feedback`, `confidence`, `tokens_input`, `tokens_output`, `request_id`, `prompt_version`, `created_at`).

**Avoids:** CRITICAL-5 (AnyOf) — schema uses `EnumSchema` from the start; CRITICAL-2 (numeric constraints) — PHP clamping planned from day one.

**Research flag:** Standard Laravel patterns — no phase research needed.

---

### Phase 2: Single-Provider Prism Integration (Anthropic First)

**Rationale:** Proves the SDK is wired correctly before adding concurrency complexity. Anthropic is recommended first because it has native structured output (constrained decoding) — least likely to return malformed JSON.

**Delivers:** `prism-php/prism` installed and configured for Anthropic; `PrismStructuredCaller::call()` for a single provider; integration test confirming schema compliance against the real API; `DiagnosticPromptBuilder` with PC³ system prompt.

**Avoids:** CRITICAL-3 (Sonnet 4.5 decoding bug) — pin to `claude-sonnet-4-20250514`; GIT-5 (v0.100 breaking change) — pin `"prism-php/prism": "^0.100"`.

**Research flag:** Live integration test against Anthropic API required — do not assume structured output works without a real call.

---

### Phase 3: Parallel Fan-Out (All 4 Providers)

**Rationale:** Concurrency only makes sense once a single call is working. This phase adds the remaining three providers, wires `Http::pool()` for parallel dispatch, and validates that total latency ≈ slowest provider.

**Delivers:** `DiagnosticService::run()` with `Http::pool()` dispatching to all four providers concurrently; error isolation per provider; partial result collection; DeepSeek JSON-mode workaround; token usage null-check for DeepSeek.

**Avoids:** CRITICAL-1 (DeepSeek structured output); CRITICAL-4 (`$this` serialization); MISTAKE-3 (zero tokens for DeepSeek); MISTAKE-4 (exception scope too broad).

**Research flag:** Live integration tests against all four providers required. DeepSeek `withProviderOptions` key must be verified against the real API.

---

### Phase 4: HTTP Layer and Differentiating Fields

**Rationale:** The controller is a thin shell that only makes sense once the service works end-to-end. Differentiating fields are additive and do not break existing rows.

**Delivers:** `DiagnoseController::store()` with FormRequest validation; `POST /api/diagnose` route; HTTP 200/206 response logic; `latency_ms`; `finish_reason`; `exercise_hash`; `provider_agreement_count`; optional `raw_response` JSON column; provider error detail in `errors` array.

**Avoids:** MISTAKE-5 (env() in service code); GIT-3 (missing APP_KEY); GIT-6 (stale config cache).

**Research flag:** Standard Laravel patterns — no phase research needed.

---

### Phase Ordering Rationale

- DTOs and schema first: the contract must be stable before any callers are written
- Persistence before HTTP layer: enables `tinker`-driven testing of the full DB round-trip before introducing HTTP validation complexity
- Single-provider before multi-provider: adding concurrency before the single-call pattern is verified multiplies debugging surface area by 4
- Anthropic before DeepSeek: most reliable structured output path; DeepSeek requires manual JSON-mode workaround easier to develop in isolation
- Controller last: a thin shell should not be built until the service it delegates to is fully tested

### Research Flags

Phases needing live integration testing (not additional research) during execution:
- **Phase 2:** Live Anthropic API call required to confirm structured output schema compliance and model pin correctness
- **Phase 3:** Live calls to all four providers; DeepSeek JSON-mode `withProviderOptions` key must be verified

Phases with standard patterns (no additional research needed):
- **Phase 1:** Standard Laravel migration, Eloquent model, DTO patterns
- **Phase 4:** Standard Laravel controller, FormRequest, route registration

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | prism-php Laravel 12 compat confirmed via composer.json; Gemini first-party via prismphp.com; Anthropic GA structured output via v0.100.0 release notes |
| Features | HIGH (table stakes) / MEDIUM (differentiators) | Table stakes derived from research reproducibility requirements. Differentiators from standard LLM observability literature |
| Architecture | HIGH | Component boundaries and build order from official Laravel and prism-php docs. `Http::pool()` verified against Laravel concurrency docs |
| Pitfalls | HIGH | All 5 critical pitfalls traced to confirmed GitHub issues or official provider docs |

**Overall confidence:** HIGH

### Gaps to Address

- **DeepSeek JSON mode in practice:** The workaround is confirmed at the API level but not tested end-to-end with prism-php. Validate with a real API call in Phase 3.
- **`Http::pool()` + Prism internal Guzzle client:** Validate true parallelism by timing in Phase 3 (total wall-clock ≈ slowest provider, not sum).
- **DeepSeek model name:** `deepseek-chat` deprecated as of 2026-07-24 — not yet in effect (today is 2026-04-27); verify canonical model name at Phase 3.
- **`confidence` calibration:** LLM self-reported confidence has documented r=−0.40 inverse correlation with accuracy. Persist as-is; document limitation in thesis methodology.

---

## Sources

### Primary (HIGH confidence)
- [prism-php/prism GitHub](https://github.com/prism-php/prism) — composer.json compatibility, releases, issues #136, #645, #936
- [prismphp.com — Structured Output](https://prismphp.com/core-concepts/structured-output/) — schema API, per-provider behavior
- [prismphp.com — Gemini Provider](https://prismphp.com/providers/gemini/) — first-party provider confirmation
- [Laravel 12 Concurrency docs](https://laravel.com/docs/12.x/concurrency) — `process` vs `fork` driver, web context restrictions
- [laravel/framework issue #55219](https://github.com/laravel/framework/issues/55219) — `Concurrency::run` closure serialization bug
- [Anthropic structured outputs docs](https://platform.claude.com/docs/en/build-with-claude/structured-outputs) — `additionalProperties`, `AnyOfSchema` restriction
- [DeepSeek JSON mode docs](https://api-docs.deepseek.com/guides/json_mode) — `response_format`, "json" prompt requirement

### Secondary (MEDIUM confidence)
- [Laravel News — SQLite in production](https://laravel-news.com/using-sqlite-in-production-with-laravel) — SQLite recommendation
- [Portkey LLM observability guide](https://portkey.ai/blog/the-complete-guide-to-llm-observability/) — `latency_ms`, token metrics
- [LangWatch — trace IDs in LLM systems](https://langwatch.ai/blog/trace-ids-llm-observability-and-distributed-tracing) — `request_id` / correlation key pattern

### Tertiary (LOW confidence)
- [prism-php DeepSeek provider docs](https://prismphp.com/providers/deepseek/) — structured output not documented; JSON mode workaround inferred from issue #136 and DeepSeek API docs
- [laravel/ai feature matrix](https://laravel.com/docs/12.x/ai-sdk) — DeepSeek structured output absence confirmed by omission

---
*Research completed: 2026-04-27*
*Ready for roadmap: yes*
