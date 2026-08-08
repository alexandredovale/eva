# EVA --- Marketing, Aplicações Institucionais e Hipóteses de Mercado

## 1. Escopo deste documento

Este documento organiza hipóteses de aplicação, posicionamento e
evolução comercial do EVA a partir das capacidades já documentadas no
repositório.

Ele não altera o Core, não define requisitos obrigatórios de
implementação e não declara superioridade comercial ou técnica ainda não
demonstrada por benchmark.

Seu objetivo é permitir triagem posterior de:

-   mercados institucionais;
-   problemas documentais de alto valor;
-   aplicações compatíveis com a arquitetura;
-   capacidades de produto que podem evoluir por módulos;
-   hipóteses comerciais que dependem de validação;
-   diferenciais que podem ser demonstrados experimentalmente.

O EVA está em construção e evolução. As cinco fases fundamentais do
projeto estão documentadas como concluídas, assim como o primeiro
upgrade arquitetural e a incorporação do Context Intelligence Engine. A
revalidação comparativa de qualidade, estabilidade, latência e tokens
permanece pendente no roadmap.

------------------------------------------------------------------------

# 2. Base arquitetural já disponível

O posicionamento comercial deve partir das capacidades reais do EVA, não
de promessas genéricas de IA.

A documentação atual sustenta os seguintes elementos:

-   memória cognitiva documental verificável;
-   preservação da hierarquia documental;
-   evidências primárias literais;
-   evidências derivadas hierárquicas;
-   linhagem entre derivações e fontes;
-   embeddings de unidades semanticamente organizadas;
-   recuperação adaptativa conforme a natureza da consulta;
-   resolução de sínteses até fontes primárias;
-   Context Intelligence Engine;
-   seleção estatística local e determinística;
-   média, desvio padrão e coeficiente de variação;
-   regiões de núcleo, convergência e descarte;
-   respostas sustentadas por evidências citadas;
-   interações `simetry` e `assimetry` transitórias;
-   neutralidade de fornecedor de IA;
-   configuração white label;
-   autenticação, auditoria e métricas operacionais;
-   módulos conectores independentes do Core;
-   perfis de resposta por projeto;
-   contenção computacional;
-   reutilização de sínteses e embeddings;
-   encerramento sem geração quando nenhuma evidência válida é
    recuperada.

Essas propriedades permitem pensar o EVA não apenas como interface de
chat, mas como uma camada intermediária entre:

``` text
documentação institucional
↓
organização evidencial
↓
recuperação e seleção
↓
contexto documental validado
↓
modelo generativo
↓
resposta rastreável
```

------------------------------------------------------------------------

# 3. Posicionamento central a investigar

Uma hipótese de posicionamento comercial coerente com a arquitetura é:

> **EVA como infraestrutura de IA para ambientes em que respostas
> precisam permanecer subordinadas à autoridade documental.**

Isso é diferente de vender:

``` text
chatbot com documentos
```

O objeto de valor passa a ser:

``` text
documentação oficial
+
recuperação contextual
+
evidência
+
linhagem
+
resposta
+
rastreabilidade
```

Outra formulação possível:

> **Camada de evidência documental entre o acervo institucional e a
> LLM.**

Esse posicionamento é especialmente relevante porque o Core é neutro
quanto a fornecedor, modelo, marca e ramo de conhecimento.

------------------------------------------------------------------------

# 4. Critério de atratividade institucional

O valor potencial do EVA tende a crescer quando aumenta o custo de uma
resposta documental incorreta, incompleta ou sem fonte demonstrável.

Podemos organizar os mercados por três níveis.

## Nível 1 --- acesso ao conhecimento

Problema:

> localizar e explicar informação existente em um acervo.

Exemplos:

-   bases internas;
-   bibliotecas;
-   treinamento;
-   FAQ documental;
-   manuais;
-   documentação administrativa.

Valor dominante:

``` text
velocidade de acesso
```

É um mercado amplo, mas altamente atendido por soluções RAG
convencionais.

------------------------------------------------------------------------

## Nível 2 --- conhecimento com evidência

Problema:

> responder e demonstrar quais documentos sustentam a resposta.

Valor dominante:

``` text
informação
+
fonte
+
proveniência
+
contexto
```

Aplicações potenciais:

-   ensino superior;
-   pesquisa;
-   jurídico;
-   engenharia;
-   documentação técnica;
-   normas;
-   editoras;
-   acervos especializados.

Este nível está mais alinhado à arquitetura evidencial do EVA.

------------------------------------------------------------------------

## Nível 3 --- conhecimento institucional auditável

Problema:

> produzir uma resposta documental cuja origem possa ser reconstruída e
> examinada.

Valor dominante:

``` text
resposta
+
evidência
+
linhagem
+
governança
+
auditabilidade
```

Aplicações potenciais:

-   governo;
-   compliance;
-   instituições reguladas;
-   jurídico institucional;
-   seguros;
-   bancos;
-   utilities;
-   grandes corporações;
-   ambientes técnicos de alto risco.

Este nível deve receber atenção prioritária na investigação comercial.

------------------------------------------------------------------------

# 5. Educação

## Problema

Instituições educacionais acumulam:

-   livros;
-   apostilas;
-   regulamentos;
-   planos pedagógicos;
-   materiais docentes;
-   artigos;
-   avaliações;
-   bibliografias oficiais.

LLMs gerais podem responder usando conhecimento externo que não
corresponde necessariamente ao material autorizado pela instituição.

## Aplicação EVA

``` text
bibliografia oficial
↓
EVA
↓
consulta do aluno/professor
↓
evidências da documentação autorizada
↓
resposta fundamentada
```

## Possíveis produtos/módulos

-   tutor documental;
-   assistente de leitura;
-   ambiente de pesquisa orientada;
-   apoio à elaboração de questões;
-   verificação de respostas contra material oficial;
-   trilhas de aprendizagem baseadas em evidências;
-   painel docente de interações;
-   exploração de relações entre conceitos presentes nas obras;
-   formação continuada baseada em acervo institucional.

## Estruturas-alvo

-   universidades;
-   faculdades;
-   escolas;
-   cursos livres;
-   instituições confessionais;
-   centros de formação;
-   educação corporativa.

## Hipótese de valor

A instituição mantém a IA subordinada à bibliografia e às fontes que
reconhece como oficiais.

------------------------------------------------------------------------

# 6. Jurídico

## Problema

O domínio jurídico possui grande dependência de:

-   contratos;
-   processos;
-   pareceres;
-   legislação;
-   regulamentos;
-   jurisprudência;
-   políticas internas;
-   documentos probatórios.

Uma resposta linguisticamente convincente sem sustentação documental
pode ter pouco valor operacional.

## Aplicação EVA

``` text
corpus jurídico autorizado
↓
estrutura documental
↓
recuperação evidencial
↓
fontes primárias
↓
resposta com referências
```

## Possíveis produtos/módulos

-   pesquisa interna de processos;
-   consulta contratual;
-   comparação documental;
-   localização de cláusulas;
-   consulta a políticas jurídicas internas;
-   apoio a due diligence documental;
-   exploração de relações explícitas entre documentos;
-   assistente de conhecimento para departamentos jurídicos.

## Estruturas-alvo

-   escritórios;
-   departamentos jurídicos;
-   tribunais;
-   procuradorias;
-   departamentos de contratos;
-   consultorias.

## Limite

O EVA deve ser posicionado como infraestrutura de recuperação e
evidência, não como substituto de decisão jurídica profissional.

------------------------------------------------------------------------

# 7. Governo e administração pública

## Problema

Órgãos públicos operam sobre grandes volumes de:

-   leis;
-   decretos;
-   portarias;
-   regulamentos;
-   manuais;
-   processos;
-   pareceres;
-   atas;
-   políticas públicas;
-   documentação administrativa.

## Aplicação EVA

Criar memória documental consultável em que a resposta possa retornar às
fontes administrativas que a sustentam.

## Possíveis produtos/módulos

-   consulta normativa interna;
-   assistente para servidores;
-   consulta a procedimentos administrativos;
-   exploração de acervos públicos;
-   apoio documental a atendimento;
-   recuperação de precedentes administrativos;
-   consulta a políticas e regulamentos.

## Estruturas-alvo

-   prefeituras;
-   secretarias;
-   autarquias;
-   agências;
-   universidades públicas;
-   órgãos legislativos;
-   órgãos administrativos.

## Hipótese de valor

Rastreabilidade pode possuir valor superior à simples fluência da
resposta.

------------------------------------------------------------------------

# 8. Compliance e governança corporativa

## Problema

Empresas precisam relacionar:

-   políticas internas;
-   códigos de conduta;
-   normas;
-   contratos;
-   procedimentos;
-   requisitos regulatórios;
-   documentação de auditoria.

## Aplicação EVA

``` text
políticas + normas + procedimentos
↓
memória documental verificável
↓
consulta
↓
fontes institucionais
```

## Possíveis produtos/módulos

-   consulta de compliance;
-   políticas internas;
-   onboarding regulatório;
-   apoio a auditoria documental;
-   consulta a procedimentos;
-   rastreamento da origem documental de respostas.

## Estruturas-alvo

-   bancos;
-   seguradoras;
-   fintechs;
-   empresas reguladas;
-   multinacionais;
-   auditorias;
-   consultorias.

------------------------------------------------------------------------

# 9. Saúde --- documentação institucional

## Escopo seguro

A aplicação comercial relevante não deve ser apresentada inicialmente
como sistema autônomo de diagnóstico ou decisão clínica.

O espaço compatível com a arquitetura é documental.

## Fontes

-   protocolos;
-   procedimentos;
-   manuais;
-   documentação administrativa;
-   normas internas;
-   documentação científica selecionada;
-   políticas institucionais.

## Possíveis aplicações

-   consulta de protocolos;
-   treinamento;
-   pesquisa documental interna;
-   apoio à localização de procedimentos;
-   consulta administrativa;
-   organização de literatura institucional.

## Estruturas-alvo

-   hospitais;
-   clínicas;
-   universidades;
-   centros de pesquisa;
-   redes de saúde.

Qualquer evolução para suporte clínico exige validação, governança e
requisitos regulatórios específicos.

------------------------------------------------------------------------

# 10. Engenharia, indústria e manutenção

## Problema

Ambientes industriais possuem documentação fragmentada:

-   manuais;
-   procedimentos;
-   especificações;
-   relatórios de falha;
-   históricos;
-   normas;
-   documentação de equipamentos;
-   instruções de manutenção.

## Aplicação EVA

A estrutura hierárquica e a resolução até fontes primárias podem
permitir consulta técnica sem reduzir o acervo a blocos textuais
isolados.

## Possíveis produtos/módulos

-   consulta a manuais;
-   manutenção assistida documentalmente;
-   pesquisa de incidentes anteriores;
-   consulta de procedimentos;
-   treinamento técnico;
-   comparação de especificações;
-   apoio documental à engenharia.

## Estruturas-alvo

-   indústrias;
-   fabricantes;
-   manutenção;
-   engenharia;
-   mineração;
-   construção;
-   logística;
-   utilities.

------------------------------------------------------------------------

# 11. Energia e utilities

Este é o setor institucional mais explicitamente explorado na
documentação atual.

O repositório já identifica aplicações para:

-   procedimentos de contingência e recuperação;
-   manuais e históricos técnicos de ativos;
-   relatórios de falha e manutenção;
-   normas;
-   contratos;
-   estudos de capacidade;
-   planos de desligamento;
-   religamento;
-   resposta a incidentes.

O EVA permanece consultivo e separado de sistemas operacionais de
controle.

## Evoluções comerciais possíveis

-   memória técnica de ativos;
-   assistente de procedimentos;
-   documentação de incidentes;
-   consulta regulatória;
-   treinamento operacional;
-   suporte documental a equipes de campo;
-   recuperação de históricos técnicos.

## Estruturas-alvo

-   distribuidoras;
-   geradoras;
-   transmissoras;
-   indústrias;
-   operadores;
-   órgãos reguladores.

------------------------------------------------------------------------

# 12. Seguros

## Fontes

-   apólices;
-   condições gerais;
-   normas;
-   procedimentos;
-   documentação de sinistros;
-   manuais internos;
-   contratos.

## Possíveis aplicações

-   consulta de cobertura documental;
-   apoio interno a análise de processos;
-   treinamento;
-   localização de cláusulas;
-   consulta de procedimentos;
-   comparação de documentação.

## Hipótese de valor

Reduzir a distância entre resposta operacional e documento contratual
que a fundamenta.

------------------------------------------------------------------------

# 13. Bancos e serviços financeiros

## Fontes

-   políticas;
-   procedimentos;
-   contratos;
-   normas regulatórias;
-   documentação de produtos;
-   manuais;
-   regras internas.

## Possíveis aplicações

-   assistente interno;
-   consulta regulatória;
-   compliance;
-   treinamento;
-   políticas de produto;
-   apoio documental a atendimento especializado.

O valor comercial dependerá fortemente de segurança, controle de acesso,
isolamento de dados e validação regulatória.

------------------------------------------------------------------------

# 14. Pesquisa científica

## Problema

Grandes coleções científicas exigem localizar não apenas documentos
semelhantes, mas relações entre conceitos e fontes.

## Possíveis aplicações

-   acervos temáticos;
-   revisão documental assistida;
-   exploração de literatura;
-   identificação de fontes relacionadas;
-   memória de grupos de pesquisa;
-   organização de documentação experimental.

## Estruturas-alvo

-   universidades;
-   laboratórios;
-   centros de pesquisa;
-   think tanks;
-   instituições científicas.

------------------------------------------------------------------------

# 15. Bibliotecas, arquivos e patrimônio documental

## Fontes

-   livros;
-   coleções;
-   documentos históricos;
-   arquivos institucionais;
-   periódicos;
-   correspondências;
-   catálogos.

## Possíveis aplicações

-   pesquisa semântica de acervo;
-   exploração temática;
-   navegação entre fontes;
-   atendimento assistido;
-   descoberta documental;
-   interfaces públicas de consulta.

A preservação da hierarquia documental é particularmente relevante para
coleções em que posição e estrutura possuem significado.

------------------------------------------------------------------------

# 16. Editoras e detentores de conteúdo

## Aplicações

-   consulta autorizada a coleções;
-   produtos de leitura assistida;
-   pesquisa interna;
-   exploração de catálogos;
-   ambientes de estudo vinculados às obras;
-   serviços de conhecimento baseados em acervos licenciados.

## Modelo potencial

O EVA pode funcionar como infraestrutura para transformar um catálogo
editorial em serviço documental interativo sem desvincular respostas das
obras de origem.

------------------------------------------------------------------------

# 17. Instituições religiosas, filosóficas e culturais

## Fontes

-   obras doutrinárias;
-   comentários;
-   documentos históricos;
-   regulamentos;
-   materiais de formação;
-   bibliotecas institucionais.

## Possíveis aplicações

-   estudo orientado;
-   pesquisa temática;
-   formação;
-   consulta a obras oficiais;
-   exploração de relações documentais;
-   preservação de fidelidade às fontes.

Esse mercado pode ser relevante para instituições em que interpretação
precisa ser claramente separada do texto documental de origem.

------------------------------------------------------------------------

# 18. Grandes corporações --- memória institucional

## Problema

Organizações acumulam conhecimento em:

-   manuais;
-   atas;
-   políticas;
-   projetos;
-   documentação técnica;
-   procedimentos;
-   relatórios;
-   contratos;
-   bases de treinamento.

A rotatividade de equipes transforma conhecimento documental disperso em
custo operacional.

## Aplicação EVA

``` text
memória institucional
↓
EVA
↓
consulta por diferentes equipes
↓
resposta baseada nas fontes internas
```

## Possíveis módulos

-   RH;
-   jurídico;
-   engenharia;
-   suporte;
-   operações;
-   compliance;
-   treinamento;
-   gestão de conhecimento.

------------------------------------------------------------------------

# 19. Consultorias e auditorias

## Aplicação

O EVA pode ser usado como camada documental para organizar acervos de
projetos ou clientes.

## Possíveis usos

-   consulta a evidências;
-   revisão documental;
-   políticas;
-   normas;
-   relatórios;
-   contratos;
-   rastreabilidade de conclusões.

O isolamento por projeto já existente na arquitetura pode ser
comercialmente relevante para este tipo de operação.

------------------------------------------------------------------------

# 20. White label como vetor comercial

O repositório já possui configuração white label e neutralidade de
fornecedor.

Isso permite explorar modelos B2B nos quais o EVA não precisa aparecer
como marca principal.

Exemplos:

``` text
Universidade X
Powered by EVA

Escritório Y
Knowledge Platform

Empresa Z
Technical Evidence Assistant
```

A instituição pode controlar:

-   nome;
-   descrição;
-   cores;
-   logotipo;
-   projetos;
-   perfis de resposta;
-   módulos;
-   fornecedor de IA.

Isso cria potencial para:

-   licenciamento;
-   implantação institucional;
-   produto embarcado;
-   soluções verticais;
-   parceiros integradores.

------------------------------------------------------------------------

# 21. Módulos como estratégia de verticalização

A arquitetura de módulos é uma das capacidades comerciais mais
importantes já existentes.

O Core conhece apenas o contrato genérico.

Portanto:

``` text
EVA Core
│
├── módulo educacional
├── módulo jurídico
├── módulo compliance
├── módulo técnico
├── módulo pesquisa
└── módulo institucional
```

podem evoluir sem transformar regras verticais em regras cognitivas do
Core.

Princípio comercial:

> **verticalizar o produto sem verticalizar o núcleo.**

Isso preserva a possibilidade de um único Evidence Algorithm atender
múltiplos mercados.

------------------------------------------------------------------------

# 22. Perfis de resposta como governança institucional

Projetos podem possuir perfis complementares de resposta.

Essa capacidade pode ser explorada comercialmente como governança
contextual.

Exemplos futuros:

``` text
Projeto Jurídico
→ comportamento documental jurídico

Projeto Treinamento
→ comportamento pedagógico

Projeto Engenharia
→ terminologia e regras institucionais

Projeto Compliance
→ política interna específica
```

Importante:

o perfil não deve substituir a evidência nem criar autoridade
inexistente no documento.

Ele governa comportamento de resposta, não verdade documental.

------------------------------------------------------------------------

# 23. Neutralidade de fornecedor como argumento B2B

O EVA separa capacidade de fornecedor.

Isso permite que uma instituição substitua:

-   modelo;
-   endpoint;
-   provedor;
-   infraestrutura;

sem reconstruir necessariamente sua memória documental.

Hipótese de valor:

``` text
documentação e arquitetura evidencial
        permanecem
              │
              ▼
        modelo pode mudar
```

Isso reduz dependência conceitual de um único fornecedor de LLM.

Pode ser relevante para:

-   procurement;
-   soberania tecnológica;
-   redução de custos;
-   requisitos regionais;
-   modelos locais;
-   políticas de privacidade.

------------------------------------------------------------------------

# 24. Soberania documental

Um posicionamento a investigar é o conceito de **soberania documental**.

Definição proposta:

> capacidade de uma instituição estabelecer que respostas de IA sobre
> seu domínio sejam fundamentadas prioritariamente ou exclusivamente em
> sua documentação autorizada e rastreável.

Isso separa:

``` text
conhecimento geral da LLM
```

de:

``` text
verdade documental institucional
```

A aplicação pode ser particularmente relevante em:

-   governo;
-   universidades;
-   empresas reguladas;
-   jurídico;
-   engenharia;
-   instituições científicas;
-   organizações com normas próprias.

O termo deve ser validado comercial e juridicamente antes de ser adotado
como posicionamento oficial.

------------------------------------------------------------------------

# 25. Produto de infraestrutura, não apenas interface

A interface atual é um produto funcional importante, mas o valor
comercial do EVA pode ultrapassá-la.

Arquitetura potencial:

``` text
              ACERVO
                │
                ▼
          Evidence Algorithm
                │
                ▼
      contexto documental validado
                │
       ┌────────┼────────┐
       ▼        ▼        ▼
     Chat     Sistema    API
             interno
```

O EVA pode ser explorado como:

-   aplicação pronta;
-   backend documental;
-   API;
-   engine embutido;
-   camada de evidência;
-   componente white label.

------------------------------------------------------------------------

# 26. Venda por problema, não por tecnologia

Evitar posicionamentos genéricos como:

``` text
IA avançada
RAG inteligente
chatbot poderoso
```

Eles não demonstram valor institucional.

Estruturar a comunicação a partir de problemas concretos:

### Problema

"Não sabemos de onde a IA tirou essa resposta."

### EVA

"Resposta vinculada às evidências recuperadas."

------------------------------------------------------------------------

### Problema

"Nossa IA mistura conhecimento externo com política interna."

### EVA

"Consulta subordinada ao acervo selecionado."

------------------------------------------------------------------------

### Problema

"Temos milhares de documentos e localizar fundamento leva tempo."

### EVA

"Recuperação semântica sobre memória documental organizada."

------------------------------------------------------------------------

### Problema

"Trocar o fornecedor de IA exige reconstruir nossa solução."

### EVA

"Core documental neutro quanto ao fornecedor."

------------------------------------------------------------------------

# 27. Mercados prioritários para triagem

A seguinte matriz não representa decisão comercial. Serve para
investigação.

  ----------------------------------------------------------------------------
  Mercado       Dor documental Necessidade de   Compatibilidade    Dependência
                                    evidência             atual    regulatória
  ------------- -------------- -------------- ----------------- --------------
  Educação                Alta           Alta              Alta          Média

  Jurídico          Muito alta     Muito alta              Alta           Alta

  Compliance        Muito alta     Muito alta              Alta           Alta

  Engenharia              Alta     Muito alta              Alta     Média/Alta

  Energia           Muito alta     Muito alta              Alta           Alta

  Governo           Muito alta     Muito alta              Alta           Alta

  Pesquisa                Alta     Muito alta              Alta          Média

  Bibliotecas             Alta           Alta              Alta          Baixa

  Editoras          Média/Alta           Alta              Alta          Média

  Grandes           Muito alta           Alta              Alta       Variável
  corporações

  Seguros           Muito alta     Muito alta        Média/Alta           Alta

  Bancos            Muito alta     Muito alta        Média/Alta     Muito alta

  Saúde             Muito alta     Muito alta        Média/Alta     Muito alta
  documental
  ----------------------------------------------------------------------------

------------------------------------------------------------------------

# 28. Critério de priorização comercial

Antes de desenvolver um módulo vertical, avaliar:

1.  volume documental;
2.  custo atual para localizar informação;
3.  frequência de consulta;
4.  custo de uma resposta incorreta;
5.  necessidade de demonstrar fonte;
6.  estabilidade do acervo;
7.  possibilidade de reutilização da construção cognitiva;
8.  requisitos de segurança;
9.  requisitos regulatórios;
10. necessidade de integração;
11. disponibilidade de corpus para benchmark;
12. existência de comprador institucional identificável.

Uma vertical é especialmente atraente quando combina:

``` text
alto volume documental
+
alta frequência de consulta
+
alto custo do erro
+
alta necessidade de evidência
```

------------------------------------------------------------------------

# 29. Benchmarks como ativo comercial

O roadmap já reconhece que a revalidação comparativa permanece pendente.

Marketing não deve antecipar conclusões científicas.

A estratégia deve ser:

``` text
hipótese arquitetural
↓
benchmark
↓
resultado reproduzível
↓
afirmação comercial
```

Comparações relevantes:

-   RAG vetorial convencional;
-   contexto longo;
-   GraphRAG;
-   RAG agente;
-   outras arquiteturas comparáveis.

Métricas comerciais/técnicas importantes:

-   precisão;
-   recall;
-   validade das citações;
-   taxa de recusa correta;
-   evidências recuperadas;
-   contexto enviado;
-   tokens;
-   latência;
-   chamadas externas;
-   custo por consulta;
-   energia por consulta, quando medida.

------------------------------------------------------------------------

# 30. Claims que podem ser usados agora

Com base na documentação atual, podem ser exploradas formulações
factuais como:

-   arquitetura de memória documental verificável;
-   preservação de hierarquia documental;
-   evidências primárias e derivadas rastreáveis;
-   recuperação semântica adaptativa;
-   resolução de linhagem até fontes primárias;
-   seleção estatística local antes da geração;
-   neutralidade de fornecedor;
-   suporte white label;
-   módulos independentes do Core;
-   auditoria operacional sanitizada;
-   reutilização de construção cognitiva;
-   contenção de operações generativas desnecessárias.

------------------------------------------------------------------------

# 31. Claims que dependem de benchmark

Não declarar como fato antes da validação:

-   "mais preciso que qualquer RAG";
-   "melhor RAG do mercado";
-   "elimina alucinações";
-   "garante respostas corretas";
-   "reduz energia";
-   "é mais barato que todas as alternativas";
-   "possui recall superior";
-   "possui menor latência";
-   "é estado da arte";
-   "é superior a GraphRAG";
-   "resolve definitivamente o problema de RAG".

Essas afirmações precisam de evidência experimental adequada.

------------------------------------------------------------------------

# 32. Diferencial comercial a validar

A hipótese mais importante é que o EVA possa apresentar simultaneamente:

\[ `\text{alto recall}`{=tex} +
`\text{alta precisão evidencial}`{=tex} +
`\text{rastreabilidade}`{=tex} + `\text{contexto reduzido}`{=tex} \]

Se benchmarks sustentarem essa combinação, o posicionamento comercial
pode avançar de:

``` text
sistema documental com IA
```

para:

``` text
infraestrutura de evidência para IA institucional
```

------------------------------------------------------------------------

# 33. Modelo de adoção progressiva

Uma implantação institucional pode ser estruturada em estágios.

## Estágio 1 --- corpus controlado

Uma coleção documental limitada.

Objetivo:

validar recuperação e resposta.

## Estágio 2 --- departamento

Aplicação em uma unidade real.

Objetivo:

medir uso, precisão e valor operacional.

## Estágio 3 --- múltiplos projetos

Diferentes acervos e perfis.

Objetivo:

validar governança e isolamento.

## Estágio 4 --- módulos verticais

Adicionar capacidades específicas sem alterar o Core.

## Estágio 5 --- infraestrutura institucional

EVA passa a atender diferentes interfaces, equipes ou aplicações sobre
uma memória documental governada.

------------------------------------------------------------------------

# 34. Formas potenciais de comercialização

Sem definir ainda modelo financeiro, a arquitetura permite investigar:

-   licença institucional;
-   implantação dedicada;
-   SaaS;
-   white label;
-   API por consumo;
-   instalação privada;
-   integração corporativa;
-   módulos pagos;
-   suporte e governança;
-   serviços de processamento e organização documental.

A escolha depende principalmente do mercado vertical e dos requisitos de
dados.

------------------------------------------------------------------------

# 35. O que ainda não aparece como eixo de marketing no repositório

A documentação atual é predominantemente:

-   arquitetural;
-   técnica;
-   operacional;
-   científica;
-   de produto;
-   validação;
-   sustentabilidade.

O repositório já contém aplicações setoriais no documento de
sustentabilidade energética, especialmente para o setor energético, mas
não apresenta uma estratégia ampla de mercados institucionais.

Os seguintes eixos podem ser desenvolvidos neste documento sem alterar a
definição técnica do EVA:

1.  infraestrutura de evidência;
2.  soberania documental;
3.  autoridade documental institucional;
4.  aplicações por vertical;
5.  custo institucional do erro;
6.  auditabilidade como valor econômico;
7.  neutralidade de fornecedor como valor de procurement;
8.  white label como estratégia B2B;
9.  módulos como estratégia de verticalização;
10. memória institucional;
11. produto API/engine além do chat;
12. benchmark como ativo comercial;
13. implantação progressiva;
14. modelos de comercialização;
15. critérios objetivos para priorizar mercados.

------------------------------------------------------------------------

# 36. Itens para triagem

Classificar cada item abaixo posteriormente como:

``` text
DESCARTAR
INVESTIGAR
VALIDAR
PROTOTIPAR
INCORPORAR
```

Itens:

-   Educação
-   Jurídico
-   Governo
-   Compliance
-   Saúde documental
-   Engenharia
-   Indústria
-   Energia
-   Seguros
-   Bancos
-   Pesquisa científica
-   Bibliotecas
-   Editoras
-   Instituições religiosas e culturais
-   Grandes corporações
-   Consultorias
-   Auditorias
-   White label B2B
-   API institucional
-   Engine embarcado
-   Soberania documental
-   Autoridade documental
-   Módulos verticais
-   Perfis de governança
-   Implantação privada
-   Benchmark comercial
-   Métricas de custo
-   Métricas de energia
-   Licenciamento institucional

------------------------------------------------------------------------

# 37. Princípio final

O marketing do EVA não deve ser construído sobre a afirmação:

> "Nossa IA sabe mais."

A arquitetura aponta para uma proposição diferente:

> **O EVA organiza o caminho entre uma pergunta e as evidências
> documentais autorizadas que podem sustentar sua resposta.**

Se benchmarks futuros demonstrarem vantagem significativa sobre
arquiteturas comparáveis, o valor comercial estará menos na fluência da
LLM e mais na capacidade de transformar documentação institucional em
uma memória consultável, rastreável e governável.

A hipótese comercial central passa a ser:

\[ `\boxed{
\text{EVA}
=
\text{infraestrutura de evidência para conhecimento institucional}
}`{=tex} \]

Essa hipótese deve ser validada mercado por mercado, preservando a regra
já presente no desenvolvimento do sistema:

``` text
arquitetura primeiro
↓
evidência experimental
↓
afirmação
```
