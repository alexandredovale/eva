# Checklist de Implementação da Arquitetura Modular EVA

Documento de execução complementar a `updates/update_eva_20260803_modular.md`.

## Objetivo

Implementar a camada modular da EVA preservando o Core atual, permitindo que zero, um ou vários módulos independentes sejam instalados em `modules/`, ativados pelo superadmin e alimentados por contratos de eventos versionados.

## Regras não negociáveis

- [x] Não alterar nenhuma tabela existente do MySQL.
- [x] Criar somente a tabela neutra `module_events` no banco principal.
- [x] Não criar tabelas pedagógicas ou especializadas no MySQL do Core.
- [x] Não adicionar vínculo de módulo em usuários, projetos ou documentos.
- [x] Manter todo código especializado dentro de `modules/`.
- [x] Manter os dados de cada módulo em seu próprio `module.sqlite`.
- [x] Impedir acesso HTTP direto a código interno, configurações e SQLite por `.htaccess`.
- [x] Não adicionar regras pedagógicas ou de outros domínios ao Core.
- [x] Preservar o funcionamento da consulta caso um módulo falhe.
- [x] Nunca armazenar ou expor chaves de API nos pacotes, eventos ou logs.

## Decisões já aprovadas

- [x] A EVA é self-hosted e administrada pela própria instituição.
- [x] Somente o superadmin instala, ativa, atualiza ou remove módulos manualmente.
- [x] Vários módulos podem permanecer ativos simultaneamente.
- [x] Os módulos são independentes de usuários, projetos e documentos.
- [x] Projetos e documentos são referências contextuais dos eventos.
- [x] O MySQL continua sendo o banco exclusivo do Core.
- [x] Cada módulo utiliza SQLite como persistência padrão independente.
- [x] A instalação atual possui `pdo_sqlite` habilitado.
- [x] Sandbox, containers e isolamento de processos não integram o MVP.
- [x] O documento arquitetural principal está atualizado.

---

## Fase 0 — Baseline e proteção do sistema atual

Objetivo: registrar o estado funcional antes da implementação.

- [x] Ler e Confirmar que `updates/update_eva_20260803_modular.md` é a fonte arquitetural oficial.
- [x] Registrar a versão atual da aplicação e do PHP.
- [x] Confirmar `pdo_mysql` e `pdo_sqlite` habilitados.
- [x] Executar a suíte de testes atual sem alterações.
- [x] Registrar testes aprovados, ignorados ou já falhos antes do update.
- [x] Fazer backup do MySQL antes da migration.
- [x] Fazer backup dos arquivos atuais que futuramente receberão o bridge ou integração visual.
- [x] Confirmar que não há credenciais versionadas no repositório.

Critério de conclusão:

- [x] Baseline documentado e recuperável.
- [x] Nenhuma alteração funcional realizada.

---

## Fase 1 — EVA Module Contract v1

Objetivo: definir os contratos antes de escrever o Runtime.

### 1.1 Contrato do manifesto

- [x] Definir `modules/runtime/contracts/module-manifest.schema.json`.
- [x] Tornar obrigatórios:
  - [x] `id`;
  - [x] `name`;
  - [x] `vendor`;
  - [x] `version`;
  - [x] `eva_contract`;
  - [x] `entrypoint`;
  - [x] `subscribed_events`;
  - [x] `capabilities`;
  - [x] `storage.driver`;
  - [x] `storage.schema_version`.
- [x] Validar o formato do identificador, por exemplo `com.fornecedor.modulo`.
- [x] Rejeitar caminhos absolutos, `..` e entrypoints fora do diretório do pacote.
- [x] Definir dashboard como capacidade opcional.

### 1.2 Contrato dos eventos

- [x] Definir `modules/runtime/contracts/module-event.schema.json`.
- [x] Tornar obrigatórios:
  - [x] `event_id`;
  - [x] `event_type`;
  - [x] `contract_version`;
  - [x] `occurred_at`;
  - [x] `actor`;
  - [x] `scope`;
  - [x] `interaction`, quando aplicável;
  - [x] `evidences`;
  - [x] `limitations`.
- [x] Definir `interaction.completed` como primeiro evento oficial.
- [x] Preservar separadamente a pergunta atual e o contexto conversacional enviado ao Core.
- [x] Definir snapshot mínimo de projetos, documentos e evidências.
- [x] Definir limite máximo do payload.
- [x] Proibir credenciais, tokens e chaves de API no payload.
- [x] Definir compatibilidade e evolução de `contract_version`.

### 1.3 Interface PHP mínima

- [x] Definir `ModuleInterface`.
- [x] Definir `ModuleEvent` imutável.
- [x] Definir retorno e comportamento de sucesso, falha e evento ignorado.
- [x] Definir idempotência por `event_id`.
- [x] Definir que nenhum módulo chama diretamente outro módulo.

Critério de conclusão:

- [x] Manifesto e evento possuem schemas válidos e testáveis.
- [x] Nenhuma classe de domínio educacional aparece no contrato genérico.

---

## Fase 2 — Estrutura física de `modules/`

Objetivo: criar a camada modular sem implementar ainda o módulo educacional.

- [x] Criar `modules/runtime/`.
- [x] Criar `modules/runtime/contracts/`.
- [x] Criar `modules/runtime/src/`.
- [x] Criar `modules/runtime/bin/`.
- [x] Criar `modules/.runtime/`.
- [x] Criar `modules/.runtime/data/`.
- [x] Criar `modules/.runtime/state.json` com estrutura inicial vazia.
- [x] Criar `.htaccess` na raiz de `modules/` bloqueando acesso HTTP direto por padrão.
- [x] Criar `.htaccess` em `modules/.runtime/` negando todo acesso HTTP.
- [x] Impedir execução de PHP em `modules/.runtime/data/`.
- [x] Adicionar ao `.gitignore`:
  - [x] `modules/.runtime/state.json`;
  - [x] `modules/.runtime/data/**/*.sqlite`;
  - [x] `modules/.runtime/data/**/*.sqlite-wal`;
  - [x] `modules/.runtime/data/**/*.sqlite-shm`;
  - [x] logs e arquivos temporários dos módulos.
- [x] Criar um módulo fixture mínimo apenas para testes do Runtime.

Critério de conclusão:

- [x] Diretórios internos e dados não podem ser baixados por HTTP.
- [x] O Runtime pode localizar a estrutura sem carregar código especializado.

---

## Fase 3 — Única tabela MySQL `module_events`

Objetivo: criar a caixa postal neutra sem alterar nenhuma tabela atual.

- [x] Criar uma nova migration SQL, sem editar migrations anteriores.
- [x] Criar somente a tabela `module_events`.
- [x] Definir campos:
  - [x] `id` como chave primária incremental;
  - [x] `event_id` como identificador público único;
  - [x] `event_type`;
  - [x] `contract_version`;
  - [x] `payload_json` como JSON;
  - [x] `created_at`.
- [x] Criar índice único para `event_id`.
- [x] Criar índice de consumo por `event_type` e `id`.
- [x] Não criar colunas de módulo, projeto, documento ou estado de entrega.
- [x] Definir inserção idempotente por `event_id`.
- [x] Criar repositório do Runtime para gravar e ler eventos.
- [x] Testar rollback da migration sem afetar tabelas existentes.

Critério de conclusão:

- [x] A migration cria exatamente uma tabela.
- [x] Eventos podem ser inseridos e lidos em ordem crescente de `id`.
- [x] Nenhuma tabela anterior foi alterada.

---

## Fase 4 — Núcleo do EVA Module Runtime

Objetivo: descobrir, validar e operar vários módulos independentes.

### 4.1 Registro e estado

- [x] Implementar `ModuleRegistry`.
- [x] Localizar somente diretórios com `module.json` válido.
- [x] Ignorar `runtime/`, `.runtime/`, arquivos soltos e diretórios inválidos.
- [x] Validar compatibilidade de `eva_contract`.
- [x] Validar o entrypoint antes de carregar o módulo.
- [x] Implementar leitura e gravação segura de `modules/.runtime/state.json`.
- [x] Preservar o manifesto distribuído sem modificá-lo.
- [x] Permitir vários módulos ativos simultaneamente.

### 4.2 Eventos e fan-out

- [x] Implementar `ModuleBridge`.
- [x] Implementar `ModuleEvent`.
- [x] Implementar `ModuleDispatcher`.
- [x] Entregar eventos somente a módulos ativos que assinem `event_type`.
- [x] Garantir que a falha de um módulo não interrompa os demais.
- [x] Garantir que a falha de módulo nunca altere a resposta do Core.
- [x] Registrar falhas sem gravar inputs, respostas completas ou segredos no log geral.

### 4.3 SQLite por módulo

- [x] Implementar `ModuleStorageFactory`.
- [x] Criar automaticamente `modules/.runtime/data/<module-id>/`.
- [x] Criar ou abrir `module.sqlite` por PDO.
- [x] Aplicar:
  - [x] `PRAGMA journal_mode = WAL`;
  - [x] `PRAGMA foreign_keys = ON`;
  - [x] `PRAGMA busy_timeout = 5000`.
- [x] Validar que o caminho final permanece dentro de `modules/.runtime/data/`.
- [x] Impedir que um módulo escolha caminho externo para o SQLite.
- [x] Criar cursor `last_event_row_id` no SQLite de cada módulo.
- [x] Atualizar dados e cursor na mesma transação.

### 4.4 Consumo e retenção

- [x] Implementar consumidor CLI em `modules/runtime/bin/consume.php`.
- [x] Ler eventos posteriores ao cursor individual de cada módulo.
- [x] Processar em lotes pequenos e configuráveis.
- [x] Impedir duplicação por `event_id`.
- [x] Registrar lacuna quando o cursor apontar para evento já removido.
- [x] Implementar retenção configurável de `module_events`.
- [x] Não excluir eventos sem respeitar a política definida.

Critério de conclusão:

- [x] Dois módulos fixture recebem o mesmo evento.
- [x] Cada módulo mantém cursor e SQLite próprios.
- [x] A falha de um fixture não interrompe o outro.

---

## Fase 5 — Ponte mínima com o Core atual

Objetivo: emitir `interaction.completed` sem introduzir domínio modular no Core.

- [x] Identificar e documentar o único ponto de integração após uma consulta concluída.
- [x] Limitar a alteração do Core à chamada genérica do `ModuleBridge`.
- [x] Gerar `event_id` único e estável.
- [x] Montar o evento somente com dados definidos pelo contrato.
- [x] Incluir usuário autenticado, data e tipo do evento.
- [x] Incluir projetos e documentos apenas como snapshots contextuais.
- [x] Incluir IDs públicos das evidências utilizadas.
- [x] Incluir limitações da resposta.
- [x] Preservar separadamente:
  - [x] pergunta atual exibida ao usuário;
  - [x] input conversacional completo utilizado pelo Core;
  - [x] resposta final produzida.
- [x] Não executar Learning Observer ou Interpreter dentro do Core.
- [x] Não bloquear a resposta da consulta por falha na emissão do evento.
- [x] Auditar apenas metadados operacionais seguros.

Arquivos existentes cuja alteração deverá ser previamente limitada e revisada:

- [x] `app/Http/Product/ProductApi.php`: emissão genérica após a consulta.
- [x] `public/assets/app.js`: envio separado da pergunta atual, somente se indispensável ao contrato.
- [x] Nenhum outro arquivo do Core deverá ser alterado nesta fase sem justificativa registrada.

Critério de conclusão:

- [x] Consultas continuam retornando exatamente o contrato público atual.
- [x] Cada consulta concluída gera no máximo um evento idempotente.
- [x] Com módulos desconectados, o Core continua funcionando normalmente.

---

## Fase 6 — Pacote oficial do Módulo Educacional

Objetivo: construir o primeiro módulo real sem incluir lógica pedagógica no Core.

### 6.1 Estrutura e manifesto

- [x] Criar `modules/com.eva.education/`.
- [x] Criar `module.json` compatível com o Contract v1.
- [x] Criar `bootstrap.php`.
- [x] Criar `src/Observer/`.
- [x] Criar `src/Interpreter/`.
- [x] Criar `src/Governance/`.
- [x] Criar `src/Dashboard/`.
- [x] Criar `public/` somente se houver ponto público necessário.
- [x] Criar `.htaccess` bloqueando acesso direto ao código interno.

### 6.2 Schema do SQLite educacional

- [x] Criar inicializador versionado do SQLite.
- [x] Criar tabela `interactions`.
- [x] Criar tabela `interpretations`.
- [x] Criar tabela `event_cursor`.
- [x] Criar tabela `module_settings`.
- [x] Criar tabela `processing_failures`.
- [x] Tornar `interactions.event_id` único.
- [x] Criar índices para usuário, data e estado de processamento.
- [x] Não criar FK para tabelas do MySQL.

### 6.3 Learning Observer

- [x] Consumir somente `interaction.completed`.
- [x] Validar o evento antes de persistir.
- [x] Registrar pergunta, resposta e contexto conversacional separadamente.
- [x] Registrar snapshots de projetos, documentos e evidências.
- [x] Produzir JSON Cognitivo versionado.
- [x] Não calcular notas, pesos, confiança pedagógica ou ranking.

### 6.4 Pedagogical Governance

- [x] Persistir a configuração no SQLite educacional.
- [x] Aceitar somente campos previstos pelo contrato aprovado.
- [x] Rejeitar scoring, pesos, notas, rankings e agregações valorativas.
- [x] Manter protocolo, taxonomia, dimensões observáveis e política de evidências.
- [x] Registrar a versão da governança usada em cada interpretação.

### 6.5 Learning Interpreter

- [x] Processar somente interações observadas e ainda pendentes.
- [x] Produzir apenas estados:
  - [x] `observed`;
  - [x] `not_observed`;
  - [x] `insufficient_evidence`;
  - [x] `conflicting_evidence`.
- [x] Vincular descrições às evidências correspondentes.
- [x] Preservar limitações e conflitos sem forçar conclusão.
- [x] Isolar indisponibilidade do provedor de IA.
- [x] Permitir reprocessamento explícito e versionado.

Critério de conclusão:

- [x] O módulo educacional pode ser removido sem alterar o Core ou o MySQL.
- [x] Seu `module.sqlite` contém todo o histórico especializado necessário.
- [x] Nenhum resultado contém pontuação ou peso pedagógico.

---

## Fase 7 — Administração dos módulos

Objetivo: permitir gestão manual exclusiva pelo superadmin.

- [x] Criar API do Runtime para listar módulos instalados.
- [x] Exibir ID, fornecedor, versão, compatibilidade e estado.
- [x] Criar ação de ativar módulo.
- [x] Criar ação de desativar módulo.
- [x] Não expor configuração JSON no painel; a governança pertence aos arquivos-fonte do módulo.
- [x] Validar superadmin em todas as mutações.
- [x] Impedir ativação de manifesto inválido ou incompatível.
- [x] Preservar SQLite ao desconectar.
- [x] Criar exclusão definitiva mediante confirmação explícita pelo ID do módulo.
- [x] Excluir o pacote `modules/<module-id>/` e todo o histórico `modules/.runtime/data/<module-id>/`.
- [x] Validar os dois caminhos exatos antes da exclusão e preservar outros módulos e o Runtime.
- [x] Permitir que vários módulos permaneçam ativos simultaneamente.

Critério de conclusão:

- [x] Usuário comum não consegue administrar módulos.
- [x] Ativar ou desativar um módulo não altera o estado dos demais.

---

## Fase 8 — Cognitive Dashboard

Objetivo: apresentar o trajeto descritivo sem cálculos subjetivos.

- [x] Criar serviço de leitura no próprio Módulo Educacional.
- [x] Declarar rótulo e ordem da interface no manifesto do módulo.
- [x] Produzir HTML, CSS e formatação do Trajeto dentro do próprio pacote.
- [x] Manter no Core somente descoberta, host e comportamentos `data-module-*` genéricos.
- [x] Consultar exclusivamente o SQLite educacional.
- [x] Usuário comum visualiza somente o próprio trajeto.
- [x] Superadmin pode filtrar usuário, período, projeto e documento.
- [x] Exibir linha do tempo das interações.
- [x] Exibir manifestações observadas.
- [x] Exibir estados insuficientes ou conflitantes.
- [x] Exibir projetos, documentos, conceitos e evidências relacionados.
- [x] Não exibir nota, percentual, ranking ou nível de domínio.
- [x] Não executar interpretação dentro do Dashboard.

Critério de conclusão:

- [x] Toda descrição exibida possui rastreabilidade até a interação e suas evidências.
- [x] O Dashboard continua funcional sem consultar tabelas especializadas no MySQL.

---

## Fase 9 — Testes obrigatórios

### Contratos

- [x] Manifesto válido é aceito.
- [x] Manifesto incompleto é rejeitado.
- [x] Entry point com path traversal é rejeitado.
- [x] Evento válido é aceito.
- [x] Evento incompatível é rejeitado sem afetar o Core.

### Fan-out e isolamento

- [x] Um evento é entregue a dois módulos ativos.
- [x] Módulo inativo não recebe evento.
- [x] Módulo não assinante não recebe evento.
- [x] Falha de um módulo não interrompe os demais.
- [x] Falha de módulo não modifica a resposta da consulta.

### Persistência

- [x] Cada módulo recebe SQLite diferente.
- [x] O mesmo evento não é duplicado no mesmo módulo.
- [x] Cursores avançam independentemente.
- [x] Registro e cursor são confirmados na mesma transação.
- [x] WAL, foreign keys e busy timeout estão ativos.
- [x] Atualização do pacote preserva `module.sqlite`.
- [x] Backup e restauração do SQLite preservam integridade.

### Segurança compatível com o MVP

- [x] `module.sqlite` não pode ser baixado por HTTP.
- [x] `state.json` não pode ser baixado por HTTP.
- [x] Diretórios internos não possuem listagem.
- [x] Usuário comum não conecta ou remove módulos.
- [x] Logs não expõem input completo, tokens ou segredos.

### Regressão

- [ ] Suíte atual permanece aprovada integralmente — permanece a falha preexistente do inventário local de `.env`; o teste live exige autorização externa.
- [x] Login e logout permanecem funcionais.
- [x] Consulta documental permanece funcional.
- [x] Upload e processamento documental permanecem funcionais.
- [x] Gestão de usuários, projetos e permissões permanece funcional.
- [x] Módulos desconectados não alteram o comportamento do Core.

---

## Fase 10 — Documentação e entrega

- [x] Documentar instalação manual de um módulo.
- [x] Documentar formato `module.json`.
- [x] Documentar EVA Module Contract v1.
- [x] Documentar APIs do EVA Module SDK.
- [x] Documentar conexão e desconexão pelo superadmin.
- [x] Documentar localização e backup dos SQLite.
- [x] Documentar política de retenção de `module_events`.
- [x] Documentar atualização sem sobrescrever dados institucionais.
- [x] Documentar remoção com preservação ou exclusão explícita dos dados.
- [x] Atualizar changelog, documentação e versão para a release 1.2.0 após aprovação.

Critério de conclusão:

- [x] Uma instituição consegue instalar, ativar, operar, atualizar e remover um módulo seguindo apenas a documentação.

---

## Ordem obrigatória de execução

1. Baseline.
2. Contratos.
3. Estrutura de diretórios.
4. Migration `module_events`.
5. Runtime genérico.
6. Testes do Runtime com fixtures.
7. Ponte mínima com o Core.
8. Módulo Educacional.
9. Administração pelo superadmin.
10. Cognitive Dashboard.
11. Regressão completa.
12. Documentação e homologação.

Nenhuma fase deve avançar enquanto o critério de conclusão da fase anterior estiver pendente, exceto por ajustes documentais sem efeito operacional.

## Definição final de pronto

- [x] O Core continua operacional com zero módulos instalados.
- [x] Dois ou mais módulos podem funcionar simultaneamente.
- [x] Nenhuma tabela atual foi alterada.
- [x] `module_events` é a única tabela adicional no MySQL.
- [x] Cada módulo possui dados e cursor independentes em SQLite.
- [x] Instalar um novo módulo não exige nova regra de domínio no Core.
- [x] Remover um módulo não afeta projetos, documentos, usuários ou outros módulos.
- [x] O Módulo Educacional produz somente interpretações descritivas baseadas em evidências.
- [x] Todas as regressões offline obrigatórias estão aprovadas, inclusive a configuração ambiental sem variáveis obsoletas.
- [ ] Executar `GoLiveReadinessTest.php --live` somente quando uma instituição autorizar a validação operacional com consumo real.
