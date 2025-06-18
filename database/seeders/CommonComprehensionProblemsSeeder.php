<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommonComprehensionProblemsSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['problem_code' => 'C8', 'problem_description' => 'Laço for com variável responsável pela iteração sendo sobrescrita', 'dif' => 30],
            ['problem_code' => 'B6', 'problem_description' => 'Comparação Booleana feita utilizando laço while desnecessário', 'dif' => 20],
            ['problem_code' => 'C1', 'problem_description' => 'Condição while testada novamente em seu interior', 'dif' => 20],
            ['problem_code' => 'B8', 'problem_description' => 'Não utilização da estrutura if-elif-else', 'dif' => 16],
            ['problem_code' => 'C2', 'problem_description' => 'Laço redundante ou desnecessário', 'dif' => 16],
            ['problem_code' => 'C4', 'problem_description' => 'Laço for executado por vezes arbitrárias ao invés de usar laço while', 'dif' => 16],
            ['problem_code' => 'D4', 'problem_description' => 'Funções acessando variáveis fora de seu escopo', 'dif' => 16],
            ['problem_code' => 'G4', 'problem_description' => 'Variáveis/funções com nomes não significativos', 'dif' => 16],
            ['problem_code' => 'H1', 'problem_description' => 'Declaração sem efeito', 'dif' => 16],
            ['problem_code' => 'B12', 'problem_description' => 'Consecutivas declarações de if’s iguais com operações distintas em seus blocos', 'dif' => 14],
            ['problem_code' => 'B9', 'problem_description' => 'elif/else retestando condição superior', 'dif' => 14],
            ['problem_code' => 'E2', 'problem_description' => 'Uso redundante ou desnecessário de listas', 'dif' => 14],
            ['problem_code' => 'A4', 'problem_description' => 'Redefinição de built-in', 'dif' => 12],
            ['problem_code' => 'F2', 'problem_description' => 'Verificação individualizada de casos de teste abertos', 'dif' => 12],
            ['problem_code' => 'G5', 'problem_description' => 'Organização arbitrária de declarações', 'dif' => 12],
            ['problem_code' => 'C3', 'problem_description' => 'Operações redundantes calculadas em laço', 'dif' => 10],
            ['problem_code' => 'E1', 'problem_description' => 'Realização desnecessária de todas combinações possíveis para uma finalidade', 'dif' => 10],
            ['problem_code' => 'G3', 'problem_description' => 'Muitas declarações numa mesma linha', 'dif' => 10],
            ['problem_code' => 'A2', 'problem_description' => 'Variável atribuída a si mesma', 'dif' => 8],
            ['problem_code' => 'A6', 'problem_description' => 'Variáveis com valores arbitrários (Magic Numbers) para operações', 'dif' => 8],
            ['problem_code' => 'A7', 'problem_description' => 'Manipulação arbitrária para modificar variáveis declaradas', 'dif' => 8],
            ['problem_code' => 'B11', 'problem_description' => 'Consecutivas declarações de if’s distintas com a mesma operação em seus blocos', 'dif' => 8],
            ['problem_code' => 'B10', 'problem_description' => 'elif/else desnecessário', 'dif' => 6],
            ['problem_code' => 'B3', 'problem_description' => 'Expressão aritmética ao invés de comparação Booleana', 'dif' => 6],
            ['problem_code' => 'B4', 'problem_description' => 'Comandos repetidos dentro de if-elif-else', 'dif' => 6],
            ['problem_code' => 'D1', 'problem_description' => 'Declaração de return inconsistente', 'dif' => 6],
            ['problem_code' => 'A8', 'problem_description' => 'Tratamento arbitrário do fim de condições de leitura de dados', 'dif' => 4],
            ['problem_code' => 'B7', 'problem_description' => 'Variável Booleana para validação ao invés de elif/else', 'dif' => 4],
            ['problem_code' => 'C7', 'problem_description' => 'Tratamento interno arbitrário para casos de contorno de um laço', 'dif' => 2],
            ['problem_code' => 'C6', 'problem_description' => 'Múltiplos laços distintos que operam sobre o mesmo conjunto', 'dif' => 0],
            ['problem_code' => 'F1', 'problem_description' => 'Verificação para condições de entrada não especificadas', 'dif' => 0],
            ['problem_code' => 'H2', 'problem_description' => 'Typecast redundante', 'dif' => 0],
            ['problem_code' => 'G6', 'problem_description' => 'Funções não comentadas no formato Docstring', 'dif' => -4],
            ['problem_code' => 'A1', 'problem_description' => 'Variável não utilizada', 'dif' => -6],
            ['problem_code' => 'A3', 'problem_description' => 'Variável iniciada sem necessidade', 'dif' => -8],
            ['problem_code' => 'B1', 'problem_description' => 'Comparação Booleana redundante ou simplificável', 'dif' => -8],
            ['problem_code' => 'D2', 'problem_description' => 'Muitos return numa mesma função', 'dif' => -8],
            ['problem_code' => 'B5', 'problem_description' => 'Aninhamento de if ao invés de comparação Booleana', 'dif' => -10],
            ['problem_code' => 'G2', 'problem_description' => 'Atribuição demasiada de expressões em variáveis', 'dif' => -10],
            ['problem_code' => 'C5', 'problem_description' => 'Uso de variáveis intermediárias pra controle de laço', 'dif' => -12],
            ['problem_code' => 'D3', 'problem_description' => 'Declaração de return redundante ou desnecessária', 'dif' => -12],
            ['problem_code' => 'H3', 'problem_description' => 'Ponto e vírgula redundante ou desnecessário', 'dif' => -16],
            ['problem_code' => 'B2', 'problem_description' => 'Comparação Booleana feita em variáveis intermediárias', 'dif' => -18],
            ['problem_code' => 'G1', 'problem_description' => 'Comentários longos numa mesma linha', 'dif' => -18],
            ['problem_code' => 'A5', 'problem_description' => 'Import não utilizado', 'dif' => -22],
        ];

        DB::table('common_comprehension_problems')->insert($data);
    }
}
