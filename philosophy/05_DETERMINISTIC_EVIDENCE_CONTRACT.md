# Contrato determinístico de evidências

**Estado:** implementado
**Atualização:** 8 de agosto de 2026
**English:** [Deterministic evidence contract](en/05_DETERMINISTIC_EVIDENCE_CONTRACT.md)

## Princípio

No EVA, o modelo de resposta não define o universo documental autorizado. A aplicação determina esse universo antes da geração:

```text
rota semântica
  → população hierárquica completa
  → κq → CIE hierárquico
  → linhagem integral por região herdada
  → κe → CIE primário
  → união dos núcleos locais → CIE global
  → núcleo global ou fallback de convergence
  → âncoras literais protegidas
  → contexto final autorizado
```

Rotas direta, estrutural e ampla não executam CIE e usam somente o limite isolado `QUERY_NON_SEMANTIC_MAX_EVIDENCE`.

## População disponível e base final

O contexto final autorizado e a base documental final são conjuntos diferentes:

```text
contexto autorizado = fontes que a aplicação permite à LLM examinar
base final           = subconjunto citado analiticamente na resposta validada
```

Uma fonte recuperada, mas não citada, é descartada sem invalidar a resposta inteira. Uma fonte externa ao contexto, uma citação inexistente ou um inventário isolado de IDs invalida a saída. A aplicação nunca acrescenta automaticamente uma citação omitida.

## Papéis preservados

O CIE global decide a elegibilidade final, mas não apaga a origem hierárquica. Uma fonte pode ser `core` global e conservar `source_region: convergence`; nesse caso, ela é enviada com o papel documental herdado `convergence`. Esse papel pode indicar reforço, contexto, limite ou contraponto, nunca importância, verdade ou confiança.

Correspondências literais exatas externas à população primária analisada permanecem como âncoras protegidas. Elas não são eliminadas pelo CIE global e continuam submetidas às mesmas regras de citação.

## Quantidade emergente

Não existe `QUERY_MAX_EVIDENCE` nas rotas semânticas. A quantidade final é:

```text
K(q) = |CoreG|, se CoreG ≠ ∅
K(q) = |ConvG|, se CoreG = ∅
```

Portanto, o número depende da geometria daquela consulta. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` nunca participa de κq, κe ou CIE.

## Interações transitórias

Quando o contexto final contém ao menos duas evidências e `QUERY_MAX_INTERACTIONS` é maior que zero, a mesma chamada que formula a resposta pode avaliar `simetry` e `assimetry`. Cada interação aceita:

- usa somente participantes presentes e citados;
- contém fragmentos literais verificáveis;
- é validada localmente;
- não recebe peso ou confiança;
- não é persistida como memória documental.

Ausência de interação válida não apaga uma resposta documental sustentada; produz apenas a limitação relacional correspondente.

## Falha fechada e tentativas

Se não houver evidência primária, o EVA retorna limitação sem chamar o provedor de resposta. Uma saída truncada, JSON inválido, ID externo, citação decorativa ou fragmento não literal é rejeitada. A aplicação mantém o mesmo contexto autorizado entre tentativas e transmite somente feedback seguro de correção; não amplia a população para acomodar a geração.

## Fronteira de persistência

São persistidos documentos, nós, evidências, derivações, embeddings e auditoria sanitizada. Permanecem transitórios:

- embedding da consulta;
- similaridades e diagnósticos κ;
- análises hierárquica, primária e global do CIE;
- contexto autorizado e resposta;
- `simetry`, `assimetry` e continuidade conversacional.

Consultar o acervo é leitura, não autorização para reescrever sua memória.

## Invariantes

1. Toda afirmação documental deve voltar a uma evidência primária citada.
2. Nenhuma fonte externa ao contexto final pode ser aceita.
3. κq e κe são query-local e não recebem Top-k prévio.
4. A linhagem semântica não é truncada.
5. O CIE global recebe apenas núcleos primários locais deduplicados.
6. A LLM recebe o núcleo global ou o fallback de convergência, mais âncoras literais protegidas.
7. A quantidade semântica final não é configurada.
8. Somente evidências citadas analiticamente integram a base final.
9. Análises e interações da consulta não alteram a memória documental.

## Referências

- [Context Intelligence Engine](04_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Fluxo detalhado da API](03_EVA_API_FLOW.md)
- [Regras obrigatórias](../docs/08_REGRAS.md)
