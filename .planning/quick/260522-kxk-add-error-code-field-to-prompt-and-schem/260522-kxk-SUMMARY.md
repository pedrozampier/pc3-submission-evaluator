---
phase: quick
plan: 260522-kxk
subsystem: diagnostic-pipeline
tags: [enum, schema, migration, dto, prompt, tdd]
dependency_graph:
  requires: [04-01]
  provides: [ErrorCode enum, v2.0 prompt, error_code DB column]
  affects: [ProviderResult, DiagnosticResult, DiagnosticResultRepository, DiagnosticAgent]
tech_stack:
  added: []
  patterns: [string-backed enum, rawColumn CHECK constraint, Eloquent enum cast]
key_files:
  created:
    - app/DTOs/ErrorCode.php
    - database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php
    - tests/Unit/DTOs/ErrorCodeTest.php
  modified:
    - app/Services/DiagnosticPromptBuilder.php
    - app/Ai/Agents/DiagnosticAgent.php
    - app/DTOs/ProviderResult.php
    - app/Models/DiagnosticResult.php
    - app/Repositories/DiagnosticResultRepository.php
    - tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php
    - tests/Unit/DTOs/ProviderResultTest.php
    - tests/Feature/Ai/PrismStructuredCallerTest.php
    - tests/Feature/Persistence/DiagnosticResultPersistenceTest.php
    - tests/Feature/Services/DiagnosticServiceTest.php
decisions:
  - "ErrorCode enum uses None='NONE' case name to avoid PHP reserved word conflict while backing string is 'NONE'"
  - "Migration uses rawColumn() with default 'NONE' — required for NOT NULL ADD COLUMN on populated SQLite tables"
  - "promptVersion bumped to v2.0 to reflect schema/prompt breaking change"
metrics:
  duration: 18min
  completed: 2026-05-22
  tasks_completed: 2
  files_changed: 11
---

# Phase quick Plan 260522-kxk: Add error_code Field to Prompt and Schema Summary

**One-liner:** ErrorCode string-backed enum (11 cases) added to LLM schema, v2.0 prompt, ProviderResult DTO, CHECK-constrained DB column, and repository — all TDD-covered with 39 passing tests.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create ErrorCode enum and update v2.0 prompt + LLM schema | bd2cdef | ErrorCode.php, DiagnosticPromptBuilder.php, DiagnosticAgent.php |
| 2 | Thread error_code through ProviderResult, DB column, repository | 49e5f0f | ProviderResult.php, DiagnosticResult.php, migration, repository |

## What Was Built

- `app/DTOs/ErrorCode.php` — string-backed enum with 11 cases: B6, B8, B9, B12, C1, C3, C8, G3, G4, H1, None (='NONE')
- `app/Services/DiagnosticPromptBuilder.php` — v2.0 SYSTEM_PROMPT adds "## Specific Error Codes" section listing all 10 codes + NONE with descriptions; JSON output spec includes `error_code` field; `promptVersion()` returns `'v2.0'`
- `app/Ai/Agents/DiagnosticAgent.php` — `schema()` now returns 7 keys; `error_code` added after `pc3_category` with `->enum([...all 11 values...])->required()`
- `app/DTOs/ProviderResult.php` — new `public readonly ErrorCode $errorCode` property; `fromPrismResponse()` maps `ErrorCode::from((string) $response['error_code'])`
- `app/Models/DiagnosticResult.php` — `error_code` in `$fillable` and cast to `ErrorCode::class`
- `database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php` — adds CHECK-constrained varchar column with `default 'NONE'`
- `app/Repositories/DiagnosticResultRepository.php` — `save()` writes `$dto->errorCode->value`

## Verification

Full suite: **39 passed, 0 failed** (190 assertions)

Migration pretend output confirms correct DDL:
```
alter table "diagnostic_results" add column "error_code" varchar not null default 'NONE'
  constraint check_error_code check (error_code IN ('B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'))
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Updated existing test stubs missing the new required error_code field**
- **Found during:** Task 2 final verification (full suite run)
- **Issue:** `PrismStructuredCallerTest` and `DiagnosticServiceTest` had `DiagnosticAgent::fake([...])` arrays without `error_code`, causing `Undefined array key "error_code"` ErrorException in `ProviderResult::fromPrismResponse()`
- **Fix:** Added `'error_code' => 'B6'/'NONE'/'H1'` to all fake response arrays; updated `promptVersion` assertions from `'v1.0'` to `'v2.0'`
- **Files modified:** `tests/Feature/Ai/PrismStructuredCallerTest.php`, `tests/Feature/Services/DiagnosticServiceTest.php`
- **Commit:** a7a79d3

## Known Stubs

None — all data flows are wired end-to-end.

## Self-Check: PASSED

Files confirmed present:
- app/DTOs/ErrorCode.php — FOUND
- database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php — FOUND
- tests/Unit/DTOs/ErrorCodeTest.php — FOUND

Commits confirmed:
- 3d70b2d (RED tests task 1) — FOUND
- bd2cdef (GREEN task 1) — FOUND
- 7915d23 (RED tests task 2) — FOUND
- 49e5f0f (GREEN task 2) — FOUND
- a7a79d3 (Rule 1 fix) — FOUND
