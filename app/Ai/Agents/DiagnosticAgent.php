<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Services\DiagnosticPromptBuilder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class DiagnosticAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Returns the version-locked PC³ system prompt — see DiagnosticPromptBuilder (PROMPT-01, PROMPT-02).
     */
    public function instructions(): string
    {
        return DiagnosticPromptBuilder::systemPrompt();
    }

    /**
     * Shared structured-output schema for ALL providers.
     * Mirrors App\DTOs\ProviderResult constructor fields (excluding provider/model/request_id/prompt_version
     * which are injected at the call site, not returned by the LLM).
     * 7 fields: diagnosis, pc3_category, error_code, feedback, confidence, tokens_input, tokens_output.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'diagnosis'     => $schema->string()->required(),
            'pc3_category'  => $schema->string()->enum(['Predicate', 'Concept', 'Context'])->required(),
            'error_code'    => $schema->string()->enum(['B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'])->required(),
            'feedback'      => $schema->string()->required(),
            'confidence'    => $schema->number()->required(),
            'tokens_input'  => $schema->integer()->required(),
            'tokens_output' => $schema->integer()->required(),
        ];
    }
}
