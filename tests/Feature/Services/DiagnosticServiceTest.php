<?php

declare(strict_types=1);

use App\Ai\Agents\DiagnosticAgent;
use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use App\Models\DiagnosticResult;
use App\Services\DiagnosticService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Concurrency;

uses(RefreshDatabase::class);

beforeEach(function () {
    // RESEARCH Pitfall 2: ProcessDriver spawns sub-processes that don't inherit
    // DiagnosticAgent::fake() registrations. Use SyncDriver so all 4 closures
    // execute in this process, sharing the fake response queue.
    Concurrency::setDefaultInstance('sync');
});

/**
 * Build a single valid structured-output array matching DiagnosticAgent::schema().
 */
function fakeResponse(string $category = 'Predicate', float $confidence = 0.85): array
{
    return [
        'diagnosis'     => 'Test diagnosis for ' . $category,
        'pc3_category'  => $category,
        'error_code'    => 'NONE',
        'feedback'      => 'Test feedback',
        'confidence'    => $confidence,
        'tokens_input'  => 100,
        'tokens_output' => 50,
    ];
}

it('fans out to all four providers in parallel and persists every result before returning', function () {
    // Four valid responses — one consumed per provider call (in order: anthropic, openai, gemini, deepseek).
    DiagnosticAgent::fake([
        fakeResponse('Predicate', 0.9),
        fakeResponse('Concept',   0.8),
        fakeResponse('Context',   0.7),
        fakeResponse('Predicate', 0.6),
    ]);

    $service   = new DiagnosticService();
    $requestId = '44444444-4444-4444-8444-444444444444';

    $results = $service->run(
        code:      'let x: number = "hi";',
        statement: 'Assign a number.',
        requestId: $requestId,
    );

    // Result array shape — API-02.
    expect($results)
        ->toBeArray()
        ->toHaveCount(4);

    foreach ($results as $r) {
        expect($r)->toBeInstanceOf(ProviderResult::class);
        expect($r->requestId)->toBe($requestId);
        expect($r->confidence)->toBeFloat()->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        expect($r->promptVersion)->toBe('v2.1');
    }

    // PERSIST-02: every row must be in DB before run() returned.
    expect(DiagnosticResult::where('request_id', $requestId)->count())->toBe(4);

    // All four providers represented (set equality, order tolerant).
    $providers = DiagnosticResult::where('request_id', $requestId)->pluck('provider')->sort()->values()->all();
    expect($providers)->toBe(['anthropic', 'deepseek', 'gemini', 'openai']);

    // Models came from config (belt + suspenders for Task 1's config additions).
    $models = DiagnosticResult::where('request_id', $requestId)->orderBy('provider')->pluck('model')->all();
    expect($models)->toBe([
        'claude-sonnet-4-20250514', // anthropic
        'deepseek-chat',            // deepseek
        'gemini-3.5-flash',         // gemini
        'gpt-4o',                   // openai
    ]);
});

it('returns partial results when one provider fails (3 succeed, 1 throws)', function () {
    // Only 3 responses queued — preventStrayPrompts forces the 4th call to throw
    // RuntimeException, which the closure catch absorbs and returns null.
    DiagnosticAgent::fake([
        fakeResponse('Predicate', 0.9),
        fakeResponse('Concept',   0.8),
        fakeResponse('Context',   0.7),
    ])->preventStrayPrompts(true);

    $service   = new DiagnosticService();
    $requestId = '55555555-5555-4555-8555-555555555555';

    $results = $service->run(
        code:      'x',
        statement: 's',
        requestId: $requestId,
    );

    // API-03: exactly 3 successful results returned (not 4).
    expect($results)
        ->toBeArray()
        ->toHaveCount(3);

    // Result array is 0-indexed (Pitfall 5: array_values after array_filter).
    expect(array_keys($results))->toBe([0, 1, 2]);

    foreach ($results as $r) {
        expect($r)->toBeInstanceOf(ProviderResult::class);
    }

    // Exactly 3 rows persisted (not 4) — failed provider produced no row.
    expect(DiagnosticResult::where('request_id', $requestId)->count())->toBe(3);
});

it('returns an empty array and persists nothing when all four providers fail', function () {
    // Empty queue + preventStrayPrompts → every call throws RuntimeException.
    // All four closures catch, log a warning, and return null. After array_filter
    // and array_values, the result is an empty array.
    DiagnosticAgent::fake([])->preventStrayPrompts(true);

    $service   = new DiagnosticService();
    $requestId = '66666666-6666-4666-8666-666666666666';

    $results = $service->run(
        code:      'x',
        statement: 's',
        requestId: $requestId,
    );

    // D-05: empty array, no exception.
    expect($results)
        ->toBeArray()
        ->toBeEmpty();

    // No rows persisted at all.
    expect(DiagnosticResult::where('request_id', $requestId)->count())->toBe(0);
});
