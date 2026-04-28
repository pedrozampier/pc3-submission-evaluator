# Phase 4: HTTP Layer - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-28
**Phase:** 04-http-layer
**Areas discussed:** Response shape, All-providers-fail response, Validation rules, Test strategy

---

## Response Shape

| Option | Description | Selected |
|--------|-------------|----------|
| Flat array | Returns `[{...}]` — minimal, matches ROADMAP "array of diagnostic objects" | ✓ |
| Envelope with request_id | Wraps in `{"data":[...],"request_id":"..."}` for caller correlation | |

**User's choice:** Flat array

---

### Response Fields (follow-up)

| Option | Description | Selected |
|--------|-------------|----------|
| Include both `request_id` and `prompt_version` | Expose all 10 DTO fields — useful for research consumers correlating response to DB rows | ✓ |
| Omit both | Return only the 8 diagnostic fields — leaner response | |

**User's choice:** Include both — all 10 DTO fields exposed in each response object

---

## All-Providers-Fail Response

| Option | Description | Selected |
|--------|-------------|----------|
| HTTP 200 empty array | Consistent with partial-result principle; caller handles empty | |
| HTTP 503 Service Unavailable | Explicit failure signal with `{"message": "All providers failed"}` | ✓ |

**User's choice:** HTTP 503 — signals a meaningful service failure

---

## Validation Rules

| Option | Description | Selected |
|--------|-------------|----------|
| `required\|string` only | Minimal rules — any non-empty string passes | ✓ |
| `required\|string\|max:N` | Add max length to prevent runaway token spend | |

**User's choice:** `required|string` only — appropriate for research tool with variable input sizes

---

## Test Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Mock DiagnosticService | Feature test hits real route, service is mocked — fast, deterministic | ✓ |
| Call through to DiagnosticService | Integration coverage with HTTP-faked LLM responses — heavier setup | |

**User's choice:** Mock DiagnosticService — follows Phase 2/3 pattern of faking the layer below

---

## Claude's Discretion

- Controller name, namespace, and file location
- Whether `request_id` is generated in controller or injected
- `routes/api.php` creation and registration approach
- FormRequest class vs inline `validate()` (FormRequest preferred for testability)

## Deferred Ideas

None
