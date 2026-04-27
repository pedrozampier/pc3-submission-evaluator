# Phase 1: Foundation - Context

**Gathered:** 2026-04-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix the data contract before any provider call exists. Deliverables: `ProviderResult` DTO, shared Laravel AI SDK ObjectSchema, `DiagnosticResult` Eloquent model + migration, and the git repo in its correct `main`/`legacy/v1` state. No HTTP layer, no prompt builder, no provider wiring — just the foundation everything else builds on.

</domain>

<decisions>
## Implementation Decisions

### Git Reset (SETUP-01)
- **D-01:** Create `legacy/v1` branch from the current `master` branch — this preserves the entire old codebase (old DTOs, services, models) as an accessible archive.
- **D-02:** Reset `main` via an orphan branch — clean break with no legacy history on `main`. Fresh Laravel 13 scaffold is the first commit.
- **D-03:** Phase 1 work goes directly on `main` after the reset — no feature branch for this phase. MVP research tool, no parallel contributors.

### Old Code Handling
- **D-04:** The orphan reset is the complete answer. Legacy files disappear from `main`; `legacy/v1` preserves them. Phase 1 creates only new files — no explicit migration or documentation step needed.

### DB Enforcement for pc3_category (SCHEMA-02)
- **D-05:** Use a CHECK constraint in the migration to enforce `Predicate`/`Concept`/`Context` at the DB level. SQLite supports CHECK constraints — this catches bad data even if PHP validation is bypassed.
- **D-06:** Also create a PHP-backed enum `Pc3Category` (cases: `Predicate`, `Concept`, `Context`). Used in the DTO and as an Eloquent cast on the model. Provides type safety and IDE autocomplete alongside the DB constraint.

### ProviderResult DTO Construction (SCHEMA-01, SCHEMA-03)
- **D-07:** Single construction path: `fromPrismResponse()` static factory only. No `fromArray()`. Tests that need a `ProviderResult` must go through a real or faked Laravel AI SDK response — keeps the DTO honest about its source.
- **D-08:** Confidence clamping (`max(0.0, min(1.0, $value))`) happens inside `fromPrismResponse()` at construction time. A `ProviderResult` is always valid by the time it exists — no caller can forget to clamp.

### Claude's Discretion
- Migration column types (string lengths, integer widths) — standard Laravel conventions apply.
- `DiagnosticResultRepository` scope — minimal: `save()` method only for MVP. Research queries can be added in a later phase.
- Namespace and file structure — follow Laravel 13 conventions.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — Full requirement list with SETUP-01, SCHEMA-01, SCHEMA-02, SCHEMA-03, PERSIST-01, PERSIST-03, PERSIST-04 (all Phase 1 requirements)
- `.planning/ROADMAP.md` §Phase 1 — Success criteria (4 items) that define done for this phase

### Stack
- No external specs — stack is Laravel 13 + `laravel/ai` SDK. Refer to official Laravel 13 docs for framework conventions.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- None on fresh `main` after the orphan reset — the scaffold is a clean Laravel 13 install.

### Established Patterns
- None yet — Phase 1 establishes the conventions that later phases follow.

### Integration Points
- `database/migrations/` — new `create_diagnostic_results_table` migration goes here.
- `app/DTOs/` — `ProviderResult.php` and `Pc3Category.php` enum go here.
- `app/Models/` — `DiagnosticResult.php` Eloquent model.
- `app/Repositories/` — `DiagnosticResultRepository.php` (create directory if not present).

</code_context>

<specifics>
## Specific Ideas

- No specific references or examples beyond REQUIREMENTS.md — open to standard Laravel 13 patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 01-foundation*
*Context gathered: 2026-04-27*
