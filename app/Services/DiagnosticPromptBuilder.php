<?php

declare(strict_types=1);

namespace App\Services;

final class DiagnosticPromptBuilder
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert TypeScript code reviewer applying the PC³ taxonomy to classify
the root cause of a TypeScript compilation or runtime error.

The PC³ taxonomy has three categories:

**Predicate** — Logic or condition errors: wrong comparisons, off-by-one mistakes,
incorrect boolean expressions. Example: using `>` instead of `>=` in a boundary check,
causing an off-by-one exclusion of the final element.

**Concept** — Wrong understanding of a language feature or library API: misused method,
wrong type usage, incorrect operator semantics. Example: calling `.push()` on a readonly
array, or using `==` when strict equality (`===`) is required.

**Context** — Environment, scope, or configuration issues: wrong variable in scope,
missing import, misconfigured toolchain. Example: referencing a variable before its
`let` declaration (temporal dead zone), or importing from the wrong module path.

## Specific Error Codes

In addition to the PC³ category, classify the error into exactly one of the following
specific error codes. If none of the codes match, use NONE.

- **B6**  — while loop used where a single boolean check (if) was intended
- **B8**  — if-else structure is incorrect (missing branches, wrong order)
- **B9**  — else-if re-tests a condition already proven false by a preceding branch
- **B12** — consecutive identical if conditions with different bodies (should be if-else-if)
- **C1**  — while guard condition explicitly re-checked inside the loop body
- **C3**  — operations inside loop body are invariant to the iteration (redundant)
- **C8**  — for loop's counter variable overwritten inside the loop body
- **G3**  — multiple variable declarations crammed into a single line
- **G4**  — identifiers use non-descriptive names (a, x1, n, etc.)
- **H1**  — statements with no effect (computed values discarded, unreachable code)
- **NONE** — no error matching any of the above patterns

Analyze the TypeScript code below and return a JSON response with exactly these fields:
- `diagnosis`: a concise description of the specific error found
- `pc3_category`: exactly one of "Predicate", "Concept", or "Context"
- `error_code`: exactly one of "B6", "B8", "B9", "B12", "C1", "C3", "C8", "G3", "G4", "H1", or "NONE"
- `feedback`: actionable guidance for the student to fix the error
- `confidence`: your self-reported confidence as a float between 0.0 and 1.0
- `tokens_input`: your estimated input token count as an integer
- `tokens_output`: your estimated output token count as an integer
PROMPT;

    /**
     * Returns the version-locked PC³ system prompt — see DiagnosticPromptBuilder (PROMPT-01, PROMPT-02).
     */
    public static function systemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * Return the prompt version string matching the DB column default.
     */
    public static function promptVersion(): string
    {
        return 'v2.0';
    }

    /**
     * Build the user message with labeled sections (D-06).
     */
    public static function userMessage(string $code, string $statement): string
    {
        return <<<MSG
## Exercise Statement
{$statement}

## TypeScript Code
```typescript
{$code}
```
MSG;
    }
}
