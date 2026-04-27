# Features Research: Laravel AI Broker

**Domain:** Multi-LLM comparison API for academic research (TCC)
**Researched:** 2026-04-27
**Overall confidence:** HIGH for table stakes; MEDIUM for differentiators

---

## Table Stakes

Features without which the research corpus is invalid, incomplete, or unreproducible.

| Feature | Why Expected / Required | Complexity | Notes |
|---------|------------------------|------------|-------|
| Structured output schema per provider | Without a schema contract, cross-provider comparison is impossible — you're comparing free-text strings, not equivalent data points | Medium | Prism PHP supports strict mode (OpenAI, Anthropic Claude Sonnet 4.5+) and JSON mode (Gemini, DeepSeek). The schema must be identical for all four providers or normalization becomes the analysis, not the LLMs. HIGH confidence — verified via prismphp.com/core-concepts/structured-output/ |
| `provider` field in every result row | Without provider identity in the persisted record, the corpus cannot be sliced by provider for comparison | Low | Must store both the provider label (e.g. "anthropic") and the exact model string (e.g. "claude-sonnet-4-5"). Model string is critical for reproducibility — LLM providers silently alias "latest" to different model versions over time. |
| `model` field with exact model identifier | Academic reproducibility requires knowing the exact checkpoint. "claude-3-5-sonnet" vs "claude-sonnet-4-5" are different models with different outputs. | Low | Store the canonical model ID returned by the provider or configured in the request, not an alias. Research confirmed: model versioning must capture the full inference identity (version, tier, quantization) — medium confidence, source: Medium/Portkey observability guides. |
| Token counts: `tokens_input` + `tokens_output` | Required for cost analysis and prompt efficiency comparison across providers. Without it, you cannot control for prompt complexity as a confound in the study. | Low | Prism returns token usage in response metadata. Each provider's tokenizer differs, so counts are not directly comparable — that difference is itself a research data point. |
| `pc3_category` as a constrained enum | The PC³ taxonomy (Predicate / Concept / Context) is the classification axis of the study. Free-text category fields invalidate inter-provider agreement analysis. | Medium | Must be enforced via the structured output schema — the schema should declare pc3_category as a string with enum constraint. Providers may still hallucinate values outside the enum; validation on ingestion is required. |
| `diagnosis` and `feedback` fields | The qualitative output — what the LLM actually says about the error. Raw text, no normalization needed beyond storing it as-is. | Low | Truncation risk: very long feedback may exceed column size. Use TEXT not VARCHAR. |
| `confidence` as a self-reported float (0.0–1.0) | The LLM's self-assessed certainty is part of the structured schema. It is not a probability calibration — it is a research variable (do higher-confidence LLMs agree more with human graders?). | Low | Academic literature flags LLMs as systematically overconfident (JMIR 2025 medical study: r=−0.40 inverse correlation between confidence and accuracy). This is a known limitation to document in the thesis, not a blocker. Persist the value as-is. |
| Parallel dispatch to all 4 providers | Sequentially calling providers collapses latency differences and introduces ordering bias (later calls may benefit from warmer infrastructure). Parallel dispatch is required for latency to be a valid metric. | Medium | Laravel's `Concurrency::run()` facade (beta) or HTTP pool handles this. The `process` driver works in web request context; `fork` driver is CLI-only. Verify driver choice early. |
| Partial result on provider failure | If one provider fails and the request returns HTTP 500, all four provider responses are lost. A partial array preserves 3/4 of the corpus for that submission. | Medium | Failed providers must be excluded from the response array; their failure must be logged. The HTTP response code should still be 200 with a partial array — callers can inspect `count(results)` to detect missing providers. |
| Persist every result to `diagnostic_results` table | The database IS the research corpus. Fire-and-forget means no thesis dataset. Every row must commit before the response is returned. | Low | Use synchronous DB write inside the Concurrency closures (or after, on the collected results) — not deferred. `Concurrency::defer()` is inappropriate here because it runs after response and has no failure guarantee. |
| Unique `request_id` (UUID) per POST call | Groups all 4 provider results from one submission. Without a shared correlation key, you cannot reconstruct "which four rows came from the same student submission." | Low | Generate a UUID before dispatching to providers. Store on every result row. Return in the API response so callers can reference it. Verified pattern from LLM observability literature (trace_id design). |
| `created_at` timestamp on every result | Corpus grows over time; timestamps allow temporal analysis (e.g., did model updates shift PC³ distributions?). Also required for data governance (LGPD context). | Low | Standard Laravel timestamp. Store in UTC. |
| System prompt versioning | The system prompt instructs LLMs to apply PC³. If the prompt changes mid-study, results before/after are not comparable — this invalidates the corpus. | Low | Store the prompt text or a hash of it in the result row, or version-lock the prompt in config and never change it during data collection. The minimum viable approach is a `prompt_version` string column (e.g., "v1.0"). |

---

## Differentiators

Features that strengthen the academic contribution beyond minimum viability. Not required for the research to be valid, but they increase publishability and analytical depth.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| `latency_ms` per provider result | Latency is a meaningful comparison dimension — a provider that is 4× slower but equally accurate has a different cost-benefit profile. | Low | Measure wall-clock milliseconds from request dispatch to structured response received, per provider. Store alongside tokens. This is a standard LLM observability metric (TTFT + TPOT), but for academic purposes total latency per call is sufficient. |
| `finish_reason` field | Distinguishes normal completion from max-token truncation. A truncated response that is also classified as "Concept" error is less reliable than a complete one. Prism exposes `finishReason` in its response object. | Low | Values: "stop", "length", "content_filter", "error". Truncated responses skew confidence and feedback quality — flagging them allows post-hoc filtering in the analysis. |
| `raw_response` storage (optional, toggled) | Preserves the exact JSON the provider returned before schema parsing. Enables re-analysis if the schema is refined later. Invaluable for a thesis where methodology evolves. | Low | Store as JSON column. Add a config flag `STORE_RAW_RESPONSE=true/false` to keep the table size manageable during development. |
| Inter-rater agreement surface | Compute and store a simple agreement signal: how many of the 4 providers agreed on the same `pc3_category` for this submission. A count column `provider_agreement_count` (1–4) adds a derived metric the thesis can cite directly. | Low | Computed after all 4 results are collected, before persistence. Zero extra API calls. | 
| `exercise_hash` (SHA-256 of the submission) | Allows grouping results by unique (code, statement) pair across multiple API calls. If the same exercise is submitted twice, the researcher can deduplicate or compare run-to-run variance — a reproducibility check on the LLMs themselves. | Low | `hash('sha256', $code . $statement)`. Stored as a fixed-length CHAR(64) column. Enables "same input, same output?" analysis without storing the full code twice. |
| HTTP 206 Partial Content on partial response | Signals to the caller (and to monitoring) that the response is incomplete without requiring them to count results. Standard semantics, zero extra work. | Low | Use HTTP 206 when `count(results) < 4`, HTTP 200 when all 4 succeed. Purely a response code choice. |
| Provider error detail in response | When a provider fails, include a `{"provider": "gemini", "error": "timeout"}` entry in a separate `errors` array in the response body. Gives the researcher visibility into which provider failed and why, without polluting the successful results array. | Low | Logged to DB separately. Not a blocker, but aids in data quality assessment during the study. |

---

## Anti-Features (Deliberate Exclusions)

Things NOT to build for this MVP, with explicit reasoning.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Authentication / API keys on the endpoint | This is an internal research tool used by the researcher alone. Auth adds implementation overhead with zero research value. | Document that the endpoint must not be exposed to the internet without auth. Local or VPN-restricted deployment only. |
| Frontend / UI dashboard | Not an academic requirement. Time spent on UI is time not spent on analysis. | The database rows are the output. Use a DB GUI (TablePlus, phpMyAdmin) for data inspection. |
| Retry logic with exponential backoff | Retries introduce timing noise. If a provider fails once and retries succeed 3 seconds later, the latency figure is meaningless. Research requires clean, first-attempt data. | Log the failure, return partial array. The researcher decides whether to re-submit manually. |
| Rate limiting on the endpoint | The research use pattern is low-volume (one submission per student exercise, not high-frequency). Rate limiting adds complexity with no benefit. | Document expected usage volume. Revisit if the tool is ever opened beyond the researcher. |
| LLM response caching | Caching would return the same result for identical inputs across runs, destroying the ability to measure run-to-run variance — a valid research metric. | Never cache LLM responses. Cache infrastructure configs only. |
| Prompt templating engine / A/B prompt testing | The thesis uses a fixed system prompt. Variable prompts create uncontrolled variables that invalidate inter-provider comparison. | Lock the system prompt at v1.0. Version it in config, not in a dynamic template system. |
| Cost calculation per request | Token pricing varies by provider tier, changes frequently, and is irrelevant to the thesis argument (diagnostic quality vs taxonomy accuracy). | If cost is needed later, compute it offline from token counts and provider pricing sheets. Don't embed pricing logic in the API. |
| Streaming responses | Streaming makes latency measurement complex (TTFT vs TPOT vs total) and structured output delivery unreliable. The added complexity gains nothing for batch academic use. | Use standard (non-streaming) API calls for all providers. Total latency is the only metric needed. |
| Provider routing / load balancing | All 4 providers are called unconditionally on every request. Routing logic implies conditional dispatch, which is incompatible with "compare all 4." | Hard-code the 4 providers. If a provider is down, it contributes a failure entry, not a routing decision. |
| Webhook / async callback on completion | The endpoint is synchronous by design — it waits for all provider responses (or timeouts) before returning. Async callbacks add stateful infrastructure that the research setup does not need. | Keep the request-response cycle synchronous. Set a generous HTTP timeout (e.g., 60 seconds) on the client side. |

---

## Feature Dependencies

What must exist before what can be built.

```
UUID request_id generation
    → All 4 provider dispatches (shared correlation key)
    → Partial array construction (group results by request_id)
    → DiagnosticResult persistence (request_id is FK-equivalent)
    → exercise_hash computation (runs alongside, pre-dispatch)

Structured output schema definition (ObjectSchema in Prism)
    → Provider calls (schema passed to each provider's request)
    → pc3_category enum validation (schema declares the constraint)
    → finish_reason capture (schema includes finish_reason or it's read from metadata)
    → raw_response storage (stored before schema parsing, after raw JSON received)

Provider dispatch (Concurrency::run or HTTP pool)
    → latency_ms measurement (start/stop clock per provider closure)
    → tokens_input / tokens_output (from Prism response metadata)
    → finish_reason (from Prism response metadata)
    → partial failure handling (each closure returns result OR null)

All provider results collected
    → provider_agreement_count computation (count matching pc3_category values)
    → HTTP response code decision (206 vs 200 based on result count)
    → Bulk persistence of result rows

DiagnosticResult persisted
    → Research corpus (the thesis dataset)
    → Temporal analysis (created_at)
    → prompt_version stability guarantee (stored on each row)
```

**Critical path for research validity:**

```
Schema definition → Provider dispatch (parallel) → Validation → Persistence → Response
```

None of the differentiating features (latency_ms, finish_reason, exercise_hash, agreement count) are on the critical path — they can be added in a second pass after the core pipeline works without breaking existing records.

---

## Academic Research-Specific Considerations

These are constraints that do not appear in typical API feature lists but matter for a TCC thesis.

**Reproducibility requirements (HIGH priority):**
- Store exact model ID, not alias. "claude-opus-4" silently changes. Use the pinned snapshot ID from each provider's API config.
- Store `prompt_version`. The system prompt is a controlled variable. If it changes, the pre-change data is from a different experiment.
- Never mutate existing rows. Append-only persistence. If a result is wrong, add a corrected row with a flag, never UPDATE.

**Statistical validity requirements (HIGH priority):**
- `pc3_category` must be an enum enforced at schema level, not validated only in application code. Schema-level enforcement means even a hallucinating provider cannot return a value that passes through silently.
- Self-reported `confidence` is a research variable with known limitations (LLMs are systematically overconfident). Persist it as-is; document the limitation in the thesis methodology section.
- Parallel dispatch is not optional. Sequential dispatch introduces a confound: later providers respond to a "warmer" API endpoint, which may affect latency and token budget.

**Data governance (MEDIUM priority for MVP):**
- The code and statement fields may contain student-identifying information (variable names, comments). The researcher should assess LGPD compliance before sharing the corpus publicly. This is a thesis concern, not an API concern — but the schema should include a `anonymized_at` nullable timestamp column to support future anonymization runs.

---

## Sources

- prism-php structured output: [https://prismphp.com/core-concepts/structured-output/](https://prismphp.com/core-concepts/structured-output/)
- prism-php provider support: [https://prismphp.com/getting-started/introduction/](https://prismphp.com/getting-started/introduction/)
- Laravel Concurrency facade: [https://laravel.com/docs/11.x/concurrency](https://laravel.com/docs/11.x/concurrency)
- LLM observability and trace IDs: [https://langwatch.ai/blog/trace-ids-llm-observability-and-distributed-tracing](https://langwatch.ai/blog/trace-ids-llm-observability-and-distributed-tracing)
- Model versioning for reproducibility: [https://medium.com/the-modern-scientist/reproducible-ai-versioning-models-prompts-and-data-96dd0337af65](https://medium.com/the-modern-scientist/reproducible-ai-versioning-models-prompts-and-data-96dd0337af65)
- LLM self-reported confidence limitations: [https://pmc.ncbi.nlm.nih.gov/articles/PMC12101789/](https://pmc.ncbi.nlm.nih.gov/articles/PMC12101789/)
- LLM confidence improves self-consistency: [https://aclanthology.org/2025.findings-acl.1030.pdf](https://aclanthology.org/2025.findings-acl.1030.pdf)
- Academic reproducibility in LLM benchmarking: [https://arxiv.org/html/2510.25506v3](https://arxiv.org/html/2510.25506v3)
- Latency and token observability metrics: [https://portkey.ai/blog/the-complete-guide-to-llm-observability/](https://portkey.ai/blog/the-complete-guide-to-llm-observability/)
- Parallel HTTP requests in Laravel: [https://medium.com/@ali74.ebrahimpour/efficient-concurrent-requests-handling-in-laravel-using-http-pooling-a40196b551c7](https://medium.com/@ali74.ebrahimpour/efficient-concurrent-requests-handling-in-laravel-using-http-pooling-a40196b551c7)
- LLM error classification taxonomies: [https://arxiv.org/abs/2404.19336](https://arxiv.org/abs/2404.19336)
