<!-- GSD:project-start source:PROJECT.md -->
## Project

**TCC Laravel AI Broker — MVP**

A Laravel 12 REST API that acts as a broker between TypeScript error diagnostic requests and
four LLM providers (Claude, GPT-4, Gemini, DeepSeek). The API receives a piece of buggy TypeScript
code and an exercise statement, dispatches the same prompt to all providers in parallel, and returns
a structured diagnostic array — one entry per provider. Built for academic research (TCC) comparing
LLM diagnostic quality using the PC³ taxonomy (Predicate, Concept, Context).

**Core Value:** All four providers respond to a single diagnostic request in parallel and every result is persisted
— making multi-LLM comparison reproducible.

### Constraints

- **Tech Stack**: Laravel 12 — the project targets the latest stable Laravel
- **AI SDK**: prism-php/prism (or official Laravel AI package) — must support all 4 providers
- **Parallelism**: All provider calls must be concurrent, not sequential
- **Persistence**: Every result saved — no fire-and-forget; DiagnosticResult is the research corpus
- **Scope**: Single endpoint, no auth, no frontend — MVP only
<!-- GSD:project-end -->

<!-- GSD:stack-start source:research/STACK.md -->
## Technology Stack

## Recommended Stack
| Package | Version | Purpose | Rationale |
|---------|---------|---------|-----------|
| laravel/framework | ^12.0 | Core framework | Project requirement; PHP 8.2+, Symfony 7, Carbon 3 |
| prism-php/prism | ^0.100 | Unified LLM interface | Only package with confirmed first-party support for all 4 required providers |
| laravel/sanctum | (bundled) | API plumbing | Out of scope for MVP but included in skeleton |
| SQLite (native PHP ext) | 3.x | Persistence | Default in Laravel 12, zero-config, single-file corpus |
## AI SDK Decision
### Winner: `prism-php/prism` — not `laravel/ai`
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
## Provider Support Matrix
| Provider | Config key | Model to use | Text | Structured Output | Notes |
|----------|------------|-------------|------|-------------------|-------|
| Anthropic (Claude) | `anthropic` | `claude-sonnet-4-5` or newer | YES | YES — native (GA since prism v0.100.0, Claude Sonnet 4.5+); falls back to tool calling for older models | Highest structured output fidelity |
| OpenAI (GPT-4) | `openai` | `gpt-4o` or `gpt-4-turbo` | YES | YES — strict mode, requires `ObjectSchema` as root | Fully supported |
| Google Gemini | `gemini` | `gemini-2.0-flash` or `gemini-1.5-pro` | YES | YES — full schema support; `AnyOfSchema` requires Gemini 2.5+ | First-party in prism-php (confirmed via prismphp.com/providers/gemini/) |
| DeepSeek | `deepseek` | `deepseek-chat` | YES | PARTIAL — JSON mode only (`response_format: json_object`); prompt must contain the word "json"; no strict schema enforcement | See warning below |
## Database
| Criterion | SQLite | MySQL |
|-----------|--------|-------|
| Setup effort | Zero — single file, default in Laravel 12 | Requires server, credentials, Docker or system install |
| Portability | Single `.sqlite` file, trivially shared between researchers | Schema dump + data export needed to share corpus |
| Concurrency | Single-writer; acceptable for sequential HTTP requests in a research tool | Multi-writer, unnecessary for single-endpoint MVP |
| Laravel Cloud compat | Not supported (ephemeral filesystem) | Supported | 
| Academic corpus backup | `cp database/database.sqlite backup.sqlite` | Requires `mysqldump` |
| Performance at research scale | Benchmarks show read performance at 2.72ms median; write locks are per-DB not per-row, acceptable for low concurrency | Better for high concurrent writes |
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
## Parallelism Strategy
## What NOT to Use
| Package | Why Not |
|---------|---------|
| `laravel/ai` | Built on prism-php; Agent class model not optimised for multi-provider parallel dispatch; DeepSeek structured output not in feature matrix; younger codebase |
| `openai-php/client` | Vendor-locked; would need 4 separate clients, no unified interface |
| `anthropic/anthropic-sdk-php` | Same problem — provider-specific, no unified abstraction |
| Individual provider SDKs | Defeats the purpose of a broker; maintenance burden × 4 |
| MySQL for MVP | Unnecessary infrastructure for a single-writer research tool |
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
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

Conventions not yet established. Will populate as patterns emerge during development.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

Architecture not yet mapped. Follow existing patterns found in the codebase.
<!-- GSD:architecture-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd:quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd:debug` for investigation and bug fixing
- `/gsd:execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd:profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
