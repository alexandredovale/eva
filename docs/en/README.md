# EVA documentation

This directory contains EVA's technical, operational, scientific, and product documentation. The primary edition is in Portuguese at [`../README.md`](../README.md); this `en/` directory provides the complete and equivalent English edition.

## Presentation documents

- [EVA — product vision, market applications, and impact](EVA_MARKETING.md)
- [EVA 8.0.2 benchmark: documentary retrieval versus full context](19_EVA_VS_FULL_CONTEXT_BENCHMARK.md)

## System and operation

1. [Overview](01_OVERVIEW.md)
2. [Architecture](02_ARCHITECTURE.md)
3. [Installation](03_INSTALLATION.md)
4. [Ingestion and cognitive build](04_INGESTION_AND_BUILD.md)
5. [Query and single-turn chat](05_QUERY_AND_CHAT.md)
6. [API and operations](06_API_AND_OPERATIONS.md)
7. [Security and deployment](07_SECURITY_AND_DEPLOYMENT.md)
8. [Scientific scope and energy sustainability](08_SCIENTIFIC_AND_ENERGY.md)
9. [Context Intelligence Engine](09_CONTEXT_INTELLIGENCE_ENGINE.md)
10. [Cnode as an EVA conceptual derivation](10_CNODE.md)
11. [Database](11_DATABASE.md)
12. [Mandatory rules](12_MANDATORY_RULES.md)
13. [Roadmap](13_ROADMAP.md)
14. [Go-live readiness validation](14_GO_LIVE_VALIDATION.md)
15. [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md)
16. [Project vision and impact assessment](16_VISION.md)
17. [Connector modules](17_MODULE_CONNECTORS.md)
18. [Database relationships](18_DATABASE_RELATIONSHIPS.md)

## Validation records

[Go-live readiness validation](14_GO_LIVE_VALIDATION.md) and [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md) preserve the date, environment, results, and limitations of the original runs. They are historical records and do not replace validation of a new deployment.

The [version 8.0.2 benchmark](19_EVA_VS_FULL_CONTEXT_BENCHMARK.md) records the corpus, models, configuration, questions, tokens, cache, cost, latency, evidence, and limitations. The experiment measures token and cost reduction in the executed scenario; it does not directly measure energy consumption or establish universal superiority.

## Correspondence with the Portuguese edition

| Portuguese document | English edition |
|---|---|
| [Visão geral](../01_VISAO_GERAL.md) | [Overview](01_OVERVIEW.md) |
| [Arquitetura](../02_ARQUITETURA.md) | [Architecture](02_ARCHITECTURE.md) |
| [Ingestão](../03_INGESTAO.md) and [Construção cognitiva](../04_CONSTRUCAO_COGNITIVA.md) | [Ingestion and cognitive build](04_INGESTION_AND_BUILD.md) |
| [Consulta](../05_CONSULTA.md) | [Query and single-turn chat](05_QUERY_AND_CHAT.md) |
| [Cnode](../06_CNODE.md) | [Cnode as an EVA conceptual derivation](10_CNODE.md) |
| [Banco de dados](../07_BANCO_DE_DADOS.md) | [Database](11_DATABASE.md) |
| [Regras obrigatórias](../08_REGRAS.md) | [Mandatory rules](12_MANDATORY_RULES.md) |
| [Roadmap](../09_ROADMAP.md) | [Roadmap](13_ROADMAP.md) |
| [Produto e operações](../10_PRODUTO.md) | [API and operations](06_API_AND_OPERATIONS.md) |
| [Validação de prontidão](../11_VALIDACAO_GO_LIVE.md) | [Go-live readiness validation](14_GO_LIVE_VALIDATION.md) |
| [Homologação pré-implantação](../12_HOMOLOGACAO_PRE_DEPLOY.md) | [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md) |
| [Sustentabilidade energética](../13_SUSTENTABILIDADE_ENERGETICA.md) | [Scientific scope and energy sustainability](08_SCIENTIFIC_AND_ENERGY.md) |
| [Context Intelligence Engine](../14_CONTEXT_INTELLIGENCE_ENGINE.md) | [Context Intelligence Engine](09_CONTEXT_INTELLIGENCE_ENGINE.md) |
| [Visão e impacto](../15_VISAO.md) | [Project vision and impact assessment](16_VISION.md) |
| [Módulos conectores](../16_MODULOS.md) | [Connector modules](17_MODULE_CONNECTORS.md) |
| [Relacionamentos do banco de dados](../17_RELACIONAMENTO_BANCO_DADOS.md) | [Database relationships](18_DATABASE_RELATIONSHIPS.md) |
| [Instalação](../18_INSTALACAO.md) | [Installation](03_INSTALLATION.md) |
| [Benchmark EVA versus contexto integral](../19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md) | [EVA versus full-context benchmark](19_EVA_VS_FULL_CONTEXT_BENCHMARK.md) |
| [Produto, aplicações de mercado e impacto](../EVA_MARKETING.md) | [Product, market applications, and impact](EVA_MARKETING.md) |

The English collection reorganizes part of the specification by reader task, while covering the same current technical scope. The presentation and benchmark documents are complete editorial mirrors with the same structure and factual content.

## Figure contracts

Ready-to-ingest Markdown, JSON, and XML examples are indexed in [`examples/figure-contracts/`](../examples/figure-contracts/README.md). The corresponding specifications are [Produto](../10_PRODUTO.md#figuras-documentais) and [API and operations](06_API_AND_OPERATIONS.md#document-figures).

## Repository boundaries

Private test corpora, third-party books, uploaded documents, credentials, operational databases, and runtime logs are not part of the public distribution.

The scientific and philosophical collection is available in [`philosophy/`](../../philosophy/README.md), with a complete English edition in [`philosophy/en/`](../../philosophy/en/README.md).
