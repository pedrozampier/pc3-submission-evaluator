# Phase 3: Parallel Fan-Out - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-28
**Phase:** 03-parallel-fan-out
**Areas discussed:** Service structure, Parallelism mechanism, All-providers-fail behavior, DeepSeek structured output

---

## Service Structure

| Option | Description | Selected |
|--------|-------------|----------|
| New service, reuse caller | DiagnosticService is new; PrismStructuredCaller refactored to accept provider+model via constructor; survives as testable single-call unit | ✓ |
| New service absorbs logic | DiagnosticService holds call logic directly; PrismStructuredCaller kept unchanged as Phase 2 artifact but unused | |
| DiagnosticService IS the caller | PrismStructuredCaller retired entirely; DiagnosticService is sole entry point | |

**User's choice:** New service, reuse caller

---

## PrismStructuredCaller Provider/Model Location

| Option | Description | Selected |
|--------|-------------|----------|
| Constructor injection | PrismStructuredCaller($provider, $model, $repository) — instantiated per-provider | ✓ |
| call() method args | Provider+model passed per-call; single instance | |

**User's choice:** Constructor injection

---

## Parallelism Mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| Concurrently::run() | Laravel 11+ fiber-based; wraps agent closures; no HTTP bypass | ✓ |
| Http::pool() (raw HTTP) | Bypasses laravel/ai agent abstraction; requires 4 custom request builders | |

**User's choice:** Concurrently::run()

---

## Exception Isolation Location

| Option | Description | Selected |
|--------|-------------|----------|
| Inside each closure (try/catch → null) | Fully isolated; one failure cannot affect others | ✓ |
| In DiagnosticService after Concurrently returns | Depends on Concurrently exception behavior; may propagate | |

**User's choice:** Inside each closure

---

## All-Providers-Fail Behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Return empty array [] | Consistent with partial-result principle; controller handles | ✓ |
| Throw DiagnosticAllFailedException | Explicit failure signal; adds exception class + controller catch | |
| You decide | Claude picks | |

**User's choice:** Return empty array

---

## DeepSeek Structured Output

| Option | Description | Selected |
|--------|-------------|----------|
| Uniform treatment — catch handles it | No special-casing; failure absorbed by existing try/catch | ✓ |
| Special-case DeepSeek | Extra null/validity check before fromPrismResponse() | |
| Exclude DeepSeek from Phase 3 | Fan out to 3 providers only | |

**User's choice:** Uniform treatment

---

## Claude's Discretion

- Model strings for OpenAI, Gemini, DeepSeek (mirror Anthropic config pattern)
- Whether to emit Log::warning() on provider null returns
- Return type annotation for DiagnosticService::run()
- Test file location

## Deferred Ideas

None mentioned during discussion.
