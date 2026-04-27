<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class DiagnosticAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Placeholder instructions. The real PC3 system prompt is added in Phase 2 (PROMPT-01).
     */
    public function instructions(): string
    {
        return 'TODO Phase 2: PC3 system prompt — DO NOT prompt this agent until Phase 2 wires it.';
    }

    /**
     * Shared structured-output schema for ALL providers.
     * Mirrors App\DTOs\ProviderResult constructor fields (excluding provider/model/request_id/prompt_version
     * which are injected at the call site, not returned by the LLM).
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'diagnosis'     => $schema->string()->required(),
            'pc3_category'  => $schema->string()->enum(['Predicate', 'Concept', 'Context'])->required(),
            'feedback'      => $schema->string()->required(),
            'confidence'    => $schema->number()->required(),
            'tokens_input'  => $schema->integer()->required(),
            'tokens_output' => $schema->integer()->required(),
        ];
    }
}
