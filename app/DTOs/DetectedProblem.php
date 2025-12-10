<?php

namespace App\DTOs;

class DetectedProblem
{
    public function __construct(
        public readonly string $descricao,
        public readonly int $linha
    ) {}

    public function toArray(): array
    {
        return [
            'descricao' => $this->descricao,
            'linha' => $this->linha,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            descricao: $data['descricao'] ?? '',
            linha: $data['linha'] ?? 0
        );
    }
}
