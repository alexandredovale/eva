# Benchmark EVA 8.0.2: recuperação documental versus contexto integral

## Resumo executivo

Este benchmark compara o fluxo documental do EVA com uma chamada direta que envia ao mesmo modelo o arquivo Markdown integral de uma obra. O ensaio utilizou cinco perguntas inéditas sobre **O Livro dos Espíritos**, sempre com o modelo `deepseek-flash`, temperatura zero, modo de raciocínio desativado e teto de 1.800 tokens de saída.

Nas cinco perguntas, o EVA contabilizou 28.450 tokens no modelo de respostas e 104 tokens adicionais no modelo de embeddings. A chamada direta contabilizou 1.247.902 tokens. Considerando o total operacional, o EVA reduziu o volume de tokens em **97,71%** e enviou **43,86 vezes menos tokens** ao modelo de respostas.

O custo observado foi de aproximadamente **US$ 0,00414271** para o EVA e **US$ 0,041383956** para o contexto integral, uma redução de **89,99%**. O baseline direto foi deliberadamente organizado para favorecer seu próprio cache: o documento completo permaneceu antes da pergunta, como prefixo idêntico. Assim, depois da primeira chamada, 249.088 tokens por pergunta foram recuperados do cache do provedor. Mesmo nesse cenário favorável, o custo agregado do contexto integral foi aproximadamente **9,99 vezes maior**.

O contexto integral apresentou menor latência total no conjunto: 22,996 segundos, contra 26,467 segundos do EVA. A diferença decorre das etapas adicionais de embedding, recuperação, κq, CIE e validação local. Isoladamente, porém, a geração de respostas do EVA consumiu 19,410 segundos, 15,59% menos que os 22,996 segundos gastos pelo modelo nas chamadas diretas.

Este resultado não estabelece superioridade universal nem mede qualidade doutrinária por pontuação. Ele demonstra, para esta obra, configuração e conjunto de perguntas, a diferença prática entre recuperar evidências antes da inferência e reenviar o documento integral a cada consulta.

## Objetivo

O teste responde a quatro questões operacionais:

1. Quantos tokens são contabilizados quando o modelo recebe apenas as evidências eleitas pelo EVA?
2. Quantos tokens são contabilizados quando o mesmo modelo recebe o `.md` integral?
3. Como cache, custo e latência se comportam em cinco consultas sucessivas sobre a mesma obra?
4. Quais diferenças observáveis aparecem nas respostas, especialmente quanto a concisão, amplitude e rastreabilidade documental?

## Ambiente e corpus

| Item | Valor |
|---|---|
| Data da execução | 13 de novembro de 2026 |
| Horário final | 13:08:27 UTC / 10:08:27 America/Sao_Paulo |
| Commit-base | `e2f5ea3f3bb7e30336524d3b714f39326931208a` |
| PHP | `8.2.12` |
| Documento | `EVA-D000221` — O Livro dos Espíritos |
| Arquivo | `storage/documents/EVA-D000221.md` |
| Tamanho | 890.163 bytes |
| Caracteres | 860.908 |
| Linhas | 3.728 |
| SHA-256 | `67de2fc192c8d5b31cf74932e1ca533d4297a9ae957b8b0b436e38c5045bb1a9` |
| Modelo de respostas | `deepseek-flash` |
| Modelo de embeddings | `text-embedding-3-small` |
| Temperatura | `0` |
| Raciocínio do provedor | desativado |
| Limite de saída | 1.800 tokens |

O arquivo-fonte não é distribuído pelo repositório público. O identificador, as dimensões e o hash permitem verificar localmente se outra execução usa o mesmo corpus sem revelar ou duplicar seu conteúdo.

## Metodologia

### Cenário EVA

Cada pergunta foi processada como consulta independente pelo fluxo operacional existente:

```text
pergunta → embedding transitório → recuperação nas evidências primárias
         → κq → CIE primário/global → contexto documental eleito
         → geração JSON → validação local → resposta
```

Foram registrados separadamente:

- tokens do embedding da pergunta;
- tokens de entrada, cache hit, cache miss e saída do modelo de respostas;
- tempo total e tempo gasto pelo provedor de respostas;
- evidências efetivamente citadas;
- interações transitórias validadas e limitações declaradas.

### Cenário de contexto integral

A chamada direta utilizou o mesmo `deepseek-flash` e recebeu:

```text
instrução mínima → Markdown integral da obra → pergunta
```

A instrução exigiu resposta em português, uso exclusivo do documento, ausência de conhecimento externo e declaração explícita quando o documento não sustentasse uma conclusão.

O documento foi colocado antes da pergunta para formar um prefixo idêntico entre chamadas. Essa ordem maximiza a possibilidade de cache do baseline direto. A primeira consulta foi fria; nas quatro seguintes, o provedor registrou cache hit de 249.088 tokens por chamada.

### Ordem e isolamento

As cinco perguntas foram definidas antes da execução. Para cada pergunta, o EVA foi executado primeiro e a chamada direta em seguida. Nenhuma resposta foi usada como entrada de outra pergunta. Não houve retry, truncamento ou falha nas dez gerações.

### Efeito do contrato de uma pergunta por vez

O benchmark respeitou integralmente o contrato operacional do EVA de uma pergunta independente por requisição. Cada uma das cinco consultas gerou um novo embedding da pergunta e reiniciou recuperação, κq e CIE sem herdar input, população recuperada, embedding transitório, resposta ou interação da consulta anterior.

Esse reinício não reconstrói a memória documental. As evidências primárias e seus embeddings, produzidos anteriormente durante a construção cognitiva, permaneceram armazenados e foram reutilizados em todas as consultas. Portanto, começar uma pergunta do zero significa refazer somente a eleição query-local; não significa reprocessar o Markdown integral, gerar novamente os embeddings da obra ou reenviar a obra completa ao modelo de respostas.

O contrato também impede o crescimento acumulativo de uma janela conversacional. Em vez de carregar perguntas, respostas e contextos anteriores, cada chamada contém apenas as instruções fixas, a pergunta atual e as evidências eleitas para ela. No conjunto medido, os cinco novos embeddings das perguntas somaram 104 tokens, enquanto o contexto enviado ao modelo de respostas somou 25.805 tokens de input.

O baseline direto seguiu o mesmo isolamento de uma pergunta por chamada, mas recebeu novamente o documento integral como prefixo. Seu cache foi deliberadamente favorecido nas quatro chamadas seguintes, porém o provedor ainda contabilizou 1.246.430 tokens de input no conjunto. Assim, a economia observada neste benchmark já incorpora o custo de reiniciar todas as consultas e não depende de memória conversacional entre perguntas.

## Perguntas

| ID | Categoria | Classificação local do EVA | Pergunta |
|---|---|---|---|
| Q1 | Definição conceitual | `conceptual` | O que é Deus e quais atributos definem a natureza divina? |
| Q2 | Relação moral | `relational`, `conceptual` | Como o livre-arbítrio se relaciona com a responsabilidade moral pelos atos humanos? |
| Q3 | Relação doutrinária | `relational`, `conceptual` | Qual é a relação entre reencarnação, progresso do Espírito e justiça divina? |
| Q4 | Explicação temática | `conceptual` | Como O Livro dos Espíritos explica a origem e a finalidade da lei do trabalho? |
| Q5 | Processo e identidade | `conceptual` | O que acontece com a alma após a morte e como ela conserva sua individualidade? |

## Resultados de tokens

### EVA

| ID | Input | Cache hit | Cache miss | Output | Total DeepSeek | Embedding |
|---|---:|---:|---:|---:|---:|---:|
| Q1 | 4.106 | 1.792 | 2.314 | 790 | 4.896 | 17 |
| Q2 | 6.060 | 1.792 | 4.268 | 413 | 6.473 | 22 |
| Q3 | 2.851 | 1.792 | 1.059 | 312 | 3.163 | 23 |
| Q4 | 2.610 | 1.792 | 818 | 461 | 3.071 | 22 |
| Q5 | 10.178 | 1.792 | 8.386 | 669 | 10.847 | 20 |
| **Total** | **25.805** | **8.960** | **16.845** | **2.645** | **28.450** | **104** |

### Contexto integral

| ID | Input | Cache hit | Cache miss | Output | Total |
|---|---:|---:|---:|---:|---:|
| Q1 | 249.282 | 0 | 249.282 | 270 | 249.552 |
| Q2 | 249.287 | 249.088 | 199 | 180 | 249.467 |
| Q3 | 249.289 | 249.088 | 201 | 388 | 249.677 |
| Q4 | 249.286 | 249.088 | 198 | 443 | 249.729 |
| Q5 | 249.286 | 249.088 | 198 | 191 | 249.477 |
| **Total** | **1.246.430** | **996.352** | **250.078** | **1.472** | **1.247.902** |

### Comparação agregada

| Indicador | EVA | Contexto integral | Diferença observada |
|---|---:|---:|---:|
| Input no modelo de respostas | 25.805 | 1.246.430 | EVA: 97,93% menor |
| Cache hit | 8.960 | 996.352 | Direto favorecido pelo prefixo integral estável |
| Cache miss | 16.845 | 250.078 | Direto: 14,85 vezes maior |
| Output | 2.645 | 1.472 | EVA: 79,69% maior |
| Total no modelo de respostas | 28.450 | 1.247.902 | Direto: 43,86 vezes maior |
| Total operacional, incluindo embeddings | 28.554 | 1.247.902 | EVA: 97,71% menor |
| Taxa de cache no input | 34,72% | 79,94% | Baseline direto aqueceu o documento integral |

Os 104 tokens de embeddings pertencem a outro modelo e tokenizador. Eles são apresentados como consumo operacional adicional, mas não representam equivalência linguística exata com tokens do `deepseek-flash`.

## Custos

Os custos abaixo usam os preços off-peak vigentes na data do ensaio para `deepseek-flash`: US$ 0,003 por milhão de tokens em cache hit, US$ 0,15 em cache miss e US$ 0,60 em output. O embedding `text-embedding-3-small` foi calculado a US$ 0,02 por milhão de tokens. Os valores não incluem infraestrutura local, banco, armazenamento ou rede.

| ID | EVA | Contexto integral | Direto / EVA |
|---|---:|---:|---:|
| Q1 | US$ 0,000826816 | US$ 0,037554300 | 45,42 vezes |
| Q2 | US$ 0,000893816 | US$ 0,000885114 | 0,99 vez |
| Q3 | US$ 0,000351886 | US$ 0,001010214 | 2,87 vezes |
| Q4 | US$ 0,000405116 | US$ 0,001042764 | 2,57 vezes |
| Q5 | US$ 0,001665076 | US$ 0,000891564 | 0,54 vez |
| **Total** | **US$ 0,004142710** | **US$ 0,041383956** | **9,99 vezes** |

A primeira chamada direta concentrou 90,75% do custo total do baseline porque o documento ainda não estava em cache. Nas quatro consultas aquecidas, o custo direto agregado foi US$ 0,003829656, contra US$ 0,003315894 do EVA. Portanto, com o documento integral ainda disponível no cache, os custos tornaram-se próximos, embora o baseline continuasse contabilizando aproximadamente 249 mil tokens de entrada por pergunta.

O resultado evidencia duas medidas diferentes:

- **tokens contabilizados:** permanecem altos no contexto integral; os tokens em cache hit são recuperados sem a mesma recomputação do cache miss;
- **custo faturado:** cai substancialmente enquanto o prefixo integral permanece disponível no cache do provedor.

O cache DeepSeek é automático, funciona por prefixo e opera em regime de melhor esforço; sua permanência não é garantida. Em consultas frias, distribuídas no tempo ou com alterações no documento, o custo direto tende a se aproximar novamente do custo integral observado em Q1.

Referências de preços e contabilização:

- [DeepSeek — Models & Pricing](https://api-docs.deepseek.com/quick_start/pricing/)
- [DeepSeek — Context Caching](https://api-docs.deepseek.com/guides/kv_cache/)
- [DeepSeek — Token & Token Usage](https://api-docs.deepseek.com/quick_start/token_usage/)
- [OpenAI — text-embedding-3-small](https://developers.openai.com/api/docs/models/text-embedding-3-small)

## Latência

| ID | EVA total | Contexto integral | Menor tempo |
|---|---:|---:|---|
| Q1 | 6,554 s | 6,839 s | EVA |
| Q2 | 4,775 s | 4,418 s | Direto |
| Q3 | 3,827 s | 4,692 s | EVA |
| Q4 | 5,122 s | 3,931 s | Direto |
| Q5 | 6,189 s | 3,117 s | Direto |
| **Total** | **26,467 s** | **22,996 s** | **Direto** |
| **Média** | **5,293 s** | **4,599 s** | **Direto** |

O contexto integral foi 13,11% mais rápido no total. O EVA adicionou aproximadamente 7,057 segundos em embeddings e processamento local ao conjunto. Considerando somente as chamadas ao provedor de respostas, o EVA consumiu 19,410 segundos, 15,59% menos que as chamadas diretas.

Latência e tokens não variaram de forma linear. Q5 foi o maior contexto do EVA, mas o baseline direto apresentou seu menor tempo. Esse comportamento pode refletir cache, carga do provedor, rede e variação operacional. Cinco observações sequenciais não constituem amostra suficiente para inferir percentis de latência.

## Evidências e rastreabilidade

| ID | Evidências usadas pelo EVA | Caracteres das evidências usadas | Interações validadas |
|---|---|---:|---:|
| Q1 | `EVA-E008287`, `EVA-E008290`, `EVA-E008285`, `EVA-E008286`, `EVA-E008284`, `EVA-E008957` | 5.293 | 0 |
| Q2 | `EVA-E009162`, `EVA-E009139`, `EVA-E009134`, `EVA-E009136`, `EVA-E009135`, `EVA-E009133`, `EVA-E009137`, `EVA-E009140` | 11.252 | 0 |
| Q3 | `EVA-E008447` | 2.244 | 0 |
| Q4 | `EVA-E008966`, `EVA-E008969` | 1.085 | 0 |
| Q5 | `EVA-E008428`, `EVA-E008426`, `EVA-E008258`, `EVA-E008441`, `EVA-E008427`, `EVA-E008431`, `EVA-E008438` | 16.131 | 0 |

O EVA utilizou 24 evidências ao longo das cinco respostas, totalizando 36.005 caracteres de conteúdo efetivamente citado. A inexistência de `simetry` ou `assimetry` não representa falha: em quatro perguntas, o sistema registrou explicitamente que as evidências sustentavam a resposta, mas não continham elementos literais suficientes para validar esses operadores. Q3 foi sustentada por uma única evidência e não exigiu essa limitação.

O baseline direto citou números de questões da obra em todas as respostas, mas esses números foram produzidos pelo próprio modelo. Eles não passaram pelo contrato de IDs, pela verificação local de pertencimento ao contexto nem pela validação de incorporação analítica do EVA.

## Observações qualitativas

As duas abordagens responderam ao núcleo das cinco perguntas. Não foi aplicada uma nota subjetiva de qualidade, pois isso exigiria avaliadores independentes, critérios previamente calibrados e revisão doutrinária especializada.

- **Q1:** ambas identificaram Deus como inteligência suprema e apresentaram seus atributos. O EVA desenvolveu limites da compreensão humana, evolução da ideia de Deus e crítica ao panteísmo; a resposta direta foi mais curta e indicou números de questões.
- **Q2:** ambas relacionaram livre-arbítrio e responsabilidade. O EVA incorporou gradações de liberdade, conhecimento, resistência e incapacidade; o baseline condensou a conclusão e apontou questões específicas.
- **Q3:** ambas relacionaram reencarnação, progresso e justiça. O EVA sustentou a resposta inteira com uma evidência primária; o baseline acrescentou referências às questões 167 e 171.
- **Q4:** o EVA concentrou-se na origem corpórea, aperfeiçoamento intelectual e dever de utilidade. O baseline ampliou a resposta com limites do trabalho, dever social e participação na Criação.
- **Q5:** ambas afirmaram sobrevivência e individualidade da alma. O EVA detalhou perispírito, separação gradual, perturbação e recuperação da consciência; o baseline respondeu de modo mais curto com referências às questões 149 e 150.

O EVA gerou 2.645 tokens de saída, contra 1.472 do baseline. Portanto, a economia do EVA ocorreu no contexto de entrada, não por respostas menores. As respostas EVA foram mais extensas e rastreáveis; as diretas foram geralmente mais concisas.

## Resultados completos

As chamadas retornaram originalmente respostas em português. No espelho inglês, esses textos são traduções editoriais fiéis, não novas gerações do modelo, e não alteram as métricas registradas.

<details>
<summary>Q1 — O que é Deus e quais atributos definem a natureza divina?</summary>

### EVA

Deus é apresentado como um ser cujos atributos incluem ser eterno, infinito, imutável, imaterial, único, onipotente, soberanamente justo e bom, ainda que a linguagem humana, restrita às suas ideias e sensações, não tenha meios de exprimir tudo o que está acima da inteligência humana [EVA-E008287]. A razão indica que Deus deve possuir essas perfeições em grau supremo, pois, se uma lhe faltasse ou não fosse infinita, já não seria superior a tudo e, por conseguinte, não seria Deus [EVA-E008287].

Cada atributo é justificado por sua função na ordem universal: é eterno porque, tendo tido princípio, teria saído do nada ou sido criado por um ser anterior; é imutável porque, sujeito a mudanças, as leis que regem o Universo nenhuma estabilidade teriam; é imaterial porque sua natureza difere de tudo o que chamamos matéria, e de outro modo estaria sujeito às transformações da matéria; é único porque, havendo muitos deuses, não haveria unidade de vistas nem de poder na ordenação do Universo; é onipotente porque é único, e, se não dispusesse do soberano poder, algo haveria mais poderoso ou tão poderoso quanto ele; é soberanamente justo e bom, sabedoria que se revela nas mais pequeninas e nas maiores coisas [EVA-E008287].

A natureza íntima de Deus, porém, não pode ser compreendida pelo homem, pois falta-lhe para isso o sentido [EVA-E008284]; ainda assim, é possível formar ideia de algumas de suas perfeições, compreendidas melhor à medida que o homem se eleva acima da matéria e as entrevê pelo pensamento [EVA-E008286]. O homem as compreenderá quando não mais tiver o espírito obscurecido pela matéria e, por sua perfeição, se houver aproximado de Deus; na infância da Humanidade, confunde-o muitas vezes com a criatura, atribuindo-lhe imperfeições, mas, à medida que se desenvolve o senso moral, faz ideia mais justa da Divindade [EVA-E008285].

A concepção de um Deus único resulta do desenvolvimento das ideias: incapaz de conceber um ser imaterial sem forma determinada atuando sobre a matéria, o homem conferiu-lhe atributos da natureza corpórea, e tudo o que ultrapassava os limites da inteligência comum era tido como divindade; em todos os tempos, porém, homens instruídos compreenderam ser impossível a existência de poderes múltiplos a governarem o mundo sem uma direção superior, elevando-se à concepção de um Deus único [EVA-E008957]. A doutrina panteísta, por sua vez, faz de Deus um ser material que, embora dotado de suprema inteligência, estaria sujeito às vicissitudes da matéria, faltando-lhe o atributo essencial da imutabilidade, e confunde o Criador com a criatura, como quem pretendesse que a máquina fosse parte integrante do mecânico que a imaginou [EVA-E008290].

### Contexto integral

Deus é a inteligência suprema, causa primária de todas as coisas (questão 1). Seus atributos são: eterno, imutável, imaterial, único, onipotente, soberanamente justo e bom (questão 13). O documento esclarece que essa ideia é completa do ponto de vista humano, mas há coisas acima da inteligência humana que a linguagem não consegue exprimir. Deus é eterno porque, se tivesse tido princípio, teria saído do nada ou sido criado por um ser anterior; é imutável porque, se estivesse sujeito a mudanças, as leis do Universo não teriam estabilidade; é imaterial porque sua natureza difere de tudo o que chamamos matéria; é único porque, se muitos deuses houvesse, não haveria unidade de poder na ordenação do Universo; é onipotente porque é único; e é soberanamente justo e bom, sabedoria que se revela nas leis divinas. O documento também afirma que o homem não pode compreender a natureza íntima de Deus (questão 10) e que só os Espíritos superiores o veem e compreendem (questão 244).

</details>

<details>
<summary>Q2 — Como o livre-arbítrio se relaciona com a responsabilidade moral pelos atos humanos?</summary>

### EVA

O livre-arbítrio é condição da responsabilidade moral: sem ele, o homem seria máquina [EVA-E009133], e não teria nem culpa pelo mal nem mérito pelo bem, de modo que a censura ou o elogio recaem sobre a intenção, isto é, sobre a vontade [EVA-E009162]. A responsabilidade cresce com a liberdade: quem é mais esclarecido é mais responsável pelo que faz do que o selvagem pelos seus atos [EVA-E009139], e a liberdade, quase nula nas primeiras fases da vida, desenvolve-se com as faculdades [EVA-E009134]. As predisposições instintivas trazidas ao nascer não eliminam essa liberdade, pois não há arrastamento irresistível quando se tem vontade de resistir [EVA-E009135], e a influência da matéria embaraça as manifestações do Espírito sem lhe dar a faculdade, incorrendo em falta quem por vontade própria se ocupa só da matéria [EVA-E009136]. A aberração das faculdades, por sua vez, retira a liberdade de quem já não é senhor do próprio pensamento [EVA-E009137], e as exigências do mundo não suprimem a responsabilidade de empregar esforços para vencer os obstáculos [EVA-E009140]. Assim, a responsabilidade moral se funda na vontade livre, que pode ceder ou resistir, e se mede pelo grau de liberdade efetivamente exercido.

### Contexto integral

Com base no documento, o livre-arbítrio é a base da responsabilidade moral. O texto afirma que, sem o livre-arbítrio, o homem seria uma máquina (q. 843) e não teria culpa pelo mal nem mérito pelo bem (q. 872). A responsabilidade é proporcional aos meios de compreender o bem e o mal: o homem instruído que pratica injustiça é mais culpado que o selvagem ignorante (q. 637). O livre-arbítrio se desenvolve com a consciência de si (q. 122) e permite ao homem escolher entre ceder ou resistir às influências espirituais (q. 851, 872). Assim, cada um responde por seus atos conforme a intenção e o conhecimento que tem da lei moral.

</details>

<details>
<summary>Q3 — Qual é a relação entre reencarnação, progresso do Espírito e justiça divina?</summary>

### EVA

A reencarnação funda-se na justiça de Deus e na revelação, pois o bom pai deixa sempre aberta aos filhos uma porta para o arrependimento [EVA-E008447]. Todos os Espíritos tendem para a perfeição e Deus lhes faculta os meios de alcançá-la, proporcionando-lhes as provações da vida corporal, e sua justiça concede realizar, em novas existências, o que não puderam fazer ou concluir numa primeira prova [EVA-E008447]. A doutrina das muitas existências sucessivas é apresentada como a única que corresponde à ideia da justiça divina para com os homens em condição moral inferior, explicando o futuro e oferecendo os meios de resgatar os erros por novas provações [EVA-E008447]. O homem consciente de sua inferioridade encontra nessa doutrina consoladora esperança, pois a inferioridade não o deserda eternamente do supremo bem, e mediante novos esforços pode conquistá-lo [EVA-E008447]. Assim, a reencarnação articula o progresso do Espírito rumo à perfeição com a justiça divina, que concede novas provações como meio de resgate e de realização do que ficou incompleto.

### Contexto integral

Com base no documento, a relação entre reencarnação, progresso do Espírito e justiça divina é apresentada da seguinte forma:

A reencarnação é o meio pelo qual o Espírito pode progredir e se depurar, passando por muitas existências corporais. O documento afirma que “todos contamos muitas existências” e que a reencarnação tem por fim a “expiação, melhoramento progressivo da Humanidade” (questão 167). O Espírito, criado simples e ignorante, adquire conhecimentos e se aperfeiçoa através das provas da vida corporal, e a reencarnação lhe permite realizar em novas existências o que não pôde concluir numa primeira prova (questão 171).

Essa doutrina é apresentada como a única que corresponde à ideia da Justiça de Deus. O documento argumenta que “não obraria Deus com equidade, nem de acordo com a sua bondade, se condenasse para sempre os que talvez hajam encontrado, oriundos do próprio meio em que foram colocados e alheios à vontade que os animava, obstáculos ao seu melhoramento” (questão 171). A reencarnação é, portanto, a expressão da justiça divina, pois oferece a todos os Espíritos os meios de resgatarem seus erros por novas provações e de alcançarem a perfeição, que é o destino final de todos. A justiça de Deus se manifesta ao conceder a todos a possibilidade de progredir, sem privilégios nem condenações eternas, fazendo com que a sorte futura de cada um dependa de seus próprios esforços e méritos ao longo das sucessivas existências.

</details>

<details>
<summary>Q4 — Como O Livro dos Espíritos explica a origem e a finalidade da lei do trabalho?</summary>

### EVA

O Livro dos Espíritos apresenta a lei do trabalho como decorrência da natureza corpórea humana, cumprindo simultaneamente função de expiação e de aperfeiçoamento da inteligência: o trabalho se impõe ao homem “por ser uma consequência da sua natureza corpórea. É expiação e, ao mesmo tempo, meio de aperfeiçoamento da sua inteligência”, de modo que, sem ele, o homem “permaneceria sempre na infância, quanto à inteligência” [EVA-E008966]. A essa finalidade liga-se a dependência material da existência, pois o alimento, a segurança e o bem-estar “dependem do seu trabalho e da sua atividade”, ainda que a mesma passagem registre que a Deus foi outorgada a inteligência ao extremamente fraco de corpo, o que também é trabalho [EVA-E008966].

A lei não se restringe ao esforço material. Aquele que possui bens suficientes para assegurar a existência pode estar isento “do trabalho material, talvez; não, porém, da obrigação de tornar-se útil”, conforme os meios de que disponha, nem de aperfeiçoar a sua própria inteligência ou a dos outros, “o que também é trabalho” [EVA-E008969]. A isenção da necessidade de se alimentar com o suor do próprio rosto não elimina a obrigação, que se amplia na medida das ocasiões de praticar o bem proporcionadas pelo adiantamento recebido [EVA-E008969].

Assim, a origem da lei do trabalho está na condição corpórea do homem, e sua finalidade abrange a expiação, o aperfeiçoamento da inteligência e a utilidade para com os semelhantes, inclusive sob a forma não material de tornar-se útil e desenvolver a inteligência própria e alheia [EVA-E008966][EVA-E008969].

### Contexto integral

Com base no documento, a lei do trabalho é explicada da seguinte forma:

**Origem e natureza:**

- O trabalho é uma **Lei da Natureza**, pois constitui uma necessidade (questão 674).
- É uma **consequência da natureza corpórea do homem** e, ao mesmo tempo, **expiação e meio de aperfeiçoamento da inteligência** (questão 676).
- Não se restringe a ocupações materiais: “o Espírito trabalha, assim como o corpo. Toda ocupação útil é trabalho” (questão 675).

**Finalidade:**

- Sem o trabalho, “o homem permaneceria sempre na infância, quanto à inteligência” (questão 676).
- O trabalho do homem visa **duplo fim**: “a conservação do corpo e o desenvolvimento da faculdade de pensar, o que também é uma necessidade e o eleva acima de si mesmo” (questão 677).
- O trabalho é também **meio de o homem concorrer para a obra da Criação**: “concorrendo para a obra geral, ele próprio se adianta” (questão 132).
- Nos mundos mais aperfeiçoados, “quanto menos materiais são as necessidades, menos material é o trabalho”, mas a ociosidade seria “um suplício, em vez de ser um benefício” (questão 678).

**Limites e deveres correlatos:**

- O limite do trabalho é o das forças; Deus deixa o homem inteiramente livre a esse respeito (questão 683).
- Quem abusa da autoridade impondo excesso de trabalho aos inferiores “transgride a Lei de Deus” (questão 684).
- O forte deve trabalhar para o fraco; à sociedade cabe amparar quem não pode trabalhar (questão 685).
- Mesmo quem possui bens suficientes não está isento da obrigação de ser útil e de aperfeiçoar sua inteligência e a dos outros (questão 679).

</details>

<details>
<summary>Q5 — O que acontece com a alma após a morte e como ela conserva sua individualidade?</summary>

### EVA

Após a morte, a alma conserva sua individualidade, jamais a perdendo [EVA-E008426]. Essa individualidade é comprovada pela permanência de um fluido próprio, haurido na atmosfera do planeta, que guarda a aparência da última encarnação: o perispírito [EVA-E008426]. A alma nada leva deste mundo além da lembrança e do desejo de ir para um mundo melhor, lembrança cheia de doçura ou de amargor conforme o uso que fez da vida [EVA-E008426].

A doutrina espírita adota a acepção de alma como ser imaterial e individual que reside em nós e sobrevive ao corpo [EVA-E008258]. A opinião de que a alma retorna ao todo universal e perde a individualidade é rejeitada: o conjunto dos Espíritos constitui um mundo completo, mas cada um conserva sempre sua individualidade, como um membro de uma assembleia [EVA-E008427]. Os que supõem que a alma se confunde num amálgama estão em erro, pois as almas manifestam consciência do eu e vontade própria, e a diversidade infinita que apresentam decorre de constituírem individualidades diversas [EVA-E008428]. A individualidade torna-se ainda mais evidente quando os seres provam sua identidade por indicações incontestáveis, particularidades individuais verificáveis referentes às suas vidas terrestres, e quando se fazem visíveis nas aparições [EVA-E008428].

A separação da alma e do corpo não é instantânea: a alma se desprende gradualmente, os laços se desatam e não se quebram [EVA-E008431]. A morte é a destruição do corpo somente, não a do perispírito, que se separa quando cessa a vida orgânica, e o desprendimento completo é mais lento nos casos de morte violenta [EVA-E008438]. Por ocasião da morte, tudo a princípio é confuso, e a alma precisa de algum tempo para entrar no conhecimento de si mesma, como quem desperta de profundo sono; a lucidez e a memória do passado voltam à medida que se apaga a influência da matéria e se dissipa a névoa que obscurece os pensamentos [EVA-E008441].

Assim, a alma sobrevive ao corpo como ser imaterial e individual, mantendo sua individualidade por meio do perispírito e da consciência de si, ainda que atravesse um período de perturbação variável antes de se reconhecer em sua nova situação [EVA-E008426][EVA-E008441].

### Contexto integral

Com base no documento, após a morte, a alma volta a ser Espírito, isto é, retorna ao mundo dos Espíritos, de onde se apartara momentaneamente (questão 149). Ela conserva sua individualidade, pois jamais a perde (questão 150). Como comprova essa individualidade, uma vez que não tem mais corpo material? “Continua a ter um fluido que lhe é próprio, haurido na atmosfera do seu planeta, e que guarda a aparência de sua última encarnação: seu perispírito” (questão 150a). A alma nada leva consigo deste mundo, “a não ser a lembrança e o desejo de ir para um mundo melhor, lembrança cheia de doçura ou de amargor, conforme o uso que ela fez da vida” (questão 150b).

</details>

## Limitações

- O benchmark cobre uma obra, cinco perguntas e uma execução sequencial por pergunta.
- O modelo usado foi o mesmo nos dois cenários, isolando a arquitetura, mas os resultados não podem ser generalizados automaticamente para outros modelos.
- O documento integral coube na janela de contexto de um milhão de tokens do `deepseek-flash`; obras maiores ou conjuntos de obras podem excedê-la.
- O cache direto foi aquecido dentro da mesma sessão temporal. Repetições após expiração, alterações no prefixo ou mudanças no corpus podem produzir outro custo.
- A medição usa os tokens retornados pelos provedores como fonte de verdade. Tokens de embeddings e de geração usam tokenizadores distintos.
- O custo usa a tabela vigente e o período off-peak no momento da execução; preços futuros podem alterar os valores.
- A latência inclui rede e carga externa e não foi repetida o suficiente para produzir percentis estatísticos.
- Não houve avaliação cega por especialistas nem cálculo de acurácia doutrinária. A análise qualitativa descreve propriedades observáveis das respostas.
- O baseline direto não implementou validação programática dos números de questões citados.
- As respostas completas do espelho inglês são traduções editoriais das saídas originais em português, não novas chamadas ao modelo.

## Conclusão

Neste ensaio, o EVA substituiu o envio repetido de um contexto integral de 1.246.430 tokens contabilizados por 25.805 tokens de contexto de resposta e 104 tokens de embeddings. O ganho permaneceu expressivo mesmo contra um baseline direto com cache otimizado: 97,71% menos tokens operacionais e 89,99% menos custo agregado.

Esses números já refletem cinco perguntas isoladas, cada uma com novo embedding e nova recuperação. A eficiência decorreu da reutilização da memória documental previamente construída e da ausência de histórico conversacional acumulado, não da herança do contexto de uma pergunta anterior.

O contexto integral foi 13,11% mais rápido no conjunto e produziu respostas mais curtas. O EVA respondeu com maior desenvolvimento e acrescentou IDs verificáveis, seleção determinística, validação local e declaração explícita quando interações cognitivas não podiam ser sustentadas literalmente.

O resultado prático é uma troca mensurável: o EVA adiciona etapas locais e pequena latência para reduzir drasticamente o contexto generativo e manter controle documental. O cache do provedor pode tornar consultas integrais repetidas temporariamente baratas e evitar recomputação do prefixo, mas não reduz o volume lógico de entrada contabilizado, depende de prefixo idêntico e não substitui os contratos de evidência e validação.
