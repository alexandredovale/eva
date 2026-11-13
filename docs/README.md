# Documentação do EVA

Esta pasta reúne a documentação técnica, operacional, científica e de produto do EVA. A edição principal está em português; a versão inglesa integral e equivalente está em [`en/`](en/README.md).

## Documentos de apresentação

- [EVA — visão de produto, aplicações de mercado e impacto](EVA_MARKETING.md)
- [Benchmark EVA 8.0.2: recuperação documental versus contexto integral](19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md)

## Sistema e operação

1. [Visão geral](01_VISAO_GERAL.md)
2. [Arquitetura](02_ARQUITETURA.md)
3. [Ingestão](03_INGESTAO.md)
4. [Construção cognitiva](04_CONSTRUCAO_COGNITIVA.md)
5. [Consulta](05_CONSULTA.md)
6. [Cnode](06_CNODE.md)
7. [Banco de dados](07_BANCO_DE_DADOS.md)
8. [Regras obrigatórias](08_REGRAS.md)
9. [Roadmap](09_ROADMAP.md)
10. [Produto e operações](10_PRODUTO.md)
11. [Validação de prontidão](11_VALIDACAO_GO_LIVE.md)
12. [Homologação pré-implantação](12_HOMOLOGACAO_PRE_DEPLOY.md)
13. [Sustentabilidade energética](13_SUSTENTABILIDADE_ENERGETICA.md)
14. [Context Intelligence Engine](14_CONTEXT_INTELLIGENCE_ENGINE.md)
15. [Visão e impacto](15_VISAO.md)
16. [Módulos conectores](16_MODULOS.md)
17. [Relacionamentos do banco de dados](17_RELACIONAMENTO_BANCO_DADOS.md)
18. [Instalação](18_INSTALACAO.md)

## Registros de validação

Os documentos [Validação de prontidão](11_VALIDACAO_GO_LIVE.md) e [Homologação pré-implantação](12_HOMOLOGACAO_PRE_DEPLOY.md) preservam data, ambiente, resultados e limitações das execuções originais. São registros históricos e não substituem a validação de uma nova implantação.

O [benchmark da versão 8.0.2](19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md) registra corpus, modelos, configuração, perguntas, tokens, cache, custo, latência, evidências e limitações. O ensaio mede redução de tokens e custo no cenário executado; não mede diretamente consumo de energia nem estabelece superioridade universal.

## Correspondência com a edição inglesa

| Documento em português | Versão inglesa |
|---|---|
| [Visão geral](01_VISAO_GERAL.md) | [Overview](en/01_OVERVIEW.md) |
| [Arquitetura](02_ARQUITETURA.md) | [Architecture](en/02_ARCHITECTURE.md) |
| [Ingestão](03_INGESTAO.md) e [Construção cognitiva](04_CONSTRUCAO_COGNITIVA.md) | [Ingestion and cognitive build](en/04_INGESTION_AND_BUILD.md) |
| [Consulta](05_CONSULTA.md) | [Query and single-turn chat](en/05_QUERY_AND_CHAT.md) |
| [Cnode](06_CNODE.md) | [Cnode as an EVA conceptual derivation](en/10_CNODE.md) |
| [Banco de dados](07_BANCO_DE_DADOS.md) | [Database](en/11_DATABASE.md) |
| [Regras obrigatórias](08_REGRAS.md) | [Mandatory rules](en/12_MANDATORY_RULES.md) |
| [Roadmap](09_ROADMAP.md) | [Roadmap](en/13_ROADMAP.md) |
| [Produto e operações](10_PRODUTO.md) | [API and operations](en/06_API_AND_OPERATIONS.md) |
| [Validação de prontidão](11_VALIDACAO_GO_LIVE.md) | [Go-live readiness validation](en/14_GO_LIVE_VALIDATION.md) |
| [Homologação pré-implantação](12_HOMOLOGACAO_PRE_DEPLOY.md) | [Pre-deployment acceptance](en/15_PRE_DEPLOYMENT_ACCEPTANCE.md) |
| [Sustentabilidade energética](13_SUSTENTABILIDADE_ENERGETICA.md) | [Scientific scope and energy sustainability](en/08_SCIENTIFIC_AND_ENERGY.md) |
| [Context Intelligence Engine](14_CONTEXT_INTELLIGENCE_ENGINE.md) | [Context Intelligence Engine](en/09_CONTEXT_INTELLIGENCE_ENGINE.md) |
| [Visão e impacto](15_VISAO.md) | [Project vision and impact assessment](en/16_VISION.md) |
| [Módulos conectores](16_MODULOS.md) | [Connector modules](en/17_MODULE_CONNECTORS.md) |
| [Relacionamentos do banco de dados](17_RELACIONAMENTO_BANCO_DADOS.md) | [Database relationships](en/18_DATABASE_RELATIONSHIPS.md) |
| [Instalação](18_INSTALACAO.md) | [Installation](en/03_INSTALLATION.md) |
| [Benchmark EVA versus contexto integral](19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md) | [EVA versus full-context benchmark](en/19_EVA_VS_FULL_CONTEXT_BENCHMARK.md) |
| [Produto, aplicações de mercado e impacto](EVA_MARKETING.md) | [Product, market applications, and impact](en/EVA_MARKETING.md) |

A coleção inglesa reorganiza parte da especificação por tarefa do leitor, mas cobre o mesmo escopo técnico atual. Os documentos de apresentação e benchmark são espelhos editoriais completos, com a mesma estrutura e o mesmo conteúdo factual.

## Contratos de figuras

Exemplos prontos para ingestão em Markdown, JSON e XML estão indexados em [`examples/figure-contracts/`](examples/figure-contracts/README.md). As especificações correspondentes estão em [Produto](10_PRODUTO.md#figuras-documentais) e [API and operations](en/06_API_AND_OPERATIONS.md#document-figures).

## Limites do repositório

Corpora privados de teste, livros de terceiros, documentos enviados, credenciais, bancos operacionais e logs de runtime não integram a distribuição pública.

A coleção científica e filosófica está em [`philosophy/`](../philosophy/README.md), com edição inglesa integral em [`philosophy/en/`](../philosophy/en/README.md).
