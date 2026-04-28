---
phase: 02-single-provider-integration
plan: "01"
subsystem: ai-integration
tags: [laravel-ai, anthropic, prompt-builder, structured-output, tdd, faked-tests]
dependency_graph:
  requires:
    - 01-02 (Pc3Category enum, ProviderResult DTO, DiagnosticAgent schema)
    - 01-03 (DiagnosticResultRepository, DiagnosticResult model, migration)
  provides:
    - DiagnosticPromptBuilder (PC3 system prompt as private const)
    - PrismStructuredCaller (single call(string,string,string):ProviderResult entry point)
    - Anthropic model pin in config/ai.php
    - DiagnosticAgent::instructions() wired to prompt builder
    - Faked integration test proving full call -> persist path
  affects:
    - DiagnosticAgent (instructions() replaced; schema() unchanged)
    - config/ai.php (models.text.default added to anthropic provider block)
tech_stack:
  added: []
  patterns:
    - "private const heredoc for version-locked system prompt"
    - "config-driven model pin (config/ai.php providers.anthropic.models.text.default)"
    - "DiagnosticAgent::fake([array]) for SDK-native structured-output testing"
    - "assertPrompted closure to verify user message format"
key_files:
  created:
    - app/Services/DiagnosticPromptBuilder.php
    - app/Services/PrismStructuredCaller.php
    - tests/Feature/Ai/PrismStructuredCallerTest.php
  modified:
    - config/ai.php
    - app/Ai/Agents/DiagnosticAgent.php
    - tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php
decisions:
  - "DiagnosticPromptBuilder uses private const SYSTEM_PROMPT (not static property) — satisfies PROMPT-02, immutable via reflection"
  - "PrismStructuredCaller reads model from config once via $model variable, passed to both prompt() and fromPrismResponse()"
  - "DiagnosticAgent has no #[Provider] attribute — preserves Phase 3 ability to reuse it for all four providers"
  - "Worktree reset to main required: worktree branch was on old master code (pre-Phase 1) — reset --hard to main to get Phase 1 files, then proceeded"
metrics:
  duration: "5 minutes"
  completed_date: "2026-04-28"
  tasks_completed: 3
  files_created: 3
  files_modified: 3
---

# Phase 02 Plan 01: Single-Provider Integration (Anthropic) Summary

JWT-like pattern not applicable — this plan wires laravel/ai for Anthropic via DiagnosticPromptBuilder (PC3 private const) + PrismStructuredCaller (config-driven model pin, provider:anthropic explicit), proven end-to-end with DiagnosticAgent::fake() in 3 Pest feature tests.

## What Was Built

### Files Created

**`app/Services/DiagnosticPromptBuilder.php`**
- `final class` in `App\Services` with `declare(strict_types=1)`
- `private const SYSTEM_PROMPT` using single-quoted heredoc (`<<<'PROMPT'`) — prevents PHP variable interpolation
- System prompt defines all three PC3 categories: Predicate (logic/condition errors), Concept (language feature misunderstanding), Context (scope/import/configuration issues)
- Contains examples for each category; ~200 tokens as targeted
- `systemPrompt()` — only accessor to the private const
- `promptVersion()` — returns `'v1.0'` (matches DB column default from Phase 1)
- `userMessage(string $code, string $statement): string` — builds labeled sections per D-06 using interpolating heredoc

**`app/Services/PrismStructuredCaller.php`**
- `final class PrismStructuredCaller` in `App\Services` with `declare(strict_types=1)`
- Constructor: `private readonly DiagnosticResultRepository $repository`
- `call(string $code, string $statement, string $requestId): ProviderResult`
- Reads model once: `$model = config('ai.providers.anthropic.models.text.default')`
- Passes `provider: 'anthropic'` explicitly (config default is `'openai'`)
- Uses `$model` variable in both `prompt()` and `fromPrismResponse()` — single source of truth
- Persists synchronously via `$this->repository->save($result)` before returning

**`tests/Feature/Ai/PrismStructuredCallerTest.php`**
- 3 Pest feature tests using `uses(RefreshDatabase::class)`
- Test 1: full happy-path — ProviderResult shape (pc3Category, confidence, provider, model, promptVersion, requestId, diagnosis, tokens) and DB persistence
- Test 2: user message format verified via `DiagnosticAgent::assertPrompted()` closure — both `## Exercise Statement` and `## TypeScript Code` headings present
- Test 3: confidence clamping (1.5 → 1.0) preserved across full caller path including DB row

### Files Modified

**`config/ai.php`**
- Added `models.text.default = 'claude-sonnet-4-20250514'` to the `anthropic` provider block
- `'default' => 'openai'` unchanged at top level (Phase 3 must pass provider explicitly)

**`app/Ai/Agents/DiagnosticAgent.php`**
- Added `use App\Services\DiagnosticPromptBuilder;`
- Replaced TODO placeholder in `instructions()` with `return DiagnosticPromptBuilder::systemPrompt();`
- `schema()` unchanged (locked from Phase 1)

**`tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php`**
- Added `use App\Services\DiagnosticPromptBuilder;`
- Replaced 1 placeholder test (`returns a non-empty placeholder instructions string`) with 3 new tests:
  1. `returns the PC3 system prompt from DiagnosticPromptBuilder` (identity check)
  2. `does not return the Phase 1 TODO placeholder`
  3. `contains all three PC3 category names in instructions`
- Total schema tests: 7 (was 5 before Phase 2)

## Test Counts

| Test Group | Tests Before | Tests After | Change |
|------------|-------------|-------------|--------|
| DiagnosticAgentSchemaTest | 5 | 7 | +3, -1 (placeholder replaced) |
| Pc3CategoryTest | 4 | 4 | unchanged |
| ProviderResultTest | 5 | 5 | unchanged |
| DiagnosticResultPersistenceTest | 3 | 3 | unchanged |
| PrismStructuredCallerTest | 0 | 3 | +3 new |
| ExampleTest (Unit + Feature) | 2 | 2 | unchanged |
| **Total** | **19** | **24** | **+5** |

All 24 tests pass. Full suite green.

## ROADMAP Phase 2 Success Criteria — Verification

| # | Criterion | Status | Automated Check |
|---|-----------|--------|-----------------|
| 1 | PrismStructuredCaller::call() returns schema-compliant ProviderResult | PASS | `./vendor/bin/pest tests/Feature/Ai/PrismStructuredCallerTest.php` exits 0 |
| 2 | System prompt is a private constant in DiagnosticPromptBuilder | PASS | `grep -q "private const SYSTEM_PROMPT" app/Services/DiagnosticPromptBuilder.php` succeeds; not in .env or config/ |
| 3 | Anthropic model pin appears exactly once in any provider config file | PASS | `grep -r "claude-sonnet-4-20250514" config/ | wc -l` = 1; `grep -r "claude-sonnet-4-20250514" app/ | wc -l` = 0 |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree branch was on old master code (pre-Phase 1)**
- **Found during:** Initial setup before Task 1
- **Issue:** The worktree branch `worktree-agent-a1d09a1edb2d7c20c` was pointing to the old `master` branch (5567cab) instead of `main` (c2f82fd where Phase 1 work lives). The `app/Ai/Agents/`, `app/DTOs/`, `app/Repositories/`, `app/Models/`, `config/ai.php`, and all Phase 1 files were missing.
- **Fix:** `git reset --hard main` in the worktree to align the branch with Phase 1 HEAD.
- **Files modified:** None (git history operation only)
- **Impact:** No plan code was affected; all Phase 1 contracts were then available as expected.

**2. [Rule 3 - Blocking] vendor/ directory absent from worktree**
- **Found during:** Task 1 verification (after git reset)
- **Issue:** Git worktrees share the object store but not untracked/gitignored directories. `vendor/` is in `.gitignore`, so it was not present in the worktree.
- **Fix:** `composer install` in the worktree directory.
- **Impact:** No code changes. Composer used the existing `composer.lock` from the shared repo.

**3. [Rule 3 - Blocking] .env absent from worktree**
- **Found during:** Task 1 verification (config() call requires bootstrapped app)
- **Issue:** `.env` is gitignored and not shared across worktrees.
- **Fix:** Copied `.env` from main repo to worktree; ran `php artisan key:generate`.
- **Impact:** No code changes. Required for Laravel bootstrap and test execution.

## TDD Execution Log

| Phase | Task | Status | Notes |
|-------|------|--------|-------|
| RED (test first) | Task 2 schema tests | 3 failing, 4 passing | Correct — instructions() still returned TODO |
| GREEN (implementation) | DiagnosticAgent + PrismStructuredCaller | 7 passing | All 3 new schema tests pass |
| Task 3 test file | PrismStructuredCallerTest | 3 passing immediately | Implementation from Task 2 satisfied all assertions |

## Phase 3 Readiness

### Reusable As-Is

| Asset | Notes |
|-------|-------|
| `DiagnosticAgent` | No `#[Provider]` attribute — can be dispatched to any of 4 providers |
| `DiagnosticPromptBuilder` | Provider-agnostic; same prompt for all 4 |
| `ProviderResult::fromPrismResponse()` | Takes `$provider` and `$model` as parameters — already polymorphic |
| `DiagnosticResultRepository::save()` | Stores any provider's result |
| `app/DTOs/Pc3Category.php` | Locked from Phase 1 |

### Needs Generalization for Phase 3

| Asset | Required Change |
|-------|----------------|
| `PrismStructuredCaller` | Phase 3 will likely introduce `DiagnosticService` that fans out to all 4 providers in parallel (Http::pool() or similar). `PrismStructuredCaller` may be refactored into a per-provider helper or replaced entirely. |
| `config/ai.php` | Model pins for OpenAI (gpt-4o), Gemini (gemini-2.0-flash), and DeepSeek (deepseek-chat) need to be added. |

## Known Stubs

None — all fields in the 3 new tests are exercised with real assertions. No placeholder text flows to UI (this is an API-only backend).

## Self-Check: PASSED

| Item | Status |
|------|--------|
| `app/Services/DiagnosticPromptBuilder.php` | FOUND |
| `app/Services/PrismStructuredCaller.php` | FOUND |
| `tests/Feature/Ai/PrismStructuredCallerTest.php` | FOUND |
| Commit 63e4334 (Task 1: config + PromptBuilder) | FOUND |
| Commit c792cb2 (RED: failing schema tests) | FOUND |
| Commit de1282c (Task 2: agent wiring + PrismStructuredCaller) | FOUND |
| Commit a7c7c94 (Task 3: faked integration tests) | FOUND |
