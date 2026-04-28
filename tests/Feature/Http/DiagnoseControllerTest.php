<?php

declare(strict_types=1);

use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use App\Services\DiagnosticService;
use Mockery\MockInterface;

/**
 * Build a ProviderResult via reflection — the only practical escape hatch
 * since ProviderResult has a private constructor and fromPrismResponse()
 * requires a StructuredAgentResponse we would need to fully fake.
 * Reflection assignment of readonly properties works on PHP 8.4.
 */
function makeProviderResult(string $provider = 'anthropic'): ProviderResult
{
    $reflection = new ReflectionClass(ProviderResult::class);
    $instance = $reflection->newInstanceWithoutConstructor();

    $set = function (string $prop, mixed $value) use ($reflection, $instance): void {
        $p = $reflection->getProperty($prop);
        $p->setValue($instance, $value);
    };

    $set('provider',       $provider);
    $set('model',          'claude-sonnet-4-20250514');
    $set('diagnosis',      'Type mismatch: assigning string to number variable.');
    $set('pc3Category',    Pc3Category::Concept);
    $set('feedback',       'Use string type or numeric value.');
    $set('confidence',     0.85);
    $set('tokensInput',    312);
    $set('tokensOutput',   95);
    $set('requestId',      '018f2c3d-aaaa-bbbb-cccc-111122223333');
    $set('promptVersion',  'v1.0');

    return $instance;
}

it('returns 200 with a flat snake_case JSON array for valid inputs', function () {
    $stub1 = makeProviderResult('anthropic');
    $stub2 = makeProviderResult('openai');

    $this->mock(DiagnosticService::class, function (MockInterface $mock) use ($stub1, $stub2) {
        $mock->expects('run')
             ->once()
             ->withArgs(function (string $code, string $statement, string $requestId): bool {
                 return $code === 'let x: number = "hi";'
                     && $statement === 'Assign a number to x.'
                     && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $requestId) === 1;
             })
             ->andReturn([$stub1, $stub2]);
    });

    $response = $this->postJson('/api/diagnose', [
        'code'      => 'let x: number = "hi";',
        'statement' => 'Assign a number to x.',
    ]);

    $response->assertStatus(200)
             ->assertJsonIsArray()
             ->assertJsonCount(2)
             ->assertJsonStructure([
                 '*' => [
                     'provider', 'model', 'diagnosis', 'pc3_category', 'feedback',
                     'confidence', 'tokens_input', 'tokens_output', 'request_id', 'prompt_version',
                 ],
             ])
             ->assertJsonPath('0.provider', 'anthropic')
             ->assertJsonPath('0.pc3_category', 'Concept')
             ->assertJsonPath('0.tokens_input', 312)
             ->assertJsonPath('0.tokens_output', 95)
             ->assertJsonPath('0.prompt_version', 'v1.0')
             ->assertJsonPath('1.provider', 'openai');
});

it('returns 422 when code is missing without invoking the service', function () {
    $this->mock(DiagnosticService::class, function (MockInterface $mock) {
        $mock->expects('run')->never();
    });

    $this->postJson('/api/diagnose', [
        'statement' => 'Some statement',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['code']);
});

it('returns 422 when statement is missing without invoking the service', function () {
    $this->mock(DiagnosticService::class, function (MockInterface $mock) {
        $mock->expects('run')->never();
    });

    $this->postJson('/api/diagnose', [
        'code' => 'let x: number = 1;',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['statement']);
});

it('returns 503 with All providers failed message when service returns empty', function () {
    $this->mock(DiagnosticService::class, function (MockInterface $mock) {
        $mock->expects('run')
             ->once()
             ->andReturn([]);
    });

    $this->postJson('/api/diagnose', [
        'code'      => 'let x: number = 1;',
        'statement' => 'Statement.',
    ])->assertStatus(503)
      ->assertExactJson(['message' => 'All providers failed']);
});
