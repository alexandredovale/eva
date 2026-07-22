# EVA (Evidence Algorithm): memória documental hierárquica e interação cognitiva transitória para respostas verificáveis

**Versão:** 2.3  
**Data:** 22 de julho de 2026  
**Autoria:** Projeto EVA

## Resumo

Este artigo apresenta o EVA (Evidence Algorithm), uma arquitetura para consulta documental assistida por modelos de linguagem cuja memória persistente é organizada em evidências rastreáveis, e não em respostas, relações cognitivas ou grafos inferidos. O sistema transforma documentos estruturados em uma árvore normalizada, preserva seus conteúdos literais como evidências primárias e produz sínteses hierárquicas como evidências derivadas com linhagem explícita. Embeddings são gerados para essas unidades semânticas completas, respeitando a organização do documento em vez de fragmentá-lo por limites arbitrários de caracteres ou tokens.

Na consulta, o EVA seleciona uma rota de recuperação compatível com o tipo de input. Perguntas diretas, estruturais e amplas podem navegar pela hierarquia; perguntas conceituais e relacionais usam uma representação vetorial transitória para localizar evidências primárias e derivadas. Evidências derivadas recuperadas são resolvidas até suas fontes primárias antes da geração da resposta. Se nenhuma evidência primária suficiente for encontrada, o fluxo é interrompido sem chamada ao provedor de resposta.

Relações cognitivas são tratadas como interações transitórias de **simetry** ou **assimetry**, produzidas somente no contexto da consulta, sem pesos, taxonomias julgamentais ou persistência. Em projetos multidisciplinares, evidências de documentos especializados distintos podem integrar uma seleção transitória e sustentar sínteses conceituais emergentes sem que a interpretação resultante seja promovida a evidência ou memória. Citações e participantes dessas interações são validados localmente contra o contexto recuperado. A proposta separa memória documental, recuperação, interpretação e apresentação, mantendo fornecedores e modelos como componentes substituíveis configurados externamente. O artigo descreve a arquitetura vigente, suas hipóteses verificáveis, limitações e um protocolo experimental para avaliação futura.

**Palavras-chave:** evidência; recuperação de informação; RAG; embeddings; memória documental; rastreabilidade; interação cognitiva; interdisciplinaridade; antievasão; simetry; assimetry; modelos de linguagem.

---

## 1. Introdução

Modelos de linguagem podem formular respostas coerentes mesmo quando a informação necessária está ausente, incompleta ou equivocadamente recuperada. Em aplicações documentais, essa característica cria um problema epistemológico e operacional: fluência não demonstra correspondência com a fonte. Sistemas de Retrieval-Augmented Generation (RAG) reduzem esse risco ao fornecer contexto externo ao modelo [1], mas o simples acréscimo de busca vetorial não garante preservação estrutural, linhagem de sínteses, citações válidas ou recusa adequada.

Uma implementação convencional costuma dividir o texto em blocos por tamanho, gerar embeddings desses fragmentos e selecionar os vizinhos mais próximos da pergunta. Essa estratégia é útil e escalável, porém pode separar definições de seus títulos, listas de suas introduções e parágrafos de sua posição argumentativa. Também pode tornar difícil explicar por que um resumo apareceu no resultado e a quais trechos literais ele corresponde.

O EVA parte de uma decisão distinta: a memória deve refletir a organização semântica já presente no documento. Títulos, seções, parágrafos, itens, propriedades e elementos formam uma árvore. Conteúdos literais dessa árvore são evidências primárias. Sínteses produzidas de baixo para cima são evidências derivadas, ligadas explicitamente às evidências que as sustentam. As duas classes podem receber embeddings, mas somente as fontes primárias fundamentam a resposta final.

A segunda decisão é separar memória de interação. Relações identificadas entre o input e as evidências não se tornam fatos permanentes. Elas descrevem a configuração cognitiva daquela consulta e são descartadas ao final. Essa fronteira evita que uma interpretação contingente, produzida por um modelo e condicionada por um contexto limitado, adquira o mesmo estatuto da fonte documental.

Este artigo documenta a versão vigente do EVA. Ele não apresenta o sistema como empiricamente superior a arquiteturas concorrentes. Em vez disso, explicita mecanismos, propriedades esperadas, limites e hipóteses que podem ser submetidas a experimentos reproduzíveis.

## 2. Problema de pesquisa

A questão central é:

> Como construir uma memória documental consultável por linguagem natural que preserve estrutura e proveniência, use modelos sem lhes delegar a autoridade da memória e produza respostas e interações verificáveis sem transformar interpretações transitórias em conhecimento persistente?

Essa questão contém seis problemas interdependentes:

1. **segmentação semântica:** representar o documento sem depender de cortes arbitrários;
2. **proveniência:** distinguir conteúdo literal de sínteses e registrar suas derivações;
3. **recuperação adaptativa:** não impor busca vetorial a perguntas que podem ser resolvidas estruturalmente;
4. **fundamentação:** impedir respostas documentais quando nenhuma evidência primária foi recuperada;
5. **fronteira epistemológica:** impedir que similaridades, respostas e relações inferidas sejam promovidas automaticamente a memória.
6. **articulação multidisciplinar:** permitir relações entre fontes especializadas distintas sem apagar sua proveniência nem contaminar o acervo com interpretações transitórias.

O EVA aborda esses problemas como uma cadeia única. A qualidade final não depende apenas do modelo gerador, mas do contrato entre ingestão, persistência, recuperação, validação e apresentação.

## 3. Princípios de projeto

### 3.1 Fonte antes de síntese

O conteúdo literal permanece distinguível de qualquer transformação produzida por modelo. Uma síntese auxilia a navegação sem substituir a fonte que lhe deu origem.

### 3.2 Estrutura antes de tamanho

Uma unidade é definida por sua função no documento, não por um número fixo de caracteres. Limites técnicos podem impedir o processamento de uma unidade excessivamente grande, mas não constituem o princípio de segmentação do sistema.

### 3.3 Recuperação antes de geração

O provedor de resposta só é acionado quando existe contexto primário validado. Ausência de evidência é um resultado legítimo, e não uma lacuna que deva ser preenchida por conhecimento paramétrico.

### 3.4 Similaridade sem autoridade

Similaridade vetorial ordena candidatos. Ela não mede verdade, importância moral, concordância ou intensidade cognitiva. Seus valores são transitórios.

### 3.5 Interação sem julgamento

Interações cognitivas descrevem reciprocidade ou direção. Não recebem pesos e não usam rótulos como “apoia”, “contradiz” ou “causa” como relações ontológicas permanentes. O conteúdo da resposta pode explicar uma convergência ou divergência, mas a estrutura da interação permanece neutra.

### 3.6 Memória somente por construção controlada

Consultas são operações de leitura. A memória só é alterada por processos explícitos de ingestão e construção, sujeitos às regras do backend. O modelo não escreve diretamente no banco.

### 3.7 Independência de fornecedor e white label

Papéis funcionais possuem nomes neutros. Fornecedor, modelo, endpoint e credencial são associados externamente por configuração. Esse princípio white label mantém a arquitetura conceitual independente de marcas específicas.

### 3.8 Interdisciplinaridade sem fusão de fontes

Agrupar documentos em um projeto amplia o espaço autorizado de recuperação, mas não funde documentos, evidências ou disciplinas em uma memória indistinta. Uma relação entre áreas só é formulada quando o input atual seleciona evidências capazes de participar da mesma análise. A relação permanece transitória, enquanto cada participante conserva sua origem documental.

Esse princípio permite sínteses conceituais emergentes sem conceder permanência automática à interpretação. O sistema pode articular uma relação que não aparece integralmente em uma única fonte, desde que seus componentes sejam rastreáveis; não pode converter essa articulação em nova evidência, verdade intrínseca ou premissa silenciosa para consultas futuras.

## 4. Relação com trabalhos anteriores

RAG combina recuperação e geração para fornecer conhecimento externo ao modelo [1]. Dense Passage Retrieval demonstrou a eficácia de representações densas na recuperação de passagens [2]. Trabalhos posteriores ampliaram o foco para autoavaliação e controle de recuperação, como Self-RAG [3], e para respostas longas com citações, como ALCE [4].

Outra linha explora contextos extensos [5] e recuperação sobre representações de maior granularidade [6]. GraphRAG organiza entidades, relações e sumários em estruturas de grafo para responder a perguntas locais e globais [7]. Pesquisas sobre embeddings de contexto longo e representação textual também mostram que o modelo de embedding e a composição da unidade representada influenciam a recuperação [8][9].

O EVA utiliza ideias compatíveis com esse campo, mas adota uma fronteira específica:

- não assume que todo input deva percorrer a mesma busca vetorial;
- não usa um grafo persistente de relações cognitivas;
- não trata resumos como equivalentes às fontes literais;
- não persiste relações produzidas durante a consulta;
- não permite geração documental sem evidência primária recuperada;
- preserva a árvore de origem como parte da memória.

O EVA, portanto, não é uma negação de RAG ou GraphRAG. É uma arquitetura de evidências com uma política mais restritiva sobre o que pode adquirir permanência.

## 5. Modelo de memória documental

### 5.1 Entrada e árvore normalizada

A implementação vigente aceita documentos em Markdown, JSON e XML. Cada parser converte a fonte em uma árvore normalizada sem apagar o conteúdo original relevante, a ordem ou a hierarquia.

Cada nó registra, conforme o formato:

- tipo estrutural;
- título ou rótulo;
- conteúdo completo;
- posição entre irmãos;
- vínculo com o nó pai;
- profundidade;
- referência verificável à fonte, como linhas, JSON Pointer ou XPath;
- hash para identidade e controle de mudança.

O parser não tenta resolver o significado filosófico de cada trecho. Sua responsabilidade é estrutural: preservar uma representação estável sobre a qual evidências possam ser construídas.

### 5.2 Evidências primárias

Todo nó elegível com conteúdo não vazio pode originar uma evidência primária do tipo `node_content`. Seu conteúdo é literal e aponta para o nó documental correspondente.

Seja (D) um documento e (N(D)) o conjunto de seus nós normalizados. A construção primária pode ser representada por:

\[
E_P(D) = \{e(n) \mid n \in N(D),\; content(n) \neq \varnothing\}
\]

Cada (e(n)) conserva a referência estrutural de (n). Uma evidência primária não é uma opinião sobre o texto; é uma unidade rastreável do próprio texto.

### 5.3 Evidências derivadas

Nós internos ou regiões hierárquicas podem receber sínteses produzidas de baixo para cima. Essas sínteses formam evidências derivadas do tipo `node_summary`.

Se (S(n)) é a síntese associada ao nó (n), sua base inclui evidências do próprio nó e de seus descendentes pertinentes:

\[
S(n) = f(E(n), S(c_1), S(c_2), \ldots, S(c_k))
\]

onde (c_1,\ldots,c_k) são filhos de (n) e (f) é o processo de síntese configurado. A equação descreve dependência, não equivalência: (S(n)) continua sendo uma interpretação derivada.

### 5.4 Linhagem de derivação

Cada evidência derivada é conectada às evidências que a sustentam. Essa linhagem permite percorrer uma síntese até fontes primárias e impede que um resumo se torne uma afirmação sem origem.

Uma derivação é representada como:

\[
e_d \rightarrow \{e_1, e_2, \ldots, e_m\}
\]

em que (e_d) é derivada e cada (e_i) é uma evidência fonte, primária ou derivada. A resolução é recursiva até que o conjunto resultante contenha evidências primárias utilizáveis.

### 5.5 Embeddings estruturais

Embeddings persistentes são produzidos para evidências primárias e derivadas. A unidade enviada ao provedor já é semanticamente organizada pelo documento ou por sua síntese hierárquica. O algoritmo não cria segmentos apenas para satisfazer um limite arbitrário de tamanho.

Cada embedding é associado à evidência, à configuração do modelo, à dimensão e ao hash do conteúdo. Isso permite detectar obsolescência e reconstruir representações quando o conteúdo ou a configuração mudam.

### 5.6 Fronteira persistente

O núcleo persistente vigente contém:

| Estrutura | Função |
|---|---|
| `documents` | identidade, formato, hash e estado do documento |
| `document_nodes` | árvore normalizada e referências à fonte |
| `evidences` | evidências primárias e derivadas |
| `evidence_derivations` | linhagem entre sínteses e fontes |
| `evidence_embeddings` | representações vetoriais versionadas |
| `processing_jobs` | execução das etapas de síntese e embeddings |
| `audit_events` | eventos operacionais sanitizados |

Não fazem parte do modelo vigente tabelas de nós cognitivos, relações cognitivas, embeddings de relações, análises de interação ou caches de consulta. Também não existe uma etapa de construção de grafo relacional. As etapas assistidas por modelo são sínteses e embeddings.

## 6. Processamento de ingestão

O fluxo de construção segue a sequência:

1. recebimento e validação do arquivo;
2. identificação do formato suportado;
3. parsing integral para árvore normalizada;
4. persistência do documento e dos nós;
5. geração determinística das evidências primárias;
6. síntese hierárquica e persistência das evidências derivadas;
7. registro da linhagem de cada derivação;
8. geração de embeddings para as evidências elegíveis;
9. atualização dos estados de processamento e auditoria.

A aplicação controla transações, validações e persistência. O provedor de síntese retorna uma transformação candidata; não possui acesso direto à memória. Falhas podem ser retomadas por estágio, e embeddings existentes podem ser reutilizados quando modelo e hash continuam compatíveis.

## 7. Fluxo de consulta

### 7.1 Detecção operacional do input

O EVA classifica localmente o input em tipos operacionais, incluindo:

- **direto:** procura um trecho ou informação explícita;
- **estrutural:** solicita seção, capítulo ou posição na hierarquia;
- **amplo:** demanda cobertura extensa do documento;
- **conceitual:** expressa uma ideia que pode não repetir o vocabulário da fonte;
- **relacional:** solicita ou implica interação entre conceitos ou evidências.

Essas categorias podem orientar rotas distintas. A detecção não pretende diagnosticar intenção psicológica do usuário; ela apenas seleciona mecanismos de recuperação.

### 7.2 Recuperação hierárquica

Inputs diretos, estruturais e amplos podem ser resolvidos por navegação textual e hierárquica. Essa rota privilegia evidências primárias e dispensa a criação de embedding do input quando a estrutura já fornece o caminho necessário.

A hierarquia permite recuperar uma unidade e seu contexto, respeitando ordem e parentesco. Assim, uma seção não é apresentada como uma sequência desordenada de fragmentos semelhantes.

As unidades recuperadas nessa rota são candidatas à fundamentação. Todas as candidatas selecionadas dentro do limite operacional são apresentadas ao provedor, inclusive possíveis intrusas decorrentes de coincidência lexical. O provedor analisa o conjunto e declara como utilizadas somente as evidências que sustentam a resposta. Se nenhuma candidata for utilizável, deve retornar ausência justificada, sem citação ou interação; a presença de intrusos não elimina candidatas válidas.

### 7.3 Recuperação semântica

Inputs conceituais e relacionais recebem um embedding transitório. Para uma consulta (q) e uma evidência persistente (e_i), a similaridade de cosseno é:

\[
sim(q,e_i) = \frac{v_q \cdot v_i}{\|v_q\|\|v_i\|}
\]

O valor ordena candidatos primários e derivados. Ele não é persistido e não representa confiança epistêmica.

Evidências derivadas relevantes funcionam como mapas semânticos para regiões maiores. Antes de compor o contexto final, o EVA percorre suas derivações e recupera evidências primárias. Dessa forma, a síntese melhora a localização, enquanto a fonte literal conserva a autoridade documental.

### 7.4 Consulta multidocumental e seleção transitória

Quando o escopo da consulta é um projeto, o EVA mantém os documentos como unidades independentes de recuperação. Cada obra produz seu próprio contexto candidato; em seguida, os candidatos são intercalados em uma seleção global limitada de evidências. A composição é determinada pelo input atual e não cria relações persistentes entre os documentos.

Essa seleção permite que evidências de disciplinas distintas cheguem simultaneamente ao provedor de resposta. Uma consulta pode, por exemplo, pedir a interseção entre um conceito jurídico, uma descrição técnica e uma análise histórica. O modelo pode formular uma síntese relacional a partir do conjunto recuperado, mas cada afirmação documental continua vinculada às evidências citadas de cada área.

Adicionar documentos ao projeto amplia o universo potencial de candidatos; não cria uma malha completa de conexões e não aumenta automaticamente o número de evidências entregue à resposta, que permanece sujeito ao limite operacional. Portanto, a interdisciplinaridade é ativada pela consulta, não pré-computada como grafo nem acumulada como memória inferida.

### 7.5 Barreira de evidência

Depois da recuperação, o sistema valida se existe pelo menos uma evidência primária utilizável. Se o conjunto for vazio, retorna uma limitação e não chama o provedor de resposta.

Quando o input combina múltiplos aspectos e apenas parte deles encontra suporte, a barreira preserva o contexto válido. O provedor responde às relações sustentadas com citações e registra limitações específicas para cada aspecto sem evidência suficiente. Assim, cobertura parcial e ausência integral de evidência são estados distintos.

Formalmente:

\[
Answer(q) =
\begin{cases}
g(q, E_P^*) & \text{se } E_P^* \neq \varnothing \\
Limitation & \text{se } E_P^* = \varnothing
\end{cases}
\]

onde (E_P^*) é o contexto primário validado e (g) é o provedor de resposta configurado.

Essa barreira reduz evasão, mas não prova que toda resposta gerada seja correta. A validade semântica da interpretação continua sendo objeto de avaliação.

### 7.6 Geração única de resposta e interação

Quando há evidências, uma única chamada ao provedor de resposta recebe o input atual, até três rodadas conversacionais anteriores anexadas pela interface e o contexto primário. Ela produz:

- resposta textual;
- identificadores das evidências utilizadas;
- interações de `simetry` e `assimetry`, quando a natureza do input as exige;
- pontos de roteamento ou limitações pertinentes.

Não existe um provedor separado dedicado a construir relações, nem uma segunda análise destinada a persistir um grafo.

### 7.7 Validação local

A saída do provedor é tratada como proposta, não como autoridade. O backend verifica, entre outros invariantes:

- todo identificador utilizado pertence ao contexto entregue;
- nenhuma citação desconhecida é aceita;
- participantes de interações são evidências recuperadas e citadas;
- trechos atribuídos a evidências são compatíveis com o conteúdo disponível;
- `simetry` possui dois participantes recíprocos;
- `assimetry` explicita origem e destino.

Quando uma evidência válida consta na saída estruturada, mas sua marca não aparece no texto visível, a aplicação pode acrescentar deterministicamente seu identificador. Esse mecanismo só apresenta IDs previamente validados; ele não inventa fontes.

### 7.8 Transitoriedade e privacidade

O embedding do input, os escores de similaridade, o contexto montado, a resposta e as interações são descartados ao final do request. Eles não são reinseridos na memória documental.

O comportamento vigente admite continuidade conversacional curta e transitória. A interface mantém o transcript completo apenas em memória local da página e anexa ao input no máximo as três rodadas concluídas mais recentes. O modelo avalia se a solicitação atual realmente depende desse histórico; mensagens sem relação devem ser ignoradas.

Essa continuidade não altera a fronteira epistemológica. Inputs e respostas anteriores podem esclarecer referências anafóricas, mas não são fontes documentais e nunca autorizam afirmações. A nova resposta permanece limitada às evidências primárias recuperadas para o request atual. O transcript não é persistido no banco, em eventos de auditoria ou no armazenamento do navegador e desaparece em reinício do chat, logout, novo login ou recarregamento.

### 7.9 Custo externo por rota

O número normal de chamadas externas é limitado pela rota:

| Resultado da rota | Embedding do input | Resposta | Total normal |
|---|---:|---:|---:|
| direta, estrutural ou ampla com evidência | 0 | 1 | 1 |
| conceitual ou relacional com evidência | 1 | 1 | 2 |
| direta, estrutural ou ampla sem evidência | 0 | 0 | 0 |
| conceitual ou relacional sem evidência | 1 | 0 | 1 |

A tabela descreve o caminho normal da implementação vigente, sem contar retentativas de rede ou operações administrativas.

## 8. Simetry e assimetry

### 8.1 Fundamento

Interação cognitiva é a forma observável pela qual participantes se relacionam dentro de uma consulta. Ela não é um fato universal extraído definitivamente do documento.

O EVA usa dois apontamentos mínimos:

- `simetry(A,B)`: (A) e (B) participam de uma interação recíproca;
- `assimetry(A \rightarrow B)`: a interação possui direção explícita de (A) para (B).

O esquema registra estrutura, participantes e base textual. Não registra peso, intensidade, aprovação ou precedência ontológica.

`simetry` e `assimetry` são operadores essenciais da compreensão cognitiva do sistema, não termos que precisem ocorrer na fonte. Eles permanecem no contexto relacional da IA e são aplicados às evidências recuperadas. Quando a interação não pode ser validada, a arquitetura preserva a resposta documental sustentada e explicita a limitação relacional.

### 8.2 Neutralidade

Taxonomias como `supports`, `contradicts`, `causes`, `depends_on` ou equivalentes podem induzir uma classificação rígida e, em alguns casos, um julgamento interpretativo. Elas não compõem o modelo persistente do EVA.

Isso não impede que a resposta explique, em linguagem natural e com evidências, que uma premissa diverge da obra ou que duas passagens apresentam conteúdos convergentes. Significa apenas que essa explicação permanece situada, rastreável e transitória.

### 8.3 Reposicionamento do conceito Cnode

Em versões anteriores, Cnode foi concebido como entidade persistente, com identidade, relações e embeddings próprios. Essa arquitetura foi removida.

No sistema atual, o termo pode ser usado apenas como descrição histórica ou fenomenológica do encontro entre input e evidências durante a consulta. Não corresponde a tabela, registro, identidade global, grafo, cache ou unidade autônoma de memória. O pilar persistente é o Evidence Algorithm.

### 8.4 Confiabilidade multidisciplinar e antievasão

A confiabilidade introduzida pelo EVA é uma propriedade do processo verificável, não um escore probabilístico de verdade. Em uma consulta multidisciplinar, ela decorre da preservação simultânea de cinco fronteiras: identidade de cada fonte, distinção entre evidência primária e derivada, seleção transitória do contexto, validação local da saída e declaração de limitações.

O sistema reduz evasão cognitiva ao impedir que fluência substitua fundamento. Se uma das disciplinas solicitadas não estiver representada por evidência suficiente, a resposta deve restringir a síntese ao subconjunto sustentado e declarar a ausência específica. Se nenhuma área possuir fundamento recuperado, a geração documental é bloqueada.

Uma síntese emergente entre disciplinas é, portanto, confiável no sentido de ser auditável até seus participantes documentais. Ela não é automaticamente verdadeira, completa ou intrínseca às fontes. Erros de recuperação e interpretação continuam possíveis e precisam ser medidos; a arquitetura oferece meios de localizá-los e impede que sejam incorporados silenciosamente à memória.

## 9. Governança, segurança e auditabilidade

A superfície pública é separada dos arquivos privados da aplicação. Operações administrativas exigem autenticação por token bearer configurado fora do código. Credenciais e associações de fornecedores não são documentadas nem expostas por endpoints públicos.

Eventos operacionais são registrados de forma sanitizada, sem transformar texto bruto de consultas em memória. A consulta permanece somente leitura em relação ao acervo documental.

O modelo de segurança vigente deve ser descrito sem extrapolações: o superadmin pode usar a credencial administrativa da instalação, usuários autenticados possuem sessões revogáveis e permissões explícitas por projeto ou documento, e projetos agrupam obras para consulta multidocumental. Esse controle não constitui identidade global, colaboração entre organizações nem isolamento multitenant. Tais capacidades exigiriam fronteiras adicionais de autorização e testes próprios.

A auditabilidade deriva principalmente de quatro propriedades:

1. referência de cada evidência primária à árvore e à fonte;
2. linhagem de evidências derivadas;
3. versionamento de conteúdo e embeddings;
4. validação das evidências citadas na resposta.

## 10. Comparação arquitetural

| Dimensão | RAG vetorial por blocos | Contexto longo | GraphRAG | EVA vigente |
|---|---|---|---|---|
| Unidade principal | bloco por janela | documento ou grande trecho | entidade, relação e comunidade | nó documental e evidência |
| Estrutura original | frequentemente parcial | preservada no input, limitada pela seleção | convertida em grafo | persistida como árvore |
| Resumos | opcionais | opcionais | centrais em comunidades | evidências derivadas com linhagem |
| Relações persistentes | geralmente não | não | sim | não para interação cognitiva |
| Busca vetorial | rota dominante | opcional | combinada ao grafo | somente quando o tipo exige |
| Fonte final | bloco recuperado | contexto fornecido | nós, relações ou relatórios | evidência primária resolvida |
| Ausência de evidência | depende do prompt | depende do modelo | depende da implementação | bloqueio antes da resposta |
| Interação cognitiva | não padronizada | não padronizada | relação do grafo | simetry/assimetry transitórias |
| Memória após consulta | varia | varia | grafo pode ser enriquecido | invariavelmente inalterada |

A tabela descreve escolhas de desenho, não um ranking de qualidade. Cada abordagem pode ser mais adequada a determinados domínios, escalas e objetivos.

## 11. Propriedades esperadas e hipóteses

As seguintes afirmações devem ser entendidas como hipóteses testáveis:

### H1 — Rastreabilidade

Respostas do EVA devem permitir maior taxa de localização da fonte primária do que uma configuração na qual resumos não possuam linhagem.

### H2 — Organização semântica

Embeddings de unidades estruturais completas devem melhorar precisão e coerência contextual em perguntas dependentes de seção quando comparados a blocos de tamanho fixo.

### H3 — Recusa fundamentada

A barreira de evidência deve reduzir respostas a perguntas sem suporte no acervo, preservando respostas quando fontes pertinentes são recuperadas.

### H4 — Eficiência por roteamento

O uso de navegação hierárquica em perguntas diretas e estruturais deve reduzir chamadas de embedding e latência em comparação com uma rota vetorial obrigatória para todos os inputs.

### H5 — Resolução derivada

Indexar sínteses, mas responder com suas fontes primárias, deve ampliar recall conceitual sem perder verificabilidade literal.

### H6 — Neutralidade relacional

Interações transitórias de simetry/assimetry devem permitir explicar convergências, divergências e direções sem depender de pesos ou de uma ontologia julgamental persistente.

### H7 — Portabilidade

Contratos neutros e configuração externa devem permitir substituição de provedores com mudanças restritas à camada de integração, preservando memória e regras centrais.

### H8 — Articulação multidisciplinar rastreável

Consultas sobre projetos com documentos de disciplinas distintas devem permitir sínteses relacionais com proveniência identificável por área, mantendo taxa baixa de relações não sustentadas e sem alterar a memória após a interação.

## 12. Protocolo experimental proposto

### 12.1 Baselines

Uma avaliação controlada deve comparar:

1. RAG por blocos de tamanho fixo;
2. RAG por blocos com sobreposição;
3. recuperação somente sobre evidências primárias;
4. recuperação sobre primárias e derivadas sem resolução de linhagem;
5. EVA completo com roteamento, linhagem e barreira de evidência;
6. contexto longo, quando tecnicamente e economicamente comparável.

Todos os baselines devem usar, na medida do possível, os mesmos documentos, provedor de embeddings, provedor de resposta e orçamento de contexto.

### 12.2 Corpora

O conjunto experimental deve incluir:

- documentos longos e hierárquicos em Markdown;
- documentos JSON com objetos, listas e profundidades distintas;
- documentos XML com atributos, elementos repetidos e namespaces quando suportados;
- documentos curtos cuja estrutura não produz naturalmente muitas sínteses;
- versões modificadas de um mesmo documento para testar hashes e reconstrução;
- projetos multidisciplinares compostos por documentos especializados, incluindo pares com convergência explícita, tensão conceitual, vocabulários diferentes e ausência real de relação.

### 12.3 Conjunto de perguntas

As perguntas devem ser anotadas por avaliadores independentes e separadas em:

- localização literal;
- navegação estrutural;
- síntese ampla;
- paráfrase conceitual;
- relação entre trechos;
- premissa contraditória à fonte;
- pergunta parcialmente suportada;
- pergunta completamente fora do acervo;
- pergunta ambígua;
- relação entre evidências de disciplinas distintas;
- síntese emergente parcialmente sustentada, com ao menos uma área sem evidência suficiente.

É importante incluir exemplos difíceis e negativos. Um sistema que só recebe perguntas respondíveis não pode demonstrar qualidade de recusa.

### 12.4 Métricas de recuperação

- Recall@k de evidências primárias relevantes;
- Precision@k;
- Mean Reciprocal Rank;
- nDCG;
- taxa de resolução correta de derivadas para primárias;
- cobertura estrutural de seções relevantes;
- duplicação de contexto;
- cobertura equilibrada dos documentos pertinentes em consultas multidisciplinares;
- taxa de exclusão indevida de uma disciplina relevante pelo limite global de contexto.

### 12.5 Métricas de resposta

- correção factual em relação à fonte;
- completude;
- precisão de citações;
- recall de citações necessárias;
- taxa de afirmações sem suporte;
- taxa de recusa correta;
- taxa de recusa indevida;
- qualidade da explicação em premissas contraditórias.

### 12.6 Métricas de interação

- validade dos participantes;
- validade dos trechos associados;
- precisão da reciprocidade em `simetry`;
- precisão da direção em `assimetry`;
- taxa de interações não sustentadas;
- concordância entre anotadores humanos;
- precisão da proveniência disciplinar dos participantes;
- taxa de sínteses multidisciplinares que extrapolam o conjunto citado.

### 12.7 Métricas operacionais

- latência por rota;
- número de chamadas externas por consulta;
- tokens de entrada e saída;
- custo por documento e por pergunta;
- tempo de construção das sínteses;
- tempo e espaço de indexação;
- taxa de falhas e retentativas.

### 12.8 Ablações

Para identificar a contribuição de cada mecanismo, devem ser executadas ablações removendo individualmente:

- sínteses derivadas;
- linhagem de derivação;
- roteamento por tipo;
- barreira de evidência;
- validação local de citações;
- detecção relacional;
- preservação de contexto hierárquico.

### 12.9 Reprodutibilidade

Cada experimento deve registrar versões do código, esquema, modelos configurados, dimensões de embedding, hashes dos documentos, parâmetros de recuperação, temperatura de geração e prompts aplicáveis. Dados sensíveis e credenciais nunca devem integrar o pacote de reprodução.

## 13. Observações operacionais preliminares

Testes manuais na instalação atual indicaram três comportamentos coerentes com o projeto:

1. perguntas apoiadas pela obra receberam respostas com baixa latência percebida e evidências visíveis;
2. perguntas sem referência recuperável no documento foram interrompidas antes de uma resposta documental;
3. inputs com premissas divergentes da obra foram explicados com base nas evidências recuperadas, sem aderência automática à premissa.

Essas observações servem como verificação funcional, não como resultado científico generalizável. Não houve, nesta etapa, amostragem controlada, avaliação cega, comparação estatística com baselines nem mensuração formal de viés.

## 14. Limitações

### 14.1 Dependência de embeddings

Na rota semântica, conceitos relevantes podem receber baixa similaridade e não entrar no conjunto candidato. Embeddings também podem aproximar passagens apenas superficialmente semelhantes.

### 14.2 Perda em sínteses

Evidências derivadas podem omitir exceções ou nuances. A resolução para fontes primárias reduz o impacto, mas não garante que todas as fontes necessárias sejam selecionadas.

### 14.3 Classificação do input

Uma detecção operacional incorreta pode escolher uma rota inferior. Perguntas híbridas, ambíguas ou muito curtas são especialmente desafiadoras.

### 14.4 Limites da validação local

Validar IDs e participantes impede referências inexistentes, mas não prova entailment. Uma frase pode citar a evidência correta e ainda interpretá-la inadequadamente. Avaliação semântica continua necessária.

### 14.5 Dependência do provedor de resposta

Mesmo com contexto válido, o provedor pode omitir aspectos, produzir estrutura inválida ou formular uma explicação imprecisa. O backend contém erros estruturais, não todos os erros de raciocínio.

### 14.6 Escopo de formatos

A implementação documentada cobre Markdown, JSON e XML. Outros formatos exigem parsers capazes de preservar estrutura e referências equivalentes.

### 14.7 Contexto conversacional curto e não persistente

O EVA vigente resolve perguntas anafóricas por meio de até três rodadas anteriores anexadas ao input. Esse mecanismo depende da capacidade do modelo de distinguir continuidade de mudança de assunto e não executa resolução linguística adicional. Como o input composto também participa da rota de recuperação, termos anteriores podem influenciar os candidatos; o limite curto e o descarte integral das rodadas mais antigas reduzem, mas não eliminam, essa possibilidade.

O transcript completo existe somente na memória da interface aberta. Não há continuidade entre dispositivos, abas, recarregamentos ou novas sessões, nem identidade persistente de conversa. Essa limitação preserva a distinção entre contexto de diálogo e memória documental.

### 14.8 Sem ontologia persistente

A ausência de grafo relacional reduz complexidade e contaminação por inferências, mas não atende diretamente casos que exigem travessias ontológicas persistentes ou análise global de redes.

### 14.9 Escopo de autorização

A instalação atual possui superadmin, usuários, sessões e autorização granular por projeto ou documento, mas não deve ser confundida com isolamento multitenant entre organizações. Ambientes compartilhados entre instituições exigirão fronteiras adicionais de tenant, administração delegada e testes de isolamento.

### 14.10 Cobertura multidisciplinar limitada pelo contexto

Um projeto pode conter mais documentos e evidências do que o limite global de uma consulta comporta. A intercalação favorece diversidade documental, mas não garante que todas as disciplinas pertinentes sejam representadas no contexto final. Uma síntese multidisciplinar pode ser incompleta mesmo quando cada afirmação apresentada possui citação válida.

## 15. Discussão

O principal compromisso do EVA é tornar explícita a fronteira entre documento e interpretação. Árvores, evidências primárias, evidências derivadas e linhagens pertencem à memória. Similaridade, contexto montado, resposta e interação pertencem ao evento de consulta.

Essa separação reduz a tentação de tratar toda saída de modelo como conhecimento. Ela também simplifica a evolução do sistema: novas estratégias de recuperação podem ser testadas sem migrar uma rede de relações inferidas, e novos provedores podem substituir os anteriores sem alterar a identidade das fontes.

O papel das evidências derivadas é particularmente importante. Elas oferecem abstração sem reivindicar autoridade literal. Em vez de escolher entre “somente trechos” e “somente resumos”, o EVA permite que o resumo localize e que o trecho fundamente.

O modelo de interação segue a mesma disciplina. Simetry e assimetry oferecem estrutura suficiente para indicar reciprocidade ou direção sem converter o encontro contextual em uma ontologia. A ausência de pesos não é ausência de análise; é uma recusa em apresentar uma estimativa implícita como força objetiva.

Em projetos multidisciplinares, essa disciplina permite aproximar vocabulários e estruturas conceituais de áreas distintas sem dissolver suas fontes. A confiabilidade resulta da possibilidade de auditar quais evidências participaram, quais relações foram aceitas e quais lacunas foram declaradas. Ela não decorre de uma pretensão de completude nem transforma a síntese emergente em conhecimento persistente.

O custo dessa prudência é real. O sistema pode recusar perguntas que um modelo geral responderia corretamente por conhecimento paramétrico. Pode também deixar de capturar relações persistentes úteis a certos domínios. O EVA aceita esse custo porque seu objetivo não é responder a qualquer pergunta, mas responder de modo compatível com o acervo declarado.

### 15.1 Fluxo compacto e separação de responsabilidades

Embora o EVA contenha ingestão, síntese, embeddings, recuperação e geração, sua cadeia epistemológica pode ser reduzida a cinco operações:

```text
DOCUMENTO → EVIDÊNCIAS → LOCALIZAÇÃO → VALIDAÇÃO → RESPOSTA
```

Essa compactação não elimina as etapas técnicas; explicita a responsabilidade dominante de cada uma. O documento estabelece a origem, as evidências preservam conteúdo e linhagem, a recuperação localiza candidatos, a aplicação valida o conjunto utilizável e o modelo formula a resposta. Em termos funcionais:

```text
síntese localiza
fonte fundamenta
aplicação valida
modelo comunica
```

Consequentemente, o modelo de síntese não cria a fonte, o modelo de embedding não determina verdade e o modelo de resposta não decide o que pode ser persistido. Essa separação permite empregar capacidades probabilísticas sem transferir ao provedor a autoridade sobre a memória documental.

A simplicidade do fluxo é uma propriedade de projeto, não uma evidência de superioridade. Seu valor deve ser avaliado experimentalmente: menos entidades persistentes podem reduzir contaminação e facilitar auditoria, mas a arquitetura ainda depende da qualidade da recuperação, da fidelidade das sínteses e da interpretação produzida na resposta. Testes futuros devem verificar se essa separação preserva significado, mantém estabilidade diante de variações estilísticas e contraditórias do input e declara limitações sem evasão.

## 16. Conclusão

O EVA organiza memória documental como um conjunto verificável de evidências primárias e derivadas sobre uma árvore estrutural preservada. Sínteses possuem linhagem, embeddings representam unidades semanticamente organizadas e consultas escolhem rotas hierárquicas ou semânticas conforme sua forma operacional.

O sistema impede geração documental quando não há evidência primária recuperada, valida localmente citações e participantes e trata relações cognitivas como interações transitórias de simetry ou assimetry. O antigo conceito de Cnode deixa de ser uma entidade persistente e passa a designar, quando necessário, apenas o fenômeno contextual da interação.

Em escopos multidisciplinares, essa transitoriedade permite articular evidências especializadas de documentos diferentes sem criar contaminação cumulativa no banco. O resultado pode revelar uma síntese conceitual nova para a consulta, mas permanece interpretação rastreável e limitada, nunca evidência automática ou verdade incorporada ao acervo.

Essa arquitetura não elimina os riscos de recuperação e geração. Ela os torna mais observáveis e auditáveis. Sua contribuição proposta é uma disciplina de memória: preservar a fonte, registrar toda derivação, restringir o que pode persistir e declarar com clareza quando o documento não sustenta uma resposta.

## Referências

[1] Lewis, P. et al. (2020). *Retrieval-Augmented Generation for Knowledge-Intensive NLP Tasks*. Advances in Neural Information Processing Systems 33. [Publicação oficial](https://proceedings.neurips.cc/paper/2020/hash/6b493230205f780e1bc26945df7481e5-Abstract.html) · [arXiv:2005.11401](https://arxiv.org/abs/2005.11401)

[2] Karpukhin, V. et al. (2020). *Dense Passage Retrieval for Open-Domain Question Answering*. Proceedings of the 2020 Conference on Empirical Methods in Natural Language Processing, p. 6769–6781. [ACL Anthology](https://aclanthology.org/2020.emnlp-main.550/) · [arXiv:2004.04906](https://arxiv.org/abs/2004.04906)

[3] Asai, A. et al. (2024). *Self-RAG: Learning to Retrieve, Generate, and Critique through Self-Reflection*. International Conference on Learning Representations. [arXiv:2310.11511](https://arxiv.org/abs/2310.11511)

[4] Gao, T. et al. (2023). *Enabling Large Language Models to Generate Text with Citations*. Proceedings of the 2023 Conference on Empirical Methods in Natural Language Processing, p. 6465–6488. [ACL Anthology](https://aclanthology.org/2023.emnlp-main.398/) · [arXiv:2305.14627](https://arxiv.org/abs/2305.14627)

[5] Liu, N. F. et al. (2024). *Lost in the Middle: How Language Models Use Long Contexts*. Transactions of the Association for Computational Linguistics, 12, p. 157–173. [ACL Anthology](https://aclanthology.org/2024.tacl-1.9/) · [arXiv:2307.03172](https://arxiv.org/abs/2307.03172)

[6] Chen, T. et al. (2024). *Dense X Retrieval: What Retrieval Granularity Should We Use?* Proceedings of the 2024 Conference on Empirical Methods in Natural Language Processing, p. 15159–15177. [ACL Anthology](https://aclanthology.org/2024.emnlp-main.845/) · [arXiv:2312.06648](https://arxiv.org/abs/2312.06648)

[7] Edge, D. et al. (2024). *From Local to Global: A Graph RAG Approach to Query-Focused Summarization*. arXiv:2404.16130. [Acesso](https://arxiv.org/abs/2404.16130)

[8] Wang, L. et al. (2022). *Text Embeddings by Weakly-Supervised Contrastive Pre-training*. arXiv:2212.03533. [Acesso](https://arxiv.org/abs/2212.03533)

[9] Günther, M. et al. (2023). *Jina Embeddings 2: 8192-Token General-Purpose Text Embeddings for Long Documents*. arXiv:2310.19923. [Acesso](https://arxiv.org/abs/2310.19923)
