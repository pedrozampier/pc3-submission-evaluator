// ============================================================================
// Corpus de Validação — Catálogo PC³ (TypeScript)
// Lopes & Garcia — Introdução à Programação: 500 Algoritmos Resolvidos
// ============================================================================
// Nomenclatura: C8-1_181
//   C8  -> Classificação PC³ do erro
//   1   -> Número do código do erro (1 a 5)
//   181 -> Número do algoritmo no livro
// ============================================================================

import teclado from "npm:readline-sync";

// ============================================================================
// B9 — Instrução else-if retestando condições já verificadas
// ============================================================================

// ----------------------------------------------------------------------------
// B9-1_097 · B9
// Enunciado: Entrar com um número e informar se ele é divisível por 10, por 5,
// por 2 ou se não é divisível por nenhum destes.
// ----------------------------------------------------------------------------
function B9_1_097(): void {
  let numero: number = 0;
  console.log("\ndigite numero: ");
  numero = teclado.questionInt();
  if (numero % 10 === 0) {
    console.log("\nmúltiplo de 10");
  } else if (numero % 10 !== 0 && numero % 2 === 0) {
    console.log("\nmúltiplo de 2");
  } else if (numero % 10 !== 0 && numero % 2 !== 0 && numero % 5 === 0) {
    console.log("\nmúltiplo de 5");
  } else {
    console.log("\nnão é múltiplo de 2 nem de 5");
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B9-2_099 · B9
// Enunciado: Ler um número inteiro de 3 casas decimais e imprimir se o
// algarismo da casa das centenas é par ou ímpar.
// ----------------------------------------------------------------------------
function B9_2_099(): void {
  let num: number = 0,
    c: number = 0;
  console.log("\número de 3 algarismos: ");
  num = teclado.questionInt();
  c = Math.trunc(num / 100);
  if (c % 2 === 0) {
    console.log("\no algarismo das centenas e par: ", c);
  } else if (c % 2 !== 0) {
    console.log("\no algarismo das centenas é impar: ", c);
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B9-3_102 · B9 + B8
// Enunciado: Entrar com um número e imprimir uma das mensagens: maior do que
// 20, igual a 20 ou menor do que 20.
// ----------------------------------------------------------------------------
function B9_3_102(): void {
  let numero: number = 0;
  console.log("\ndigite numero: ");
  numero = teclado.questionFloat();
  if (numero > 20) {
    console.log("\nmaior que 20");
  } else if (numero <= 20 && numero < 20) {
    console.log("\nmenor que 20");
  }
  if (numero === 20) {
    console.log("\nigual a 20");
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B9-4_103 · B9 + B8
// Enunciado: Entrar com o ano de nascimento de uma pessoa e o ano atual.
// Imprimir a idade da pessoa. Não se esqueça de verificar se o ano de
// nascimento é um ano válido.
// ----------------------------------------------------------------------------
function B9_4_103(): void {
  let anon: number = 0,
    anoa: number = 0;
  console.log("\nEntre com ano atual: ");
  anoa = teclado.questionInt();
  console.log("\nEntre com ano de nascimento: ");
  anon = teclado.questionInt();
  if (anon > anoa) {
    console.log("\nAno de Nascimento Invalido");
  } else if (anon <= anoa) {
    console.log("\nIdade: ", anoa - anon);
  }
  if (anon === anoa) {
    console.log("\nIdade: 0");
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B9-5_105 · B9 + B12 + B8
// Enunciado: Entrar com a sigla do estado de uma pessoa e imprimir uma das
// mensagens: carioca, paulista, mineiro, outros estados.
// ----------------------------------------------------------------------------
function B9_5_105(): void {
  let sigla: string = "";
  console.log("\ndigite sigla: ");
  sigla = teclado.question();
  if (sigla === "RJ" || sigla === "rj") {
    console.log("\ncarioca");
  } else if (sigla !== "RJ" && sigla !== "rj" && (sigla === "SP" || sigla === "sp")) {
    console.log("\npaulista");
  } else if (
    sigla !== "RJ" &&
    sigla !== "rj" &&
    sigla !== "SP" &&
    sigla !== "sp" &&
    (sigla === "MG" || sigla === "mg")
  ) {
    console.log("\nmineiro");
  }
  if (sigla !== "RJ" && sigla !== "rj") {
    if (sigla !== "SP" && sigla !== "sp") {
      if (sigla !== "MG" && sigla !== "mg") {
        console.log("\noutros estados");
      }
    }
  }
  console.log("\n");
}

// ============================================================================
// B8 — Não utilização correta da estrutura if-else
// ============================================================================

// ----------------------------------------------------------------------------
// B8-1_102 · B8
// Enunciado: Entrar com um número e imprimir uma das mensagens: maior do que
// 20, igual a 20 ou menor do que 20.
// ----------------------------------------------------------------------------
function B8_1_102(): void {
  let numero: number = 0;
  console.log("\ndigite numero: ");
  numero = teclado.questionFloat();
  if (numero > 20) {
    console.log("\nmaior que 20");
  }
  if (numero < 20) {
    console.log("\nmenor que 20");
  }
  if (numero === 20) {
    console.log("\nigual a 20");
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B8-2_103 · B8
// Enunciado: Entrar com o ano de nascimento de uma pessoa e o ano atual.
// Imprimir a idade da pessoa. Não se esqueça de verificar se o ano de
// nascimento é um ano válido.
// ----------------------------------------------------------------------------
function B8_2_103(): void {
  let anon: number = 0,
    anoa: number = 0;
  console.log("\nEntre com ano atual: ");
  anoa = teclado.questionInt();
  console.log("\nEntre com ano de nascimento: ");
  anon = teclado.questionInt();
  if (anon > anoa) {
    console.log("\nAno de Nascimento Invalido");
  }
  if (anon <= anoa) {
    console.log("\nIdade: ", anoa - anon);
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B8-3_097 · B8 + B9
// Enunciado e código: Ver B9-1_097 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver B9_1_097)

// ----------------------------------------------------------------------------
// B8-4_099 · B8 + B12
// Enunciado: Ler um número inteiro de 3 casas decimais e imprimir se o
// algarismo da casa das centenas é par ou ímpar.
// ----------------------------------------------------------------------------
function B8_4_099(): void {
  let num: number = 0,
    c: number = 0;
  console.log("\nnumero de 3 algarismos: ");
  num = teclado.questionInt();
  c = Math.trunc(num / 100);
  if (c % 2 === 0) {
    console.log("\no algarismo das centenas e par: ", c);
  }
  if (c % 2 === 0) {
    console.log("\nalgarismo par confirmado: ", c);
  }
  if (c % 2 !== 0) {
    console.log("\no algarismo das centenas e impar: ", c);
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B8-5_105 · B8 + B9 + B12
// Enunciado e código: Ver B9-5_105 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver B9_5_105)

// ============================================================================
// B12 — Consecutivas declarações de ifs iguais com operações distintas
// ============================================================================

// ----------------------------------------------------------------------------
// B12-1_105 · B12
// Enunciado: Entrar com a sigla do estado de uma pessoa e imprimir uma das
// mensagens: carioca, paulista, mineiro, outros estados.
// ----------------------------------------------------------------------------
function B12_1_105(): void {
  let sigla: string = "";
  console.log("\ndigite sigla: ");
  sigla = teclado.question();
  if (sigla === "RJ" || sigla === "rj") {
    console.log("\ncarioca");
  }
  if (sigla === "RJ" || sigla === "rj") {
    console.log("\nestado: Rio de Janeiro");
  }
  if (sigla === "SP" || sigla === "sp") {
    console.log("\npaulista");
  }
  if (sigla === "MG" || sigla === "mg") {
    console.log("\nmineiro");
  }
  if (
    sigla !== "RJ" &&
    sigla !== "rj" &&
    sigla !== "SP" &&
    sigla !== "sp" &&
    sigla !== "MG" &&
    sigla !== "mg"
  ) {
    console.log("\noutros estados");
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B12-2_097 · B12
// Enunciado: Entrar com um número e informar se ele é divisível por 10, por 5,
// por 2 ou se não é divisível por nenhum destes.
// ----------------------------------------------------------------------------
function B12_2_097(): void {
  let numero: number = 0;
  console.log("\ndigite numero: ");
  numero = teclado.questionInt();
  if (numero % 10 === 0) {
    console.log("\nmúltiplo de 10");
  }
  if (numero % 10 === 0) {
    console.log("\ndivisível por 10");
  }
  if (numero % 2 === 0) {
    console.log("\nmúltiplo de 2");
  }
  if (numero % 5 === 0) {
    console.log("\nmúltiplo de 5");
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B12-3_102 · B12 + B8
// Enunciado e código: Ver B8-1_102 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver B8_1_102)

// ----------------------------------------------------------------------------
// B12-4_099 · B12 + B8
// Enunciado e código: Ver B8-4_099 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver B8_4_099)

// ----------------------------------------------------------------------------
// B12-5_103 · B12 + B9 + B8
// Enunciado: Entrar com o ano de nascimento de uma pessoa e o ano atual.
// Imprimir a idade da pessoa. Não se esqueça de verificar se o ano de
// nascimento é um ano válido.
// ----------------------------------------------------------------------------
function B12_5_103(): void {
  let anon: number = 0,
    anoa: number = 0;
  console.log("\nEntre com ano atual: ");
  anoa = teclado.questionInt();
  console.log("\nEntre com ano de nascimento: ");
  anon = teclado.questionInt();
  if (anon > anoa) {
    console.log("\nAno de Nascimento Invalido");
  }
  if (anon > anoa) {
    console.log("\nVerificacao: ano invalido");
  }
  if (anon <= anoa) {
    console.log("\nIdade: ", anoa - anon);
  } else if (anon > anoa) {
    console.log("\nAno de Nascimento Invalido");
  }
  console.log("\n");
}

// ============================================================================
// C8 — Laço for com variável responsável pela iteração sendo sobrescrita
// ============================================================================

// ----------------------------------------------------------------------------
// C8-1_181 · C8
// Enunciado: Criar um algoritmo que imprima todos os números de 1 até 100 e a
// soma deles.
// ----------------------------------------------------------------------------
function C8_1_181(): void {
  let i: number = 0,
    soma: number = 0;
  soma = 0;
  for (i = 1; i <= 100; i++) {
    i = i;
    soma = soma + i;
    console.log(i, " ");
  }
  console.log("\nSomatorio de 1 a 100: ", soma);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C8-2_181 · C8
// Enunciado: Criar um algoritmo que imprima todos os números de 1 até 100 e a
// soma deles.
// ----------------------------------------------------------------------------
function C8_2_181(): void {
  let i: number = 0,
    soma: number = 0;
  soma = 0;
  for (i = 1; i <= 100; i++) {
    soma = soma + i;
    console.log(i, " ");
    i = i + 0;
  }
  console.log("\nSomatorio de 1 a 100: ", soma);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C8-3_181 · C8 + C3
// Enunciado: Criar um algoritmo que imprima todos os números de 1 até 100 e a
// soma deles.
// ----------------------------------------------------------------------------
function C8_3_181(): void {
  let i: number = 0,
    soma: number = 0;
  soma = 0;
  for (i = 1; i <= 100; i++) {
    i = i;
    soma = soma + i;
    soma = soma - 0;
    console.log(i, " ");
  }
  console.log("\nSomatorio de 1 a 100: ", soma);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C8-4_181 · C8 + C1
// Enunciado: Criar um algoritmo que imprima todos os números de 1 até 100 e a
// soma deles.
// ----------------------------------------------------------------------------
function C8_4_181(): void {
  let i: number = 0,
    soma: number = 0;
  soma = 0;
  for (i = 1; i <= 100; i++) {
    i = i;
    if (i <= 100) {
      soma = soma + i;
      console.log(i, " ");
    }
  }
  console.log("\nSomatorio de 1 a 100: ", soma);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C8-5_181 · C8 + C3 + C1
// Enunciado: Criar um algoritmo que imprima todos os números de 1 até 100 e a
// soma deles.
// ----------------------------------------------------------------------------
function C8_5_181(): void {
  let i: number = 0,
    soma: number = 0;
  soma = 0;
  for (i = 1; i <= 100; i++) {
    i = i;
    soma = soma - 0;
    if (i <= 100) {
      soma = soma + i;
      console.log(i, " ");
    }
  }
  console.log("\nSomatorio de 1 a 100: ", soma);
  console.log("\n");
}

// ============================================================================
// C1 — Condição while testada novamente no interior do bloco
// ============================================================================

// ----------------------------------------------------------------------------
// C1-1_262 · C1
// Enunciado: Entrar com números e imprimir o triplo de cada número. O
// algoritmo acaba quando entrar o número -999.
// ----------------------------------------------------------------------------
function C1_1_262(): void {
  let num: number = 0;
  console.log("\ndigite numero ou -999. para terminar: ");
  num = teclado.questionFloat();
  while (num !== -999) {
    if (num !== -999) {
      console.log("\ntriplo: ", num * 3);
    }
    console.log("\ndigite numero ou -999. para terminar: ");
    num = teclado.questionFloat();
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C1-2_263 · C1
// Enunciado: Entrar com números enquanto forem positivos e imprimir quantos
// números foram digitados.
// ----------------------------------------------------------------------------
function C1_2_263(): void {
  let a: number = 0;
  let num: number = 0;
  a = 0;
  console.log("\ndigite numero positivo: ");
  num = teclado.questionFloat();
  while (num > 0) {
    if (num > 0) {
      a++;
    }
    console.log("\nigite numero positivo: ");
    num = teclado.questionFloat();
  }
  console.log("\ntotal: ", a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C1-3_264 · C1 + C3
// Enunciado: Entrar com vários números positivos e imprimir a média dos
// números digitados.
// ----------------------------------------------------------------------------
function C1_3_264(): void {
  let a: number = 0;
  let num: number = 0,
    soma: number = 0;
  a = 0;
  soma = 0;
  console.log("\ndigite numero positivo: ");
  num = teclado.questionFloat();
  while (num > 0) {
    if (num > 0) {
      a++;
      soma = soma + num;
    }
    soma = soma + 0;
    console.log("\ndigite numero positivo: ");
    num = teclado.questionFloat();
  }
  console.log("\nmédia: ", soma / a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C1-4_265 · C1 + C3
// Enunciado: Ler vários números e informar quantos números entre 100 e 200
// foram digitados. Quando o valor 0 (zero) for lido, o algoritmo deverá cessar
// sua execução.
// ----------------------------------------------------------------------------
function C1_4_265(): void {
  let a: number = 0;
  let num: number = 0;
  a = 0;
  console.log("\ndigite numero ou 0 para sair: ");
  num = teclado.questionFloat();
  while (num !== 0) {
    a = a + 0;
    if (num !== 0) {
      if (num >= 100 && num <= 200) {
        a++;
      }
    }
    console.log("digite numero ou 0 para sair: ");
    num = teclado.questionFloat();
  }
  console.log("\ntotal: ", a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C1-5_181 · C1 + C3 + C8
// Enunciado e código: Ver C8-5_181 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver C8_5_181)

// ============================================================================
// C3 — Operações redundantes dentro de um laço
// ============================================================================

// ----------------------------------------------------------------------------
// C3-1_264 · C3
// Enunciado: Entrar com vários números positivos e imprimir a média dos
// números digitados.
// ----------------------------------------------------------------------------
function C3_1_264(): void {
  let a: number = 0;
  let num: number = 0,
    soma: number = 0;
  a = 0;
  soma = 0;
  console.log("\ndigite numero positivo: ");
  num = teclado.questionFloat();
  while (num > 0) {
    a++;
    soma = soma + num;
    console.log("\nmédia: ", soma / a);
    console.log("\ndigite numero positivo: ");
    num = teclado.questionFloat();
  }
  console.log("\nmédia: ", soma / a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C3-2_265 · C3
// Enunciado: Ler vários números e informar quantos números entre 100 e 200
// foram digitados. Quando o valor 0 (zero) for lido, o algoritmo deverá cessar
// sua execução.
// ----------------------------------------------------------------------------
function C3_2_265(): void {
  let a: number = 0;
  let num: number = 0;
  a = 0;
  console.log("\ndigite numero ou 0 para sair: ");
  num = teclado.questionFloat();
  while (num !== 0) {
    a = 0 + a;
    if (num >= 100 && num <= 200) {
      a++;
    }
    console.log("digite numero ou 0 para sair: ");
    num = teclado.questionFloat();
  }
  console.log("\ntotal: ", a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// C3-3_263 · C3 + C1
// Enunciado e código: Ver C1-2_263 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver C1_2_263)

// ----------------------------------------------------------------------------
// C3-4_181 · C3 + C8
// Enunciado e código: Ver C8-3_181 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver C8_3_181)

// ----------------------------------------------------------------------------
// C3-5_181 · C3 + C1 + C8
// Enunciado e código: Ver C8-5_181 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver C8_5_181)

// ============================================================================
// B6 — Tentativa de comparação booleana feita com laço while
// ============================================================================

// ----------------------------------------------------------------------------
// B6-1_262 · B6
// Enunciado: Entrar com números e imprimir o triplo de cada número. O
// algoritmo acaba quando entrar o número -999.
// ----------------------------------------------------------------------------
function B6_1_262(): void {
  let num: number = 0;
  let continuar: boolean = true;
  console.log("\ndigite numero ou -999. para terminar: ");
  num = teclado.questionFloat();
  while (continuar === true) {
    console.log("\ntriplo: ", num * 3);
    console.log("\ndigite numero ou -999. para terminar: ");
    num = teclado.questionFloat();
    if (num === -999) {
      continuar = false;
    }
  }
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B6-2_263 · B6
// Enunciado: Entrar com números enquanto forem positivos e imprimir quantos
// números foram digitados.
// ----------------------------------------------------------------------------
function B6_2_263(): void {
  let a: number = 0;
  let num: number = 0;
  let continuar: boolean = true;
  a = 0;
  console.log("\ndigite numero positivo: ");
  num = teclado.questionFloat();
  while (continuar === true) {
    a++;
    console.log("\nigite numero positivo: ");
    num = teclado.questionFloat();
    if (num <= 0) {
      continuar = false;
    }
  }
  console.log("\ntotal: ", a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B6-3_264 · B6 + C3
// Enunciado: Entrar com vários números positivos e imprimir a média dos
// números digitados.
// ----------------------------------------------------------------------------
function B6_3_264(): void {
  let a: number = 0;
  let num: number = 0,
    soma: number = 0;
  let continuar: boolean = true;
  a = 0;
  soma = 0;
  console.log("\ndigite numero positivo: ");
  num = teclado.questionFloat();
  while (continuar === true) {
    a++;
    soma = soma + num;
    console.log("\nmédia: ", soma / a);
    console.log("\ndigite numero positivo: ");
    num = teclado.questionFloat();
    if (num <= 0) {
      continuar = false;
    }
  }
  console.log("\nmédia: ", soma / a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B6-4_265 · B6 + C1
// Enunciado: Ler vários números e informar quantos números entre 100 e 200
// foram digitados. Quando o valor 0 (zero) for lido, o algoritmo deverá cessar
// sua execução.
// ----------------------------------------------------------------------------
function B6_4_265(): void {
  let a: number = 0;
  let num: number = 0;
  let continuar: boolean = true;
  a = 0;
  console.log("\ndigite numero ou 0 para sair: ");
  num = teclado.questionFloat();
  while (continuar === true) {
    if (continuar === true) {
      if (num >= 100 && num <= 200) {
        a++;
      }
    }
    console.log("digite numero ou 0 para sair: ");
    num = teclado.questionFloat();
    if (num === 0) {
      continuar = false;
    }
  }
  console.log("\ntotal: ", a);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// B6-5_262 · B6 + C1 + C3
// Enunciado: Entrar com números e imprimir o triplo de cada número. O
// algoritmo acaba quando entrar o número -999.
// ----------------------------------------------------------------------------
function B6_5_262(): void {
  let num: number = 0;
  let continuar: boolean = true;
  console.log("\ndigite numero ou -999. para terminar: ");
  num = teclado.questionFloat();
  while (continuar === true) {
    if (continuar === true) {
      console.log("\ntriplo: ", num * 3);
    }
    num = num + 0;
    console.log("\ndigite numero ou -999. para terminar: ");
    num = teclado.questionFloat();
    if (num === -999) {
      continuar = false;
    }
  }
  console.log("\n");
}

// ============================================================================
// G4 — Identificadores com nomes não significativos
// ============================================================================

// ----------------------------------------------------------------------------
// G4-1_039 · G4
// Enunciado: Entrar com dois números reais e imprimir a média aritmética com a
// mensagem "média" antes do resultado.
// ----------------------------------------------------------------------------
function G4_1_039(): void {
  let n1: number = 0,
    n2: number = 0,
    m: number = 0;
  console.log("\ndigite 1a nota: ");
  n1 = teclado.questionFloat();
  console.log("\ndigite 2a nota: ");
  n2 = teclado.questionFloat();
  m = (n1 + n2) / 2;
  console.log("\nmedia: ", m);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// G4-2_040 · G4
// Enunciado: Entrar com dois números inteiros e imprimir a seguinte saída:
// dividendo / divisor / quociente / resto.
// ----------------------------------------------------------------------------
function G4_2_040(): void {
  let q: number = 0,
    r: number = 0,
    a: number = 0,
    b: number = 0;
  console.log("\nentre com o dividendo: ");
  a = teclado.questionInt();
  console.log("\nentre com divisor: ");
  b = teclado.questionInt();
  q = Math.trunc(a / b);
  r = a % b;
  console.log("\n\n");
  console.log("\ndividendo : ", a);
  console.log("\ndivisor : ", b);
  console.log("\nquociente : ", q);
  console.log("\nresto : ", r);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// G4-3_036 · G4 + H1
// Enunciado: Ler dois números inteiros e imprimir a soma. Antes do resultado,
// deverá aparecer a mensagem: Soma.
// ----------------------------------------------------------------------------
function G4_3_036(): void {
  let x: number = 0,
    y: number = 0,
    s: number = 0;
  console.log("\n entre com um numero: ");
  x = teclado.questionInt();
  console.log("\n entre com outro numero: ");
  y = teclado.questionInt();
  s = x + y;
  s = s;
  console.log("\nSoma: ", s);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// G4-4_037 · G4 + G3
// Enunciado: Ler dois números inteiros e imprimir o produto.
// ----------------------------------------------------------------------------
function G4_4_037(): void {
  let x: number = 0, y: number = 0, p: number = 0;
  console.log("\n entre com um numero: ");
  x = teclado.questionInt();
  console.log("\n entre com outro numero: ");
  y = teclado.questionInt();
  p = x * y;
  console.log("\nproduto: ", p);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// G4-5_033 · G4 + H1 + G3
// Enunciado: Ler dois números inteiros e imprimi-los.
// ----------------------------------------------------------------------------
function G4_5_033(): void {
  let x: number = 0, y: number = 0;
  console.log("\n entre com um numero: ");
  x = teclado.questionInt();
  x = x;
  console.log("\n entre com outro numero: ");
  y = teclado.questionInt();
  console.log("\nnumero 1 : ", x);
  console.log("\nnumero 2 : ", y);
  console.log("\n");
}

// ============================================================================
// H1 — Código sem efeito
// ============================================================================

// ----------------------------------------------------------------------------
// H1-1_036 · H1
// Enunciado: Ler dois números inteiros e imprimir a soma. Antes do resultado,
// deverá aparecer a mensagem: Soma.
// ----------------------------------------------------------------------------
function H1_1_036(): void {
  let num1: number = 0,
    num2: number = 0,
    soma: number = 0;
  console.log("\n entre com um numero: ");
  num1 = teclado.questionInt();
  console.log("\n entre com outro numero: ");
  num2 = teclado.questionInt();
  soma = num1 + num2;
  soma = soma;
  console.log("\nSoma: ", soma);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// H1-2_037 · H1
// Enunciado: Ler dois números inteiros e imprimir o produto.
// ----------------------------------------------------------------------------
function H1_2_037(): void {
  let num1: number = 0,
    num2: number = 0,
    prod: number = 0,
    dobro: number = 0;
  console.log("\n entre com um numero: ");
  num1 = teclado.questionInt();
  console.log("\n entre com outro numero: ");
  num2 = teclado.questionInt();
  prod = num1 * num2;
  dobro = prod * 2;
  console.log("\nproduto: ", prod);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// H1-3_039 · H1 + G4
// Enunciado e código: Ver G4-1_039 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver G4_1_039)

// ----------------------------------------------------------------------------
// H1-4_033 · H1 + G3
// Enunciado: Ler dois números inteiros e imprimi-los.
// ----------------------------------------------------------------------------
function H1_4_033(): void {
  let num1: number = 0, num2: number = 0;
  console.log("\n entre com um numero: ");
  num1 = teclado.questionInt();
  num1 = num1;
  console.log("\n entre com outro numero: ");
  num2 = teclado.questionInt();
  console.log("\nnumero 1 : ", num1);
  console.log("\nnumero 2 : ", num2);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// H1-5_040 · H1 + G4 + G3
// Enunciado: Entrar com dois números inteiros e imprimir a seguinte saída:
// dividendo / divisor / quociente / resto.
// ----------------------------------------------------------------------------
function H1_5_040(): void {
  let q: number = 0, r: number = 0, a: number = 0, b: number = 0;
  console.log("\nentre com o dividendo: ");
  a = teclado.questionInt();
  console.log("\nentre com divisor: ");
  b = teclado.questionInt();
  q = Math.trunc(a / b);
  r = a % b;
  r = r;
  console.log("\n\n");
  console.log("\ndividendo : ", a);
  console.log("\ndivisor : ", b);
  console.log("\nquociente : ", q);
  console.log("\nresto : ", r);
  console.log("\n");
}

// ============================================================================
// G3 — Muitas declarações em uma única linha de código
// ============================================================================

// ----------------------------------------------------------------------------
// G3-1_033 · G3
// Enunciado: Ler dois números inteiros e imprimi-los.
// ----------------------------------------------------------------------------
function G3_1_033(): void {
  let num1: number = 0, num2: number = 0;
  console.log("\n entre com um numero: ");
  num1 = teclado.questionInt();
  console.log("\n entre com outro numero: ");
  num2 = teclado.questionInt();
  console.log("\nnumero 1 : ", num1);
  console.log("\nnumero 2 : ", num2);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// G3-2_040 · G3
// Enunciado: Entrar com dois números inteiros e imprimir a seguinte saída:
// dividendo / divisor / quociente / resto.
// ----------------------------------------------------------------------------
function G3_2_040(): void {
  let quoc: number = 0, rest: number = 0, val1: number = 0, val2: number = 0;
  console.log("\nentre com o dividendo: ");
  val1 = teclado.questionInt();
  console.log("\nentre com divisor: ");
  val2 = teclado.questionInt();
  quoc = Math.trunc(val1 / val2);
  rest = val1 % val2;
  console.log("\n\n");
  console.log("\ndividendo : ", val1);
  console.log("\ndivisor : ", val2);
  console.log("\nquociente : ", quoc);
  console.log("\nresto : ", rest);
  console.log("\n");
}

// ----------------------------------------------------------------------------
// G3-3_037 · G3 + H1
// Enunciado e código: Ver H1-2_037 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver H1_2_037)

// ----------------------------------------------------------------------------
// G3-4_036 · G3 + G4
// Enunciado e código: Ver G4-3_036 — exercício compartilhado entre os erros.
// ----------------------------------------------------------------------------
// (compartilhado — ver G4_3_036)

// ----------------------------------------------------------------------------
// G3-5_039 · G3 + G4 + H1
// Enunciado: Entrar com dois números reais e imprimir a média aritmética com a
// mensagem "média" antes do resultado.
// ----------------------------------------------------------------------------
function G3_5_039(): void {
  let n1: number = 0, n2: number = 0, m: number = 0;
  console.log("\ndigite 1a nota: ");
  n1 = teclado.questionFloat();
  console.log("\ndigite 2a nota: ");
  n2 = teclado.questionFloat();
  m = (n1 + n2) / 2;
  m = m;
  console.log("\nmedia: ", m);
  console.log("\n");
}