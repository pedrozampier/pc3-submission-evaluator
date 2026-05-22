<?php

declare(strict_types=1);

use App\Ai\Agents\DiagnosticAgent;
use App\Services\DiagnosticPromptBuilder;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

it('implements both Agent and HasStructuredOutput contracts', function () {
    $agent = new DiagnosticAgent();
    expect($agent)->toBeInstanceOf(Agent::class);
    expect($agent)->toBeInstanceOf(HasStructuredOutput::class);
});

it('declares the seven expected schema keys', function () {
    $agent = new DiagnosticAgent();
    $schema = new JsonSchemaTypeFactory();

    $shape = $agent->schema($schema);

    expect(array_keys($shape))->toBe([
        'diagnosis',
        'pc3_category',
        'error_code',
        'feedback',
        'confidence',
        'tokens_input',
        'tokens_output',
    ]);
});

it('returns the PC3 system prompt from DiagnosticPromptBuilder', function () {
    $agent = new DiagnosticAgent();
    expect($agent->instructions())->toBe(DiagnosticPromptBuilder::systemPrompt());
});

it('does not return the Phase 1 TODO placeholder', function () {
    $agent = new DiagnosticAgent();
    expect($agent->instructions())->not->toContain('TODO');
});

it('contains all three PC3 category names in instructions', function () {
    $agent = new DiagnosticAgent();
    $instructions = $agent->instructions();
    expect($instructions)
        ->toContain('Predicate')
        ->toContain('Concept')
        ->toContain('Context');
});

it('declares pc3_category as a string enum of the three PC3 values', function () {
    // Read the source file directly to verify the enum() call shape — this is robust
    // even if the JsonSchema fluent builder hides values behind private state.
    $source = file_get_contents(__DIR__ . '/../../../../app/Ai/Agents/DiagnosticAgent.php');
    expect($source)->toContain("'pc3_category'");
    expect($source)->toContain("->enum(['Predicate', 'Concept', 'Context'])");
    expect($source)->toContain("->required()"); // appears for every field
});

it('declares error_code as a string enum of all eleven values', function () {
    $source = file_get_contents(__DIR__ . '/../../../../app/Ai/Agents/DiagnosticAgent.php');
    expect($source)->toContain("'error_code'");
    expect($source)->toContain("->enum(['B6', 'B8', 'B9', 'B12', 'C1', 'C3', 'C8', 'G3', 'G4', 'H1', 'NONE'])");
});

it('declares every field as required', function () {
    // Source-level verification: count ->required() occurrences. Schema has 7 fields,
    // each must be required — so the count is at least 7.
    $source = file_get_contents(__DIR__ . '/../../../../app/Ai/Agents/DiagnosticAgent.php');
    $occurrences = substr_count($source, '->required()');
    expect($occurrences)->toBeGreaterThanOrEqual(7);
});
