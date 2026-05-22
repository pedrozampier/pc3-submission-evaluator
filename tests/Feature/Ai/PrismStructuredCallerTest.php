<?php

declare(strict_types=1);

use App\Ai\Agents\DiagnosticAgent;
use App\DTOs\ErrorCode;
use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use App\Models\DiagnosticResult;
use App\Repositories\DiagnosticResultRepository;
use App\Services\PrismStructuredCaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a schema-compliant ProviderResult and persists it via the repository', function () {
    DiagnosticAgent::fake([
        [
            'diagnosis'     => 'TS2322: Type "string" is not assignable to type "number"',
            'pc3_category'  => 'Predicate',
            'error_code'    => 'B6',
            'feedback'      => 'Change the literal to a number, e.g. 42 instead of "42".',
            'confidence'    => 0.88,
            'tokens_input'  => 200,
            'tokens_output' => 75,
        ],
    ]);

    $caller = new PrismStructuredCaller(
        provider:   'anthropic',
        model:      config('ai.providers.anthropic.models.text.default'),
        repository: new DiagnosticResultRepository(),
    );
    $requestId = '11111111-1111-4111-8111-111111111111';

    $result = $caller->call(
        code:      'let x: number = "hello";',
        statement: 'Assign a number to variable x.',
        requestId: $requestId,
    );

    // ProviderResult shape — schema compliance.
    expect($result)->toBeInstanceOf(ProviderResult::class);
    expect($result->pc3Category)->toBe(Pc3Category::Predicate);
    expect($result->errorCode)->toBe(ErrorCode::B6);
    expect($result->confidence)
        ->toBeFloat()
        ->toBeGreaterThanOrEqual(0.0)
        ->toBeLessThanOrEqual(1.0);
    expect($result->provider)->toBe('anthropic');
    expect($result->model)->toBe(config('ai.providers.anthropic.models.text.default'));
    expect($result->model)->toBe('claude-sonnet-4-20250514'); // belt + suspenders — pin came from config
    expect($result->promptVersion)->toBe('v2.1');
    expect($result->requestId)->toBe($requestId);
    expect($result->diagnosis)->toContain('TS2322');
    expect($result->tokensInput)->toBe(200);
    expect($result->tokensOutput)->toBe(75);

    // Persistence — row exists, enum cast applied on reload.
    $row = DiagnosticResult::query()
        ->where('request_id', $requestId)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->pc3_category)->toBe(Pc3Category::Predicate);
    expect($row->error_code)->toBe(ErrorCode::B6);
    expect($row->provider)->toBe('anthropic');
    expect($row->prompt_version)->toBe('v2.1');
});

it('passes a labeled-section user message containing both headings to the agent', function () {
    DiagnosticAgent::fake([
        [
            'diagnosis'     => 'd',
            'pc3_category'  => 'Concept',
            'error_code'    => 'NONE',
            'feedback'      => 'f',
            'confidence'    => 0.5,
            'tokens_input'  => 10,
            'tokens_output' => 10,
        ],
    ]);

    $caller = new PrismStructuredCaller(
        provider:   'anthropic',
        model:      config('ai.providers.anthropic.models.text.default'),
        repository: new DiagnosticResultRepository(),
    );
    $caller->call(
        code:      'const x = 1;',
        statement: 'Declare a constant.',
        requestId: '22222222-2222-4222-8222-222222222222',
    );

    DiagnosticAgent::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, '## Enunciado do Exercício')
            && str_contains($prompt->prompt, '## Código TypeScript')
            && str_contains($prompt->prompt, 'Declare a constant.')
            && str_contains($prompt->prompt, 'const x = 1;');
    });
});

it('clamps out-of-range confidence to [0.0, 1.0] when the LLM over-reports', function () {
    DiagnosticAgent::fake([
        [
            'diagnosis'     => 'd',
            'pc3_category'  => 'Context',
            'error_code'    => 'H1',
            'feedback'      => 'f',
            'confidence'    => 1.5, // out of range — must clamp to 1.0
            'tokens_input'  => 1,
            'tokens_output' => 1,
        ],
    ]);

    $caller = new PrismStructuredCaller(
        provider:   'anthropic',
        model:      config('ai.providers.anthropic.models.text.default'),
        repository: new DiagnosticResultRepository(),
    );
    $requestId = '33333333-3333-4333-8333-333333333333';

    $result = $caller->call(
        code:      'x',
        statement: 's',
        requestId: $requestId,
    );

    expect($result->confidence)->toBe(1.0);

    $row = DiagnosticResult::query()->where('request_id', $requestId)->first();
    expect($row)->not->toBeNull();
    expect($row->confidence)->toBe(1.0);
    expect($row->pc3_category)->toBe(Pc3Category::Context);
});
