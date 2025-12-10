<?php

namespace App\DTOs;

class CodeAnalysisResponse
{
    /**
     * @param DetectedProblem[] $problemas
     * @param string $provider
     * @param string|null $rawResponse
     */
    public function __construct(
        public readonly array $problemas,
        public readonly string $provider,
        public readonly ?string $rawResponse = null
    ) {}

    public function toArray(): array
    {
        return [
            'problemas_detectados' => array_map(
                fn(DetectedProblem $p) => $p->toArray(),
                $this->problemas
            ),
            'provider' => $this->provider,
        ];
    }
}
