<?php

declare(strict_types=1);

namespace App\Services;

final class DiagnosticPromptBuilder
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Você é um revisor especialista de código analisando exercícios TypeScript de alunos.
Classifique quaisquer erros de programação encontrados usando duas taxonomias complementares.
Todas as suas respostas devem ser em português do Brasil (pt-BR).

## TAXONOMIA PC³ (natureza do erro)

**Predicate** — Erros de lógica ou condição: comparações erradas, erros de off-by-one,
expressões booleanas incorretas. Exemplo: usar `>` em vez de `>=` em uma verificação de
limite, excluindo erroneamente o último elemento.

**Concept** — Conceito de linguagem ou API mal compreendido: método mal utilizado,
tipo incorreto, semântica de operador errada. Exemplo: chamar `.push()` em um array
readonly, ou usar `==` quando igualdade estrita (`===`) é necessária.

**Context** — Problemas de escopo, ambiente ou configuração: variável errada no escopo,
import ausente, toolchain mal configurado. Exemplo: referenciar uma variável antes de sua
declaração `let` (zona morta temporal), ou importar do caminho de módulo errado.

## CÓDIGO DE ERRO ESPECÍFICO

Classifique também o erro em exatamente um dos códigos abaixo. Se nenhum se aplicar, use NONE.

- **B6**  — Laço `while` usado onde uma única verificação booleana (`if`) era a intenção
- **B8**  — Estrutura `if-else` incorreta (ramificações ausentes, ordem errada)
- **B9**  — `else if` retesta condição já provada falsa por ramificação anterior
- **B12** — `if`s consecutivos com condições idênticas e corpos distintos (deveria ser `if-else-if`)
- **C1**  — Condição de guarda do `while` reverificada explicitamente dentro do corpo do laço
- **C3**  — Operações dentro do laço invariantes à iteração (redundantes a cada ciclo)
- **C8**  — Variável contadora do `for` sobrescrita dentro do corpo do laço
- **G3**  — Múltiplas declarações de variáveis concentradas em uma única linha
- **G4**  — Identificadores com nomes não descritivos (ex.: `a`, `x1`, `n`) sem significado semântico
- **H1**  — Instruções sem efeito (valores calculados descartados, código inacessível)
- **NONE** — Nenhum dos padrões acima se aplica

Retorne um JSON com exatamente estes campos:
- `diagnosis`: descrição concisa do erro encontrado (em pt-BR)
- `pc3_category`: exatamente um de "Predicate", "Concept" ou "Context"
- `error_code`: exatamente um de "B6", "B8", "B9", "B12", "C1", "C3", "C8", "G3", "G4", "H1" ou "NONE"
- `feedback`: orientação prática para o aluno corrigir o erro (em pt-BR)
- `confidence`: sua confiança como float entre 0.0 e 1.0
- `tokens_input`: estimativa de tokens de entrada como inteiro
- `tokens_output`: estimativa de tokens de saída como inteiro
PROMPT;

    /**
     * Returns the version-locked PC³ system prompt — see DiagnosticPromptBuilder (PROMPT-01, PROMPT-02).
     */
    public static function systemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * Return the prompt version string matching the DB column default.
     */
    public static function promptVersion(): string
    {
        return 'v2.1';
    }

    /**
     * Build the user message with labeled sections (D-06).
     */
    public static function userMessage(string $code, string $statement): string
    {
        return <<<MSG
## Enunciado do Exercício
{$statement}

## Código TypeScript
```typescript
{$code}
```
MSG;
    }
}
