<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\DiagnosticAgent;
use App\DTOs\ProviderResult;
use App\Repositories\DiagnosticResultRepository;

final class PrismStructuredCaller
{
    public function __construct(
        private readonly DiagnosticResultRepository $repository,
    ) {}

    /**
     * Call the Anthropic provider for a single structured diagnostic result.
     */
    public function call(string $code, string $statement, string $requestId): ProviderResult
    {
        $model = config('ai.providers.anthropic.models.text.default');

        $userMessage = DiagnosticPromptBuilder::userMessage($code, $statement);

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = (new DiagnosticAgent)->prompt(
            $userMessage,
            provider: 'anthropic',
            model: $model,
        );

        $result = ProviderResult::fromPrismResponse(
            response:      $response,
            provider:      'anthropic',
            model:         $model,
            requestId:     $requestId,
            promptVersion: DiagnosticPromptBuilder::promptVersion(),
        );

        $this->repository->save($result);

        return $result;
    }
}
