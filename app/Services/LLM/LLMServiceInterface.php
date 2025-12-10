<?php

namespace App\Services\LLM;

use App\DTOs\CodeAnalysisRequest;
use App\DTOs\CodeAnalysisResponse;

interface LLMServiceInterface
{
    /**
     * Analisa código e retorna problemas de compreensão detectados
     *
     * @param CodeAnalysisRequest $request
     * @return CodeAnalysisResponse
     */
    public function analyzeCode(CodeAnalysisRequest $request): CodeAnalysisResponse;

    /**
     * Retorna o nome do provedor LLM
     *
     * @return string
     */
    public function getProviderName(): string;
}
