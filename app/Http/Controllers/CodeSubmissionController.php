<?php

namespace App\Http\Controllers;

use App\DTOs\CodeAnalysisRequest;
use App\Services\LLM\LLMServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CodeSubmissionController extends Controller
{
    public function __construct(
        private readonly LLMServiceInterface $llmService
    ) {}

    /**
     * Analisa código submetido e retorna problemas de compreensão detectados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enunciado' => 'nullable|string',
            'codigo' => 'required|string',
            'classificacao' => 'nullable|string',
        ]);

        try {
            $analysisRequest = CodeAnalysisRequest::fromArray($validated);
            $response = $this->llmService->analyzeCode($analysisRequest);

            return response()->json($response->toArray());

        } catch (\Exception $e) {
            Log::error('Erro ao analisar código', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erro ao processar análise de código',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}