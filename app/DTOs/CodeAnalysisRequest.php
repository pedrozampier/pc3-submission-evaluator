<?php

namespace App\DTOs;

class CodeAnalysisRequest
{
    public function __construct(
        public readonly string $codigo,
        public readonly ?string $enunciado = null,
        public readonly ?string $classificacao = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            codigo: $data['codigo'],
            enunciado: $data['enunciado'] ?? null,
            classificacao: $data['classificacao'] ?? null
        );
    }
}
