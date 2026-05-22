---
phase: quick
plan: 260522-kxk
type: execute
wave: 1
depends_on: []
files_modified:
  - app/DTOs/ErrorCode.php
  - app/Services/DiagnosticPromptBuilder.php
  - app/Ai/Agents/DiagnosticAgent.php
  - app/DTOs/ProviderResult.php
  - app/Models/DiagnosticResult.php
  - database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php
  - app/Repositories/DiagnosticResultRepository.php
  - tests/Unit/DTOs/ErrorCodeTest.php
  - tests/Unit/DTOs/ProviderResultTest.php
  - tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php
  - tests/Feature/Persistence/DiagnosticResultPersistenceTest.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "The LLM is instructed to classify each error into one of 10 specific codes or NONE"
    - "The structured-output schema requires an error_code field constrained to the 11 valid values"
    - "ProviderResult carries a typed ErrorCode and maps it from the LLM response"
    - "Every persisted diagnostic_results row stores a CHECK-constrained error_code"
  artifacts:
    - path: "app/DTOs/ErrorCode.php"
      provides: "Backed string enum with 11 cases (B6, B8, B9, B12, C1, C3, C8, G3, G4, H1, NONE)"
      contains: "enum ErrorCode"
    - path: "database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php"
      provides: "error_code column with CHECK constraint on diagnostic_results"
      contains: "add_error_code"
  key_links:
    - from: "app/Ai/Agents/DiagnosticAgent.php"
      to: "app/DTOs/ErrorCode.php"
      via: "enum() values must match the 11 ErrorCode cases"
      pattern: "error_code.*enum"
    - from: "app/Repositories/DiagnosticResultRepository.php"
      to: "diagnostic_results.error_code"
      via: "create() array includes error_code => dto->errorCode->value"
      pattern: "error_code.*errorCode"
---

<objective>
Add a specific `error_code` field to the diagnostic pipeline so the broker classifies each
TypeScript error into one of 10 fine-grained codes (or NONE), in addition to the existing
3-category PC³ classification.

Purpose: The PC³ taxonomy's 3 high-level buckets (Predicate/Concept/Context) are too coarse
for the TCC research corpus. A specific error code per result enables fine-grained multi-LLM
comparison.
Output: New `ErrorCode` enum, an updated v2.0 prompt, an expanded LLM schema, an `errorCode`
DTO property, a CHECK-constrained DB column, and a repository write — all covered by tests.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@./CLAUDE.md

<interfaces>
<!-- Existing contracts the executor must extend. Use these directly — no exploration needed. -->

From app/DTOs/Pc3Category.php (pattern to mirror for the new ErrorCode enum):
```php
enum Pc3Category: string
{
    case Predicate = 'Predicate';
    case Concept   = 'Concept';
    case Context   = 'Context';
}
```

From app/DTOs/ProviderResult.php — private constructor + static factory:
```php
private function __construct(
    public readonly string $provider, public readonly string $model,
    public readonly string $diagnosis, public readonly Pc3Category $pc3Category,
    public readonly string $feedback, public readonly float $confidence,
    public readonly int $tokensInput, public readonly int $tokensOutput,
    public readonly string $requestId, public readonly string $promptVersion,
) {}
// fromPrismResponse(StructuredAgentResponse $response, string $provider, string $model,
//                   string $requestId, string $promptVersion): self
// maps: Pc3Category::from((string) $response['pc3_category'])
```

From app/Ai/Agents/DiagnosticAgent.php — schema() returns a keyed array of JsonSchema types:
```php
'pc3_category' => $schema->string()->enum(['Predicate', 'Concept', 'Context'])->required(),
```

From the migration — SQLite CHECK constraints use rawColumn() (Blueprint::check() absent in L13):
```php
$table->rawColumn(
    'pc3_category',
    "varchar not null constraint check_pc3_category check (pc3_category IN ('Predicate', 'Concept', 'Context'))"
);
```

From app/Repositories/DiagnosticResultRepository.php — create() writes enum `->value`, not the enum object.

WARNING — existing source-level tests will break and MUST be updated in this plan:
- tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php asserts `array_keys($shape)` is exactly
  the 6 current keys, and counts `->required()` occurrences as `>= 6`. Adding error_code
  changes both. Add 'error_code' to the expected key list (after 'pc3_category') and keep the
  count assertion (it stays `>= 6`, still passes — but verify it).
- tests/Unit/DTOs/ProviderResultTest.php and tests/Feature/Persistence/DiagnosticResultPersistenceTest.php
  use makeStubResponse()/makeResponse() helpers — every stub array MUST gain an 'error_code' key
  or `ProviderResult::fromPrismResponse()` will read a missing offset.
</interfaces>

The 10 error codes plus NONE (case name => description for the prompt):
- B6  — while loop used where a single boolean check (if) was intended
- B8  — if-else structure is incorrect (missing branches, wrong order)
- B9  — else-if re-tests a condition already proven false by a preceding branch
- B12 — consecutive identical if conditions with different bodies (should be if-else-if)
- C1  — while guard condition explicitly re-checked inside the loop body
- C3  — operations inside loop body are invariant to the iteration (redundant)
- C8  — for loop's counter variable overwritten inside the loop body
- G3  — multiple variable declarations crammed into a single line
- G4  — identifiers use non-descriptive names (a, x1, n, etc.)
- H1  — statements with no effect (computed values discarded, unreachable code)
- NONE — no error matching any of the above patterns
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create ErrorCode enum and update the v2.0 prompt + LLM schema</name>
  <files>app/DTOs/ErrorCode.php, app/Services/DiagnosticPromptBuilder.php, app/Ai/Agents/DiagnosticAgent.php, tests/Unit/DTOs/ErrorCodeTest.php, tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php</files>
  <behavior>
    - ErrorCode enum: 11 cases with backing strings — B6='B6', B8='B8', B9='B9', B12='B12',
      C1='C1', C3='C3', C8='C8', G3='G3', G4='G4', H1='H1', None='NONE'.
    - ErrorCode::from('NONE') returns ErrorCode::None; ErrorCode::from('B6') returns ErrorCode::B6.
    - ErrorCode::from('Invalid') throws ValueError.
    - ErrorCode::cases() has count 11.
    - DiagnosticAgent::schema() array_keys becomes exactly:
      ['diagnosis', 'pc3_category', 'error_code', 'feedback', 'confidence', 'tokens_input', 'tokens_output'].
    - DiagnosticAgent source contains `'error_code'` with an `->enum([...])` listing all 11 values, `->required()`.
    - DiagnosticPromptBuilder::promptVersion() returns 'v2.0'.
    - DiagnosticPromptBuilder::systemPrompt() contains every code token (B6, B8, B9, B12, C1, C3, C8, G3, G4, H1, NONE)
      and the literal `error_code` JSON field name.
  </behavior>
  <action>
    1. Create app/DTOs/ErrorCode.php — `namespace App\DTOs;`, `declare(strict_types=1);`,
       `enum ErrorCode: string` with the 11 cases above (declare `None = 'NONE'` last,
       all others use case name === backing string). Mirror the structure of Pc3Category.php exactly.

    2. Update app/Services/DiagnosticPromptBuilder.php:
       - Rewrite the SYSTEM_PROMPT heredoc. KEEP the existing PC³ taxonomy explanation
         (Predicate/Concept/Context paragraphs — pc3_category is unchanged). ADD a new section
         titled e.g. "## Specific Error Codes" listing all 10 codes with their descriptions
         (see <context> list) plus the NONE fallback rule.
       - In the JSON output spec section, add a new bullet between `pc3_category` and `feedback`:
         `` - `error_code`: exactly one of "B6", "B8", "B9", "B12", "C1", "C3", "C8", "G3", "G4", "H1", or "NONE" ``
       - Change promptVersion() return value from 'v1.0' to 'v2.0'.

    3. Update app/Ai/Agents/DiagnosticAgent.php schema(): add a new entry right after
       `'pc3_category'`:
       `'error_code' => $schema->string()->enum(['B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'])->required(),`
       Update the docblock comment count if it mentions a field count.

    4. Create tests/Unit/DTOs/ErrorCodeTest.php — mirror Pc3CategoryTest.php structure: assert
       backing strings, `from()` round-trip for a sample (B6, B12, NONE), `ValueError` on unknown
       input, and `cases()` count of 11.

    5. Update tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php:
       - In the "declares the ... expected schema keys" test, change the expected `array_keys`
         array to include `'error_code'` after `'pc3_category'` (7 keys total). Rename the test
         description from "six" to "seven" if it says "six".
       - In the "declares pc3_category as a string enum" source-check test, ADD an assertion that
         the source contains `'error_code'` and contains
         `->enum(['B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'])`.
       - The `->required()` count test asserts `>= 6` — leave it (still true), but bump the
         minimum to `>= 7` to reflect the new field.
  </action>
  <verify>
    <automated>cd /home/rec/Documents/Faculdade/pc3-submission-evaluator && ./vendor/bin/pest tests/Unit/DTOs/ErrorCodeTest.php tests/Unit/Ai/Agents/DiagnosticAgentSchemaTest.php</automated>
  </verify>
  <done>ErrorCode enum exists with 11 cases; DiagnosticAgent schema has 7 keys including a constrained error_code; prompt describes all 10 codes + NONE and bumps to v2.0; ErrorCode + schema tests pass.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Thread error_code through ProviderResult, the DB column, and the repository</name>
  <files>app/DTOs/ProviderResult.php, app/Models/DiagnosticResult.php, database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php, app/Repositories/DiagnosticResultRepository.php, tests/Unit/DTOs/ProviderResultTest.php, tests/Feature/Persistence/DiagnosticResultPersistenceTest.php</files>
  <behavior>
    - ProviderResult has a `public readonly ErrorCode $errorCode` constructor property.
    - fromPrismResponse() maps `ErrorCode::from((string) $response['error_code'])` into $errorCode.
    - Migration adds an `error_code` column to diagnostic_results, CHECK-constrained to the 11 values.
    - DiagnosticResult model: `error_code` is fillable and cast to `ErrorCode::class`.
    - Repository save() writes `'error_code' => $dto->errorCode->value` (the string, not the enum).
    - A persisted row round-trips error_code as an ErrorCode enum after reload.
    - A direct DB insert with `error_code = 'Bogus'` raises a CHECK constraint QueryException.
  </behavior>
  <action>
    1. Update app/DTOs/ProviderResult.php:
       - `use App\DTOs\ErrorCode;` is unnecessary (same namespace) — reference `ErrorCode` directly.
       - Add `public readonly ErrorCode $errorCode,` to the private constructor, placed right
         after `$pc3Category` for consistency.
       - In fromPrismResponse(), add `errorCode: ErrorCode::from((string) $response['error_code']),`
         in the `new self(...)` call, right after `pc3Category:`.

    2. Update app/Models/DiagnosticResult.php:
       - Add `'error_code'` to the `$fillable` array (after `'pc3_category'`).
       - Add `'error_code' => ErrorCode::class,` to the `casts()` array; add
         `use App\DTOs\ErrorCode;` at the top alongside the existing `use App\DTOs\Pc3Category;`.

    3. Create database/migrations/2026_05_22_000001_add_error_code_to_diagnostic_results_table.php
       — anonymous-class migration. up() uses `Schema::table('diagnostic_results', ...)`.
       Add the column with an inline CHECK constraint using the rawColumn() pattern proven in
       Phase 1 (Blueprint::check() is absent in Laravel 13.6.0):
       ```php
       $table->rawColumn(
           'error_code',
           "varchar not null default 'NONE' constraint check_error_code check (error_code IN ('B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'))"
       );
       ```
       Use `default 'NONE'` so the ALTER works against any existing rows (SQLite requires a
       default when adding a NOT NULL column to a populated table). down() drops the `error_code`
       column via `Schema::table` + `$table->dropColumn('error_code')`.

    4. Update app/Repositories/DiagnosticResultRepository.php save(): add
       `'error_code' => $dto->errorCode->value,` to the `create([...])` array, right after
       `'pc3_category'`. The existing docblock pitfall note about writing the string `->value`
       applies identically — no doc change required.

    5. Update tests/Unit/DTOs/ProviderResultTest.php:
       - Add `'error_code' => 'B6'` (or another valid code) to EVERY `makeStubResponse([...])`
         array so fromPrismResponse() does not read a missing offset.
       - In the "preserves a confidence already in range" test, add an assertion:
         `expect($dto->errorCode)->toBe(ErrorCode::from('B6'))` (matching whatever code you
         used in that stub) and `use App\DTOs\ErrorCode;` at the top.

    6. Update tests/Feature/Persistence/DiagnosticResultPersistenceTest.php:
       - Add an `'error_code'` key to every `makeResponse([...])` array.
       - In the round-trip test, assert `expect($reloaded->error_code)->toBe(ErrorCode::from('...'))`
         (matching the stub) — proves the model cast applies. Add `use App\DTOs\ErrorCode;`.
       - In the "rejects a direct insert with an unknown pc3_category" test, the direct
         `DB::table()->insert([...])` array MUST gain a valid `'error_code' => 'NONE'` key
         (the row uses a bogus pc3_category but a valid error_code, so the test still isolates
         the pc3 constraint).
       - Add ONE new test: a direct `DB::table('diagnostic_results')->insert([...])` with all
         valid columns EXCEPT `'error_code' => 'Bogus'`, wrapped in try/catch expecting a
         `QueryException` whose message contains `check` — mirrors the existing pc3_category
         CHECK-constraint test.
  </action>
  <verify>
    <automated>cd /home/rec/Documents/Faculdade/pc3-submission-evaluator && ./vendor/bin/pest tests/Unit/DTOs/ProviderResultTest.php tests/Feature/Persistence/DiagnosticResultPersistenceTest.php</automated>
  </verify>
  <done>ProviderResult carries a typed ErrorCode mapped from the LLM response; the diagnostic_results table has a CHECK-constrained error_code column; the repository persists it; DTO and persistence tests pass including the new CHECK-violation test.</done>
</task>

</tasks>

<verification>
Run the full suite to confirm no regressions across the schema, DTO, persistence, service,
and HTTP layers (existing source-level schema tests and stub helpers were the main breakage risk):

```bash
cd /home/rec/Documents/Faculdade/pc3-submission-evaluator && ./vendor/bin/pest
```

All tests must pass. Then confirm the migration applies cleanly:

```bash
cd /home/rec/Documents/Faculdade/pc3-submission-evaluator && php artisan migrate --pretend | grep error_code
```
</verification>

<success_criteria>
- `app/DTOs/ErrorCode.php` exists as a string-backed enum with exactly 11 cases.
- `DiagnosticPromptBuilder::systemPrompt()` describes all 10 codes + NONE and includes the
  `error_code` JSON field; `promptVersion()` returns `'v2.0'`.
- `DiagnosticAgent::schema()` returns 7 keys; `error_code` is an enum of the 11 values, required.
- `ProviderResult` has a `readonly ErrorCode $errorCode` mapped via `ErrorCode::from()` in
  `fromPrismResponse()`.
- A new migration adds a CHECK-constrained `error_code` column to `diagnostic_results`.
- `DiagnosticResultRepository::save()` persists `error_code`; the model casts it to `ErrorCode`.
- `./vendor/bin/pest` passes the entire suite with zero failures.
</success_criteria>

<output>
After completion, create `.planning/quick/260522-kxk-add-error-code-field-to-prompt-and-schem/260522-kxk-SUMMARY.md`
</output>
