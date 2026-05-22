<?php

declare(strict_types=1);

namespace App\DTOs;

use Laravel\Ai\Responses\StructuredAgentResponse;

final class ProviderResult
{
    private function __construct(
        public readonly string      $provider,
        public readonly string      $model,
        public readonly string      $diagnosis,
        public readonly Pc3Category $pc3Category,
        public readonly ErrorCode   $errorCode,
        public readonly string      $feedback,
        public readonly float       $confidence,
        public readonly int         $tokensInput,
        public readonly int         $tokensOutput,
        public readonly string      $requestId,
        public readonly string      $promptVersion,
    ) {}

    public static function fromPrismResponse(
        StructuredAgentResponse $response,
        string $provider,
        string $model,
        string $requestId,
        string $promptVersion,
    ): self {
        return new self(
            provider:      $provider,
            model:         $model,
            diagnosis:     (string) $response['diagnosis'],
            pc3Category:   Pc3Category::from((string) $response['pc3_category']),
            errorCode:     ErrorCode::from((string) $response['error_code']),
            feedback:      (string) $response['feedback'],
            confidence:    max(0.0, min(1.0, (float) $response['confidence'])),
            tokensInput:   (int) $response['tokens_input'],
            tokensOutput:  (int) $response['tokens_output'],
            requestId:     $requestId,
            promptVersion: $promptVersion,
        );
    }
}
