<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\CommonComprehensionProblem;

class CodeSubmissionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'enunciado' => 'nullable|string',
            'codigo' => 'required|string',
            'classificacao' => 'nullable|string',
        ]);

        $classifications = CommonComprehensionProblem::all();

        $prompt = <<<PROMPT
        You are an expert code reviewer specializing in identifying fundamental programming errors. You will analyze code submissions and classify errors according to a specific taxonomy.

        Here is the code you need to analyze:

        <code>
        {$validated['codigo']}
        </code>

        Your task is to systematically check the code for errors in these 6 specific categories:

        1. **Não usar variáveis declaradas previamente** (Not using previously declared variables): Variables that are declared but never used in the code
        2. **Não usar nomes significativos para identificadores** (Not using meaningful identifier names): Variables, functions, or identifiers with non-descriptive names like 'x', 'y', 'z', 'a', 'b', etc.
        3. **Fazer atribuição sem efeito** (Making assignments without effect): Assignments that don't affect the program's outcome or logic
        4. **Usar o operador de atribuição (=) ao invés do operador de comparação (==)** (Using assignment operator instead of comparison operator): Using = when == should be used in conditionals
        5. **Retestar, em uma estrutura condicional if-else, condições já verificadas** (Retesting already verified conditions in if-else structures): Testing the same condition multiple times unnecessarily  
        6. **Usar um laço for somente com a expressão condicional, sem os outros parâmetros** (Using for loop with only conditional expression): For loops that function like while loops by omitting initialization and increment expressions

        ## Analysis Process

        First, work through your analysis inside <analysis> tags:
        1. Read and understand what the code should accomplish
        2. List out all variables, functions, and identifiers declared in the code
        3. Go through the code line by line
        4. Systematically check against all 6 error categories
        5. For each error found, note the specific line number and category
        6. Write a clear description of each problem found

        ## Output Requirements

        After your analysis, you MUST output a valid JSON object with this exact structure:

        {
          "problemas": [
            {
              "descricao": "Description of the problem",
              "linha": line_number
            }
          ]
        }

        CRITICAL INSTRUCTIONS:
        - Output the JSON directly, without wrapping it in markdown code blocks
        - Do NOT use ```json or ``` around the JSON
        - Output ONLY the raw JSON object
        - If no problems found, return: {"problemas": []}
        - The JSON must be the last thing in your response

        Begin your analysis now.
        PROMPT;
        
        $response = Http::withHeaders([
            'x-api-key' => env('ANTHROPIC_API_KEY'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $raw = $response->json('content.0.text') ?? '';

        // Extrair JSON da resposta
        $decoded = $this->extractJson($raw);

        return response()->json([
            'problemas_detectados' => $decoded['problemas'] ?? [],
        ]);
    }

    /**
     * Extrai JSON da resposta do Claude, removendo markdown e tags XML
     */
    private function extractJson(string $text): ?array
    {
        $text = preg_replace('/<analysis>.*?<\/analysis>/s', '', $text);
        $text = preg_replace('/<code_review>.*?<\/code_review>/s', '', $text);
        
        $text = preg_replace('/```json\s*/s', '', $text);
        $text = preg_replace('/```\s*$/s', '', $text);
        
        if (preg_match('/\{[\s\S]*"problemas"[\s\S]*\}/U', $text, $matches)) {
            $jsonString = $matches[0];
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return ['problemas' => []];
    }
}