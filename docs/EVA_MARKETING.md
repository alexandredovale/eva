# EVA — Visão de Produto, Aplicações de Mercado e Impacto

**Versão documental:** 8.0.2  
**Edição equivalente em inglês:** [`en/EVA_MARKETING.md`](en/EVA_MARKETING.md)

## 1. Resumo executivo

O EVA é uma plataforma de IA documental criada para ambientes em que não basta produzir uma resposta convincente: é necessário mostrar quais fontes autorizadas sustentam o que foi respondido.

O sistema transforma documentos estruturados em uma memória consultável de evidências primárias, controla quais projetos e obras cada usuário pode acessar, seleciona contexto conforme a natureza da pergunta e valida as citações antes de entregar a resposta.

> **O EVA transforma documentação institucional em respostas verificáveis, mantendo a fonte como autoridade e a decisão sob governança humana.**

O produto público atual possui dois níveis:

1. **Core horizontal:** ingestão, memória documental, consulta, permissões, administração, auditoria operacional, API, fila e identidade white label.
2. **Extensão modular comprovada:** runtime independente e o módulo público `com.eva.explorer`, que reutiliza o Core em um fluxo educacional sem inserir regras pedagógicas no núcleo.

O principal ativo não é apenas um chat com arquivos. É a combinação de uma infraestrutura documental reutilizável com contratos que preservam procedência, escopo, validação e extensibilidade.

Este documento distingue:

- o que está presente na distribuição pública;
- impactos que decorrem diretamente do comportamento implementado;
- resultados econômicos, educacionais ou energéticos que ainda exigem medição de campo.

## 2. O problema que o EVA resolve

Organizações acumulam conhecimento em políticas, manuais, normas, livros, contratos, procedimentos, relatórios e materiais de formação. Esse conhecimento costuma permanecer fragmentado entre arquivos e equipes.

Ferramentas generativas ajudam no acesso, mas introduzem riscos quando:

- misturam conhecimento externo com regras internas;
- respondem sem indicar a origem da afirmação;
- citam fontes que não integraram o contexto recuperado;
- retiram trechos da estrutura em que foram publicados;
- expõem documentos fora do escopo autorizado;
- tratam uma saída de IA como decisão final;
- reenviam grandes volumes documentais a cada pergunta;
- exigem reconstruir toda a solução para cada novo caso de uso.

O EVA atua entre o acervo e o modelo generativo:

```text
documentação autorizada
        ↓
estrutura e evidências primárias
        ↓
controle de acesso
        ↓
recuperação adequada à pergunta
        ↓
seleção query-local de contexto
        ↓
resposta com citações validadas
        ↓
conferência e decisão humana
```

O objetivo é tornar o conhecimento institucional mais acessível sem remover da instituição o controle sobre fonte, escopo e responsabilidade.

## 3. Retrato fiel do produto público

O EVA possui aplicação web em PHP, banco MySQL ou MariaDB, armazenamento privado de documentos, fila de processamento, adaptadores configuráveis de IA, API autenticada e runtime modular com armazenamento SQLite próprio por pacote.

### 3.1 Capacidades implementadas

- ingestão de Markdown, JSON e XML;
- normalização dos formatos em uma árvore documental comum;
- persistência de fonte, hierarquia, ordem, caminho e referência;
- evidências primárias literais `primary:node_content`;
- embeddings de unidades documentais completas;
- recuperação direta, estrutural, ampla, conceitual e relacional;
- fronteira query-local κq e Context Intelligence Engine;
- respostas com citações validadas localmente;
- relações transitórias `simetry` e `assimetry`;
- consulta simultânea a várias obras autorizadas;
- projetos, usuários, permissões e perfis de resposta;
- uma pergunta independente por requisição, sem herança de contexto anterior;
- auditoria operacional sanitizada e métricas descritivas;
- fila, retomada explícita de falhas e processamento confirmado;
- figuras documentais privadas e autenticadas;
- configuração white label;
- neutralidade de fornecedor por configuração;
- runtime para módulos independentes;
- módulo público de referência `com.eva.explorer`.

### 3.2 Limites atuais

Não fazem parte do produto público atual:

- captura automática de sites, e-mails, drives ou sistemas corporativos;
- OCR ou interpretação multimodal de imagens;
- sincronização em tempo real com fontes externas;
- memória permanente do chat do Core;
- decisão jurídica, clínica, financeira ou operacional autônoma;
- certificação regulatória ou de segurança implícita;
- marketplace automatizado de módulos;
- garantia de superioridade sobre outras arquiteturas RAG;
- comprovação direta de redução líquida de energia;
- módulos que existam apenas em diretórios locais e não estejam rastreados na `main` pública.

### 3.3 Base cognitiva atual

Desde a versão 6.0.0, o fluxo operacional não utiliza resumos hierárquicos, evidências derivadas ou linhagens persistidas de síntese. A base cognitiva é formada por evidências primárias literais e seus embeddings.

## 4. Como o produto funciona

### 4.1 Da fonte à evidência

O EVA valida o arquivo, escolhe o parser e cria uma árvore normalizada. Cada conteúdo documental válido produz uma evidência primária vinculada ao nó de origem.

São preservados documento, título do nó, caminho estrutural, ordem, referência, conteúdo literal e hash. Conteúdo gerado por IA não é persistido como se fosse fonte original.

### 4.2 Da evidência à recuperação

O processamento gera embeddings de unidades documentais completas acompanhadas de contexto estrutural. Uma unidade acima do orçamento seguro do provedor deve ser subdividida na própria documentação; o serviço não a corta silenciosamente. Embeddings do mesmo modelo e hash podem ser reutilizados.

O EVA identifica localmente se a pergunta é direta, estrutural, ampla, conceitual ou relacional:

- rotas diretas, estruturais e amplas percorrem identificadores, texto e árvore sem embedding da pergunta;
- rotas conceituais e relacionais usam recuperação vetorial sobre evidências primárias elegíveis;
- κq e o CIE organizam a distribuição de similaridades da consulta;
- o núcleo forma a base da resposta e a convergência pode complementar o contexto;
- não há Top-k configurável nas rotas semânticas atuais.

O CIE organiza candidatos de forma determinística para a mesma entrada matemática. Ele não atribui verdade, qualidade, confiança ou importância às fontes.

### 4.3 Do contexto à resposta

Sem evidência recuperada, o EVA informa a limitação e não chama o provedor de resposta. Quando há contexto, a saída deve:

- citar somente evidências recuperadas;
- usar cada citação onde ela contribui analiticamente;
- manter coerência entre citações visíveis e IDs declarados;
- usar fragmentos literais válidos nas relações cognitivas;
- separar limitações da resposta principal.

Uma saída incompatível pode ser descartada e regenerada dentro do limite de três tentativas totais. O sistema não acrescenta citações ausentes apenas para aparentar conformidade.

### 4.4 Uma pergunta por vez

Cada requisição reinicia a eleição query-local: input, embedding transitório, recuperação, κq, CIE, resposta e interações não são herdados da pergunta anterior.

Esse reinício não reconstrói a memória documental. Evidências primárias e embeddings da obra permanecem armazenados e são reutilizados. Assim, a independência entre perguntas evita o crescimento acumulativo do histórico sem exigir reprocessamento integral do acervo.

### 4.5 Rastreabilidade e auditoria

O resultado apresenta evidências utilizadas com documento, nó, caminho, referência e conteúdo. Isso é rastreabilidade documental.

A auditoria do Core é operacional e sanitizada: não armazena prompts, perguntas, respostas, chaves, tokens ou conteúdo documental. O transcript do chat não é persistido como conversa pelo produto-base.

> **Formulação correta: rastreabilidade documental no resultado e auditoria operacional sanitizada.**

## 5. Segurança, governança e operação

O EVA implementa autenticação, sessão, recuperação de acesso, papel de superadmin e permissões por projeto ou obra. Perfis de resposta podem orientar público, tom, vocabulário e foco, mas nunca substituem as regras de evidência.

Impactos diretos:

- usuários consultam apenas os escopos concedidos;
- obras compartilhadas entre projetos selecionados são deduplicadas;
- módulos revalidam ator e escopo antes de reutilizar o Core;
- regras de apresentação não se transformam em autoridade documental;
- configurações de fornecedor, endpoint, modelo e credenciais permanecem fora da interface pública.

Esses controles não equivalem, por si só, a certificação regulatória, garantia absoluta de segurança ou isolamento multitenant certificado.

O painel administrativo gerencia documentos, projetos, usuários, permissões, fila, módulos, métricas e auditoria. O uso real de IA exige habilitação no servidor e confirmação explícita.

## 6. Extensão modular verificada na main pública

A verificação da árvore rastreada na `main` pública identifica o runtime modular e um módulo de referência: `com.eva.explorer`. Diretórios locais não rastreados não são apresentados aqui como produto disponível.

### 6.1 EXPLORER

O `com.eva.explorer` demonstra o EVA como plataforma de aprendizagem baseada em acervo institucional.

| Perfil | Operação |
|---|---|
| **Professor** | Cria, pausa e reativa Temas de Aprendizado; acompanha etapas e análises. |
| **Aluno** | Realiza Quizz, Nodes e Prova e recebe devolutiva qualitativa. |
| **Secretaria** | Consulta temas, participação, etapas concluídas, evolução e atividade por professor. |
| **Superadmin** | Vincula usuários aos perfis do módulo. |

As três experiências são:

- **Quizz:** pergunta aberta curta sobre conceito central;
- **Nodes:** construção, pelo aluno, de uma relação entre assuntos da obra;
- **Prova:** questão com quatro alternativas e uma resposta correta.

Depois da resposta, o resultado é classificado como `correct` ou `deepen` e acompanhado de análise qualitativa. Não há nota, percentual, ranking ou nível de domínio.

Impactos diretamente observáveis:

- transforma uma obra em três formas de prática;
- separa a atividade apresentada do suporte documental usado na análise;
- fornece devolutiva vinculada ao mesmo documento-base;
- preserva papéis distintos;
- mantém atividade e progresso no armazenamento privado do módulo;
- revalida autorização documental em cada geração e análise.

Ganho de aprendizagem, redução de tempo docente, retenção e satisfação precisam ser medidos em pilotos; não são comprovados pelo repositório.

## 7. O Core como aplicação horizontal

```text
administrador importa e processa documentos
        ↓
organiza obras em projetos
        ↓
concede acesso aos usuários
        ↓
usuário escolhe projeto ou obras
        ↓
faz uma pergunta independente
        ↓
recebe resposta, citações e evidências utilizadas
```

| Situação | Comportamento do EVA | Impacto direto |
|---|---|---|
| Confirmar uma regra interna | Consulta somente a política autorizada e apresenta o trecho citado. | Aproxima a orientação da fonte institucional. |
| Localizar um procedimento pela seção | A rota estrutural percorre a árvore sem embedding da pergunta. | Usa a organização original e evita uma chamada externa nessa rota. |
| Combinar conceitos de várias obras | Consolida o contexto autorizado e deduplica fontes compartilhadas. | Permite análise multiobra sem duplicação documental. |
| Pergunta sem suporte | Informa insuficiência e não chama o provedor de resposta. | Evita geração sem base recuperada. |
| Reutilizar um manual entre equipes | A obra integra diferentes projetos e permissões. | Reutiliza a mesma memória documental. |

## 8. Aplicações possíveis por mercado

Os cenários abaixo são compatíveis com o Core quando o trabalho consiste em consultar documentação fornecida, estruturada, processada e autorizada. Eles não significam que integrações ou certificações setoriais já estejam prontas.

| Mercado | Fontes típicas | Aplicação compatível | Limite atual |
|---|---|---|---|
| Educação | livros, apostilas, regulamentos | consulta e prática com EXPLORER | impacto pedagógico requer medição |
| Conhecimento corporativo | políticas, manuais, processos | onboarding e suporte interno | sincronização externa exige integração |
| Compliance | normas e controles | orientação documental citada | não emite parecer nem certificação |
| Engenharia e energia | manuais e procedimentos | localização e comparação de instruções | não opera telemetria ou equipamentos |
| Jurídico | contratos e regulamentos | pesquisa e conferência de cláusulas | não substitui assessoria jurídica |
| Governo | atos e procedimentos | consulta administrativa sustentada | não decide nem monitora diários em tempo real |
| Pesquisa e editoras | livros, artigos e coleções | exploração estrutural e semântica | não é gestor completo de referências |
| Saúde, finanças e seguros | protocolos e regras internas | apoio documental consultivo | decisões de alto impacto ficam fora do produto |

## 9. Benchmark documental da versão 8.0.2

O benchmark público comparou cinco perguntas independentes sobre uma obra com duas abordagens:

1. **EVA:** novo embedding da pergunta, recuperação, κq, CIE, evidências eleitas, geração e validação.
2. **Contexto integral:** o mesmo modelo recebeu novamente o Markdown completo antes de cada pergunta, com o prefixo organizado para favorecer o cache do provedor.

| Indicador agregado | EVA | Contexto integral | Resultado observado |
|---|---:|---:|---|
| Input no modelo de respostas | 25.805 | 1.246.430 | EVA: 97,93% menor |
| Output | 2.645 | 1.472 | EVA: 79,69% maior |
| Total no modelo de respostas | 28.450 | 1.247.902 | contexto integral: 43,86 vezes maior |
| Total operacional, com embeddings | 28.554 | 1.247.902 | EVA: 97,71% menor |
| Custo agregado | US$ 0,004142710 | US$ 0,041383956 | EVA: 89,99% menor |
| Latência total | 26,467 s | 22,996 s | contexto integral: 13,11% menor |

O EVA gerou mais tokens de saída porque suas respostas foram mais desenvolvidas e incluíram rastreabilidade. A economia ocorreu no contexto de entrada, não por respostas menores.

O ensaio respeitou o contrato de uma pergunta por vez. Cada consulta reiniciou a eleição query-local, mas reutilizou a memória documental persistente. Portanto, o resultado não dependeu de herdar o contexto de perguntas anteriores.

### 9.1 Interpretação energética responsável

A redução de tokens e cache miss demonstra menor carga lógica enviada ao modelo no cenário medido e torna plausível esperar menor trabalho computacional de inferência. Essa é uma inferência técnica coerente, inclusive quando o sistema processa uma pergunta por vez.

Entretanto, o benchmark não mediu potência, energia, água, carbono, PUE, hardware, localização do data center ou energia gasta na construção inicial dos embeddings. Por isso, a formulação correta é:

> **O EVA demonstrou contenção de tokens e custo operacional no ensaio; a contribuição para eficiência energética é logicamente plausível, mas a redução líquida de energia ainda precisa de medição instrumental.**

O contexto integral foi mais rápido no total, enquanto as chamadas do provedor de respostas do EVA foram mais curtas no agregado. O EVA troca pequena latência local por redução substancial do contexto generativo e por contratos de evidência e validação.

Consulte o [benchmark completo](19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md) para metodologia, perguntas, respostas, custos, limitações e referências de preços.

## 10. Impacto verificável versus resultado a provar

| Dimensão | Impacto verificável agora |
|---|---|
| Fundamentação | Respostas aceitas citam evidências recuperadas e usadas analiticamente. |
| Recusa | Sem evidência, o provedor de resposta não é chamado. |
| Procedência | Evidências mantêm documento, nó, caminho, referência e conteúdo literal. |
| Escopo | Usuários e módulos consultam projetos e obras autorizados. |
| Reutilização | Embeddings idênticos por modelo e hash podem ser reaproveitados. |
| Contenção | Rotas não semânticas dispensam embedding da pergunta. |
| Validação | Citações externas ao contexto e relações sem fragmentos literais são recusadas. |
| Extensibilidade | O EXPLORER opera com domínio, dados e interface próprios. |

Ainda exigem evidência adicional:

- precisão ou recall superiores a outras arquiteturas;
- redução percentual em outros corpora, modelos e cargas;
- diminuição de risco regulatório;
- ganho de aprendizagem ou produtividade;
- redução líquida de energia;
- retorno sobre investimento;
- desempenho e disponibilidade em escala não testada.

## 11. Onde o EVA tem melhor aderência

O produto é mais adequado quando:

- existe um acervo relevante e reutilizado;
- documentos podem ser preparados em Markdown, JSON ou XML;
- a estrutura da fonte ajuda a interpretar o conteúdo;
- a origem da resposta precisa ser conferida;
- grupos diferentes precisam de escopos distintos;
- o uso permanece consultivo e supervisionado;
- há um responsável pela qualidade e autorização das fontes.

Exige desenvolvimento adicional para ingestão contínua de sistemas externos, OCR, análise visual, dados operacionais em tempo real, workflows transacionais específicos, retenção legal integral, decisão automática de alto impacto ou requisitos especiais de escala e certificação.

## 12. Diferenciais comunicáveis

- evidências primárias literais como base persistente;
- preservação de hierarquia, ordem e referência;
- embeddings de unidades documentais completas;
- recuperação adaptativa ao tipo de pergunta;
- seleção estatística query-local sem Top-k semântico fixo;
- bloqueio de geração quando não há evidência;
- validação local de citações e uso analítico;
- relações transitórias verificadas por fragmentos literais;
- consulta multiobra com deduplicação;
- permissões por projeto e obra;
- uma pergunta por vez sem histórico acumulado;
- auditoria operacional sanitizada;
- neutralidade de fornecedor por configuração;
- experiência white label;
- runtime modular independente e EXPLORER público.

Distinções necessárias:

- o CIE é determinístico para a mesma distribuição; embeddings e geração não se tornam determinísticos por isso;
- neutralidade de fornecedor não garante a mesma qualidade ou custo entre modelos;
- white label não constitui automaticamente um SaaS multitenant;
- controle de acesso não é certificação de conformidade;
- citações validadas reduzem falhas, mas não garantem que toda interpretação esteja correta.

## 13. Tese de produto e oferta

```text
                    EVA Core
          memória e consulta documental
                       │
              runtime de módulos
                       │
                   EXPLORER
                  aprendizagem
```

A arquitetura permite reutilizar o Core, manter dados e regras de domínio fora do núcleo, oferecer implantação dedicada e white label e desenvolver novos módulos com menor acoplamento.

Modelos de oferta tecnicamente possíveis:

- instalação institucional dedicada;
- produto white label;
- consulta documental;
- pacote educacional com EXPLORER;
- desenvolvimento de novo módulo vertical;
- integração por API autenticada;
- implantação, organização e governança de acervo.

Uma proposta comercial deve separar licença, infraestrutura, consumo de IA, processamento inicial, atualizações, suporte, backup, preparação documental, integrações e responsabilidades da instituição.

## 14. Medição em clientes

Um piloto deve comparar processo anterior e processo com EVA sobre o mesmo corpus e público.

| Objetivo | Indicadores recomendados |
|---|---|
| Acesso à informação | tempo até a fonte; consultas resolvidas; buscas manuais; escalonamentos |
| Qualidade documental | validade das citações; cobertura; recusa correta; correções |
| Eficiência computacional | chamadas; embeddings; tokens; cache; latência; custo por consulta |
| Energia | potência e energia por consulta; hardware; PUE; localização; construção e operação |
| Operação do acervo | ingestão; reutilização; falhas; retomadas; atualização |
| EXPLORER | temas; participação; conclusão; recorrência de `deepen`; tempo docente |
| Adoção | usuários ativos; retorno; abandono; satisfação; utilidade percebida |

Toda alegação quantitativa deve informar corpus, modelo, configuração, baseline, período e método.

## 15. Claims permitidos e proibidos

### Formulações factuais

- “O EVA transforma Markdown, JSON e XML em evidências primárias estruturadas.”
- “As respostas aceitas citam evidências recuperadas do acervo autorizado.”
- “Sem evidência recuperada, o EVA não chama o provedor de resposta.”
- “O produto público inclui o módulo de referência EXPLORER.”
- “O benchmark 8.0.2 observou 97,71% menos tokens operacionais e 89,99% menos custo no cenário documentado.”
- “O EVA implementa mecanismos de contenção computacional cujo efeito energético líquido ainda precisa ser medido.”

### Formulações que exigem prova adicional

- “Elimina alucinações.”
- “Garante respostas corretas.”
- “É superior a qualquer RAG.”
- “Reduz energia em determinada porcentagem.”
- “Garante compliance.”
- “É seguro para qualquer dado sensível.”
- “Substitui especialistas.”
- “Mantém histórico completo das respostas do Core.”

| Evitar | Usar |
|---|---|
| “IA sem alucinação” | “Citações fora do contexto são recusadas e consultas sem evidência terminam sem geração.” |
| “Resposta garantidamente correta” | “Resposta limitada às evidências recuperadas e validada pelo contrato local.” |
| “Auditoria completa do chat” | “Rastreabilidade documental no resultado e auditoria operacional sanitizada.” |
| “Economia de energia comprovada” | “Redução mensurada de tokens e custo, com efeito energético líquido ainda por medir.” |

## 16. Riscos e diligência

O repositório demonstra produto e arquitetura, mas não comprova clientes pagantes, receita recorrente, custo de aquisição, retenção comercial, margem, SLA, certificações ou impacto financeiro e educacional em campo.

Antes de uma implantação, é necessário:

1. executar validação em ambiente isolado;
2. testar o corpus e as perguntas reais do cliente;
3. medir qualidade, latência, tokens, custo e, se relevante, energia;
4. validar conversão e atualização documental;
5. testar backup, recuperação, escala e disponibilidade;
6. definir governança humana e responsabilidade por domínio;
7. validar requisitos legais, de privacidade e de segurança.

## 17. Plano de adoção

1. **Corpus controlado:** selecionar documentos estruturados, perguntas e responsáveis.
2. **Piloto operacional:** comparar qualidade, tempo, falhas, uso e custo com a linha de base.
3. **Aplicação especializada:** ativar EXPLORER quando o caso for educacional ou desenvolver módulo próprio.
4. **Expansão governada:** adicionar projetos, obras, perfis, usuários e módulos após validar corpus e operação.
5. **Caso de negócio:** converter resultados medidos em proposta comercial com método declarado.

## 18. Mensagens recomendadas

### Institucional

> **O EVA transforma documentação autorizada em respostas verificáveis, com fontes visíveis, acesso governado e extensão modular.**

### Produto

> **Uma infraestrutura documental que preserva evidências primárias, seleciona contexto conforme a pergunta e valida citações antes de entregar a resposta.**

### Eficiência responsável

> **O EVA reduz o contexto generativo ao recuperar evidências relevantes; o benchmark 8.0.2 quantifica tokens e custo, enquanto o impacto energético permanece uma hipótese mensurável.**

### Por que não apenas um chatbot com arquivos?

Porque o EVA preserva estrutura e evidências primárias, governa quais obras podem ser consultadas, seleciona o contexto conforme a pergunta, valida citações e permite extensões independentes sobre o mesmo acervo.

## 19. Conclusão

O EVA é uma plataforma funcional de ingestão, processamento, consulta, validação, acesso e administração. A distribuição pública também demonstra extensibilidade por meio do runtime modular e do EXPLORER.

O retrato responsável é:

- há uma plataforma documental executável;
- há um módulo público de referência;
- há casos horizontais imediatamente demonstráveis;
- o benchmark 8.0.2 mensura uma redução substancial de tokens e custo em um cenário delimitado;
- o efeito energético é coerente em nível lógico, mas não foi medido diretamente;
- impacto econômico, educacional, escala e tração não devem ser presumidos.

> **O EVA cria uma ponte verificável entre conhecimento institucional e aplicações de IA, preservando a fonte como fundamento e a governança humana como limite.**

## 20. Referências internas

- [Índice da documentação](README.md)
- [Visão geral](01_VISAO_GERAL.md)
- [Arquitetura](02_ARQUITETURA.md)
- [Ingestão](03_INGESTAO.md)
- [Construção cognitiva](04_CONSTRUCAO_COGNITIVA.md)
- [Consulta](05_CONSULTA.md)
- [Produto](10_PRODUTO.md)
- [Sustentabilidade energética](13_SUSTENTABILIDADE_ENERGETICA.md)
- [Context Intelligence Engine](14_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Módulos](16_MODULOS.md)
- [Relacionamentos do banco de dados](17_RELACIONAMENTO_BANCO_DADOS.md)
- [Benchmark EVA versus contexto integral](19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md)
- [README do EXPLORER](../modules/com.eva.explorer/README.md)
- [Changelog](../CHANGELOG.md)
