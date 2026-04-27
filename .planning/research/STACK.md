# Stack Research: Laravel AI Broker

**Project:** TCC Laravel AI Broker (pc3-submission-evaluator)
**Researched:** 2026-04-27
**Research mode:** Ecosystem — Stack dimension

---

## Recommended Stack

| Package | Version | Purpose | Rationale |
|---------|---------|---------|-----------|
| laravel/framework | ^12.0 | Core framework | Project requirement; PHP 8.2+, Symfony 7, Carbon 3 |
| prism-php/prism | ^0.100 | Unified LLM interface | Only package with confirmed first-party support for all 4 required providers |
| laravel/sanctum | (bundled) | API plumbing | Out of scope for MVP but included in skeleton |
| SQLite (native PHP ext) | 3.x | Persistence | Default in Laravel 12, zero-config, single-file corpus |

**Installation:**
```bash
composer require prism-php/prism
```

No separate provider packages are needed — all four required providers are bundled inside `prism-php/prism`.

---

## AI SDK Decision

### Winner: `prism-php/prism` — not `laravel/ai`

**Evidence:**

| Criterion | prism-php/prism | laravel/ai |
|-----------|----------------|------------|
| DeepSeek text support | YES (first-party) | YES |
| DeepSeek structured output | Partially documented (JSON mode via provider quirks — see warning below) | NOT listed in official feature matrix |
| Gemini text support | YES (first-party, confirmed via docs) | YES |
| Anthropic text support | YES (GA structured output since v0.100.0) | YES |
| OpenAI text support | YES | YES |
| Stability | v0.100.1, stable | v0.6.3, released Feb 2026, still relatively new |
| Laravel 12 compat | Confirmed — composer.json: `^11.0|^12.0|^13.0` | Confirmed — documented for Laravel 12.x and 13.x |
| PHP requirement | ^8.2 | Not separately specified (inherits Laravel's ^8.2) |
| Level of abstraction | Lower — fluent builder interface | Higher — Agent class pattern (wraps Prism internally) |
| Parallel calls | Direct — wrap in `Http::pool()` or `Concurrently` | Agent-centric — parallel requires custom orchestration |

**Why prism-php wins for this project:**

`laravel/ai` is a higher-level abstraction layer that sits *on top of* Prism (confirmed by Taylor Otwell). For this project the lower abstraction is actually an advantage: the broker dispatches calls to 4 providers in parallel, collects raw responses, and maps them into `DiagnosticResult` rows. The prism fluent builder (`Prism::text()->using(...)->withSchema(...)`) maps cleanly onto that pattern. The Agent class model in `laravel/ai` is optimised for a single provider per agent invocation, which requires extra orchestration to parallelise.

Additionally, `laravel/ai` was announced on **February 5, 2026** and is at v0.6.3 as of April 2026 — it is evolving quickly and its DeepSeek structured output support is **not listed** in the official feature matrix. For a research corpus where reproducibility matters, the more mature package is the safer choice.

**The package name used today is `prism-php/prism`** (the old `echolabsdev/prism` name is the predecessor on Packagist but points to the same repository; the canonical install command is `composer require prism-php/prism`).

---

## Provider Support Matrix

| Provider | Config key | Model to use | Text | Structured Output | Notes |
|----------|------------|-------------|------|-------------------|-------|
| Anthropic (Claude) | `anthropic` | `claude-sonnet-4-5` or newer | YES | YES — native (GA since prism v0.100.0, Claude Sonnet 4.5+); falls back to tool calling for older models | Highest structured output fidelity |
| OpenAI (GPT-4) | `openai` | `gpt-4o` or `gpt-4-turbo` | YES | YES — strict mode, requires `ObjectSchema` as root | Fully supported |
| Google Gemini | `gemini` | `gemini-2.0-flash` or `gemini-1.5-pro` | YES | YES — full schema support; `AnyOfSchema` requires Gemini 2.5+ | First-party in prism-php (confirmed via prismphp.com/providers/gemini/) |
| DeepSeek | `deepseek` | `deepseek-chat` | YES | PARTIAL — JSON mode only (`response_format: json_object`); prompt must contain the word "json"; no strict schema enforcement | See warning below |

**Config block in `config/prism.php`:**
```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY', ''),
],
'openai' => [
    'api_key' => env('OPENAI_API_KEY', ''),
    'url'     => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
],
'gemini' => [
    'api_key' => env('GEMINI_API_KEY', ''),
    'url'     => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
],
'deepseek' => [
    'api_key' => env('DEEPSEEK_API_KEY', ''),
    'url'     => env('DEEPSEEK_URL', 'https://api.deepseek.com/v1'),
],
```

**DeepSeek structured output warning (MEDIUM confidence):**
GitHub issue #136 (opened Jan 2025, still labelled "planned") requested formal DeepSeek structured output support in prism-php. As of prism v0.100.1 (March 2026), no dedicated implementation has shipped. The DeepSeek provider docs at prismphp.com do not mention structured output. The provider *does* expose a JSON mode compatible with OpenAI's `response_format: json_object`, meaning the prompt must include the word "json" and the model returns a JSON string — but schema enforcement is not guaranteed. The workaround is to instruct the LLM via the system prompt to produce the exact JSON shape and parse/validate the response in application code. This is acceptable for the MVP given that all 4 providers returning the same shape is enforced by the system prompt anyway.

---

## Database

**Recommendation: SQLite for this MVP**

| Criterion | SQLite | MySQL |
|-----------|--------|-------|
| Setup effort | Zero — single file, default in Laravel 12 | Requires server, credentials, Docker or system install |
| Portability | Single `.sqlite` file, trivially shared between researchers | Schema dump + data export needed to share corpus |
| Concurrency | Single-writer; acceptable for sequential HTTP requests in a research tool | Multi-writer, unnecessary for single-endpoint MVP |
| Laravel Cloud compat | Not supported (ephemeral filesystem) | Supported | 
| Academic corpus backup | `cp database/database.sqlite backup.sqlite` | Requires `mysqldump` |
| Performance at research scale | Benchmarks show read performance at 2.72ms median; write locks are per-DB not per-row, acceptable for low concurrency | Better for high concurrent writes |

**Why SQLite wins:** The project has one endpoint, no concurrent writers (research tool, not production SaaS), and the entire corpus being a single file is a research reproducibility feature. Laravel 12 ships with SQLite as the default database. Zero additional infrastructure.

**When to switch to MySQL/PostgreSQL:** If the project ever needs Laravel Cloud deployment or expects simultaneous parallel research sessions writing to the DB at high volume. For the current scope, SQLite is correct.

---

## Laravel 12 Notes

| Area | Laravel 11 | Laravel 12 | Impact |
|------|-----------|-----------|--------|
| Carbon dependency | Carbon 2.x | Carbon 3.x | Breaking if any code calls removed Carbon 2 methods; unlikely to matter on a fresh install |
| Symfony dependency | Symfony 6 | Symfony 7 | Internal; no action needed |
| Default database | SQLite (since L11) | SQLite (unchanged) | No change |
| Routing engine | Standard | Optimised (~20% faster) | Free performance win |
| `Http::batch()` | Not available | Added in L12.32+ | Prefer `Http::pool()` for parallel provider calls (available in L11 and L12); `Http::batch()` is a newer alternative |
| Feature flags / major changes | — | No major new framework-level features vs L11 | L12 is deliberately evolutionary, not revolutionary |
| `whereRelation()` on Eloquent | Not available | Available | Useful for querying DiagnosticResult by provider but not critical |
| Breaking changes vs L11 | — | Minimal — primarily Carbon 3 | Fresh install is unaffected; only matters when upgrading an existing L11 app |

**Practical conclusion:** Laravel 12 vs 11 is a non-issue for a greenfield project. The only dependency to pin is `prism-php/prism` to avoid breaking changes from rapid minor releases (the docs explicitly recommend pinning: `"prism-php/prism": "^0.100"`).

---

## Parallelism Strategy

Prism itself does not expose an async/parallel dispatch API. The correct pattern for calling all 4 providers concurrently is Laravel's built-in `Http::pool()` or wrapping each prism call in a Laravel `Concurrently` closure:

```php
// Pattern A: Http::pool() — if calling provider APIs directly
// Pattern B: Wrap Prism calls in concurrent jobs/fibers

use Illuminate\Support\Facades\Concurrently;

[$anthropic, $openai, $gemini, $deepseek] = Concurrently::run([
    fn() => $this->callProvider('anthropic', $prompt),
    fn() => $this->callProvider('openai', $prompt),
    fn() => $this->callProvider('gemini', $prompt),
    fn() => $this->callProvider('deepseek', $prompt),
]);
```

`Concurrently` (added in Laravel 11, available in L12) spawns concurrent fibers. Exceptions from individual providers are caught per-fiber, enabling the partial-result pattern the project requires.

---

## What NOT to Use

| Package | Why Not |
|---------|---------|
| `laravel/ai` | Built on prism-php; Agent class model not optimised for multi-provider parallel dispatch; DeepSeek structured output not in feature matrix; younger codebase |
| `openai-php/client` | Vendor-locked; would need 4 separate clients, no unified interface |
| `anthropic/anthropic-sdk-php` | Same problem — provider-specific, no unified abstraction |
| Individual provider SDKs | Defeats the purpose of a broker; maintenance burden × 4 |
| MySQL for MVP | Unnecessary infrastructure for a single-writer research tool |

---

## Confidence Levels

| Area | Level | Verification Source |
|------|-------|---------------------|
| prism-php/prism Laravel 12 compatibility | HIGH | Official composer.json: `^11.0|^12.0|^13.0`; PHP `^8.2` |
| Gemini is first-party in prism-php | HIGH | prismphp.com/providers/gemini/ — listed in main providers nav with full config docs |
| Anthropic structured output (GA) | HIGH | prism-php release notes v0.100.0: "GA structured output support" |
| OpenAI structured output | HIGH | prismphp.com/core-concepts/structured-output/ — documented with strict mode |
| Gemini structured output | HIGH | prismphp.com/providers/gemini/ — documented with schema types |
| DeepSeek structured output (prism-php) | LOW | Issue #136 labelled "planned" (Jan 2025), not in release notes through v0.100.1; provider docs on prismphp.com do not document it |
| laravel/ai DeepSeek structured output | LOW | Feature matrix in official docs does not list DeepSeek under structured output |
| laravel/ai is built on prism-php | HIGH | Taylor Otwell quote in official Laravel blog post |
| SQLite recommendation | HIGH | Laravel News article + official Laravel 12 default |
| Laravel 12 vs 11 delta | MEDIUM | Multiple secondary sources consistent; no single authoritative changelog link verified |

---

## Sources

- [prism-php/prism — GitHub](https://github.com/prism-php/prism)
- [Prism Introduction (prismphp.com)](https://prismphp.com/getting-started/introduction/)
- [Prism Installation (prismphp.com)](https://prismphp.com/getting-started/installation.html)
- [Prism Gemini Provider docs](https://prismphp.com/providers/gemini/)
- [Prism DeepSeek Provider docs](https://prismphp.com/providers/deepseek/) — no structured output docs
- [Prism Structured Output docs](https://prismphp.com/core-concepts/structured-output/)
- [prism-php/prism releases (GitHub)](https://github.com/prism-php/prism/releases)
- [DeepSeek structured output issue #136 (GitHub)](https://github.com/prism-php/prism/issues/136)
- [laravel/ai official docs (Laravel 12.x)](https://laravel.com/docs/12.x/ai-sdk)
- [laravel/ai official docs (Laravel 13.x)](https://laravel.com/docs/13.x/ai-sdk)
- [Introducing the Laravel AI SDK (official blog)](https://laravel.com/blog/introducing-the-laravel-ai-sdk)
- [Laravel AI SDK vs Prism vs MCP (official blog)](https://laravel.com/blog/laravel-ai-sdk-boost-or-mcp-which-tool-do-you-need)
- [laravel/ai on Packagist](https://packagist.org/packages/laravel/ai)
- [echolabsdev/prism on Packagist](https://packagist.org/packages/echolabsdev/prism) (legacy name, same repo)
- [Using SQLite in production with Laravel (Laravel News)](https://laravel-news.com/using-sqlite-in-production-with-laravel)
- [Laravel HTTP pool — parallel requests](https://saasykit.com/blog/how-to-make-parallel-http-requests-in-laravel-and-when-you-should)
