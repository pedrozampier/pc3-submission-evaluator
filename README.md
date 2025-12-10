# PC3 Submission Evaluator

Sistema de avaliação automática de código utilizando Large Language Models (LLMs) para identificação de problemas de compreensão em programação. Projeto desenvolvido como Trabalho de Conclusão de Curso (TCC).

## Sobre o Projeto

Este sistema utiliza APIs de modelos de linguagem de grande escala para analisar código submetido por estudantes e identificar problemas comuns de compreensão em programação, categorizados em 6 tipos principais:

1. **Não usar variáveis declaradas previamente**: Variáveis declaradas mas não utilizadas no código
2. **Não usar nomes significativos para identificadores**: Uso de nomes não descritivos como 'x', 'y', 'z', etc.
3. **Fazer atribuição sem efeito**: Atribuições que não afetam o resultado do programa
4. **Usar operador de atribuição ao invés de comparação**: Uso de `=` quando deveria ser `==` em condicionais
5. **Retestar condições já verificadas**: Testar a mesma condição múltiplas vezes em estruturas if-else
6. **Usar laço for somente com expressão condicional**: Laços for que funcionam como while

## Arquitetura do Sistema

### Estrutura de Diretórios

```
app/
├── Http/Controllers/
│   └── CodeSubmissionController.php    # Controller principal da API
├── Services/LLM/
│   ├── LLMServiceInterface.php         # Interface para serviços LLM
│   ├── AnthropicService.php            # Implementação para Claude (Anthropic)
│   └── OpenAIService.php               # Implementação para GPT (OpenAI)
├── DTOs/
│   ├── CodeAnalysisRequest.php         # DTO para requisição de análise
│   ├── CodeAnalysisResponse.php        # DTO para resposta de análise
│   └── DetectedProblem.php             # DTO para problemas detectados
└── Providers/
    └── LLMServiceProvider.php          # Provider para injeção de dependência
```

### Padrões de Projeto Utilizados

- **Strategy Pattern**: Diferentes provedores de LLM (Anthropic, OpenAI) implementam a mesma interface
- **Dependency Injection**: LLMServiceInterface injetada no controller
- **Data Transfer Objects (DTOs)**: Estruturas tipadas para requisições e respostas
- **Service Layer**: Lógica de negócio isolada em services

## Tecnologias Utilizadas

- **Backend**: Laravel 12 (PHP 8.2+)
- **LLM Providers**:
  - Anthropic Claude (claude-sonnet-4)
  - OpenAI GPT (gpt-4o)
- **Database**: MariaDB
- **HTTP Client**: Guzzle (via Laravel HTTP)

## Instalação

### Pré-requisitos

- PHP >= 8.2
- Composer
- MariaDB/MySQL
- Node.js & NPM (para assets frontend)

### Passos de Instalação

1. Clone o repositório:
```bash
git clone <repository-url>
cd pc3-submission-evaluator
```

2. Instale as dependências PHP:
```bash
composer install
```

3. Instale as dependências Node:
```bash
npm install
```

4. Configure o arquivo `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

5. Configure as variáveis de ambiente no `.env`:

```env
# Database Configuration
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pc3_submission_evaluator
DB_USERNAME=root
DB_PASSWORD=

# LLM Configuration
LLM_PROVIDER=anthropic  # ou 'openai'

# Anthropic (Claude) API
ANTHROPIC_API_KEY=your_anthropic_api_key
ANTHROPIC_MODEL=claude-sonnet-4-20250514

# OpenAI (GPT) API
OPENAI_API_KEY=your_openai_api_key
OPENAI_MODEL=gpt-4o
```

6. Execute as migrations:
```bash
php artisan migrate
```

7. Inicie o servidor:
```bash
php artisan serve
```

## Uso da API

### Endpoint: Análise de Código

**POST** `/api/submissoes`

#### Request Body:

```json
{
  "codigo": "função código aqui",
  "enunciado": "Descrição do problema (opcional)",
  "classificacao": "Classificação esperada (opcional)"
}
```

#### Response:

```json
{
  "problemas_detectados": [
    {
      "descricao": "Variável 'x' declarada mas não utilizada",
      "linha": 5
    }
  ],
  "provider": "Anthropic Claude"
}
```

#### Exemplo com cURL:

```bash
curl -X POST http://localhost:8000/api/submissoes \
  -H "Content-Type: application/json" \
  -d '{
    "codigo": "int x = 5;\nint y = 10;\nprintf(\"%d\", y);"
  }'
```

## Configuração dos Provedores LLM

### Usando Anthropic Claude

No arquivo `.env`:
```env
LLM_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

### Usando OpenAI GPT

No arquivo `.env`:
```env
LLM_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o
```

### Obtendo API Keys

- **Anthropic**: https://console.anthropic.com/
- **OpenAI**: https://platform.openai.com/api-keys

## Estrutura do Código

### LLMServiceInterface

Define o contrato para serviços de análise de código:

```php
interface LLMServiceInterface
{
    public function analyzeCode(CodeAnalysisRequest $request): CodeAnalysisResponse;
    public function getProviderName(): string;
}
```

### AnthropicService

Implementação para a API do Claude:
- Utiliza o endpoint `/v1/messages`
- Modelo padrão: `claude-sonnet-4-20250514`
- Extração de JSON com regex para lidar com markdown

### OpenAIService

Implementação para a API do GPT:
- Utiliza o endpoint `/v1/chat/completions`
- Modelo padrão: `gpt-4o`
- Usa `response_format: json_object` para garantir JSON válido

## Testes Comparativos

Para realizar testes comparativos entre os dois modelos:

1. Configure ambas as API keys no `.env`
2. Alterne o provider usando `LLM_PROVIDER`
3. Execute as mesmas submissões de código
4. Compare os resultados retornados

## Desenvolvimento

### Executar em modo desenvolvimento:

```bash
composer run dev
```

Este comando inicia:
- Laravel server (porta 8000)
- Queue listener
- Log viewer (Pail)
- Vite dev server

### Executar testes:

```bash
composer test
```

## Logging

O sistema registra todas as interações com as APIs LLM:

- Erros de API são logados com status code e body
- Erros de extração de JSON são logados com a resposta original
- Logs estão disponíveis em `storage/logs/`

## Estrutura do Banco de Dados

### Tabela: common_comprehension_problems

Armazena a taxonomia de problemas de compreensão utilizados na análise.

## Contribuindo

Este projeto é um TCC acadêmico. Para sugestões ou melhorias, entre em contato com o autor.

## Próximos Passos

- [ ] Adicionar suporte para mais modelos (Gemini, etc.)
- [ ] Implementar cache de respostas para códigos idênticos
- [ ] Implementar comparação side-by-side entre provedores
- [ ] Adicionar testes automatizados
- [ ] Implementar rate limiting
- [ ] Adicionar métricas de custo por análise

## Licença

Este projeto foi desenvolvido para fins acadêmicos.

## Autor

Pedro Zampier - Trabalho de Conclusão de Curso

## Referências

- [Laravel Documentation](https://laravel.com/docs)
- [Anthropic API Documentation](https://docs.anthropic.com/)
- [OpenAI API Documentation](https://platform.openai.com/docs)
