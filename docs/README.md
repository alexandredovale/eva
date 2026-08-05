# EVA documentation

English is the primary language of the public repository. The original Portuguese technical documents are preserved in this directory because they record the implemented system in greater historical detail.

## English documentation

### System and operation

1. [Overview](en/01_OVERVIEW.md)
2. [Architecture](en/02_ARCHITECTURE.md)
3. [Installation](en/03_INSTALLATION.md)
4. [Ingestion and cognitive build](en/04_INGESTION_AND_BUILD.md)
5. [Query and conversational continuity](en/05_QUERY_AND_CHAT.md)
6. [API and operations](en/06_API_AND_OPERATIONS.md)
7. [Security and deployment](en/07_SECURITY_AND_DEPLOYMENT.md)
8. [Scientific scope and energy sustainability](en/08_SCIENTIFIC_AND_ENERGY.md)
9. [Context Intelligence Engine](en/09_CONTEXT_INTELLIGENCE_ENGINE.md)
10. [Cnode as an EVA conceptual derivation](en/10_CNODE.md)
11. [Database](en/11_DATABASE.md)
12. [Mandatory rules](en/12_MANDATORY_RULES.md)
13. [Roadmap](en/13_ROADMAP.md)
18. [Database relationships](en/18_DATABASE_RELATIONSHIPS.md)

### Validation records

14. [Go-live readiness validation](en/14_GO_LIVE_VALIDATION.md)
15. [Pre-deployment acceptance](en/15_PRE_DEPLOYMENT_ACCEPTANCE.md)

### Platform evolution

16. [Project vision and impact assessment](en/16_VISION.md)
17. [Connector modules](en/17_MODULE_CONNECTORS.md)

The English collection covers the complete current technical scope. The two validation records preserve the date, environment, results, and limitations of the original executions; they are historical evidence, not a substitute for validating a new deployment.

### Source coverage

The English edition is organized by reader task rather than as filename-for-filename duplication. This map shows where each original specification is covered.

| Original Portuguese specification | English edition |
|---|---|
| `01_VISAO_GERAL.md` | [Overview](en/01_OVERVIEW.md) |
| `02_ARQUITETURA.md` | [Architecture](en/02_ARCHITECTURE.md) |
| `03_INGESTAO.md` | [Ingestion and cognitive build](en/04_INGESTION_AND_BUILD.md) |
| `04_CONSTRUCAO_COGNITIVA.md` | [Ingestion and cognitive build](en/04_INGESTION_AND_BUILD.md) |
| `05_CONSULTA.md` | [Query and conversational continuity](en/05_QUERY_AND_CHAT.md) |
| `06_CNODE.md` | [Cnode as an EVA conceptual derivation](en/10_CNODE.md) |
| `07_BANCO_DE_DADOS.md` | [Database](en/11_DATABASE.md) |
| `08_REGRAS.md` | [Mandatory rules](en/12_MANDATORY_RULES.md) |
| `09_ROADMAP.md` | [Roadmap](en/13_ROADMAP.md) |
| `10_PRODUTO.md` | [API and operations](en/06_API_AND_OPERATIONS.md) |
| `11_VALIDACAO_GO_LIVE.md` | [Go-live readiness validation](en/14_GO_LIVE_VALIDATION.md) |
| `12_HOMOLOGACAO_PRE_DEPLOY.md` | [Pre-deployment acceptance](en/15_PRE_DEPLOYMENT_ACCEPTANCE.md) |
| `13_SUSTENTABILIDADE_ENERGETICA.md` | [Scientific scope and energy sustainability](en/08_SCIENTIFIC_AND_ENERGY.md) |
| `14_CONTEXT_INTELLIGENCE_ENGINE.md` | [Context Intelligence Engine](en/09_CONTEXT_INTELLIGENCE_ENGINE.md) |
| `15_VISAO.md` | [Project vision and impact assessment](en/16_VISION.md) |
| `16_MODULOS.md` | [Connector modules](en/17_MODULE_CONNECTORS.md) |
| `17_RELACIONAMENTO_BANCO_DADOS.md` | [Database relationships](en/18_DATABASE_RELATIONSHIPS.md) |

[Installation](en/03_INSTALLATION.md) is an additional English operational guide assembled from the current repository configuration and deployment requirements.

## Original Portuguese specifications

| Document | Subject |
|---|---|
| [`01_VISAO_GERAL.md`](01_VISAO_GERAL.md) | Project and Evidence Algorithm overview |
| [`02_ARQUITETURA.md`](02_ARQUITETURA.md) | Modules, boundaries, and provider neutrality |
| [`03_INGESTAO.md`](03_INGESTAO.md) | Parsing, normalization, upload, and persistence |
| [`04_CONSTRUCAO_COGNITIVA.md`](04_CONSTRUCAO_COGNITIVA.md) | Summaries, embeddings, and lineage |
| [`05_CONSULTA.md`](05_CONSULTA.md) | Query routes, chat context, limits, and validation |
| [`06_CNODE.md`](06_CNODE.md) | Cnode as a transient conceptual derivation of EVA, `simetry`, and `assimetry` |
| [`07_BANCO_DE_DADOS.md`](07_BANCO_DE_DADOS.md) | Persistent data model |
| [`08_REGRAS.md`](08_REGRAS.md) | System invariants and security rules |
| [`09_ROADMAP.md`](09_ROADMAP.md) | Completed phases and experimental roadmap |
| [`10_PRODUTO.md`](10_PRODUTO.md) | Product, API, queue, audit, and metrics |
| [`11_VALIDACAO_GO_LIVE.md`](11_VALIDACAO_GO_LIVE.md) | Go-live validation record |
| [`12_HOMOLOGACAO_PRE_DEPLOY.md`](12_HOMOLOGACAO_PRE_DEPLOY.md) | Pre-deployment acceptance record |
| [`13_SUSTENTABILIDADE_ENERGETICA.md`](13_SUSTENTABILIDADE_ENERGETICA.md) | Energy-efficiency mechanisms and validation protocol |
| [`14_CONTEXT_INTELLIGENCE_ENGINE.md`](14_CONTEXT_INTELLIGENCE_ENGINE.md) | Statistical stabilization between vector retrieval and cognitive layers |
| [`15_VISAO.md`](15_VISAO.md) | Visão crítica, impacto potencial, limites atuais e prioridades de evolução |
| [`16_MODULOS.md`](16_MODULOS.md) | Instalação, contratos, SDK, operação, backup e remoção de módulos |
| [`17_RELACIONAMENTO_BANCO_DADOS.md`](17_RELACIONAMENTO_BANCO_DADOS.md) | Cardinalidades, chaves estrangeiras, relações lógicas, fluxos e efeitos de exclusão |

Private test books and operational corpora are intentionally not part of this repository.
