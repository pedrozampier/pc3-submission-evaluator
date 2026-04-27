# Phase 2: Single-Provider Integration - Context

**Gathered:** 2026-04-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire `laravel/ai` for Anthropic only. Build the prompt builder and the caller class. Prove that one live (or faked) structured call returns a schema-compliant `ProviderResult` that persists correctly. No parallelism, no other providers — that is Phase 3.

</domain>

<decisions>
## Implementation Decisions

### PC³ System Prompt (PROMPT-01, PROMPT-02)

- **D-01:** System prompt uses the **expert code reviewer** role: "You are an expert TypeScript code reviewer applying the PC³ taxonomy..."
- **D-02:** Taxonomy definitions are **full** — each category includes its name, a definition, and 1–2 examples. Enough context for the LLM to classify edge cases reliably.
- **D-03:** Three categories to define:
  - **Predicate** — logic/condition errors (wrong comparisons, off-by-one, incorrect boolean expressions)
  - **Concept** — wrong understanding of a language feature or library API (misused method, wrong type usage, incorrect operator semantics)
  - **Context** — environment, scope, or configuration issues (wrong variable in scope, missing import, misconfigured toolchain)
- **D-04:** Prompt is a **private constant** inside `DiagnosticPromptBuilder` — never in `.env`, never in `config/`. Changes only via code commit. (PROMPT-02)
- **D-05:** Prompt version string is `"v1.0"` — matches the `prompt_version` column seeded in the migration.

### Input Message Format (PROMPT-01)

- **D-06:** User message uses **labeled sections**:
  ```
  ## Exercise Statement
  {statement}

  ## TypeScript Code
  ```typescript
  {code}
  ```
  ```
  Clear structure — LLM can parse the two inputs as separate concerns.

### Caller Class (SETUP-02)

- **D-07:** Class name is `PrismStructuredCaller` (locked by ROADMAP success criteria: "Calling `PrismStructuredCaller::call()` in tinker").
  Note: legacy name from before SDK switch — the class uses `laravel/ai`, not prism-php.
- **D-08:** Single public method: `call(string $code, string $statement, string $requestId): ProviderResult`.
  Anthropic-only in this phase; Phase 3 adds other providers.
- **D-09:** Anthropic model is pinned to `claude-sonnet-4-20250514`. Pin location: a constant or config key in `config/ai.php` — **not** a hardcoded string scattered through code.

### Test Strategy

- **D-10:** Phase 2 test uses **HTTP fake / mocked response** — no real Anthropic API call.
  The test fakes the `laravel/ai` HTTP layer and returns a valid `StructuredAgentResponse`. This is:
  - Deterministic (no token spend, no network dependency)
  - CI-safe (no `ANTHROPIC_API_KEY` required)
  - Sufficient to verify the wiring: schema → agent → caller → ProviderResult → repository

### Claude's Discretion

- Exact wording of the PC³ definitions in the system prompt — keep them accurate and concise; target ~150–250 tokens for the full prompt
- Namespace/location of `PrismStructuredCaller` (e.g. `App\Services\` or `App\Ai\`)
- How to construct the fake `StructuredAgentResponse` in the test (reflection, factory, or public constructor if available)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — SETUP-02, PROMPT-01, PROMPT-02 are the Phase 2 requirement IDs
- `.planning/ROADMAP.md` §Phase 2 — Three success criteria (tinker call, prompt as private const, model pinned)

### Existing Phase 1 Artifacts
- `app/Ai/Agents/DiagnosticAgent.php` — `instructions()` placeholder is the integration point for PROMPT-01; `schema()` is already locked
- `app/DTOs/ProviderResult.php` — `fromPrismResponse(StructuredAgentResponse $response, ...)` is the factory the caller must invoke
- `app/Repositories/DiagnosticResultRepository.php` — `save(ProviderResult)` is the persistence method Phase 2 calls

### Stack
- `config/ai.php` — Anthropic provider already configured with `ANTHROPIC_API_KEY` env var; model pin goes here or alongside the caller
- No external specs — all implementation detail is in the files above and Laravel 13 + laravel/ai v0.6.3 conventions

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `DiagnosticAgent` — already implements `Agent` and `HasStructuredOutput`; `instructions()` needs to be filled, `schema()` is complete
- `ProviderResult::fromPrismResponse()` — accepts `StructuredAgentResponse`; caller just needs to invoke it with the right arguments
- `DiagnosticResultRepository::save()` — ready; Phase 2 calls it after constructing `ProviderResult`
- `config/ai.php` — Anthropic driver + key already wired; model constant just needs to be added

### Established Patterns
- PHP 8.2+ `declare(strict_types=1)` on every file (established in Phase 1)
- Final classes + private constructors for immutable DTOs (established in Phase 1)
- Pest for all tests (established in Phase 1)
- Namespaces follow Laravel 13 conventions (`App\Ai\`, `App\DTOs\`, `App\Repositories\`)

### Integration Points
- `DiagnosticAgent::instructions()` — replace TODO placeholder with real PC³ prompt from `DiagnosticPromptBuilder`
- New: `app/Services/PrismStructuredCaller.php` (or `app/Ai/PrismStructuredCaller.php`)
- New: `app/Services/DiagnosticPromptBuilder.php` (private const for system prompt)
- New: `tests/Feature/Ai/PrismStructuredCallerTest.php` (HTTP-faked integration test)

</code_context>

<specifics>
## Specific Ideas

- The ROADMAP success criterion explicitly names `PrismStructuredCaller::call()` as the tinker entry point — keep that exact class and method name
- `prompt_version` for this phase is `"v1.0"` — matches the migration default

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 02-single-provider-integration*
*Context gathered: 2026-04-27*
