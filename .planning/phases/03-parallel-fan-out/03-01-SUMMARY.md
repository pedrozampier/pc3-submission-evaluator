---
phase: 03-parallel-fan-out
plan: "01"
subsystem: services
tags: [parallel, concurrency, fan-out, laravel-ai, diagnostic-service, persistence]
dependency_graph:
  requires:
    - 02-01-SUMMARY.md  # PrismStructuredCaller + DiagnosticAgent + DiagnosticResultRepository
    - 01-01-SUMMARY.md  # ProviderResult DTO + DiagnosticResult model + migration
    - 01-02-SUMMARY.md  # Pc3Category enum + DiagnosticAgent schema
  provides:
    - DiagnosticService::run(code, statement, requestId): array  # Phase 4 controller entry point
    - Provider-agnostic PrismStructuredCaller (3-param constructor)
    - Model config pins for all four providers in config/ai.php
  affects:
    - app/Services/PrismStructuredCaller.php  # constructor signature changed (breaking — callers must update)
    - tests/Feature/Ai/PrismStructuredCallerTest.php  # updated for new constructor
tech_stack:
  added: []
  patterns:
    - "Illuminate\\Support\\Facades\\Concurrency::run([keyed closures]) for parallel dispatch"
    - "Concurrency::setDefaultInstance('sync') in beforeEach for test isolation"
    - "DiagnosticAgent::fake([...])->preventStrayPrompts(true) for failure injection"
    - "array_values(array_filter($results)) for null-filtering and re-indexing"
key_files:
  created:
    - app/Services/DiagnosticService.php
    - tests/Feature/Services/DiagnosticServiceTest.php
  modified:
    - app/Services/PrismStructuredCaller.php
    - config/ai.php
    - tests/Feature/Ai/PrismStructuredCallerTest.php
decisions:
  - "Concurrency facade (not Concurrently) — CONTEXT.md D-03 had a typo; RESEARCH Finding 1 corrected it to Illuminate\\Support\\Facades\\Concurrency"
  - "DiagnosticService uses verbose 4-closure spelling instead of a loop — SerializableClosure-friendly for ProcessDriver compatibility"
  - "Docblock in DiagnosticService uses 'Concurrency facade' phrasing (not FQN) to avoid grep count interference on acceptance criterion"
metrics:
  duration: "~4 minutes"
  completed_date: "2026-04-28"
  tasks_completed: 3
  files_created: 2
  files_modified: 3
---

# Phase 03 Plan 01: Parallel Fan-Out Service Summary

DiagnosticService with Concurrency::run() fan-out to four LLM providers, per-closure Throwable isolation, and synchronous persistence via PrismStructuredCaller (provider-agnostic refactor).

## What Was Built

### Files Created

**`app/Services/DiagnosticService.php`**
- Single `run(string $code, string $statement, string $requestId): array` method
- Constructs four `PrismStructuredCaller` instances (one per provider: anthropic, openai, gemini, deepseek), each with their model read from `config('ai.providers.{provider}.models.text.default')`
- Dispatches all four via `Concurrency::run([keyed closures])` — genuine parallelism via Laravel's ProcessDriver in production
- Each closure wraps the call in `try { ... } catch (Throwable $e) { Log::warning(...); return null; }` — per-provider failure isolation (D-04)
- Returns `array_values(array_filter($results))` — nulls removed, 0-indexed array of 0-4 ProviderResult DTOs (D-05)

**`tests/Feature/Services/DiagnosticServiceTest.php`**
- Three Pest feature tests using `Concurrency::setDefaultInstance('sync')` in `beforeEach()` so `DiagnosticAgent::fake()` registrations are visible to all four closures
- Test 1 (full fan-out): 4 faked responses → 4 ProviderResult DTOs returned, 4 rows in DB, correct provider/model values (API-02 + PERSIST-02)
- Test 2 (partial failure): 3 faked responses + `preventStrayPrompts(true)` → 3 DTOs returned (0-indexed), 3 rows in DB (API-03)
- Test 3 (all-fail): empty queue + `preventStrayPrompts(true)` → empty array returned, 0 rows, no exception (D-05)

### Files Modified

**`app/Services/PrismStructuredCaller.php`**
- Constructor changed from 1-param `(DiagnosticResultRepository $repository)` to 3-param `(string $provider, string $model, DiagnosticResultRepository $repository)`
- All references to hardcoded `'anthropic'` and `config('ai.providers.anthropic.models.text.default')` replaced with `$this->provider` and `$this->model`
- `call()` public signature unchanged: `public function call(string $code, string $statement, string $requestId): ProviderResult`

**`config/ai.php`**
- Added `models.text.default` to `openai` block: `'gpt-4o'`
- Added `models.text.default` to `gemini` block: `'gemini-2.0-flash'`
- Added `models.text.default` to `deepseek` block: `'deepseek-chat'`
- `anthropic` block unchanged (already had `claude-sonnet-4-20250514` from Phase 2)

**`tests/Feature/Ai/PrismStructuredCallerTest.php`**
- All three `new PrismStructuredCaller(new DiagnosticResultRepository())` calls updated to named-argument 3-param form: `new PrismStructuredCaller(provider: 'anthropic', model: config('ai.providers.anthropic.models.text.default'), repository: new DiagnosticResultRepository())`
- Assertion values unchanged — tests still pass and verify the same behavior

## Test Counts

| Suite | Tests | Assertions |
|-------|-------|------------|
| Phase 1 (Unit + Feature) | 20 | 66 |
| Phase 2 (Feature/Ai + Feature/Persistence) | 4 | 10 |
| Phase 3 (Feature/Services) | 3 | 47 |
| **Total** | **27** | **123** |

All 27 tests pass. No real API keys required (`DiagnosticAgent::fake()` short-circuits before HTTP).

## ROADMAP Phase 3 Success Criteria Status

| Criterion | Status | Verification |
|-----------|--------|--------------|
| 1. Wall-clock time ≈ slowest provider (parallelism) | Automated proxy + manual | `Concurrency::run([4 closures])` exists in DiagnosticService.php; real parallel behavior requires ProcessDriver + real API keys (tinker step) |
| 2. Partial results on provider failure | Fully automated | `DiagnosticServiceTest` Test 2: 3 DTOs returned when 4th throws via `preventStrayPrompts` |
| 3. All results in DB before run() returns | Fully automated | `DiagnosticServiceTest` Test 1: `DiagnosticResult::where('request_id', $requestId)->count() === 4` asserted immediately after `run()` returns |

## Requirements Addressed

- **API-02 (parallel dispatch):** DiagnosticService uses `Concurrency::run()` — Laravel's concurrent dispatch mechanism. All four provider calls are submitted in a single batch.
- **API-03 (partial results on failure):** `try { ... } catch (Throwable) { return null; }` inside each closure. `array_filter` removes nulls. Failed providers produce no row and no DTO.
- **PERSIST-02 (synchronous persistence before return):** `$this->repository->save($result)` executes inside `PrismStructuredCaller::call()` — i.e., inside each closure — before the closure returns. By the time `Concurrency::run()` resolves all closures, every row is in the DB.

## Manual Verification Steps (Post-Phase)

After setting real API keys in `.env`:

1. Set `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`, `DEEPSEEK_API_KEY`
2. Run `php artisan tinker` then:
   ```php
   (new App\Services\DiagnosticService())->run('let x: number = "hi";', 'Assign a number.', \Illuminate\Support\Str::uuid()->toString())
   ```
   Should return up to 4 ProviderResult DTOs in roughly the time of the slowest provider (ROADMAP criterion 1).
3. Immediately query: `App\Models\DiagnosticResult::latest()->take(4)->get()` — should match the returned DTOs.

## Readiness for Phase 4

The HTTP layer can now wire `POST /api/diagnose` as:
```
Controller → FormRequest (validate code + statement) → DiagnosticService::run($code, $statement, $requestId) → JSON response
```

No further service-layer changes needed. `DiagnosticService::run(string $code, string $statement, string $requestId): array` is the complete, tested entry point.

## Deviations from Plan

None — plan executed exactly as written.

One minor technical note: the CONTEXT.md D-03 referred to `Concurrently::run()` (typo), which RESEARCH Finding 1 and the plan's critical_pointers correctly identified as `Illuminate\Support\Facades\Concurrency`. The plan already contained the corrected FQN; no deviation required.

## Known Stubs

None. All four provider calls are wired, config keys are set, persistence is proven by tests, and `DiagnosticService::run()` returns real ProviderResult DTOs (or an empty array on all-fail) — no placeholder data flows to any output.

## Self-Check: PASSED
