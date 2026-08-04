# Validação de prontidão para produção

## Parecer

**Resultado inicial em 22/07/2026: NO-GO.**

**Revalidação da consulta do EVA em 22/07/2026: APROVADA após as correções.**

**Homologação pré-deploy em 22/07/2026: APROVADA PARA UPLOAD CONTROLADO.**

O diagnóstico inicial confirmou dois defeitos no fluxo de consulta do EVA: detecção incompleta de perguntas relacionais em português e respostas relacionais intermitentemente truncadas pelo limite de saída. Ambos foram corrigidos e a matriz relacional ao vivo foi aprovada sem falhas. Em seguida, foram concluídos o smoke visual local, a homologação pré-deploy da infraestrutura, o teste real de backup/restauração e a melhoria do diagnóstico seguro. O aceite do ambiente online depende somente da verificação posterior ao upload, conforme `docs/12_HOMOLOGACAO_PRE_DEPLOY.md`.

Este parecer cobre aplicação, banco, permissões, autenticação, consultas reais ao provedor configurado, navegador e infraestrutura local. Métricas de concorrência locais não substituem a observação de capacidade da hospedagem depois da publicação.

> Atualização de 2 de agosto de 2026: a matriz ao vivo e as contagens históricas abaixo antecedem a introdução do Context Intelligence Engine. Naquele momento, o CIE possuía apenas o teste matemático offline próprio, e as consultas conceituais e relacionais ainda precisavam ser revalidadas com `QUERY_CANDIDATE_LIMIT=30`. Este trecho registra a condição daquela data e não substitui os ajustes operacionais posteriores.

> Validação dirigida em 2 de agosto de 2026: uma consulta conceitual real sobre *O Livro dos Espíritos*, com Top-30, três evidências finais de núcleo e sete de convergência, terminou sem truncamento em 24,32 segundos. As dez evidências foram incorporadas à prosa analítica e nenhuma apareceu apenas em inventário de citações. Esse resultado aprova o contrato determinístico e a validação fechada no caso de referência, mas não substitui a matriz comparativa ainda pendente sobre um corpus representativo.

> Ajuste operacional em 3 de agosto de 2026: o padrão corrente foi reduzido para `QUERY_CANDIDATE_LIMIT=20` após uma consulta dirigida sobre *O Evangelho Segundo o Espiritismo* produzir contexto mais concentrado e resposta documental de melhor foco. Esse caso orienta o novo padrão, mas permanece uma observação operacional; a matriz comparativa representativa continua pendente.

> Revalidação do contrato de citações em 4 de agosto de 2026: a antiga exigência de incorporar todas as fontes recuperadas foi substituída pelo contrato de citações visíveis. Na chamada real “O que é ectoplasma?” sobre um projeto autorizado com sete obras, foram recuperadas dez evidências. Antes da correção, a terceira geração terminou normalmente (`finish_reason=stop`) com 659 de 1800 tokens, citou nove evidências e omitiu uma; ainda assim, toda a resposta era rejeitada. Depois da correção, a consulta terminou com sucesso, citou quatro evidências e descartou as seis candidatas não citadas. A geração validada terminou com `finish_reason=stop` e 354 tokens. As 24 suítes sem chamada ao provedor também foram aprovadas. O caso confirma que a falha não era causada pelo teto de saída e valida o novo descarte, mas não substitui a matriz comparativa representativa.

## Ambiente e escopo

A validação utilizou o projeto ativo `Pentateuco Espírita` e suas duas obras prontas:

- `O Livro dos Médiuns`;
- `O Livro dos Espíritos`.

Foram usados um superadmin existente e um usuário temporário criado exclusivamente pelo teste. O usuário temporário e todas as suas atribuições foram removidos no encerramento, inclusive em caso de falha. As contagens originais de usuários e permissões foram restauradas.

Configuração efetiva da consulta do EVA durante o teste:

```env
QUERY_MAX_EVIDENCE=10
QUERY_MAX_INTERACTIONS=20
AI_QUERY_MAX_OUTPUT_TOKENS=1800
```

As credenciais foram apenas verificadas como configuradas. Nenhuma chave ou segredo foi registrado nos relatórios.

## Matriz funcional executada

Cada combinação positiva recebeu três perguntas sintéticas: uma ampla, uma conceitual e uma relacional. As chamadas alternaram entre superadmin e usuário cadastrado.

| Escopo selecionado | Permissão do usuário | Superadmin | Usuário | Resultado de acesso |
|---|---|---:|---:|---|
| Projeto completo | Projeto completo | 3 perguntas | 3 perguntas | Permitido corretamente |
| Obra pertencente ao projeto | Projeto completo | 3 perguntas | 3 perguntas | Permitido corretamente |
| Uma obra individual | Somente essa obra | 3 perguntas | 3 perguntas | Permitido corretamente |
| Duas obras individuais | Duas obras, sem o projeto | 3 perguntas | 3 perguntas | Permitido corretamente |

Também foram executadas nove tentativas negativas, com três perguntas em cada situação:

- projeto sem permissão de projeto;
- outra obra não concedida;
- projeto completo quando o usuário possuía apenas permissões individuais sobre as obras.

Todas as nove tentativas foram recusadas com HTTP 403. Não houve vazamento de evidência entre projetos ou obras.

## Resultados confirmados

### Permissões e granularidade

- O superadmin acessou todos os projetos e obras, independentemente de atribuição explícita.
- A permissão de projeto permitiu consultar o projeto completo e cada obra filha.
- A permissão de uma obra individual não liberou outra obra nem o projeto completo.
- Duas permissões individuais permitiram uma consulta combinada às duas obras, sem transformar essa concessão em permissão sobre o projeto.
- Todas as respostas autorizadas utilizaram somente documentos contidos no escopo solicitado.

### Respostas, evidências e citações

- As 24 chamadas da matriz principal retornaram HTTP 200.
- Cada resposta autorizada trouxe conteúdo e evidências.
- Nenhuma resposta ultrapassou `QUERY_MAX_EVIDENCE=10`.
- Nenhuma resposta ultrapassou `QUERY_MAX_INTERACTIONS=20`.
- As citações e os identificadores de evidência permaneceram vinculados ao contexto recuperado.
- As perguntas amplas e conceituais funcionaram nos quatro cenários e nos dois perfis.

### Sessão e revogação

- O logout invalidou a sessão utilizada.
- A sessão anterior não voltou a ser aceita após novo login.
- Usuário desativado não conseguiu autenticar.
- Revogação e reativação foram refletidas na autenticação.
- O estado temporário criado pelo teste foi removido ao final.

### Suíte automatizada sem chamadas pagas

Na regressão final, foram executadas 15 suítes sem chamadas pagas, totalizando 883 asserções. Todas passaram, incluindo as suítes adicionais de configuração do `.env`, segurança de logs e backup/restauração:

- controle de acesso;
- adaptadores de IA;
- construção cognitiva;
- exclusão;
- ingestão documental;
- esquema do algoritmo de evidências;
- parsers;
- camada de produto;
- consulta;
- ingestão de documento real;
- segurança de upload;
- arquitetura white-label.
- inventário completo e comentado do `.env`;
- diagnóstico seguro nos logs;
- backup e restauração da infraestrutura.

### Context Intelligence Engine — atualização de 2 de agosto de 2026

Foram adicionadas as suítes `tests/ContextIntelligenceEngineTest.php` e `tests/ContextIntelligenceIntegrationTest.php`. A primeira independe de banco e cobre fórmulas populacionais, núcleo, fallback para convergência, média zero, distribuição homogênea, distribuição vazia, tolerância de fronteira e saída auditável. A segunda monta um documento controlado com cinco vetores em transação, verifica o contexto final e desfaz os dados ao final. `tests/QueryTest.php` também verifica a integração do CIE com a recuperação semântica e a resolução para fontes primárias.

O esquema vazio foi importado no banco local por conter somente criações idempotentes. O teste matemático foi aprovado com 13 asserções, a integração controlada com 10 e `tests/QueryTest.php` com 49. Nenhuma chamada externa ou paga foi feita. A revalidação ao vivo de qualidade e estabilidade continua pendente antes de publicação.

## Bloqueadores encontrados no diagnóstico inicial

### 1. Detecção incompleta de perguntas relacionais

As oito perguntas relacionais da matriz principal usaram a construção natural `se relacionam`. Todas retornaram HTTP 200 e respostas documentais, mas foram classificadas apenas como `conceptual`. Consequentemente, não ativaram a produção de `simetry`, `assimetry` ou limitação relacional.

O detector atual reconhece formulações como `Qual é a relação entre ...`, mas a expressão regular baseada em `relaciona` exige uma fronteira de palavra imediatamente depois desse radical e não contempla a terminação de `relacionam`.

Impacto: uma formulação comum e correta em português não aciona uma função central do sistema. Resultado observado: **8 falhas em 8 tentativas com `se relacionam`**.

Arquivo relacionado: `app/Application/Query/InputTypeDetector.php`.

**Correção aplicada:** o detector passou a normalizar diacríticos e a reconhecer famílias morfológicas completas, além de operadores formais neutros. A classificação continua local e determinística, sem chamada adicional de IA. Foram adicionados casos positivos para `se relacionam`, `relacionados`, `interact` e `↔`, além de um caso negativo para impedir que a simples presença de dois conceitos seja classificada como relacional.

### 2. Truncamento intermitente da resposta JSON do provedor

Foi executado um complemento com formulações reconhecidas pelo detector, usando `Qual é a relação entre ...`:

- 8 consultas relacionais autorizadas;
- 4 retornaram HTTP 200 com evidências, interações validadas e limitação;
- 4 retornaram HTTP 503.

Em seguida, a mesma consulta e o mesmo escopo foram repetidos cinco vezes de forma controlada:

- 3 respostas válidas;
- 2 falhas por JSON incompleto.

A inspeção segura do envelope do provedor confirmou `finish_reason=length` nas falhas. O conteúdo começava com `{`, não terminava com `}` e não podia ser decodificado. Com `AI_QUERY_MAX_OUTPUT_TOKENS=900`, a saída foi encerrada antes de completar o contrato JSON.

Na versão diagnosticada, o fluxo não tratava especificamente `finish_reason=length`, não descartava a resposta truncada antes da decodificação e não repetia a solicitação com um orçamento seguro. A exceção chegava à camada de produto como indisponibilidade cognitiva genérica.

Impacto: consultas relacionais válidas podem falhar de forma intermitente mesmo com permissões, documentos e provedor operacionais. Resultado observado no teste controlado: **2 falhas em 5 repetições idênticas**.

Arquivos relacionados:

- `.env`, em `AI_QUERY_MAX_OUTPUT_TOKENS`;
- `config/ai.php`;
- `app/Infrastructure/Ai/QueryAnswerProvider.php`.

**Correção aplicada:** o teto foi calibrado para `1800`, o prompt recebeu um comando explícito de compacidade e `interaction_limit` deixou de poder ser interpretado como meta. `finish_reason=length` é verificado antes da decodificação; a saída parcial é descartada e ocorre no máximo uma regeneração integral e compacta, no mesmo teto. Uma segunda ocorrência encerra com erro explícito, sem reparo de JSON e sem repetição ilimitada.

## Revalidação após as correções

A matriz relacional foi repetida com as formulações naturais que haviam falhado, alternando superadmin e usuário cadastrado:

- 8 de 8 consultas autorizadas aprovadas;
- 3 de 3 tentativas proibidas recusadas com HTTP 403;
- 321 asserções aprovadas;
- zero truncamentos;
- zero respostas HTTP 503;
- zero vazamentos entre obras ou projetos;
- limites de 10 evidências e 20 interações respeitados;
- usuário temporário e permissões removidos ao final.

O arquivo bruto dessa execução é `go-live-relational-after-fix.json`.

### 3. Diagnóstico operacional insuficiente

A API devolve corretamente uma mensagem genérica ao cliente, mas o log operacional registra apenas a classe da exceção para esse caso. Sem o motivo seguro, como `finish_reason=length`, a equipe pode interpretar truncamento como queda do provedor.

O cliente não deve receber detalhes internos. O log do servidor, contudo, precisa distinguir de forma segura indisponibilidade, timeout, resposta truncada, JSON inválido e violação do contrato.

**Correção aplicada:** cada requisição recebeu um `X-Request-Id`, e as falhas passaram a ser classificadas por categorias operacionais seguras. Mensagens de exceção, credenciais, prompts, inputs, conteúdos e respostas brutas são omitidos ou substituídos por `[REDACTED]`. A suíte específica aprovou 13 asserções de segurança.

## Observações do ambiente

Durante as execuções CLI, o PHP informou inicialmente que o módulo `openssl` já estava carregado. A declaração duplicada em `C:\xampp\php\php.ini` foi removida; o módulo permaneceu ativo, o aviso desapareceu e `httpd -t` aprovou a configuração do Apache.

O primeiro relatório bruto foi gerado antes da correção de um erro de contabilização no campo visual `passed` do testador. Esse erro marcava linhas bem-sucedidas como falsas por uso incorreto de união de arrays. Os status HTTP, conteúdos, evidências, asserções e a lista de falhas não foram alterados; o testador foi corrigido antes do encerramento desta validação.

## Situação dos critérios para GO

1. ~~Ampliar a detecção relacional para flexões usuais em português, incluindo `relacionam`, e criar testes de regressão linguística.~~ Concluído.
2. ~~Tratar explicitamente `finish_reason=length` no provedor de respostas.~~ Concluído.
3. ~~Calibrar o orçamento de saída relacional e implementar uma repetição limitada e segura, sem duplicar efeitos nem aceitar JSON parcial.~~ Concluído.
4. ~~Registrar no servidor a categoria segura da falha cognitiva, sem incluir credenciais, prompts integrais ou conteúdo sensível.~~ Concluído.
5. ~~Reexecutar a matriz relacional ao vivo e obter 100% de sucesso nas consultas autorizadas, 100% de negação nos acessos proibidos e zero vazamento de escopo.~~ Concluído para a matriz relacional após as correções.
6. ~~Executar um smoke test no navegador para a árvore de checkboxes, seleção múltipla, estado do chat após logout e responsividade em desktop e dispositivos móveis.~~ Concluído no ambiente local por HTTPS.
7. ~~Executar a homologação pré-deploy: cabeçalhos, permissões de arquivos, backup com restauração, diagnóstico, concorrência básica e camada HTTPS do domínio.~~ Concluído. A verificação conjunta no domínio permanece obrigatória depois do upload.

Os critérios pré-deploy foram atendidos. O sistema está aprovado para upload controlado; o parecer online definitivo exige zero falhas em `php bin\verify-deployment.php https://eva.oceanno.com.br` depois da publicação.

## Reexecução

O teste reproduzível está em `tests/GoLiveReadinessTest.php`. Por segurança, chamadas reais exigem as duas confirmações explícitas:

```powershell
$env:AI_LIVE_ENABLED='true'
php tests\GoLiveReadinessTest.php --live --report=go-live-readiness.json
```

Sem `--live` e `AI_LIVE_ENABLED=true`, o arquivo não realiza chamadas pagas ao provedor.
