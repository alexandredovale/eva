# Construção cognitiva

## Objetivo

Construir a memória documental a partir de evidências primárias completas, preservando origem e hierarquia sem materializar combinações relacionais.

## Classe e tipo operacional

- `primary` + `node_content`: conteúdo literal extraído do nó.

Na versão 6.0.0, essa é a única combinação operacional persistida pelo Evidence Algorithm. Resumos derivados e linhagens de síntese não integram mais a captura, a construção ou a consulta.

## Embeddings

Cada embedding representa uma evidência primária completa já organizada pelo documento. O texto vetorizado inclui contexto estrutural, como título, caminho, classe, tipo e conteúdo literal da unidade.

Embeddings nunca são formados por cortes arbitrários de caracteres ou tokens. Quando uma unidade excede a capacidade técnica, o documento exige subdivisão estrutural real; o sistema não inventa fragmentos por tamanho.

Antes de qualquer lote ser enviado ao provedor, `EmbeddingInputGuard` estima de forma conservadora o tamanho de todas as unidades pendentes. O limite nominal é definido por `AI_EMBEDDING_MAX_INPUT_TOKENS`; a aplicação utiliza 90% desse valor como margem preventiva contra diferenças entre tokenizadores.

Uma evidência primária incompatível não é truncada nem enviada ao provedor. A etapa é interrompida antes da primeira requisição, informa o identificador público da evidência e exige subdivisão estrutural real da fonte. Aumentar o lote, cortar texto ou criar fragmentos artificiais não é uma correção permitida.

Modelo, dimensão e hash identificam a versão vetorial. Similaridades são usadas apenas durante a recuperação e descartadas após a ordenação.

## Limite da construção persistente

A construção termina nos embeddings das evidências primárias. Ela não materializa Cnode: essa derivação conceitual do EVA existe somente durante uma consulta. Também não existe análise antecipada de pares, cache de interação ou vetor relacional.

Esse limite evita explosão combinatória, chamadas externas sem demanda e duplicação da informação já presente nas evidências.

## Implementação

`EvidenceEmbeddingService` valida a compatibilidade de todas as unidades pendentes e vetoriza exclusivamente evidências primárias em lotes técnicos que preservam cada unidade completa. Versões idênticas por modelo e hash são reutilizadas.

A CLI possui somente a etapa:

```powershell
php bin/build-cognitive.php <document-id> --stage=embeddings --live
```
