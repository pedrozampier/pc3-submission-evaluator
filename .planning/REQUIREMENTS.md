# Requirements — TCC Laravel AI Broker MVP

**Milestone:** v1.0 — MVP
**Generated:** 2026-04-27
**Source:** PROJECT.md + research synthesis

---

## v1 Requirements

### Setup

- [x] **SETUP-01**: Git repo reset to fresh Laravel 12 on `main`, old code preserved on `legacy/v1`
- [ ] **SETUP-02**: prism-php/prism installed and configured for Anthropic, OpenAI, Google Gemini, and DeepSeek

### API

- [ ] **API-01**: `POST /api/diagnose` accepts `code` (TypeScript string, required) and `statement` (string, required)
- [ ] **API-02**: All 4 providers are called in parallel per request (not sequentially)
- [ ] **API-03**: Partial results returned when individual providers fail (failed providers omitted from array)

### Schema

- [ ] **SCHEMA-01**: LLM structured output schema includes: `provider`, `model`, `diagnosis`, `pc3_category` (enum: Predicate/Concept/Context), `feedback`, `confidence` (self-reported float 0.0–1.0), `tokens_input`, `tokens_output`
- [ ] **SCHEMA-02**: `pc3_category` enforced as enum at schema level — Predicate, Concept, or Context only
- [ ] **SCHEMA-03**: `confidence` clamped to [0.0, 1.0] in PHP post-processing (Anthropic silently strips numeric constraints)

### Persistence

- [ ] **PERSIST-01**: `DiagnosticResult` Eloquent model and migration persist every provider result row
- [ ] **PERSIST-02**: Every result saved synchronously before response is returned (no fire-and-forget)
- [ ] **PERSIST-03**: `request_id` (UUID) generated per POST call and stored on every result row to group the 4 provider results from one submission
- [ ] **PERSIST-04**: `prompt_version` string column on every row (e.g., "v1.0") — controlled variable for research reproducibility

### Prompt

- [ ] **PROMPT-01**: System prompt instructs the LLM to apply PC³ taxonomy classification (Predicate / Concept / Context)
- [ ] **PROMPT-02**: System prompt is version-locked in code (not environment config) — changes only via code commit

---

## v2 Requirements (Deferred)

- `latency_ms` per provider result — valid comparison dimension but additive, not blocking research
- `finish_reason` field — distinguishes truncation from completion; additive field
- `raw_response` JSON column (config-toggled) — enables re-analysis; additive field
- `exercise_hash` SHA-256 of `code + statement` — grouping by unique input; additive field
- `provider_agreement_count` (1–4) — inter-rater agreement signal; computed field, additive

---

## Out of Scope

- **Authentication / API keys on the endpoint** — internal research tool; auth adds overhead with zero research value. Deployment must be local or VPN-restricted.
- **Frontend / UI dashboard** — not an academic requirement; use a DB GUI for data inspection
- **Retry logic with exponential backoff** — retries introduce timing noise; clean first-attempt data required for research validity
- **Rate limiting** — low-volume research tool; not needed for MVP
- **LLM response caching** — caching destroys run-to-run variance measurement; never cache LLM responses
- **Streaming responses** — structured output delivery unreliable with streaming; adds latency measurement complexity
- **Any endpoint beyond `POST /api/diagnose`** — single-feature MVP

---

## Traceability

| REQ-ID | Phase | Status | Notes |
|--------|-------|--------|-------|
| SETUP-01 | Phase 1 — Foundation | Complete | Git reset is a prerequisite for all phases |
| SCHEMA-01 | Phase 1 — Foundation | Pending | `ObjectSchema` + DTO definitions |
| SCHEMA-02 | Phase 1 — Foundation | Pending | `EnumSchema` for `pc3_category` |
| SCHEMA-03 | Phase 1 — Foundation | Pending | PHP clamping in `ProviderResult::fromPrismResponse()` |
| PERSIST-01 | Phase 1 — Foundation | Pending | Migration + Eloquent model |
| PERSIST-03 | Phase 1 — Foundation | Pending | UUID generation in DTO / service |
| PERSIST-04 | Phase 1 — Foundation | Pending | `prompt_version` column in migration |
| SETUP-02 | Phase 2 — Single-Provider Integration | Pending | prism-php configured for Anthropic first, then all providers in Phase 3 |
| PROMPT-01 | Phase 2 — Single-Provider Integration | Pending | `DiagnosticPromptBuilder` with PC³ system prompt |
| PROMPT-02 | Phase 2 — Single-Provider Integration | Pending | Prompt as private const in `DiagnosticPromptBuilder` |
| API-02 | Phase 3 — Parallel Fan-Out | Pending | `Http::pool()` parallel dispatch |
| API-03 | Phase 3 — Parallel Fan-Out | Pending | Per-provider try/catch, null filtering |
| PERSIST-02 | Phase 3 — Parallel Fan-Out | Pending | Synchronous persist inside service after pool |
| API-01 | Phase 4 — HTTP Layer | Pending | Controller and FormRequest validation |
