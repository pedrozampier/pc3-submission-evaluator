# Roadmap: TCC Laravel AI Broker — MVP

## Overview

The project builds a single Laravel 12 REST endpoint that fans out a structured diagnostic
prompt to four LLM providers in parallel and persists every result as the research corpus.
The build follows a strict dependency chain: fix the data contract first (DTOs, schema, DB),
prove a single working provider call second, add concurrency and the remaining three providers
third, and wire the thin HTTP controller last.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Foundation** - DTOs, shared Agent schema (laravel/ai), Eloquent model, and migration (completed 2026-04-27)
- [ ] **Phase 2: Single-Provider Integration** - prism-php wired for Anthropic + PC³ prompt builder
- [x] **Phase 3: Parallel Fan-Out** - All 4 providers dispatched concurrently with partial-result isolation (completed 2026-04-28)
- [x] **Phase 4: HTTP Layer** - Controller, FormRequest validation, and route registration (completed 2026-04-28)

## Phase Details

### Phase 1: Foundation
**Goal**: The data contract is fixed — DTOs, the shared Agent schema (laravel/ai), and the persistence layer are all fully defined and verifiable without any provider call.
**Depends on**: Nothing (first phase)
**Requirements**: SETUP-01, SCHEMA-01, SCHEMA-02, SCHEMA-03, PERSIST-01, PERSIST-03, PERSIST-04
**Success Criteria** (what must be TRUE):
  1. Running `php artisan migrate` on a fresh checkout completes without error and produces the `diagnostic_results` table with all required columns (`provider`, `model`, `diagnosis`, `pc3_category`, `feedback`, `confidence`, `tokens_input`, `tokens_output`, `request_id`, `prompt_version`, `created_at`).
  2. A `ProviderResult` DTO can be instantiated in `tinker` with a `confidence` value outside [0, 1] and the clamped value is stored correctly when persisted via `DiagnosticResultRepository`.
  3. The shared `DiagnosticAgent::schema()` uses `$schema->string()->enum(['Predicate', 'Concept', 'Context'])->required()` for `pc3_category` and every field is marked `->required()` — verifiable by inspecting the agent source.
  4. The git repo has a clean `main` branch running a fresh Laravel 13 install and a `legacy/v1` branch preserving the old codebase.
**Plans:** 3/3 plans complete
- [x] 01-01-PLAN.md — Git reset (legacy/v1 + orphan main) + fresh Laravel 13 + laravel/ai install (SETUP-01)
- [x] 01-02-PLAN.md — Pc3Category enum, ProviderResult DTO with fromPrismResponse() factory, and DiagnosticAgent shared schema (SCHEMA-01/02/03)
- [x] 01-03-PLAN.md — diagnostic_results migration with CHECK constraint, DiagnosticResult Eloquent model, and DiagnosticResultRepository::save() (PERSIST-01/03/04)

### Phase 2: Single-Provider Integration
**Goal**: A single live Anthropic call returns a schema-compliant `ProviderResult` that can be persisted — proving the SDK is correctly wired before any concurrency is introduced.
**Depends on**: Phase 1
**Requirements**: SETUP-02, PROMPT-01, PROMPT-02
**Success Criteria** (what must be TRUE):
  1. Calling `PrismStructuredCaller::call()` in `tinker` with a sample code snippet returns a `ProviderResult` DTO where `pc3_category` is one of Predicate/Concept/Context and `confidence` is between 0.0 and 1.0.
  2. The system prompt is a private constant in `DiagnosticPromptBuilder` (not in `.env` or config files) — verified by reading the class source.
  3. The Anthropic provider is pinned to `claude-sonnet-4-20250514` in config — no other model string appears in any provider configuration file.
**Plans:** 1 plan
- [x] 02-01-PLAN.md — Anthropic model pin (config/ai.php) + DiagnosticPromptBuilder (private const PC³ system prompt) + DiagnosticAgent::instructions() wiring + PrismStructuredCaller (call entry point) + faked integration test (SETUP-02, PROMPT-01, PROMPT-02)
**UI hint**: no

### Phase 3: Parallel Fan-Out
**Goal**: All four providers are dispatched concurrently per request, individual provider failures are isolated, and every successful result is persisted synchronously before the service returns.
**Depends on**: Phase 2
**Requirements**: API-02, API-03, PERSIST-02
**Success Criteria** (what must be TRUE):
  1. Calling `DiagnosticService::run()` in `tinker` produces up to four `ProviderResult` rows in the database within a single call — wall-clock time is approximately equal to the slowest provider, not the sum of all four.
  2. Killing or providing an invalid API key for one provider still returns results from the remaining three providers (partial array, no exception thrown to the caller).
  3. All successful results are present in the `diagnostic_results` table before `DiagnosticService::run()` returns — no fire-and-forget; confirmed by querying the DB immediately after the call.
**Plans:** 1/1 plans complete
- [x] 03-01-PLAN.md — DiagnosticService with Concurrency::run() fan-out + provider-agnostic PrismStructuredCaller refactor + 4-provider config pins + Pest tests for full/partial/all-fail (API-02, API-03, PERSIST-02)

### Phase 4: HTTP Layer
**Goal**: The `POST /api/diagnose` endpoint is reachable, validates its inputs, delegates entirely to `DiagnosticService`, and returns the correct JSON response shape.
**Depends on**: Phase 3
**Requirements**: API-01
**Success Criteria** (what must be TRUE):
  1. `POST /api/diagnose` with valid `code` and `statement` fields returns HTTP 200 with a JSON array of provider diagnostic objects matching the defined schema.
  2. `POST /api/diagnose` with missing `code` or `statement` returns HTTP 422 with a validation error response — no provider calls are made.
  3. A curl request to the running app completes end-to-end: four (or partial) diagnostic rows appear in the DB and the response body contains the same data.
**Plans:** 1/1 plans complete
- [x] 04-01-PLAN.md — routes/api.php + DiagnoseRequest (required|string) + DiagnoseController (snake_case mapping, HTTP 503 on empty) + 4 feature tests covering 200/422/422/503 (API-01)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 3/3 | Complete   | 2026-04-27 |
| 2. Single-Provider Integration | 0/1 | Planned | - |
| 3. Parallel Fan-Out | 1/1 | Complete   | 2026-04-28 |
| 4. HTTP Layer | 1/1 | Complete   | 2026-04-28 |
