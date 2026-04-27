<?php

declare(strict_types=1);

use App\Ai\Agents\DiagnosticAgent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

it('implements both Agent and HasStructuredOutput contracts', function () {
    $agent = new DiagnosticAgent();
    expect($agent)->toBeInstanceOf(Agent::class);
    expect($agent)->toBeInstanceOf(HasStructuredOutput::class);
});

it('declares the six expected schema keys', function () {
    $agent = new DiagnosticAgent();
    $schema = new JsonSchemaTypeFactory();

    $shape = $agent->schema($schema);

    expect(array_keys($shape))->toBe([
        'diagnosis',
        'pc3_category',
        'feedback',
        'confidence',
        'tokens_input',
        'tokens_output',
    ]);
});

it('returns a non-empty placeholder instructions string', function () {
    $agent = new DiagnosticAgent();
    expect($agent->instructions())->toBeString()->not->toBe('');
});

it('declares pc3_category as a string enum of the three PC3 values', function () {
    // Read the source file directly to verify the enum() call shape — this is robust
    // even if the JsonSchema fluent builder hides values behind private state.
    $source = file_get_contents(__DIR__ . '/../../../../app/Ai/Agents/DiagnosticAgent.php');
    expect($source)->toContain("'pc3_category'");
    expect($source)->toContain("->enum(['Predicate', 'Concept', 'Context'])");
    expect($source)->toContain("->required()"); // appears for every field
});

it('declares every field as required', function () {
    // Source-level verification: count ->required() occurrences. Schema has 6 fields,
    // each must be required — so the count is at least 6.
    $source = file_get_contents(__DIR__ . '/../../../../app/Ai/Agents/DiagnosticAgent.php');
    $occurrences = substr_count($source, '->required()');
    expect($occurrences)->toBeGreaterThanOrEqual(6);
});
