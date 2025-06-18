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
            'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'deepseek/deepseek-r1-0528-qwen3-8b:free',
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um revisor de código.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $raw = $response->json('choices.0.message.content') ?? '';

        $decoded = json_decode($raw, true);

        return response()->json([
            'problemas_detectados' => $decoded['problemas'] ?? [$raw],
        ]);

    }
}
