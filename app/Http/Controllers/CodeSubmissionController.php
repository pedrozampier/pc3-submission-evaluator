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
            'enunciado' => 'required|string',
            'codigo' => 'required|string',
            'classificacao' => 'nullable|string',
        ]);

        $classifications = CommonComprehensionProblem::all();

        $prompt = <<<PROMPT
        Analise o código abaixo e identifique problemas comuns de compreensão de código (PC3), usando a lista de classificações fornecida. Para cada problema encontrado, associe o código correspondente (como C8, B6, etc.) com base na descrição.
        
        ### Classificações disponíveis:
        $classifications
        
        Retorne APENAS EM JSON neste formato:
        
        {
          "problemas": [
            {
              "codigo": "C8",
              "descricao": "Laço for com variável responsável pela iteração sendo sobrescrita",
              "linha": 12
            },
            {
              "codigo": "G4",
              "descricao": "Variável com nome não significativo",
              "linha": 5
            }
          ]
        }
        
        Código:
        {$validated['codigo']}
        PROMPT;
        
        $response = Http::withHeaders([
            'x-api-key' => env('ANTHROPIC_API_KEY'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [

            'model' => 'claude-3-5-sonnet-20240620',
            'system' => 'Você é um revisor de código.',
            'max_tokens' => 2048,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $raw = $response->json('content.0.text') ?? '';

        $decoded = json_decode($raw, true);

        return response()->json([
            'problemas_detectados' => $decoded['problemas'] ?? [$raw],
        ]);
    }
}