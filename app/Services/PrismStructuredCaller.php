<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\DiagnosticAgent;
use App\DTOs\ProviderResult;
use App\Repositories\DiagnosticResultRepository;

final class PrismStructuredCaller
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly DiagnosticResultRepository $repository,
    ) {}

    /**
     * Call the given provider for a single structured diagnostic result.
     */
    public function call(string $code, string $statement, string $requestId): ProviderResult
    {
        $userMessage = DiagnosticPromptBuilder::userMessage($code, $statement);

        $startedAt = hrtime(true);

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = (new DiagnosticAgent)->prompt(
            $userMessage,
            provider: $this->provider,
            model:    $this->model,
        );

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $result = ProviderResult::fromPrismResponse(
            response:      $response,
            provider:      $this->provider,
            model:         $this->model,
            requestId:     $requestId,
            promptVersion: DiagnosticPromptBuilder::promptVersion(),
            latencyMs:     $latencyMs,
        );

        $this->repository->save($result);

        return $result;
    }
}
