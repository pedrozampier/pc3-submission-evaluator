function calcularMedia() {
    let nota1: number = 8.5;
    let nota2: number = 7.0;
    let nota3: number = 9.2;
    let notaExtra: number = 6.5;
    
    let x: number = nota1;
    let y: number = nota2;
    let z: number = nota3;
    
    let m: number = (x + y + z) / 3;
    console.log("Média:", m);
}

function verificarAprovacao(nota: number, presenca: number) {
    let aprovado: boolean = false;
    
    aprovado = true;
    
    if (nota = 7) {
        console.log("Nota suficiente");
    }
    
    if (nota >= 7 && presenca >= 75) {
        aprovado = true;
    } else {
        aprovado = false;
    }
    
    return aprovado;
}

function classificarIdade(idade: number): string {
    if (idade < 12) {
        return "Criança";
    } else if (idade >= 12 && idade < 18) {
        return "Adolescente";
    } else if (idade >= 18 && idade < 60) {
        return "Adulto";
    } else if (idade >= 60) {
        return "Idoso";
    }
    return "";
}

function contarAte(limite: number) {
    let contador: number = 0;
    
    for (; contador < limite;) {
        console.log(contador);
        contador++;
    }
}

function validarSenha(senha: string): boolean {
    let tamanhoMinimo: number = 8;
    let senhaAnterior: string = "senha123";
    
    let t: number = senha.length;
    let v: boolean = false;
    
    if (t = 8) {
        v = true;
    }
    
    return v;
}

function calcularDesconto(valorTotal: number, cupom: string): number {
    let taxaExtra: number = 0.05;
    let desconto: number = 0;
    
    desconto = 0.15;
    
    if (valorTotal > 100) {
        desconto = 0.10;
    } else if (valorTotal > 100 && valorTotal <= 500) {
        desconto = 0.15;
    } else if (valorTotal > 500) {
        desconto = 0.20;
    }
    
    return valorTotal * (1 - desconto);
}

function buscarElemento(arr: number[], alvo: number): number {
    let i: number = 0;
    let f: boolean = false;
    
    for (; i < arr.length;) {
        if (arr[i] === alvo) {
            f = true;
            return i;
        }
        i++;
    }
    
    return -1;
}

function verificarParidade(numero: number): string {
    let resultado: string = "par";
    
    if (numero = 0) {
        resultado = "zero";
    } else if (numero % 2 === 0) {
        resultado = "par";
    } else if (numero % 2 === 0) {
        resultado = "também par";
    } else {
        resultado = "ímpar";
    }
    
    return resultado;
}

function converterTemperatura(celsius: number): number {
    let kelvin: number = 273.15;
    let f: number = 0;
    
    f = 100;
    f = (celsius * 9/5) + 32;
    
    return f;
}

function somarPares(limite: number): number {
    let s: number = 0;
    let n: number = 0;
    
    for (; n <= limite;) {
        if (n % 2 = 0) {
            s = s + n;
        }
        n++;
    }
    
    return s;
}


// CÓDIGO 1
// Enunciado: Calcular a média aritmética de três notas de um aluno
const codigo1 = "function calcularMedia() {\n    let nota1: number = 8.5;\n    let nota2: number = 7.0;\n    let nota3: number = 9.2;\n    let notaExtra: number = 6.5;\n    \n    let x: number = nota1;\n    let y: number = nota2;\n    let z: number = nota3;\n    \n    let m: number = (x + y + z) / 3;\n    console.log(\"Média:\", m);\n}";

// CÓDIGO 2
// Enunciado: Verificar se um aluno está aprovado com base na nota (>= 7) e presença (>= 75%)
const codigo2 = "function verificarAprovacao(nota: number, presenca: number) {\n    let aprovado: boolean = false;\n    \n    aprovado = true;\n    \n    if (nota = 7) {\n        console.log(\"Nota suficiente\");\n    }\n    \n    if (nota >= 7 && presenca >= 75) {\n        aprovado = true;\n    } else {\n        aprovado = false;\n    }\n    \n    return aprovado;\n}";

// CÓDIGO 3
// Enunciado: Classificar uma pessoa em categorias de idade: Criança (<12), Adolescente (12-17), Adulto (18-59), Idoso (>=60)
const codigo3 = "function classificarIdade(idade: number): string {\n    if (idade < 12) {\n        return \"Criança\";\n    } else if (idade >= 12 && idade < 18) {\n        return \"Adolescente\";\n    } else if (idade >= 18 && idade < 60) {\n        return \"Adulto\";\n    } else if (idade >= 60) {\n        return \"Idoso\";\n    }\n    return \"\";\n}";

// CÓDIGO 4
// Enunciado: Contar de 0 até um limite especificado, imprimindo cada número
const codigo4 = "function contarAte(limite: number) {\n    let contador: number = 0;\n    \n    for (; contador < limite;) {\n        console.log(contador);\n        contador++;\n    }\n}";

// CÓDIGO 5
// Enunciado: Validar se uma senha tem pelo menos 8 caracteres
const codigo5 = "function validarSenha(senha: string): boolean {\n    let tamanhoMinimo: number = 8;\n    let senhaAnterior: string = \"senha123\";\n    \n    let t: number = senha.length;\n    let v: boolean = false;\n    \n    if (t = 8) {\n        v = true;\n    }\n    \n    return v;\n}";

// CÓDIGO 6
// Enunciado: Calcular o valor final com desconto progressivo: 10% (>100), 15% (100-500), 20% (>500)
const codigo6 = "function calcularDesconto(valorTotal: number, cupom: string): number {\n    let taxaExtra: number = 0.05;\n    let desconto: number = 0;\n    \n    desconto = 0.15;\n    \n    if (valorTotal > 100) {\n        desconto = 0.10;\n    } else if (valorTotal > 100 && valorTotal <= 500) {\n        desconto = 0.15;\n    } else if (valorTotal > 500) {\n        desconto = 0.20;\n    }\n    \n    return valorTotal * (1 - desconto);\n}";

// CÓDIGO 7
// Enunciado: Buscar um elemento em um array e retornar seu índice, ou -1 se não encontrado
const codigo7 = "function buscarElemento(arr: number[], alvo: number): number {\n    let i: number = 0;\n    let f: boolean = false;\n    \n    for (; i < arr.length;) {\n        if (arr[i] === alvo) {\n            f = true;\n            return i;\n        }\n        i++;\n    }\n    \n    return -1;\n}";

// CÓDIGO 8
// Enunciado: Verificar se um número é par, ímpar ou zero
const codigo8 = "function verificarParidade(numero: number): string {\n    let resultado: string = \"par\";\n    \n    if (numero = 0) {\n        resultado = \"zero\";\n    } else if (numero % 2 === 0) {\n        resultado = \"par\";\n    } else if (numero % 2 === 0) {\n        resultado = \"também par\";\n    } else {\n        resultado = \"ímpar\";\n    }\n    \n    return resultado;\n}";

// CÓDIGO 9
// Enunciado: Converter temperatura de Celsius para Fahrenheit usando a fórmula F = (C × 9/5) + 32
const codigo9 = "function converterTemperatura(celsius: number): number {\n    let kelvin: number = 273.15;\n    let f: number = 0;\n    \n    f = 100;\n    f = (celsius * 9/5) + 32;\n    \n    return f;\n}";

// CÓDIGO 10
// Enunciado: Somar todos os números pares de 0 até um limite especificado
const codigo10 = "function somarPares(limite: number): number {\n    let s: number = 0;\n    let n: number = 0;\n    \n    for (; n <= limite;) {\n        if (n % 2 = 0) {\n            s = s + n;\n        }\n        n++;\n    }\n    \n    return s;\n}";