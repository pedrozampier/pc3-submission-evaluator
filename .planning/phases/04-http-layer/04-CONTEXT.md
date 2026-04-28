# Phase 4: HTTP Layer - Context

**Gathered:** 2026-04-28
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire the single HTTP endpoint: `POST /api/diagnose`. A thin controller validates inputs,
generates a `request_id` UUID, delegates entirely to `DiagnosticService::run()`, and returns
a JSON response. No changes to the service layer — everything below the HTTP layer is already
built and tested.

</domain>

<decisions>
## Implementation Decisions

### Response Shape

- **D-01:** Response body is a **flat JSON array** — no envelope, no wrapper object. Each
  element is a provider diagnostic object. Matches ROADMAP success criterion: "JSON array of
  provider diagnostic objects".
- **D-02:** Each object exposes **all 10 DTO fields**: `provider`, `model`, `diagnosis`,
  `pc3_category`, `feedback`, `confidence`, `tokens_input`, `tokens_output`, `request_id`,
  `prompt_version`. No fields stripped — `request_id` lets callers correlate the response
  to DB rows; `prompt_version` preserves research reproducibility context.

  ```json
  // HTTP 200
  [
    {
      "provider": "anthropic",
      "model": "claude-sonnet-4-20250514",
      "diagnosis": "...",
      "pc3_category": "Concept",
      "feedback": "...",
      "confidence": 0.85,
      "tokens_input": 312,
      "tokens_output": 95,
      "request_id": "018f2c3d-...",
      "prompt_version": "v1.0"
    }
  ]
  ```

### All-Providers-Fail Behavior

- **D-03:** When `DiagnosticService::run()` returns `[]` (all four providers failed), the
  controller returns **HTTP 503 Service Unavailable** with body `{"message": "All providers failed"}`.
  Signals a meaningful service-level failure rather than silently returning an empty array.

### Validation Rules

- **D-04:** FormRequest rules are `required|string` only for both `code` and `statement`.
  No max/min length constraints — appropriate for a research tool where inputs vary widely.
  A missing or non-string value returns HTTP 422 with no provider calls made.

### Test Strategy

- **D-05:** Feature tests use `$this->mock(DiagnosticService::class)` — mocks the service
  layer, hits the real route and FormRequest. Fast, deterministic, no provider calls.
  Follows the Phase 2/3 pattern of faking the layer immediately below the one under test.
  Location: `tests/Feature/Http/DiagnoseControllerTest.php`.

  Tests must cover:
  1. HTTP 200 with valid inputs + mocked service returning results
  2. HTTP 422 with missing `code` (service mock never called)
  3. HTTP 422 with missing `statement` (service mock never called)
  4. HTTP 503 when service returns `[]` (all-fail path)

### Claude's Discretion

- Controller name (`DiagnoseController`) and namespace (`App\Http\Controllers`)
- Whether `request_id` is generated in the controller or injected — controller generates
  it via `Str::uuid()->toString()` before calling the service
- `routes/api.php` creation (Laravel 13 ships without it by default — create it and register
  via `bootstrap/app.php` if needed)
- Whether to use a dedicated `DiagnoseRequest` FormRequest class or inline `validate()` on
  the controller — FormRequest is preferred for testability

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — API-01 is the sole Phase 4 requirement (POST /api/diagnose endpoint)
- `.planning/ROADMAP.md` §Phase 4 — Three success criteria (HTTP 200 valid, HTTP 422 missing fields, curl end-to-end)

### Existing Phase 3 Artifacts
- `app/Services/DiagnosticService.php` — `run(string $code, string $statement, string $requestId): array`
  is the complete service entry point; controller calls this with generated UUID
- `app/DTOs/ProviderResult.php` — all public fields are the response fields; expose as-is

### No external specs
- All implementation detail is in the files above and standard Laravel 13 HTTP layer conventions

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `DiagnosticService::run()` — complete, tested; controller just needs to call it
- `app/Http/Controllers/Controller.php` — base controller exists; new `DiagnoseController` extends it
- `ProviderResult` DTO fields — map directly to response JSON; no API resource transformation needed

### Established Patterns
- `declare(strict_types=1)` + Pest tests (all phases)
- Mocked service layer in feature tests (Phases 2 and 3)
- `config('ai.providers.{provider}.models.text.default')` for config lookups (established pattern)

### Integration Points
- New: `routes/api.php` — does not exist yet; needs to be created and registered in `bootstrap/app.php`
- New: `app/Http/Controllers/DiagnoseController.php`
- New: `app/Http/Requests/DiagnoseRequest.php` (create `Requests/` directory)
- New: `tests/Feature/Http/DiagnoseControllerTest.php`

</code_context>

<specifics>
## Specific Ideas

- ROADMAP success criterion 3 requires a curl end-to-end test with real DB rows — manual
  verification step (not automated), but must be documented in the phase summary
- `ProviderResult` fields should be exposed directly (no Laravel API Resource transformation)
  since all 10 fields are needed and there is no field renaming or computed properties required

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 04-http-layer*
*Context gathered: 2026-04-28*
