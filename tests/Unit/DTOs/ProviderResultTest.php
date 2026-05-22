<?php

declare(strict_types=1);

use App\DTOs\ErrorCode;
use App\DTOs\Pc3Category;
use App\DTOs\ProviderResult;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Build a StructuredAgentResponse stub using the public `$structured` property
 * declared by the ProvidesStructuredResponse trait.
 * Using newInstanceWithoutConstructor() avoids needing real Usage/Meta objects.
 */
function makeStubResponse(array $data): StructuredAgentResponse
{
    $reflection = new ReflectionClass(StructuredAgentResponse::class);
    $instance = $reflection->newInstanceWithoutConstructor();
    $instance->structured = $data;

    return $instance;
}

it('clamps a negative confidence to 0.0', function () {
    $response = makeStubResponse([
        'diagnosis'    => 'Type mismatch',
        'pc3_category' => 'Predicate',
        'error_code'   => 'B6',
        'feedback'     => 'Use string',
        'confidence'   => -0.5,
        'tokens_input' => 120,
        'tokens_output' => 45,
    ]);

    $dto = ProviderResult::fromPrismResponse(
        response: $response,
        provider: 'anthropic',
        model: 'claude-sonnet-4-20250514',
        requestId: 'req-1',
        promptVersion: 'v2.0',
    );

    expect($dto->confidence)->toBe(0.0);
});

it('clamps a confidence above 1.0 to 1.0', function () {
    $response = makeStubResponse([
        'diagnosis'    => 'Bad cast',
        'pc3_category' => 'Concept',
        'error_code'   => 'B8',
        'feedback'     => 'Cast properly',
        'confidence'   => 1.5,
        'tokens_input' => 80,
        'tokens_output' => 30,
    ]);

    $dto = ProviderResult::fromPrismResponse(
        response: $response,
        provider: 'openai',
        model: 'gpt-4o',
        requestId: 'req-2',
        promptVersion: 'v2.0',
    );

    expect($dto->confidence)->toBe(1.0);
});

it('preserves a confidence already in range', function () {
    $response = makeStubResponse([
        'diagnosis'    => 'Missing import',
        'pc3_category' => 'Context',
        'error_code'   => 'B6',
        'feedback'     => 'Add import',
        'confidence'   => 0.7,
        'tokens_input' => 90,
        'tokens_output' => 40,
    ]);

    $dto = ProviderResult::fromPrismResponse(
        response: $response,
        provider: 'gemini',
        model: 'gemini-2.0-flash',
        requestId: 'req-3',
        promptVersion: 'v2.0',
    );

    expect($dto->confidence)->toBe(0.7);
    expect($dto->pc3Category)->toBe(Pc3Category::Context);
    expect($dto->errorCode)->toBe(ErrorCode::from('B6'));
    expect($dto->provider)->toBe('gemini');
    expect($dto->requestId)->toBe('req-3');
    expect($dto->promptVersion)->toBe('v2.0');
});

it('forbids direct instantiation via new', function () {
    $reflection = new ReflectionClass(ProviderResult::class);
    $constructor = $reflection->getConstructor();
    expect($constructor->isPrivate())->toBeTrue();
});

it('is final', function () {
    $reflection = new ReflectionClass(ProviderResult::class);
    expect($reflection->isFinal())->toBeTrue();
});
