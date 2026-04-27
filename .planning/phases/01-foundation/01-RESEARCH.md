# Phase 1: Foundation - Research

**Researched:** 2026-04-27
**Domain:** Laravel 13 scaffold reset, `laravel/ai` SDK installation, PHP-backed enums, Eloquent migrations with CHECK constraints, Repository pattern
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01:** Create `legacy/v1` branch from the current `master` branch — this preserves the entire old codebase (old DTOs, services, models) as an accessible archive.

**D-02:** Reset `main` via an orphan branch — clean break with no legacy history on `main`. Fresh Laravel 13 scaffold is the first commit.

**D-03:** Phase 1 work goes directly on `main` after the reset — no feature branch for this phase. MVP research tool, no parallel contributors.

**D-04:** The orphan reset is the complete answer. Legacy files disappear from `main`; `legacy/v1` preserves them. Phase 1 creates only new files — no explicit migration or documentation step needed.

**D-05:** Use a CHECK constraint in the migration to enforce `Predicate`/`Concept`/`Context` at the DB level. SQLite supports CHECK constraints — this catches bad data even if PHP validation is bypassed.

**D-06:** Also create a PHP-backed enum `Pc3Category` (cases: `Predicate`, `Concept`, `Context`). Used in the DTO and as an Eloquent cast on the model.

**D-07:** Single construction path: `fromPrismResponse()` static factory only. No `fromArray()`. Tests that need a `ProviderResult` must go through a real or faked Laravel AI SDK response.

**D-08:** Confidence clamping (`max(0.0, min(1.0, $value))`) happens inside `fromPrismResponse()` at construction time.

### Claude's Discretion

- Migration column types (string lengths, integer widths) — standard Laravel conventions apply.
- `DiagnosticResultRepository` scope — minimal: `save()` method only for MVP.
- Namespace and file structure — follow Laravel 13 conventions.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| SETUP-01 | Git repo reset to fresh Laravel 13 on `main`, old code preserved on `legacy/v1` | Git orphan branch workflow; `composer create-project laravel/laravel:^13.0` |
| SCHEMA-01 | LLM structured output schema includes all required fields with correct types | `laravel/ai` `HasStructuredOutput` + `JsonSchema` callback pattern confirmed in SKD.md |
| SCHEMA-02 | `pc3_category` enforced as enum at schema level (Predicate/Concept/Context) | `$schema->string()->enum([...])->required()` confirmed in SKD.md nested object example |
| SCHEMA-03 | `confidence` clamped to [0.0, 1.0] in PHP post-processing | Pure PHP: `max(0.0, min(1.0, $value))` in `fromPrismResponse()` factory |
| PERSIST-01 | `DiagnosticResult` Eloquent model and migration | Standard Laravel Eloquent; CHECK constraint on `pc3_category`; `$casts` array with enum |
| PERSIST-03 | `request_id` (UUID) column on every result row | `Str::uuid()` in DTO; `string` column with `unique` constraint optional; `$table->uuid('request_id')` |
| PERSIST-04 | `prompt_version` string column on every row | `$table->string('prompt_version')` — e.g., "v1.0"; hardcoded per-instance at construction |
</phase_requirements>

---

## Summary

Phase 1 establishes the data contract before any live provider call. The work falls into three tracks: (1) git infrastructure — create `legacy/v1` from current `master`, then reset `main` via orphan branch and install fresh Laravel 13; (2) schema/DTO — define the `Pc3Category` PHP-backed enum, the `ProviderResult` DTO with a `fromPrismResponse()` factory that clamps confidence, and the shared Agent schema using `laravel/ai`'s `JsonSchema` callback API; (3) persistence — a `diagnostic_results` migration with all required columns plus a DB-level CHECK constraint on `pc3_category`, the `DiagnosticResult` Eloquent model with enum cast, and a minimal `DiagnosticResultRepository`.

The stack correction is confirmed: `laravel/ai` v0.6.3 (released 2026-04-22) supports both `^12.0|^13.0` Laravel and requires `php: ^8.3`. The machine has PHP 8.4.1, so there is no PHP version blocker. Laravel 13.6.0 is the current latest release (released March 2026). The decision to use Laravel 13 on the fresh `main` is fully executable.

The `laravel/ai` SDK's structured output API uses `JsonSchema $schema` callbacks inside a `schema()` method on an Agent class that implements `HasStructuredOutput`. Fields use fluent methods like `$schema->string()->enum([...])->required()` and `$schema->number()->required()`. The agent response is accessed as an array: `$response['field']`. The factory method name `fromPrismResponse()` (locked in D-07/D-08) is a project-internal convention — the factory receives the `StructuredAgentResponse` (which implements `ArrayAccess`) and extracts field values from it.

**Primary recommendation:** Create the orphan reset and fresh Laravel 13 install first (SETUP-01), then layer in the DTO/enum/schema, then the migration/model/repository. All pieces are independent after the scaffold exists.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | ^13.0 (latest: v13.6.0) | Core framework | Project decision; PHP 8.3+ minimum, released March 2026 |
| laravel/ai | ^0.6.3 | Official Laravel AI SDK | Project decision; unified interface for all 4 providers; wraps prism-php internally |
| SQLite (php ext) | 3.x (bundled) | Persistence | Default in Laravel 13, zero-config, single-file corpus |

### Supporting (installed with fresh Laravel 13 scaffold)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| laravel/tinker | ^2.x | REPL for validation | Manual success criteria verification |
| phpunit/phpunit | ^11.x | Test framework | Unit tests for DTO clamping, enum cases |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| laravel/ai | prism-php/prism | prism-php is what laravel/ai wraps internally; agent abstraction in laravel/ai is what the project selected |
| SQLite | MySQL | MySQL needs a running server; SQLite is zero-config and sufficient for this research tool |
| PHP-backed enum | string constants | Enum gives type safety, IDE autocomplete, and cleaner Eloquent cast |

**Installation (on fresh Laravel 13 scaffold):**

```bash
composer create-project laravel/laravel:^13.0 .
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

**Version verification (confirmed 2026-04-27):**
- `laravel/framework`: latest is v13.6.0 (confirmed via `composer show --available laravel/framework`)
- `laravel/ai`: latest is v0.6.3 (confirmed via `composer show --available laravel/ai`), requires `php: ^8.3`, compatible with `^12.0|^13.0`
- PHP on this machine: 8.4.1 (satisfies `^8.3`)

---

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Ai/
│   └── Agents/          # laravel/ai Agent classes (Phase 2+)
├── DTOs/
│   ├── Pc3Category.php  # PHP-backed enum
│   └── ProviderResult.php  # Immutable DTO
├── Models/
│   └── DiagnosticResult.php  # Eloquent model
└── Repositories/
    └── DiagnosticResultRepository.php  # Minimal save() only
database/
└── migrations/
    └── YYYY_MM_DD_create_diagnostic_results_table.php
```

### Pattern 1: PHP-Backed String Enum

**What:** PHP 8.1+ backed enum with string cases for `pc3_category`.
**When to use:** Any domain value with a fixed set of valid strings that must be validated at both PHP and DB levels.

```php
<?php
// Source: PHP 8.1 official docs — backed enums
namespace App\DTOs;

enum Pc3Category: string
{
    case Predicate = 'Predicate';
    case Concept   = 'Concept';
    case Context   = 'Context';
}
```

### Pattern 2: Immutable DTO with Static Factory

**What:** A readonly PHP class with a single named constructor (`fromPrismResponse()`) that enforces invariants at construction time.
**When to use:** Any external data boundary where you want to guarantee validity on the way in.

The `StructuredAgentResponse` from `laravel/ai` implements `ArrayAccess`. Fields are accessed as `$response['field_name']`.

```php
<?php
// Source: SKD.md — StructuredAgentResponse array access pattern
namespace App\DTOs;

use Laravel\Ai\Responses\StructuredAgentResponse;

final class ProviderResult
{
    public function __construct(
        public readonly string    $provider,
        public readonly string    $model,
        public readonly string    $diagnosis,
        public readonly Pc3Category $pc3Category,
        public readonly string    $feedback,
        public readonly float     $confidence,   // always [0.0, 1.0]
        public readonly int       $tokensInput,
        public readonly int       $tokensOutput,
        public readonly string    $requestId,
        public readonly string    $promptVersion,
    ) {}

    public static function fromPrismResponse(
        StructuredAgentResponse $response,
        string $provider,
        string $model,
        string $requestId,
        string $promptVersion,
    ): self {
        return new self(
            provider:      $provider,
            model:         $model,
            diagnosis:     $response['diagnosis'],
            pc3Category:   Pc3Category::from($response['pc3_category']),
            feedback:      $response['feedback'],
            confidence:    max(0.0, min(1.0, (float) $response['confidence'])),
            tokensInput:   (int) $response['tokens_input'],
            tokensOutput:  (int) $response['tokens_output'],
            requestId:     $requestId,
            promptVersion: $promptVersion,
        );
    }
}
```

**Note on factory name:** `fromPrismResponse()` is the project-locked name (D-07). It does NOT call prism-php directly — the name is historical convention from when prism-php was the planned SDK. The parameter type is `StructuredAgentResponse` from `laravel/ai`. This name must be used as locked.

### Pattern 3: Laravel AI SDK Structured Output Schema

**What:** An Agent implementing `HasStructuredOutput` with a `schema(JsonSchema $schema): array` method.
**When to use:** Any agent that must return a fixed data shape from the LLM.

```php
<?php
// Source: SKD.md §Structured Output
namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class DiagnosticAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a TypeScript error diagnostician...';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'diagnosis'    => $schema->string()->required(),
            'pc3_category' => $schema->string()->enum(['Predicate', 'Concept', 'Context'])->required(),
            'feedback'     => $schema->string()->required(),
            'confidence'   => $schema->number()->required(),
            'tokens_input' => $schema->integer()->required(),
            'tokens_output'=> $schema->integer()->required(),
        ];
    }
}
```

**Note:** `tokens_input` and `tokens_output` in the agent schema represent self-reported values from the LLM response body. Actual token usage is also available via `$response->usage` on the `AgentResponse` in `laravel/ai`; however, for this MVP the structured output fields provide the values.

### Pattern 4: Migration with CHECK Constraint

**What:** SQLite-compatible `CHECK` constraint on the `pc3_category` column, enforced at the database level independently of PHP validation.
**When to use:** Any column with a fixed set of allowed string values where DB-level enforcement is required (D-05).

```php
// Source: Laravel docs — raw column modifiers, SQLite CHECK constraint support
Schema::create('diagnostic_results', function (Blueprint $table) {
    $table->id();
    $table->string('provider');
    $table->string('model');
    $table->text('diagnosis');
    $table->string('pc3_category');
    $table->text('feedback');
    $table->float('confidence');
    $table->integer('tokens_input');
    $table->integer('tokens_output');
    $table->uuid('request_id');
    $table->string('prompt_version');
    $table->timestamps();

    // DB-level enforcement (D-05)
    $table->check(
        "pc3_category IN ('Predicate', 'Concept', 'Context')",
        'check_pc3_category'
    );
});
```

**Important:** Laravel's `Blueprint::check()` method (added in Laravel 10) generates a `CHECK` constraint. SQLite supports CHECK constraints — they are enforced on INSERT/UPDATE.

### Pattern 5: Eloquent Model with Enum Cast

**What:** Casting `pc3_category` to `Pc3Category` enum in the Eloquent model so retrieval returns typed enum values.

```php
<?php
// Source: Laravel docs — Enum Casting
namespace App\Models;

use App\DTOs\Pc3Category;
use Illuminate\Database\Eloquent\Model;

class DiagnosticResult extends Model
{
    protected $fillable = [
        'provider', 'model', 'diagnosis', 'pc3_category',
        'feedback', 'confidence', 'tokens_input', 'tokens_output',
        'request_id', 'prompt_version',
    ];

    protected function casts(): array
    {
        return [
            'pc3_category' => Pc3Category::class,
            'confidence'   => 'float',
            'tokens_input' => 'integer',
            'tokens_output'=> 'integer',
        ];
    }
}
```

### Pattern 6: Minimal Repository

**What:** A thin repository class with a single `save()` method — wraps model persistence to keep the service layer free of Eloquent coupling.

```php
<?php
namespace App\Repositories;

use App\DTOs\ProviderResult;
use App\Models\DiagnosticResult;

class DiagnosticResultRepository
{
    public function save(ProviderResult $dto): DiagnosticResult
    {
        return DiagnosticResult::create([
            'provider'      => $dto->provider,
            'model'         => $dto->model,
            'diagnosis'     => $dto->diagnosis,
            'pc3_category'  => $dto->pc3Category->value,
            'feedback'      => $dto->feedback,
            'confidence'    => $dto->confidence,
            'tokens_input'  => $dto->tokensInput,
            'tokens_output' => $dto->tokensOutput,
            'request_id'    => $dto->requestId,
            'prompt_version'=> $dto->promptVersion,
        ]);
    }
}
```

### Pattern 7: Git Orphan Reset (SETUP-01)

**What:** Create `legacy/v1` from current `master`, then reset `main` using a new orphan branch, then install Laravel 13 scaffold as the first clean commit.

```bash
# Step 1: preserve legacy
git checkout master
git branch legacy/v1
git push origin legacy/v1

# Step 2: create orphan main
git checkout --orphan new-main
git rm -rf .
# install Laravel 13 scaffold into current directory
composer create-project laravel/laravel:^13.0 .

# Step 3: commit scaffold, replace main
git add -A
git commit -m "chore: fresh Laravel 13 scaffold"
git branch -D main 2>/dev/null || true
git branch -m new-main main
git push origin main --force
```

### Anti-Patterns to Avoid

- **Putting confidence clamping in the repository or model:** Clamping must happen in `fromPrismResponse()` (D-08). If a `ProviderResult` ever exists with an out-of-range confidence, the invariant is broken everywhere.
- **Using `fromArray()` as an alternate constructor:** D-07 locks a single construction path. `fromArray()` would allow callers to skip clamping.
- **Forgetting `->value` when persisting enum:** Eloquent `create()` receives the array; `pc3_category` must be `$dto->pc3Category->value` (a string), not the enum object.
- **Omitting `request_id` from the DTO constructor:** `request_id` is passed in at construction time from the service (or test), not generated inside the DTO. The DTO is a data container, not a UUID factory.
- **Using `$table->enum()` instead of `string` + CHECK:** Laravel's `enum()` column type is MySQL-specific. For SQLite compatibility, use `string` + `$table->check(...)`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Structured LLM output parsing | Custom JSON regex/parser | `laravel/ai` `HasStructuredOutput` | Provider quirks, null fields, extra tokens — SDK handles it |
| UUID generation | `bin2hex(random_bytes(16))` | `Str::uuid()` or `Str::orderedUuid()` | Collision-free, RFC 4122 compliant, already in Laravel |
| Enum validation in PHP | `in_array($value, ['Predicate', ...])` | PHP-backed enum `Pc3Category::from()` | Throws `ValueError` on invalid input automatically |
| DB-level enum enforcement | Application-only validation | `$table->check(...)` in migration | PHP can be bypassed via direct DB writes; CHECK cannot |
| Float clamping | Custom clamp helper class | `max(0.0, min(1.0, $value))` inline | One-liner; no abstraction needed |

**Key insight:** The Laravel ecosystem already provides all primitives needed for Phase 1. The risk is building custom solutions and introducing new failure modes in what is meant to be a stable data contract layer.

---

## Runtime State Inventory

Phase 1 involves a git reset (renaming/deleting branches) and a fresh scaffold install. This is not a rename/refactor of running services, but the orphan reset does affect git state.

| Category | Items Found | Action Required |
|----------|-------------|-----------------|
| Stored data | None — no production data; SQLite file has only scaffolded tables | None |
| Live service config | None — no deployed service | None |
| OS-registered state | None — no scheduled tasks, no pm2/launchd processes | None |
| Secrets/env vars | `.env` file is untracked (gitignored) — contains no project-specific keys yet | None; will be recreated from `.env.example` on fresh install |
| Build artifacts | `vendor/` directory present under Laravel 12 — orphan reset wipes it; `composer install` on fresh scaffold recreates it | Recreated automatically during Laravel 13 scaffold install |

---

## Common Pitfalls

### Pitfall 1: `$table->check()` method availability

**What goes wrong:** Developer uses `DB::statement("ALTER TABLE ... ADD CONSTRAINT ...")` because they don't know `Blueprint::check()` exists.
**Why it happens:** The `check()` method was added in Laravel 10 and is not widely documented in tutorials.
**How to avoid:** Use `$table->check("pc3_category IN ('Predicate', 'Concept', 'Context')", 'check_pc3_category')` directly in the migration closure.
**Warning signs:** Raw `DB::statement` in a migration for a CHECK constraint.

### Pitfall 2: `laravel/ai` SDK publishes its own migration

**What goes wrong:** Running `php artisan migrate` after publishing creates `agent_conversations` and `agent_conversation_messages` tables that are irrelevant to Phase 1 but not harmful.
**Why it happens:** `vendor:publish --provider="Laravel\Ai\AiServiceProvider"` publishes SDK migrations by default (per SKD.md installation instructions).
**How to avoid:** These tables are safe to have in the DB. Phase 1 does not use them. Do not attempt to suppress the SDK migrations.
**Warning signs:** Migration output showing unexpected tables is normal, not an error.

### Pitfall 3: Enum cast writes enum object instead of value

**What goes wrong:** `DiagnosticResult::create(['pc3_category' => $dto->pc3Category, ...])` silently writes `null` or throws a type error because the cast expects a string input during `create()`.
**Why it happens:** The Eloquent enum cast converts a `string` to `Pc3Category` on READ. On WRITE via `create()`, you must pass the string value (`->value`), not the enum instance.
**How to avoid:** Always write `'pc3_category' => $dto->pc3Category->value` in the repository's `save()` method.
**Warning signs:** `pc3_category` is `null` in the DB after a `save()` call; or a `ValueError` from `Pc3Category::from(null)` on subsequent reads.

### Pitfall 4: Orphan reset leaves remote `main` ahead

**What goes wrong:** After creating the orphan `main` locally and force-pushing, CI or other developers have a cached remote `origin/main` that points to the old history.
**Why it happens:** `--orphan` creates a branch with no history, but remote already has history on `main`.
**How to avoid:** The force-push (`git push origin main --force`) is required. Document the reset in a commit message or PR description so collaborators know to re-clone. Since D-03 says no parallel contributors, this is low risk.
**Warning signs:** `git pull` after reset complains about unrelated histories.

### Pitfall 5: `fromPrismResponse()` receives wrong response type

**What goes wrong:** In Phase 2, a developer accidentally passes a plain `AgentResponse` (text response) instead of `StructuredAgentResponse` to `fromPrismResponse()`.
**Why it happens:** The SDK returns different response types based on whether the agent implements `HasStructuredOutput`.
**How to avoid:** Type-hint the factory parameter as `StructuredAgentResponse`. PHP will throw a `TypeError` at call time if the wrong type is passed.
**Warning signs:** `StructuredAgentResponse` is in namespace `Laravel\Ai\Responses\StructuredAgentResponse` (confirm at implementation time).

### Pitfall 6: `laravel/ai` requires php ^8.3 — not ^8.2

**What goes wrong:** Fresh Laravel 13 scaffold pins `php: ^8.3` in its `composer.json`. Environments with PHP 8.2 will fail to install.
**Why it happens:** Laravel 13 raised the minimum PHP version from 8.2 (Laravel 12) to 8.3.
**How to avoid:** The machine has PHP 8.4.1, which satisfies `^8.3`. This is not a blocker here. Document the requirement clearly in any deployment notes.
**Warning signs:** `composer install` fails with PHP version constraint error on a CI runner.

---

## Code Examples

Verified patterns from official sources:

### laravel/ai Structured Output Schema (from SKD.md)

```php
// Source: SKD.md §Structured Output
public function schema(JsonSchema $schema): array
{
    return [
        'score'    => $schema->integer()->required(),
        'feedback' => $schema->string()->required(),
        // Enum using string with enum() constraint:
        'metadata' => $schema->object(fn ($schema) => [
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
        ])->required(),
    ];
}
```

For the diagnostic agent, enum is top-level (not nested):
```php
'pc3_category' => $schema->string()->enum(['Predicate', 'Concept', 'Context'])->required(),
'confidence'   => $schema->number()->required(),
```

### Accessing StructuredAgentResponse (from SKD.md)

```php
// Source: SKD.md §Structured Output — "you can access the returned StructuredAgentResponse like an array"
$response = (new DiagnosticAgent)->prompt('...');
$score = $response['score'];        // integer
$feedback = $response['feedback'];  // string
```

### Agent Class Skeleton with Structured Output (from SKD.md)

```php
// Source: SKD.md §Agents §Structured Output
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class DiagnosticAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string { ... }

    public function schema(JsonSchema $schema): array { ... }
}
```

### PHP-Backed Enum (PHP 8.1+, standard Laravel pattern)

```php
enum Pc3Category: string
{
    case Predicate = 'Predicate';
    case Concept   = 'Concept';
    case Context   = 'Context';
}

// Usage
Pc3Category::from('Predicate');  // returns Pc3Category::Predicate
Pc3Category::from('Invalid');    // throws ValueError
```

### Migration CHECK Constraint (Laravel 10+)

```php
// Source: Laravel docs — Blueprint::check() added Laravel 10
$table->string('pc3_category');
$table->check(
    "pc3_category IN ('Predicate', 'Concept', 'Context')",
    'check_pc3_category'
);
```

### Confidence Clamping in Factory

```php
// No external library needed — pure PHP
'confidence' => max(0.0, min(1.0, (float) $response['confidence'])),
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| prism-php/prism directly | laravel/ai (wraps prism-php) | Feb 2026 (laravel/ai v0.1.0) | Higher-level Agent abstraction; parallel dispatch pattern changes |
| Laravel 12 scaffold | Laravel 13 scaffold | March 2026 (v13.0.0) | PHP min bumped to 8.3; minor evolutionary changes, no breaking migration concerns on fresh install |
| `ObjectSchema` / `EnumSchema` (prism-php) | `JsonSchema` callback (laravel/ai) | On adoption of laravel/ai | Different API surface; prism concepts don't map 1:1 |

**Deprecated/outdated references in REQUIREMENTS.md and ROADMAP.md:**
- "prism-php/prism" and "prism ObjectSchema/EnumSchema" → map to `laravel/ai` `JsonSchema` API
- "Laravel 12" in ROADMAP.md → resolve to Laravel 13 on fresh `main`
- `fromPrismResponse()` factory name → kept by locked decision D-07, but the SDK parameter type is `StructuredAgentResponse` from `laravel/ai`
- SCHEMA-03 in REQUIREMENTS.md reads "in `ProviderResult::fromPrismResponse()`" → correct method name, confirmed by D-07

---

## Open Questions

1. **Exact namespace of `StructuredAgentResponse`**
   - What we know: SKD.md confirms agents with `HasStructuredOutput` return a response "like an array"; the Testing section documents `fake()` returning this type.
   - What's unclear: The exact fully qualified class name (`Laravel\Ai\Responses\StructuredAgentResponse` vs another namespace) is not explicitly stated in SKD.md. The class appears in the streaming section as `StreamedAgentResponse` and in the testing section implicitly.
   - Recommendation: At implementation time, check `vendor/laravel/ai/src/Responses/` after `composer require laravel/ai` to confirm the FQN. Type the factory parameter accordingly.

2. **Whether `tokens_input`/`tokens_output` should come from schema fields or `$response->usage`**
   - What we know: SKD.md mentions `$response->text`, `$response->events`, `$response->usage` on `StreamedAgentResponse`. For non-streamed structured output, `StructuredAgentResponse` is accessed as array — it's unclear if `->usage` is also exposed.
   - What's unclear: If `StructuredAgentResponse` exposes `->usage->inputTokens` etc., those values would be more authoritative than LLM-self-reported values via the schema fields.
   - Recommendation: Include `tokens_input`/`tokens_output` as schema fields for now (per SCHEMA-01). If `->usage` is accessible on `StructuredAgentResponse`, prefer it in Phase 2 when the agent is wired. For Phase 1, defining the schema fields is sufficient.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel 13, laravel/ai | Yes | 8.4.1 (satisfies ^8.3) | — |
| Composer | Package management | Yes | 2.8.3 | — |
| Git | SETUP-01 branch operations | Yes | (system git) | — |
| SQLite (php-sqlite3 ext) | Default DB for Laravel | Yes | bundled with PHP 8.4 | — |
| Internet / Packagist | `composer create-project` for Laravel 13 | Yes (assumed) | — | — |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:** None identified.

---

## Sources

### Primary (HIGH confidence)
- `SKD.md` in project root — Official `laravel/ai` SDK documentation; Agent/schema/structured output patterns read directly
- `laravel/ai` on Packagist (verified via `composer show --available laravel/ai` 2026-04-27) — v0.6.3 is latest, requires `php: ^8.3`, supports `^12.0|^13.0`
- `laravel/framework` on Packagist (verified via `composer show --available laravel/framework` 2026-04-27) — Laravel 13 is available as v13.6.0
- PHP 8.4.1 on machine (verified via `php --version`) — satisfies `^8.3`

### Secondary (MEDIUM confidence)
- [Laravel 13 Release Notes](https://laravel.com/docs/13.x/releases) — PHP 8.3 minimum confirmed; released March 17, 2026
- [laravel/ai Packagist page](https://packagist.org/packages/laravel/ai) — v0.6.3 released 2026-04-22; `^12.0|^13.0` framework requirement

### Tertiary (LOW confidence)
- None — all critical claims verified through primary sources above.

---

## Metadata

**Confidence breakdown:**
- SETUP-01 (git orphan reset): HIGH — standard git operations, well-documented
- SCHEMA-01/SCHEMA-02 (laravel/ai schema API): HIGH — read directly from SKD.md
- SCHEMA-03 (confidence clamping): HIGH — pure PHP, no external dependency
- PERSIST-01/PERSIST-03/PERSIST-04 (migration, model, repository): HIGH — standard Laravel Eloquent patterns
- `StructuredAgentResponse` FQN: MEDIUM — type confirmed to exist, exact namespace requires `vendor/` inspection at install time

**Research date:** 2026-04-27
**Valid until:** 2026-05-27 (stable stack; `laravel/ai` is moving fast — re-verify if > 30 days)
