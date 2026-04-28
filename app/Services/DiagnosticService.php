<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DiagnosticResultRepository;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DiagnosticService
{
    /**
     * Fan out a single diagnostic request to all four LLM providers in parallel.
     *
     * Each provider call is dispatched via the Concurrency facade (ProcessDriver in
     * production, SyncDriver in tests). Per-provider failures are
     * absorbed inside each closure and surface as null in the result array, then
     * filtered out before return. Persistence happens inside PrismStructuredCaller::call()
     * before the closure returns, so every successful row is in the DB before this
     * method returns (PERSIST-02).
     *
     * @return array<int, \App\DTOs\ProviderResult>  0-indexed, 0–4 elements
     */
    public function run(string $code, string $statement, string $requestId): array
    {
        $repository = new DiagnosticResultRepository();

        $callers = [
            'anthropic' => new PrismStructuredCaller(
                provider:   'anthropic',
                model:      config('ai.providers.anthropic.models.text.default'),
                repository: $repository,
            ),
            'openai' => new PrismStructuredCaller(
                provider:   'openai',
                model:      config('ai.providers.openai.models.text.default'),
                repository: $repository,
            ),
            'gemini' => new PrismStructuredCaller(
                provider:   'gemini',
                model:      config('ai.providers.gemini.models.text.default'),
                repository: $repository,
            ),
            'deepseek' => new PrismStructuredCaller(
                provider:   'deepseek',
                model:      config('ai.providers.deepseek.models.text.default'),
                repository: $repository,
            ),
        ];

        $results = Concurrency::run([
            'anthropic' => function () use ($callers, $code, $statement, $requestId) {
                try {
                    return $callers['anthropic']->call($code, $statement, $requestId);
                } catch (Throwable $e) {
                    Log::warning('DiagnosticService: anthropic provider failed', [
                        'request_id' => $requestId,
                        'error'      => $e->getMessage(),
                    ]);
                    return null;
                }
            },
            'openai' => function () use ($callers, $code, $statement, $requestId) {
                try {
                    return $callers['openai']->call($code, $statement, $requestId);
                } catch (Throwable $e) {
                    Log::warning('DiagnosticService: openai provider failed', [
                        'request_id' => $requestId,
                        'error'      => $e->getMessage(),
                    ]);
                    return null;
                }
            },
            'gemini' => function () use ($callers, $code, $statement, $requestId) {
                try {
                    return $callers['gemini']->call($code, $statement, $requestId);
                } catch (Throwable $e) {
                    Log::warning('DiagnosticService: gemini provider failed', [
                        'request_id' => $requestId,
                        'error'      => $e->getMessage(),
                    ]);
                    return null;
                }
            },
            'deepseek' => function () use ($callers, $code, $statement, $requestId) {
                try {
                    return $callers['deepseek']->call($code, $statement, $requestId);
                } catch (Throwable $e) {
                    Log::warning('DiagnosticService: deepseek provider failed', [
                        'request_id' => $requestId,
                        'error'      => $e->getMessage(),
                    ]);
                    return null;
                }
            },
        ]);

        return array_values(array_filter($results));
    }
}
