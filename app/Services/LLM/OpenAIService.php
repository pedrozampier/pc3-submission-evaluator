<?php

namespace App\Services\LLM;

use App\DTOs\CodeAnalysisRequest;
use App\DTOs\CodeAnalysisResponse;
use App\DTOs\DetectedProblem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService implements LLMServiceInterface
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o');
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
    }

    public function analyzeCode(CodeAnalysisRequest $request): CodeAnalysisResponse
    {
        $prompt = $this->buildPrompt($request->codigo);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert code reviewer specializing in identifying fundamental programming errors.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 4096,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Erro ao comunicar com API OpenAI: ' . $response->status());
            }

            $rawResponse = $response->json('choices.0.message.content') ?? '';
            $problemas = $this->extractProblems($rawResponse);

            return new CodeAnalysisResponse(
                problemas: $problemas,
                provider: $this->getProviderName(),
                rawResponse: $rawResponse
            );

        } catch (\Exception $e) {
            Log::error('Error analyzing code with OpenAI', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getProviderName(): string
    {
        return 'OpenAI GPT';
    }

    private function buildPrompt(string $code): string
    {
        return <<<PROMPT
        You will analyze code submissions and classify errors according to a specific taxonomy.

        Here is the code you need to analyze:

        <code>
        {$code}
        </code>

        Your task is to systematically check the code for errors in these 6 specific categories:

        1. **Não usar variáveis declaradas previamente** (Not using previously declared variables): Variables that are declared but never used in the code
        2. **Não usar nomes significativos para identificadores** (Not using meaningful identifier names): Variables, functions, or identifiers with non-descriptive names like 'x', 'y', 'z', 'a', 'b', etc.
        3. **Fazer atribuição sem efeito** (Making assignments without effect): Assignments that don't affect the program's outcome or logic
        4. **Usar o operador de atribuição (=) ao invés do operador de comparação (==)** (Using assignment operator instead of comparison operator): Using = when == should be used in conditionals
        5. **Retestar, em uma estrutura condicional if-else, condições já verificadas** (Retesting already verified conditions in if-else structures): Testing the same condition multiple times unnecessarily
        6. **Usar um laço for somente com a expressão condicional, sem os outros parâmetros** (Using for loop with only conditional expression): For loops that function like while loops by omitting initialization and increment expressions

        ## Analysis Process

        1. Read and understand what the code should accomplish
        2. List out all variables, functions, and identifiers declared in the code
        3. Go through the code line by line
        4. Systematically check against all 6 error categories
        5. For each error found, note the specific line number and category
        6. Write a clear description of each problem found

        ## Output Requirements

        You MUST output a valid JSON object with this exact structure:

        {
          "problemas": [
            {
              "descricao": "Description of the problem",
              "linha": line_number
            }
          ]
        }

        CRITICAL INSTRUCTIONS:
        - Output ONLY the raw JSON object
        - If no problems found, return: {"problemas": []}
        - Do not include any explanation outside the JSON structure

        Begin your analysis now.
        PROMPT;
    }

    private function extractProblems(string $rawResponse): array
    {
        try {
            $decoded = json_decode($rawResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to decode OpenAI JSON response', [
                    'error' => json_last_error_msg(),
                    'response' => $rawResponse
                ]);
                return [];
            }

            $problemsData = $decoded['problemas'] ?? [];
            return $this->convertToProblems($problemsData);

        } catch (\Exception $e) {
            Log::error('Error extracting problems from OpenAI response', [
                'error' => $e->getMessage(),
                'response' => $rawResponse
            ]);
            return [];
        }
    }

    /**
     * @param array $problemsData
     * @return DetectedProblem[]
     */
    private function convertToProblems(array $problemsData): array
    {
        return array_map(
            fn(array $data) => DetectedProblem::fromArray($data),
            $problemsData
        );
    }
}
