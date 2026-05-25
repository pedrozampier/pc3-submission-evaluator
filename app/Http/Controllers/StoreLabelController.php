<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExerciseLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreLabelController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anchor_request_id' => ['required', 'string', 'max:36'],
            'label'             => ['required', 'string', 'max:255'],
        ]);

        ExerciseLabel::updateOrCreate(
            ['anchor_request_id' => $data['anchor_request_id']],
            ['label' => $data['label']],
        );

        return redirect('/results');
    }
}
