# Roadmap

## Fase 1 — Fundação

- estrutura, configuração e esquema do banco — concluído;
- identificadores, estados e logs — concluído;
- modelo não julgamental `simetry`/`assimetry` — concluído.

## Fase 2 — Ingestão

- upload seguro — concluído;
- parsers Markdown, JSON e XML — concluído;
- árvore normalizada comum — concluído;
- documentos, nós e evidências primárias — concluído;
- testes com arquivos reais e inválidos — concluído.

## Fase 3 — Evidence Algorithm

- sínteses ascendentes rastreáveis — concluído;
- evidências `primary`/`derived` e tipos semânticos — concluído;
- derivações de origem — concluído;
- embeddings contextuais de unidades completas — concluído;
- versionamento e retomada sem chamadas duplicadas — concluído.

## Fase 4 — Consulta

- detecção de input direto, estrutural, conceitual, relacional ou amplo — concluído;
- busca adaptativa em evidências primárias e derivadas — concluído;
- resolução de sínteses até fontes primárias — concluído;
- Cnode reposicionado como interação transitória — concluído;
- `simetry`/`assimetry` na mesma chamada de resposta — concluído;
- validação de participantes, orientação, citações e fragmentos literais — concluído;
- ausência de persistência relacional — concluído.

## Fase 5 — Produto

- interface administrativa e de consulta — concluído;
- fila limitada a sínteses e embeddings — concluído;
- configuração white label — concluído;
- auditoria, métricas e controles de acesso — concluído;
- testes sem consumo externo — concluído.

## Upgrade arquitetural — Evidence Algorithm como padrão

- remoção de `cnodes`, `cnode_evidences`, `cnode_embeddings` e `interaction_analyses` — concluído;
- remoção da etapa persistente `cnodes` — concluído;
- recuperação semântica usando classe, tipo e linhagem das evidências — concluído;
- interações exclusivamente contextuais e não persistentes — concluído;
- documentação, produto e testes atualizados — concluído.

As cinco fases e o upgrade arquitetural estão concluídos. Novas fases devem partir de uso real do produto sem reintroduzir entidades ou pesos relacionais redundantes.

## Critério de avanço

Cada fase exige fluxo funcional, testes críticos, erros seguros e documentação coerente com o comportamento real.

## Próxima validação experimental — sustentabilidade energética

- medir joules por consulta e kWh por mil consultas em ambiente instrumentado;
- amortizar o custo de construção por diferentes volumes de consulta;
- comparar EVA, RAG vetorial por blocos, contexto longo, GraphRAG e RAG agente sob qualidade documental equivalente;
- separar consultas diretas, estruturais, amplas, conceituais, relacionais e controles negativos;
- registrar chamadas externas, embeddings, tokens, tempo de GPU, latência e reutilização da construção;
- publicar resultados com dispersão, configuração experimental e limites de generalização.

Até a execução desse protocolo, eficiência energética permanece uma hipótese arquitetural fundamentada nos mecanismos de contenção computacional, não uma superioridade experimental declarada. O protocolo completo está em [Sustentabilidade energética](13_SUSTENTABILIDADE_ENERGETICA.md).
