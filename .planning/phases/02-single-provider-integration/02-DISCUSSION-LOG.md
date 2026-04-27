# Phase 2: Single-Provider Integration - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-27
**Phase:** 02-single-provider-integration
**Areas discussed:** PC³ system prompt, Input message format, Integration test strategy

---

## PC³ System Prompt

| Option | Description | Selected |
|--------|-------------|----------|
| Full definitions | Name + definition + 1-2 examples per category | ✓ |
| Names + one-line definitions | Compact, relies on LLM pre-training | |
| Names only | Minimal — LLM infers from training data | |

**User's choice:** Full definitions

---

| Option | Description | Selected |
|--------|-------------|----------|
| Expert code reviewer | "You are an expert TypeScript code reviewer..." | ✓ |
| Academic researcher | "You are a software engineering researcher..." | |
| Teaching assistant | "You are a programming instructor..." | |

**User's choice:** Expert code reviewer

**Notes:** Full PC³ definitions with examples give the LLM enough context for edge-case classification. Expert reviewer framing establishes authority and domain focus.

---

## Input Message Format

| Option | Description | Selected |
|--------|-------------|----------|
| Labeled sections | `## Exercise Statement` + `## TypeScript Code` with fenced code block | ✓ |
| Single paragraph | Combined sentence with code inline | |
| JSON body | Structured JSON object | |

**User's choice:** Labeled sections

**Notes:** Clear separation of `statement` and `code` into markdown sections makes it easy for the LLM to parse both inputs independently.

---

## Integration Test Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| HTTP fake / mocked response | Fakes laravel/ai HTTP layer — deterministic, CI-safe | ✓ |
| Live integration test (gated) | Real Anthropic API call, skipped if no key | |
| Tinker-only, no automated test | Manual verification via tinker | |

**User's choice:** HTTP fake / mocked response

**Notes:** No real API call needed in Phase 2 tests. Wiring is verified by structure. Live call can be done manually via tinker if desired.

---

## Claude's Discretion

- Exact wording of PC³ definitions in the system prompt (~150–250 tokens target)
- Namespace/location of `PrismStructuredCaller`
- How to construct the fake `StructuredAgentResponse` in the test

## Deferred Ideas

None.
