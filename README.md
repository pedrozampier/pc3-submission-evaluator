# PC³ Submission Evaluator — Broker Laravel AI

API REST construída em Laravel que atua como um **broker** entre requisições de diagnóstico de erros TypeScript e quatro provedores de LLM (Claude, GPT-4, Gemini e DeepSeek). A API recebe um trecho de código TypeScript com erro e o enunciado do exercício correspondente, dispara o mesmo prompt para os quatro provedores **em paralelo** e retorna um array de diagnósticos estruturados — um item por provedor que respondeu com sucesso.

Cada diagnóstico é classificado segundo a taxonomia **PC³** (Predicate, Concept, Context). Este projeto é o instrumento de coleta de dados de um Trabalho de Conclusão de Curso (TCC) cujo objetivo é comparar, de forma reprodutível, a qualidade diagnóstica de diferentes LLMs frente a erros de programação em TypeScript.

## Valor central

Os quatro provedores respondem a uma única requisição de diagnóstico em paralelo, e **todo resultado retornado é persistido no banco de dados** — nada é "fire-and-forget". Isso torna a comparação multi-LLM reproduzível e auditável, formando o corpus de pesquisa do TCC.

## Stack tecnológica

| Componente | Versão | Observação |
|---|---|---|
| PHP | `^8.3` | |
| Laravel Framework | `^13.0` | Laravel 13 |
| `laravel/ai` | `^0.6` | SDK **oficial** de IA do Laravel, usado para integração com os provedores de LLM |
| `laravel/tinker` | `^3.0` | |
| SQLite | nativo do PHP | Banco de dados padrão; arquivo único, zero configuração |
| Pest | `^4.6` | Framework de testes (via `pestphp/pest` + `pestphp/pest-plugin-laravel`) |

> Atenção: este projeto usa o SDK oficial `laravel/ai` sobre Laravel 13. Qualquer documentação ou anotação anterior que mencione um SDK de terceiros para integração com LLMs ou "Laravel 12" está desatualizada.

## Pré-requisitos

- PHP 8.3 ou superior
- Composer
- Node.js + npm (necessário para compilar os assets do front-end do dashboard)
- Chaves de API válidas para os quatro provedores de LLM (Anthropic, OpenAI, Gemini, DeepSeek)

## Instalação

```bash
git clone <url-do-repositorio>
cd pc3-submission-evaluator

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Alternativamente, existe um atalho que executa todos os passos acima de uma vez (incluindo a instalação dos assets front-end):

```bash
composer setup
```

## Configuração de ambiente (chaves de API)

As chaves de API dos quatro provedores usados pelo broker são lidas via variáveis de ambiente e mapeadas em `config/ai.php`. Defina-as no seu `.env`:

| Variável de ambiente | Provedor | Modelo padrão (`config/ai.php`) |
|---|---|---|
| `ANTHROPIC_API_KEY` | Anthropic (Claude) | `claude-sonnet-4-6` |
| `OPENAI_API_KEY` | OpenAI (GPT-4) | `gpt-4o` |
| `GEMINI_API_KEY` | Google Gemini | `gemini-3.5-flash` |
| `DEEPSEEK_API_KEY` | DeepSeek | `deepseek-chat` |

Os modelos padrão de cada provedor podem ser ajustados em `config/ai.php` (`providers.<provedor>.models.text.default`).

O banco de dados é SQLite por padrão (`DB_CONNECTION=sqlite` no `.env.example`) — nenhuma outra configuração de banco é necessária para rodar o projeto localmente.

## Como executar

Para subir o ambiente completo de desenvolvimento (servidor HTTP, worker de queue, log viewer e Vite, tudo em paralelo):

```bash
composer dev
```

Ou, apenas o servidor HTTP:

```bash
php artisan serve
```

Por padrão a aplicação fica disponível em `http://localhost:8000`.

Para rodar a suíte de testes (Pest):

```bash
composer test
```

## Uso da API — `POST /api/diagnose`

Endpoint único do broker. Recebe um código TypeScript com erro e o enunciado do exercício, e retorna o diagnóstico de cada provedor de LLM que respondeu com sucesso.

### Corpo da requisição

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `code` | string | sim | Código TypeScript contendo o erro a ser diagnosticado |
| `statement` | string | sim | Enunciado do exercício associado ao código |

### Exemplo de requisição

```bash
curl -X POST http://localhost:8000/api/diagnose \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "code": "function soma(a: number, b: number) { return a + b; }\nconsole.log(soma(\"2\", 3));",
    "statement": "Implemente uma função soma que recebe dois números e retorna a soma deles."
  }'
```

### Exemplo de resposta (200)

Array JSON com 0 a 4 itens — um objeto por provedor que respondeu com sucesso. Provedores que falham são simplesmente omitidos do array (diagnóstico parcial):

```json
[
  {
    "provider": "anthropic",
    "model": "claude-sonnet-4-6",
    "diagnosis": "O argumento \"2\" é uma string, mas o parâmetro a foi tipado como number.",
    "pc3_category": "Predicate",
    "feedback": "Remova as aspas de \"2\" para que o valor seja um number literal, compatível com a assinatura da função.",
    "confidence": 0.92,
    "tokens_input": 184,
    "tokens_output": 76,
    "request_id": "b3f1c2a4-9e7d-4a1b-8c3e-1f2d3a4b5c6d",
    "prompt_version": "v1"
  }
]
```

### Comportamento de falha

- Se um ou mais provedores falharem, eles são omitidos do array de resposta — o diagnóstico retornado é parcial (não é uma falha de toda a requisição).
- Se **todos** os quatro provedores falharem, a resposta é `503`:

```json
{ "message": "All providers failed" }
```

### Taxonomia PC³

Cada diagnóstico é classificado em uma das três categorias:

- **Predicate** — erro relacionado à lógica/condição do código (ex.: comparação incorreta, condição mal formulada).
- **Concept** — entendimento equivocado de um conceito da linguagem ou de uma biblioteca/API.
- **Context** — erro relacionado ao ambiente, configuração ou escopo em que o código é executado.

## Persistência / corpus de pesquisa

Todo resultado de diagnóstico retornado por um provedor é persistido na tabela `diagnostic_results` (SQLite), **antes** da resposta HTTP ser retornada ao cliente. Essa tabela é o corpus de pesquisa do TCC.

Colunas principais:

| Coluna | Tipo | Descrição |
|---|---|---|
| `provider` | string | Nome do provedor (`anthropic`, `openai`, `gemini`, `deepseek`) |
| `model` | string | Modelo efetivamente usado na chamada |
| `diagnosis` | text | Diagnóstico do erro gerado pelo LLM |
| `pc3_category` | enum | `Predicate` \| `Concept` \| `Context` |
| `feedback` | text | Sugestão de correção/feedback para o desenvolvedor |
| `confidence` | float | Confiança do modelo no diagnóstico (0.0 a 1.0) |
| `tokens_input` | integer | Tokens consumidos na entrada |
| `tokens_output` | integer | Tokens consumidos na saída |
| `request_id` | uuid (indexado) | Identifica todas as respostas de uma mesma requisição (até 4 linhas por `request_id`) |
| `prompt_version` | string | Versão do prompt usado na chamada |
| `error_code` | enum | Código de erro padronizado (`B6`, `B8`, `B9`, `B12`, `C1`, `C3`, `C8`, `G3`, `G4`, `H1`, `NONE`) |
| `latency_ms` | integer | Latência da chamada ao provedor, em milissegundos |
| `created_at` / `updated_at` | timestamp | |

Cada requisição a `POST /api/diagnose` gera até 4 linhas na tabela (uma por provedor que respondeu), todas compartilhando o mesmo `request_id` — isso forma o corpus reproduzível usado na análise comparativa do TCC.

Como o banco é um único arquivo SQLite, o backup do corpus é trivial:

```bash
cp database/database.sqlite backup.sqlite
```

## Dashboard de resultados (opcional)

A rota `GET /results` expõe um painel web de comparação dos diagnósticos, organizados por exercício e por LLM. É possível atribuir rótulos personalizados e persistentes a cada grupo de exercício via `POST /results/label`.
