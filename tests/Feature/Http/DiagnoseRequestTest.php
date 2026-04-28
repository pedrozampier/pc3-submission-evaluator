<?php

declare(strict_types=1);

use App\Http\Requests\DiagnoseRequest;

it('authorizes all requests (no auth on endpoint)', function () {
    $request = new DiagnoseRequest();
    expect($request->authorize())->toBeTrue();
});

it('requires code and statement as strings with no extra rules', function () {
    $request = new DiagnoseRequest();
    expect($request->rules())->toBe([
        'code'      => ['required', 'string'],
        'statement' => ['required', 'string'],
    ]);
});
