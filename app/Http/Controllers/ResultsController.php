<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DiagnosticResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ResultsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $view = $request->query('view', 'exercise');

        $all = DiagnosticResult::orderBy('created_at', 'desc')->get();

        $byExercise = $all
            ->groupBy('request_id')
            ->map(fn ($rows) => [
                'request_id' => $rows->first()->request_id,
                'created_at' => $rows->first()->created_at,
                'providers'  => $rows->keyBy('provider'),
            ]);

        $byLlm = $all->groupBy('provider');

        $stats = [
            'total'       => $all->count(),
            'exercises'   => $byExercise->count(),
            'by_category' => $all->groupBy(fn ($r) => $r->pc3_category->value)->map->count(),
        ];

        return view('results', compact('view', 'byExercise', 'byLlm', 'stats'));
    }
}
