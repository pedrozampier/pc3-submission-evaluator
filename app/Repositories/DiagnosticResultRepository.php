<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\ProviderResult;
use App\Models\DiagnosticResult;

final class DiagnosticResultRepository
{
    /**
     * Persist a single provider's diagnostic result.
     *
     * Pitfall 3 (RESEARCH.md): we MUST write `pc3Category->value` (the string), not the enum object.
     * Eloquent's enum cast converts string→enum on READ; on WRITE via create(), it expects the string.
     * Same applies to errorCode->value for the error_code column.
     */
    public function save(ProviderResult $dto): DiagnosticResult
    {
        return DiagnosticResult::create([
            'provider'       => $dto->provider,
            'model'          => $dto->model,
            'diagnosis'      => $dto->diagnosis,
            'pc3_category'   => $dto->pc3Category->value,
            'error_code'     => $dto->errorCode->value,
            'feedback'       => $dto->feedback,
            'confidence'     => $dto->confidence,
            'tokens_input'   => $dto->tokensInput,
            'tokens_output'  => $dto->tokensOutput,
            'request_id'     => $dto->requestId,
            'prompt_version' => $dto->promptVersion,
        ]);
    }
}
