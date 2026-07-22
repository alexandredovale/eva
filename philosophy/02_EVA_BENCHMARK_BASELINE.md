# EVA — Benchmark Baseline de Consulta Documental

**Versão:** 1.1  
**Data da execução:** 20 de julho de 2026  
**Documento:** `EVA-D000060` — *O Livro dos Médiuns*  
**Tipo de execução:** sequência única, sem concorrência

## 1. Objetivo

Registrar uma linha de base operacional e científica para futuras comparações do EVA com outras versões do próprio sistema e com arquiteturas alternativas de recuperação documental.

Esta execução não demonstra superioridade estatística. Ela mede cinco casos previamente definidos e explicita resultados válidos, recusas e falhas de contrato sem seleção retrospectiva.

Identidades de fornecedores e modelos foram omitidas em conformidade com o princípio white label. As contagens de tokens foram fornecidas diretamente pelas APIs configuradas; não são estimativas por caracteres.

## 2. Estado do acervo

| Parâmetro | Valor |
|---|---:|
| Nós documentais | 472 |
| Evidências primárias | 371 |
| Evidências derivadas | 472 |
| Embeddings persistentes | 843 |
| Limite de evidências por consulta | 8 |
| Limite de interações por consulta | 4 |

## 3. Metodologia

As consultas foram executadas sequencialmente no ambiente local. A duração foi medida com relógio monotônico de alta resolução. Para cada chamada externa foram registrados apenas tipo, duração, tamanho do request e contadores numéricos de uso. Credenciais, endpoints, modelos e payloads não foram registrados.

As cinco perguntas foram definidas antes da execução para exercitar:

1. recuperação literal;
2. navegação estrutural;
3. recuperação conceitual;
4. consulta relacional;
5. controle negativo sem evidência.

## 4. Resultados por consulta

| # | Categoria | Latência total | Chamadas externas | Tokens totais | Evidências utilizadas | Resultado |
|---:|---|---:|---:|---:|---:|---|
| 1 | literal | 2,052 s | 1 resposta | 2.709 | 1 | válido |
| 2 | estrutural | 2,547 s | 1 resposta | 10.667 | 0 | bloqueado |
| 3 | conceitual | 4,617 s | 1 embedding + 1 resposta | 10.596 | 3 | válido |
| 4 | relacional | 6,271 s | 1 embedding + 1 resposta | 8.438 | — | bloqueado |
| 5 | controle negativo | 10,4 ms | 0 | 0 | 0 | recusa correta |

### 4.1 Recuperação literal

**Pergunta**

> Como deve ser entendida a afirmação “cada um traga em si o gérmen das qualidades necessárias para se tornar médium”?

**Resposta validada**

> A afirmação significa que toda pessoa possui, em potencial, as qualidades necessárias para desenvolver a mediunidade, mas essas qualidades existem em graus variados e seu desenvolvimento depende de causas que não podem ser controladas à vontade.
>
> Evidências: [EVA-E000894]

| Métrica | Valor |
|---|---:|
| Tokens do prompt | 2.625 |
| Tokens da resposta | 84 |
| Latência externa | 2,045 s |
| Latência local | 6,7 ms |
| Caracteres da evidência utilizada | 8.188 |
| Embedding transitório | dispensado |

### 4.2 Recuperação estrutural

**Pergunta**

> Na estrutura do Capítulo XXVIII — Do Charlatanismo e do Embuste, quais critérios o texto oferece para distinguir fraude de fenômeno autêntico?

O provedor retornou texto, mas não declarou nenhuma evidência utilizada. O EVA rejeitou a resposta com a regra: `A resposta não indicou nenhuma evidência primária utilizada.`

O diagnóstico local mostrou que o capítulo correto foi recuperado, mas a seleção estrutural também incluiu um caminho do Capítulo XIX por sobreposição lexical. Uma evidência irrelevante de 12.341 caracteres entrou no contexto.

| Métrica | Valor |
|---|---:|
| Tokens do prompt | 10.561 |
| Tokens da resposta rejeitada | 106 |
| Latência externa | 2,538 s |
| Latência local | 9,2 ms |
| Tamanho do request | 39.805 bytes |
| Resultado | bloqueio por ausência de citação estruturada |

### 4.3 Recuperação conceitual

**Pergunta**

> Por que a qualidade moral do médium influencia as comunicações sem determinar mecanicamente a faculdade mediúnica?

**Resposta validada**

> A qualidade moral do médium influencia as comunicações porque atrai ou repele Espíritos conforme a afinidade, mas não determina mecanicamente a faculdade mediúnica, pois esta é orgânica e independente do moral. O médium imperfeito pode, excepcionalmente, transmitir boas comunicações quando os bons Espíritos se servem dele à falta de outro, mas isso é momentâneo. A faculdade em si é um dom que pode ser usado bem ou mal, e a influência moral se exerce sobre o uso e a natureza dos Espíritos que se comunicam, não sobre a existência da faculdade.
>
> Evidências: [EVA-E001126] [EVA-E001130] [EVA-E001127]

| Métrica | Valor |
|---|---:|
| Tokens do embedding | 28 |
| Latência do embedding | 1,135 s |
| Tokens do prompt de resposta | 10.382 |
| Tokens da resposta | 186 |
| Latência da resposta | 3,111 s |
| Latência local | 371 ms |
| Evidências utilizadas | 3 |
| Caracteres nas evidências utilizadas | 14.786 |

### 4.4 Consulta relacional

**Pergunta**

> Qual é a relação entre a influência moral do médium e a influência do meio nas comunicações, e em que sentido essa interação é simetry ou assimetry segundo as evidências?

O provedor produziu uma resposta relacional, mas apresentou fragmentos que não eram cópias literais das evidências indicadas. O EVA rejeitou toda a saída com a regra: `A interação não contém fragmentos literais das evidências indicadas.`

| Métrica | Valor |
|---|---:|
| Tokens do embedding | 41 |
| Latência do embedding | 712 ms |
| Tokens do prompt de resposta | 7.818 |
| Tokens da resposta estruturada | 579 |
| Latência da resposta | 5,187 s |
| Latência local | 372 ms |
| Tamanho total dos requests | 29.251 bytes |
| Resultado | bloqueio por interação não verificável |

### 4.5 Controle negativo

**Pergunta**

> Há evidência para a declaração “a mediunidade pode ser medida por eletroencefalograma”?

**Resposta determinística**

> Não há evidência documental suficiente para responder a este input.

| Métrica | Valor |
|---|---:|
| Latência total | 10,4 ms |
| Chamadas externas | 0 |
| Tokens | 0 |
| Evidências | 0 |
| Resultado | recusa correta |

## 5. Métricas agregadas

| Métrica | Valor |
|---|---:|
| Tempo total | 15,498 s |
| Latência média | 3,100 s |
| Mediana de latência | 2,547 s |
| Tempo externo acumulado | 14,728 s |
| Participação externa na latência | 95,04% |
| Tempo local acumulado | 769 ms |
| Tokens de entrada | 31.455 |
| Tokens de saída | 955 |
| Tokens totais | 32.410 |
| Chamadas externas | 6 |
| Volume total enviado | 118.649 bytes |
| Maior incremento de memória observado | aproximadamente 54 MiB |
| Resultados funcionais válidos | 3 de 5 |
| Saídas bloqueadas por validação | 2 de 5 |

As duas saídas rejeitadas consumiram 19.105 tokens, equivalentes a 58,95% de todos os tokens desta execução.

## 6. Benefícios observados

- A consulta literal dispensou embedding transitório.
- O controle negativo foi encerrado localmente em 10,4 ms, sem chamada ou custo externo.
- A recuperação conceitual percorreu 843 embeddings em aproximadamente 371 ms de processamento local.
- Validações impediram que uma resposta sem evidência declarada e uma interação com fragmentos não literais chegassem ao usuário.
- A latência local representou 4,96% do tempo total; chamadas externas foram o componente dominante.

## 7. Limitações reveladas

- Unidades semânticas completas podem produzir prompts extensos.
- A seleção estrutural pode misturar caminhos por coincidência lexical.
- O contrato relacional depende de fragmentos literais corretamente reproduzidos pelo provedor.
- Uma interação inválida bloqueou também a resposta documental potencialmente aproveitável.
- Uma única execução de cinco casos não permite inferência estatística nem comparação de superioridade.

## 8. Próximo protocolo comparativo

O experimento seguinte deve executar ao menos 30 repetições por categoria e comparar o EVA com um RAG de blocos fixos usando o mesmo documento, perguntas, provedores e orçamento de contexto. Devem ser medidos, no mínimo:

- taxa de resposta documental válida;
- taxa de recusa correta e indevida;
- precisão e recall de evidências;
- validade de citações;
- validade de simetry/assimetry;
- tokens de entrada e saída;
- latências p50, p95 e p99;
- custo monetário;
- memória máxima;
- estabilidade entre execuções.

## 9. Verificação da cobertura relacional parcial

Depois da linha de base, foi aplicado e testado o comportamento de cobertura parcial para inputs relacionais compostos. `simetry` e `assimetry` permaneceram como operadores essenciais da compreensão cognitiva; o ajuste foi direcionado somente à distinção entre aspectos com e sem evidência.

**Pergunta de verificação**

> Qual é a relação entre a influência moral do médium, a influência do meio nas comunicações e a medição da mediunidade por eletroencefalograma?

Neste input:

- X: influência moral do médium — evidências encontradas;
- Y: influência do meio nas comunicações — evidências encontradas;
- Z: medição da mediunidade por eletroencefalograma — nenhuma evidência suficiente encontrada.

**Resposta validada**

> A influência moral do médium e a influência do meio nas comunicações são fatores que afetam a natureza e a qualidade das comunicações espíritas. A medição da mediunidade por eletroencefalograma não é mencionada nas evidências.
>
> Evidências: [EVA-E001126] [EVA-E001127] [EVA-E001128] [EVA-E001129] [EVA-E001130] [EVA-E001133]

**Limitação específica**

> Não foi localizada evidência suficiente no contexto recuperado para: medição da mediunidade por eletroencefalograma.

O resultado preservou uma interação `simetry` validada entre evidências de X e Y. Uma candidata adicional sem fragmentos literais válidos foi descartada sem invalidar a resposta nem a interação válida.

| Métrica | Valor |
|---|---:|
| Resultado documental | válido |
| Evidências citadas | 6 |
| Interações `simetry` validadas | 1 |
| Interações `assimetry` validadas | 0 |
| Aspectos sem evidência identificados | 1 |
| Latência total | 5,803 s |
| Latência externa | 5,399 s |
| Latência local | 404 ms |
| Tokens do embedding | 39 |
| Tokens do prompt de resposta | 9.830 |
| Tokens da resposta estruturada | 502 |
| Tokens totais | 10.371 |
| Tamanho total dos requests | 37.172 bytes |

Essa execução confirma funcionalmente o padrão esperado: relação solicitada entre X, Y e Z; resposta sustentada entre X e Y; limitação explícita para Z; compreensão cognitiva `simetry`/`assimetry` preservada. Uma única execução não demonstra taxa estatística de sucesso.

## 10. Verificação da recuperação lexical e estrutural sem descarte

O caso estrutural que havia sido bloqueado na linha de base foi repetido após a introdução da triagem de candidatos pela IA.

**Pergunta de verificação**

> Na estrutura do Capítulo XXVIII — Do Charlatanismo e do Embuste, quais critérios o texto oferece para distinguir fraude de fenômeno autêntico?

O recuperador entregou oito evidências candidatas ao provedor. O conjunto ainda continha um caminho intruso do Capítulo XIX, mas nenhum candidato foi descartado antes da análise. A IA selecionou quatro evidências pertinentes do Capítulo XXVIII e não citou os quatro candidatos intrusos.

**Resposta validada**

> O texto do Capítulo XXVIII oferece critérios para distinguir fraude de fenômeno autêntico: o desinteresse material é a garantia mais peremptória, pois “não há charlatanismo desinteressado” [EVA-E001204]; a fraude visa sempre a um interesse material, “onde nada haja a ganhar, nenhum interesse há em enganar” [EVA-E001214]; fenômenos físicos são mais facilmente imitáveis, mas a prestidigitação não pode imitar “esses belos e sublimes ditados” das comunicações inteligentes [EVA-E001215]; e a mediunidade não deve ser usada para ambições pessoais, pois “os bons Espíritos se afastam de quem pretenda fazer dela um degrau” [EVA-E001206].

| Métrica | Linha de base | Após triagem pela IA |
|---|---:|---:|
| Candidatos entregues | 8 | 8 |
| Evidências citadas | 0 | 4 |
| Candidatos intrusos citados | 0 | 0 |
| Resultado documental | bloqueado | válido |
| Chamadas de embedding | 0 | 0 |
| Chamadas de resposta | 1 | 1 |
| Latência total | 2,547 s | 3,876 s |
| Latência local | 9,2 ms | 7,2 ms |
| Tokens do prompt | 10.561 | 10.994 |
| Tokens da resposta | 106 | 242 |
| Tokens totais | 10.667 | 11.236 |
| Tamanho do request | 39.805 bytes | 41.734 bytes |

O teste confirma o comportamento esperado: recuperação produz candidatos; a IA analisa todos os candidatos entregues; somente textos sustentadores tornam-se evidências utilizadas; toda afirmação documental permanece acompanhada de citação; intrusos não invalidam nem contaminam a resposta.

## 11. Protocolo multidisciplinar a executar

A linha de base registrada neste documento utilizou uma única obra e não mede a capacidade multidisciplinar do EVA. A aplicação dessa propriedade deve ser avaliada separadamente, sem converter expectativa arquitetural em resultado científico antecipado.

O experimento deverá formar projetos com documentos especializados de ao menos três disciplinas e incluir quatro classes de consulta:

1. relação explicitamente sustentada entre duas áreas;
2. relação sustentada por vocabulários distintos, sem repetição lexical direta;
3. cobertura parcial, com duas áreas sustentadas e uma sem evidência suficiente;
4. falsa interseção, na qual os documentos são semanticamente próximos, mas não sustentam a relação solicitada.

Cada resposta deverá ser avaliada quanto a:

- proveniência correta de cada evidência e disciplina;
- precisão e recall das evidências multidocumentais;
- validade dos fragmentos e participantes de `simetry`/`assimetry`;
- taxa de afirmações que extrapolam as fontes citadas;
- declaração não evasiva das áreas sem fundamento;
- diversidade de documentos no contexto diante do limite global;
- estabilidade da resposta sob variações do input;
- invariância da memória documental antes e depois das consultas.

A invariância deverá ser verificada comparando contagens e hashes de documentos, nós, evidências, derivações e embeddings antes e depois do conjunto de consultas. Respostas, contextos e interações não poderão produzir novos registros de memória.

O estudo também deverá distinguir três conceitos:

- **confiabilidade processual:** capacidade de auditar a resposta até as fontes participantes;
- **correção semântica:** adequação da interpretação às evidências, avaliada por anotadores;
- **completude multidisciplinar:** presença das disciplinas pertinentes dentro do limite de contexto.

Uma síntese conceitual emergente será considerada válida somente quando seus componentes forem rastreáveis e sua formulação não ultrapassar o conjunto citado. Mesmo válida, continuará sendo resultado transitório da consulta, não nova evidência ou verdade incorporada ao acervo.
