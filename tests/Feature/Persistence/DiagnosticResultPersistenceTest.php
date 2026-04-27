<?php

declare(strict_types=1);

use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use App\Models\DiagnosticResult;
use App\Repositories\DiagnosticResultRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\StructuredAgentResponse;

uses(RefreshDatabase::class);

/**
 * Build a StructuredAgentResponse stub using the public `$structured` property
 * declared by the ProvidesStructuredResponse trait (confirmed in Plan 02).
 * Using newInstanceWithoutConstructor() avoids needing real Usage/Meta objects.
 */
function makeResponse(array $data): StructuredAgentResponse
{
    $reflection = new ReflectionClass(StructuredAgentResponse::class);
    $instance   = $reflection->newInstanceWithoutConstructor();
    $instance->structured = $data;

    return $instance;
}

it('persists a ProviderResult and round-trips all fields with enum cast', function () {
    $response = makeResponse([
        'diagnosis'     => 'TS2322: Type "string" is not assignable to type "number"',
        'pc3_category'  => 'Predicate',
        'feedback'      => 'Change the literal to a number, e.g. `42` instead of `"42"`.',
        'confidence'    => 0.92,
        'tokens_input'  => 215,
        'tokens_output' => 88,
    ]);

    $dto = ProviderResult::fromPrismResponse(
        response:      $response,
        provider:      'anthropic',
        model:         'claude-sonnet-4-20250514',
        requestId:     '11111111-1111-4111-8111-111111111111',
        promptVersion: 'v1.0',
    );

    $repo = new DiagnosticResultRepository();
    $row  = $repo->save($dto);

    // Returned instance is hydrated and persisted.
    expect($row)->toBeInstanceOf(DiagnosticResult::class);
    expect($row->id)->not->toBeNull();

    // Reload from DB to confirm persistence and casts.
    $reloaded = DiagnosticResult::query()->find($row->id);

    expect($reloaded)->not->toBeNull();
    expect($reloaded->provider)->toBe('anthropic');
    expect($reloaded->model)->toBe('claude-sonnet-4-20250514');
    expect($reloaded->diagnosis)->toBe('TS2322: Type "string" is not assignable to type "number"');
    expect($reloaded->pc3_category)->toBe(Pc3Category::Predicate); // enum cast applied
    expect($reloaded->feedback)->toContain('Change the literal');
    expect($reloaded->confidence)->toBe(0.92);
    expect($reloaded->tokens_input)->toBe(215);
    expect($reloaded->tokens_output)->toBe(88);
    expect($reloaded->request_id)->toBe('11111111-1111-4111-8111-111111111111');
    expect($reloaded->prompt_version)->toBe('v1.0');
});

it('persists clamped confidence (0.0) when fromPrismResponse received a negative value', function () {
    $response = makeResponse([
        'diagnosis'     => 'd',
        'pc3_category'  => 'Concept',
        'feedback'      => 'f',
        'confidence'    => -2.5, // out of range; DTO must clamp to 0.0
        'tokens_input'  => 1,
        'tokens_output' => 1,
    ]);

    $dto = ProviderResult::fromPrismResponse(
        response:      $response,
        provider:      'openai',
        model:         'gpt-4o',
        requestId:     '22222222-2222-4222-8222-222222222222',
        promptVersion: 'v1.0',
    );

    // Sanity: clamping happened at construction time (Plan 02's invariant).
    expect($dto->confidence)->toBe(0.0);

    $row      = (new DiagnosticResultRepository())->save($dto);
    $reloaded = DiagnosticResult::query()->find($row->id);

    expect($reloaded->confidence)->toBe(0.0);
    expect($reloaded->pc3_category)->toBe(Pc3Category::Concept);
});

it('rejects a direct insert with an unknown pc3_category via the CHECK constraint', function () {
    // Bypass the model and the DTO — go straight at the DB to prove the CHECK constraint exists.
    try {
        \Illuminate\Support\Facades\DB::table('diagnostic_results')->insert([
            'provider'       => 'gemini',
            'model'          => 'gemini-2.0-flash',
            'diagnosis'      => 'd',
            'pc3_category'   => 'Bogus',
            'feedback'       => 'f',
            'confidence'     => 0.5,
            'tokens_input'   => 1,
            'tokens_output'  => 1,
            'request_id'     => '33333333-3333-4333-8333-333333333333',
            'prompt_version' => 'v1.0',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        // If we reach here, the CHECK constraint didn't fire — fail the test.
        $this->fail('Expected a CHECK constraint violation for pc3_category=Bogus');
    } catch (\Illuminate\Database\QueryException $e) {
        expect($e->getMessage())->toContain('check'); // SQLite "CHECK constraint failed: ..."
    }
});
