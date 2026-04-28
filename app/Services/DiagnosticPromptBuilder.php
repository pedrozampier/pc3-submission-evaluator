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

Analyze the TypeScript code below and return a JSON response with exactly these fields:
- `diagnosis`: a concise description of the specific error found
- `pc3_category`: exactly one of "Predicate", "Concept", or "Context"
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
        return 'v1.0';
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
