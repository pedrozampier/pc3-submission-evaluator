<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\ProviderResult;
use App\Http\Requests\DiagnoseRequest;
use App\Services\DiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class DiagnoseController extends Controller
{
    public function __construct(private readonly DiagnosticService $service) {}

    public function __invoke(DiagnoseRequest $request): JsonResponse
    {
        $requestId = Str::uuid()->toString();

        $results = $this->service->run(
            code:      $request->string('code')->toString(),
            statement: $request->string('statement')->toString(),
            requestId: $requestId,
        );

        if (empty($results)) {
            return response()->json(['message' => 'All providers failed'], 503);
        }

        return response()->json(
            array_map(fn (ProviderResult $r) => [
                'provider'       => $r->provider,
                'model'          => $r->model,
                'diagnosis'      => $r->diagnosis,
                'pc3_category'   => $r->pc3Category->value,
                'feedback'       => $r->feedback,
                'confidence'     => $r->confidence,
                'tokens_input'   => $r->tokensInput,
                'tokens_output'  => $r->tokensOutput,
                'request_id'     => $r->requestId,
                'prompt_version' => $r->promptVersion,
            ], $results)
        );
    }
}
