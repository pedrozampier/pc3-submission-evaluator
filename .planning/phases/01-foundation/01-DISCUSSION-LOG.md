# Phase 1: Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-27
**Phase:** 01-foundation
**Areas discussed:** Git reset strategy, Old code handling, DB enforcement for pc3_category, ProviderResult DTO factories

---

## Git Reset Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| From master | Tag master as legacy/v1, reset master to fresh scaffold | ✓ |
| From feature branch | Create legacy/v1 from FEAT-add-providers-for-each-LLMS | |
| Skip the reset | Continue on feature branch, legacy/v1 is optional | |

**User's choice:** Create `legacy/v1` from current `master`.

| Option | Description | Selected |
|--------|-------------|----------|
| Orphan branch reset | git checkout --orphan, wipe all files, fresh Laravel 13 scaffold | ✓ |
| Keep git history | Delete legacy files and commit removal, history preserved | |

**User's choice:** Orphan branch reset — clean break, no legacy history on `main`.

| Option | Description | Selected |
|--------|-------------|----------|
| Directly on main | Phase 1 work commits straight to main | ✓ |
| New feature branch | feat/phase-1-foundation off main | |

**User's choice:** Directly on `main`.

---

## Old Code Handling

| Option | Description | Selected |
|--------|-------------|----------|
| Orphan reset is enough | Legacy preserved in branch; no migration step needed | ✓ |
| Document what was removed | CHANGELOG/README note for academic provenance | |

**User's choice:** Orphan reset is the complete answer.

---

## DB Enforcement for pc3_category

| Option | Description | Selected |
|--------|-------------|----------|
| CHECK constraint in migration | DB-level enforcement, SQLite compatible | ✓ |
| PHP-only validation | Plain string column, app-layer only | |
| PHP enum cast + string column | App safety + IDE autocomplete, no DB constraint | |

**User's choice:** CHECK constraint in migration.

| Option | Description | Selected |
|--------|-------------|----------|
| PHP enum + CHECK constraint | Both layers: DB rejects bad values, PHP gives type safety | ✓ |
| CHECK constraint only | String values in PHP, constraint in DB only | |

**User's choice:** PHP-backed `Pc3Category` enum alongside the CHECK constraint.

---

## ProviderResult DTO Factories

| Option | Description | Selected |
|--------|-------------|----------|
| fromPrismResponse() only | Single construction path, tests must use real/faked response | ✓ |
| fromPrismResponse() + fromArray() | Test flexibility, two construction paths | |

**User's choice:** `fromPrismResponse()` only.

| Option | Description | Selected |
|--------|-------------|----------|
| Clamping inside fromPrismResponse() | ProviderResult always valid at construction | ✓ |
| Clamping in DiagnosticResultRepository::save() | Clamping deferred to persistence step | |

**User's choice:** Clamping inside `fromPrismResponse()`.

---

## Mid-Discussion Correction

**User noted during discussion:** The new project targets **Laravel 13** with the **official Laravel AI SDK** (`laravel/ai`), not Laravel 12 + prism-php/prism. CLAUDE.md was written before this decision was finalized.

## Claude's Discretion

- Migration column types and widths
- `DiagnosticResultRepository` scope (save() only for MVP)
- Namespace and file structure conventions

## Deferred Ideas

None.
