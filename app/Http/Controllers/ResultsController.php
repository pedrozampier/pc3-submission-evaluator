<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DiagnosticResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ResultsController extends Controller
{
    private const WINDOW_MINUTES = 2;

    public function __invoke(Request $request): View
    {
        $view = $request->query('view', 'exercise');

        $all = DiagnosticResult::orderBy('created_at', 'asc')->get();

        // Build per-request_id entries (sorted ASC for time-window grouping)
        $requests = $all
            ->groupBy('request_id')
            ->map(fn ($rows) => [
                'request_id' => $rows->first()->request_id,
                'created_at' => $rows->first()->created_at,
                'providers'  => $rows->keyBy('provider'),
            ])
            ->sortBy('created_at')
            ->values();

        // Group consecutive requests within the 2-minute window
        $exerciseGroups = collect();
        $currentGroup   = collect();

        foreach ($requests as $req) {
            if ($currentGroup->isEmpty()) {
                $currentGroup->push($req);
            } elseif ($currentGroup->last()['created_at']->diffInMinutes($req['created_at']) <= self::WINDOW_MINUTES) {
                $currentGroup->push($req);
            } else {
                $exerciseGroups->push($currentGroup);
                $currentGroup = collect([$req]);
            }
        }

        if ($currentGroup->isNotEmpty()) {
            $exerciseGroups->push($currentGroup);
        }

        // Most recent exercise first
        $exerciseGroups = $exerciseGroups->reverse()->values();

        $byLlm = $all->groupBy('provider');

        $stats = [
            'total'       => $all->count(),
            'exercises'   => $exerciseGroups->count(),
            'by_category' => $all->groupBy(fn ($r) => $r->pc3_category->value)->map->count(),
        ];

        return view('results', compact('view', 'exerciseGroups', 'byLlm', 'stats'));
    }
}
