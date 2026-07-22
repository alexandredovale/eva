# Sustentabilidade energética

## Escopo

O EVA pode contribuir para amenizar a pressão energética associada à adoção de IA em escala por reduzir computação documental evitável e apoiar decisões técnicas fundamentadas em evidências. Essa contribuição não significa geração de energia, controle direto da rede elétrica ou substituição de sistemas operacionais como SCADA, EMS e proteções elétricas.

O efeito esperado é de eficiência: pequenas reduções por consulta podem se acumular em menor demanda de processamento, refrigeração e capacidade computacional quando o sistema atende grande volume de usuários.

Segundo a International Energy Agency, o consumo elétrico de data centers cresceu aproximadamente 17% em 2025, enquanto cargas relacionadas à IA também podem introduzir variações rápidas de potência. Esse cenário torna relevante medir e eliminar chamadas, tokens e recomputações que não contribuam para uma resposta documental válida.

## Mecanismos existentes no EVA

### Seleção antes da geração

O fluxo de consulta não aciona indiscriminadamente todas as capacidades externas:

- quando nenhuma evidência primária é recuperada, o EVA encerra a consulta com uma limitação explícita e não chama o provedor de resposta;
- consultas diretas, estruturais e amplas percorrem a estrutura documental sem gerar embedding transitório do input;
- somente consultas conceituais e relacionais utilizam embedding transitório para recuperação semântica;
- a resposta documental e as interações `simetry`/`assimetry` são produzidas na mesma chamada generativa;
- uma saída truncada admite no máximo uma regeneração integral e compacta, sem ciclos ilimitados.

Esse desenho reduz o número de operações externas em relação a fluxos que sempre criam embeddings, enviam contexto extenso ou executam múltiplas rodadas agentes para cada pergunta.

### Contexto e saída delimitados

`QUERY_MAX_EVIDENCE` limita quantas evidências primárias completas chegam ao provedor. `AI_QUERY_MAX_OUTPUT_TOKENS` limita cada tentativa de resposta, e o histórico conversacional enviado contém no máximo as três rodadas anteriores que caibam integralmente no teto de 20.000 bytes do input.

Esses limites não garantem, isoladamente, menor consumo energético, mas impedem crescimento irrestrito do contexto e da saída. Estudos experimentais de inferência indicam correlação entre energia consumida, tempo de resposta e quantidade de tokens gerados, tornando a contenção de tokens um mecanismo operacional relevante.

### Reutilização da construção cognitiva

Sínteses hierárquicas e embeddings persistentes são versionados por modelo e hash de conteúdo. Unidades idênticas podem ser reutilizadas, e o processamento se concentra nas versões ausentes ou alteradas.

O custo inicial de construção pode, portanto, ser amortizado por consultas posteriores ao mesmo acervo. Essa característica é especialmente importante quando documentos relativamente estáveis atendem grande volume de perguntas.

### Ausência de explosão relacional

O EVA não materializa antecipadamente combinações de pares entre evidências, não cria embeddings relacionais e não persiste Cnodes ou análises de interação. `simetry` e `assimetry` existem somente durante a consulta que as justifica.

Essa delimitação evita crescimento combinatório de armazenamento e processamento sem demanda real. A memória persistente termina em evidências, derivações e embeddings de unidades documentais organizadas.

### Neutralidade de fornecedores

O vínculo entre capacidade, fornecedor, endpoint e modelo é configurável. Essa separação permite substituir modelos e infraestrutura por alternativas mais eficientes sem alterar o núcleo documental do EVA.

O sistema atual não seleciona automaticamente modelos por consumo energético. A arquitetura apenas preserva a possibilidade de adotar modelos menores, hardware mais eficiente ou fornecedores com melhor relação entre energia, latência e qualidade.

## Efeito potencial quando adotado em escala

O consumo computacional agregado pode ser representado de forma simplificada por:

```text
energia total = construção reutilizável
              + consultas locais
              + embeddings transitórios necessários
              + respostas generativas justificadas
              + tentativas adicionais limitadas
```

O EVA atua sobre esses termos ao reutilizar a construção, dispensar embedding em rotas não semânticas, bloquear geração sem evidência e limitar contexto, saída e novas tentativas.

Em grande volume, essa disciplina pode resultar em:

- menos chamadas generativas e horas de GPU;
- menor energia consumida por servidores e refrigeração;
- menor potência computacional simultânea em períodos de pico;
- melhor aproveitamento da capacidade instalada;
- menor pressão para expansão emergencial da infraestrutura de processamento.

O efeito líquido depende da distribuição real das consultas. Acervos reutilizados intensamente tendem a amortizar melhor a construção do que documentos processados uma única vez e raramente consultados.

## Aplicação em sistemas do setor energético

Além de reduzir sua própria carga computacional, o EVA pode organizar memória documental verificável para distribuidoras, geradoras, operadores, indústrias e órgãos reguladores. Entre as fontes aplicáveis estão:

- procedimentos de contingência e recuperação;
- manuais e históricos técnicos de ativos;
- relatórios de falha e manutenção;
- normas, contratos e estudos de capacidade;
- planos de desligamento, religamento e resposta a incidentes.

Durante uma crise, a recuperação rastreável pode reduzir o tempo necessário para localizar procedimentos, confrontar documentos e identificar ausência de fundamento documental. Esse apoio continua consultivo: qualquer integração com dados operacionais em tempo real exige conectores autorizados, validação própria, governança humana e separação explícita dos sistemas de controle da rede.

## Limite científico

Os mecanismos arquiteturais estão implementados, mas a economia energética líquida ainda não foi demonstrada experimentalmente. Sínteses, embeddings, armazenamento, consultas e respostas também consomem energia. O resultado depende, no mínimo, de:

- volume e composição das consultas;
- frequência de atualização e reutilização do acervo;
- modelo, hardware e eficiência do data center;
- tokens de entrada e saída;
- taxa de recusa sem geração e de reutilização da construção;
- qualidade mínima exigida para considerar os sistemas equivalentes;
- matriz elétrica e fator de eficiência energética da infraestrutura.

Consequentemente, a formulação oficial é: **o EVA possui mecanismos verificáveis de contenção computacional com potencial de reduzir demanda energética em escala; a magnitude do efeito e o benefício líquido permanecem por medir.**

## Protocolo de validação

O experimento deve comparar o EVA com RAG vetorial por blocos, contexto longo, GraphRAG e RAG agente, mantendo iguais o acervo, as perguntas, o modelo ou classe de capacidade, o hardware, o limite de qualidade e as condições de execução.

A carga precisa representar separadamente consultas diretas, estruturais, amplas, conceituais, relacionais e controles negativos. O custo de construção deve ser amortizado por diferentes volumes de uso, evitando comparar apenas a inferência e ocultar o processamento inicial.

As métricas mínimas são:

- joules por consulta e kWh por mil consultas;
- energia de construção amortizada;
- chamadas externas e embeddings por consulta;
- tokens de entrada e saída;
- tempo de GPU e latências p50, p95 e p99;
- taxa de reutilização de sínteses e embeddings;
- taxa de consultas encerradas sem geração;
- precisão, recall, validade das citações e taxa de recusa correta;
- energia ajustada pelo PUE da infraestrutura, quando disponível.

A comparação somente sustentará vantagem energética quando o EVA consumir menos energia para qualidade documental equivalente ou superior. Resultados devem informar dispersão, configuração experimental e proporção de cada classe de consulta.

## Referências

1. International Energy Agency. [Energy and AI — Energy demand from AI](https://www.iea.org/reports/energy-and-ai/energy-demand-from-ai). Acesso em 22 de julho de 2026.
2. International Energy Agency. [Key questions on energy and AI — Executive summary](https://www.iea.org/reports/key-questions-on-energy-and-ai/executive-summary). Acesso em 22 de julho de 2026.
3. Poddar et al. [Towards Sustainable NLP: Insights from Benchmarking Inference Energy in Large Language Models](https://aclanthology.org/2025.naacl-long.632/). NAACL 2025.
4. Chung et al. [The ML.ENERGY Benchmark: Toward Automated Inference Energy Measurement and Optimization](https://papers.nips.cc/paper_files/paper/2025/hash/9dc510e3d7b0b3b2a58ffed7a3ad6b0f-Abstract-Datasets_and_Benchmarks_Track.html). NeurIPS 2025.
5. [EVA — Benchmark Baseline de Consulta Documental](../philosophy/02_EVA_BENCHMARK_BASELINE.md).
