# TCC Laravel AI Broker — MVP

## What This Is

A Laravel 12 REST API that acts as a broker between TypeScript error diagnostic requests and
four LLM providers (Claude, GPT-4, Gemini, DeepSeek). The API receives a piece of buggy TypeScript
code and an exercise statement, dispatches the same prompt to all providers in parallel, and returns
a structured diagnostic array — one entry per provider. Built for academic research (TCC) comparing
LLM diagnostic quality using the PC³ taxonomy (Predicate, Concept, Context).

## Core Value

All four providers respond to a single diagnostic request in parallel and every result is persisted
— making multi-LLM comparison reproducible.

## Requirements

### Validated

- [x] Git repo reset to fresh Laravel 13 on `main`, old code preserved on `legacy/v1` — *Validated in Phase 1: Foundation*
- [x] `Pc3Category` backed string enum (Predicate/Concept/Context) — *Validated in Phase 1: Foundation*
- [x] `ProviderResult` immutable DTO with `fromPrismResponse()` factory and confidence clamping — *Validated in Phase 1: Foundation*
- [x] `DiagnosticAgent` structured-output schema with all fields required — *Validated in Phase 1: Foundation*
- [x] `DiagnosticResult` Eloquent model and migration persist every provider result — *Validated in Phase 1: Foundation*

### Active

- [ ] laravel/ai SDK wired for Anthropic — single live call returns schema-compliant ProviderResult
- [ ] `POST /api/diagnose` accepts `code` (TypeScript) and `statement` (string)
- [ ] All 4 providers are called in parallel per request
- [ ] LLM structured output includes: `provider`, `model`, `diagnosis`, `pc3_category`, `feedback`, `confidence` (self-reported float), `tokens_input`, `tokens_output`
- [ ] Partial results returned when individual providers fail (failed providers omitted from array)
- [ ] System prompt instructs the LLM to apply PC³ taxonomy classification

### Out of Scope

- Authentication / API keys for the endpoint — MVP only, no auth layer
- Frontend or UI — API-only
- Advanced error handling or retry logic — basic best-effort only
- Rate limiting — not needed for research use
- Any endpoint beyond `POST /api/diagnose` — single feature

## Context

- This is a TCC (undergraduate thesis) project for academic research comparing LLM diagnostic quality
- The PC³ taxonomy classifies programming errors into: Predicate (logic/condition errors), Concept
  (wrong understanding of language/library), Context (environment/configuration/scope issues)
- The existing repo contains an older Laravel version used for a previous evaluator tool; it must be
  preserved in `legacy/v1` before the fresh install
- The `confidence` field is self-reported by the LLM as part of the structured output schema —
  no external probability computation
- If a provider fails mid-request, the response array is partial (surviving providers only)

## Constraints

- **Tech Stack**: Laravel 12 — the project targets the latest stable Laravel
- **AI SDK**: prism-php/prism (or official Laravel AI package) — must support all 4 providers
- **Parallelism**: All provider calls must be concurrent, not sequential
- **Persistence**: Every result saved — no fire-and-forget; DiagnosticResult is the research corpus
- **Scope**: Single endpoint, no auth, no frontend — MVP only

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Fresh orphan `main`, old code → `legacy/v1` | Avoids polluting history while keeping old work accessible | Done — `legacy/v1` at ef5d869, `main` at bb98f63 (no shared history) |
| laravel/ai SDK (not prism-php) | User correction — laravel/ai v0.6.3 is the chosen SDK | Installed; `agent_conversations` migration published with scaffold |
| `Blueprint::rawColumn()` for CHECK constraint | `Blueprint::check()` absent in Laravel 13.6.0 | Named constraint `check_pc3_category` via raw DDL — SQLite confirmed |
| `$instance->structured` direct assignment | `makeStubResponse()` uses public `structured` property, not constructor | Confirmed via reflection in plan 02 SUMMARY |
| Self-reported LLM confidence | Simplest approach for MVP; avoids token probability hacks | — Pending Phase 2 |
| Partial array on provider failure | Research still valuable with 3/4 providers; hard failure loses data | — Pending Phase 3 |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-27 after Phase 1: Foundation*
