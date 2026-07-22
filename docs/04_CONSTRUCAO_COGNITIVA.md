# Construção cognitiva

## Objetivo

Construir a memória documental a partir de unidades organizadas, preservando origem e hierarquia sem materializar combinações relacionais.

## Movimento ascendente

```text
trechos → subtítulos → capítulos → seções → partes → obra
```

O resumo de um nó pai deriva de seu conteúdo próprio e das sínteses filhas. Cada evidência derivada registra em `evidence_derivations` as evidências que a originaram.

## Classes e tipos

- `primary` + `node_content`: conteúdo literal extraído do nó.
- `derived` + `node_summary`: síntese hierárquica gerada a partir de evidências identificadas.

Esses campos são o contrato semântico persistente do Evidence Algorithm. Conteúdo original e conteúdo gerado permanecem distinguíveis.

## Embeddings

Cada embedding representa uma evidência completa já organizada pelo documento. O texto vetorizado inclui contexto estrutural, como título, caminho, classe, tipo e conteúdo da unidade.

Embeddings nunca são formados por cortes arbitrários de caracteres ou tokens. Quando uma unidade excede a capacidade técnica, o sistema utiliza sua subdivisão documental e as sínteses correspondentes; não inventa fragmentos por tamanho.

Antes de qualquer lote ser enviado ao provedor, `EmbeddingInputGuard` estima de forma conservadora o tamanho de todas as unidades pendentes. O limite nominal é definido por `AI_EMBEDDING_MAX_INPUT_TOKENS`; a aplicação utiliza 90% desse valor como margem preventiva contra diferenças entre tokenizadores.

Uma evidência primária incompatível não é truncada nem enviada ao provedor. Se existir uma síntese `derived` + `node_summary` válida, diretamente ligada à primária por `evidence_derivations` e compatível com o limite, o embedding da síntese passa a ser a rota semântica daquela unidade. A recuperação continua resolvendo essa síntese até a evidência primária integral, preservando conteúdo, identificador e linhagem.

Se a evidência primária incompatível não possuir essa síntese derivada válida, a etapa é interrompida antes da primeira requisição ao provedor. O diagnóstico informa o identificador público da evidência e exige uma subdivisão estrutural real da fonte. Aumentar o lote, cortar texto ou criar fragmentos artificiais não é uma correção permitida.

Modelo, dimensão e hash identificam a versão vetorial. Similaridades são usadas apenas durante a recuperação e descartadas após a ordenação.

## Limite da construção persistente

A construção termina em sínteses, derivações e embeddings. Não existe etapa de Cnode, análise antecipada de pares, cache de interação ou vetor relacional.

Esse limite evita explosão combinatória, chamadas externas sem demanda e duplicação da informação já presente nas evidências.

## Implementação

`HierarchicalSummaryService` percorre a árvore de baixo para cima e reutiliza versões idênticas por modelo e hash. `EvidenceEmbeddingService` valida a compatibilidade de todas as unidades pendentes e vetoriza evidências primárias e derivadas em lotes técnicos que preservam cada unidade completa. O resultado da etapa expõe `represented_by_derived`, permitindo auditar quantas primárias excedentes foram representadas semanticamente por sínteses rastreáveis.

A CLI possui somente as etapas:

```powershell
php bin/build-cognitive.php <document-id> --stage=summaries --live
php bin/build-cognitive.php <document-id> --stage=embeddings --live
```
