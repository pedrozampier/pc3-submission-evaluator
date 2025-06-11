<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CodeSubmissionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'enunciado' => 'required|string',
            'codigo' => 'required|string',
            'classificacao' => 'nullable|string',
        ]);

        $prompt = <<<PROMPT
        Analise o código abaixo e **identifique problemas comuns de compreensão de código (PC3)**, como:
        
        - variáveis não usadas
        - loops desnecessários
        - nomes confusos
        - lógica desnecessariamente complexa
        - problemas de estilo e clareza
        
        Responda **apenas em JSON**, sem explicações adicionais. Use o seguinte formato:
        
        {
          "problemas": [
            "Descreva o problema 1.",
            "Descreva o problema 2."
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
