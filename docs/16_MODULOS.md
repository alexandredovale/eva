# Módulos do EVA — instalação e operação

## Modelo operacional

Um módulo é um pacote independente colocado diretamente em `modules/<module-id>/`. Ele não pertence a usuário, projeto ou documento. Quando conectado, recebe os eventos que declarou no manifesto; vários módulos podem receber o mesmo evento.

O Core mantém somente a caixa postal neutra `module_events`, incluída diretamente em `database/schema.sql`. A migration `database/migrations/20260803_010_module_events.sql` permanece para atualizar bancos criados antes dessa consolidação. Cada módulo mantém cursor, histórico e resultados no arquivo privado `modules/.runtime/data/<module-id>/module.sqlite`. Se um deploy legado não tiver recebido a migration, a emissão falha de forma isolada e a resposta documental continua, mas nenhum evento modular novo é armazenado.

## Instalação manual

1. Audite e extraia o pacote para `modules/<module-id>/`.
2. Confirme que o nome da pasta é exatamente o `id` de `module.json`.
3. Abra **Módulos** no painel do superadmin. Um manifesto inválido aparecerá como inválido e não poderá ser conectado.
4. Clique em **Ativar**.
5. O dashboard do módulo incorpora automaticamente os eventos pendentes antes de apresentar seus dados.

Não coloque chaves, tokens ou senhas no pacote ou no manifesto. As credenciais do provedor continuam exclusivamente na configuração segura do EVA; módulos recebem somente a porta `LanguageModelInterface`.

## `module.json` — Contract v1

```json
{
  "id": "com.fornecedor.modulo",
  "name": "Nome do módulo",
  "vendor": "Fornecedor",
  "version": "1.0.0",
  "eva_contract": "1",
  "entrypoint": "bootstrap.php",
  "subscribed_events": ["interaction.completed"],
  "capabilities": [],
  "dashboard": {
    "enabled": true,
    "entrypoint": "dashboard.php",
    "order": 100
  },
  "storage": {"driver": "sqlite", "schema_version": 1}
}
```

O Runtime rejeita campos desconhecidos, IDs incompatíveis com a pasta, versões inválidas, caminhos absolutos, `..`, entrypoints ausentes e contratos diferentes de `1`.

Os schemas canônicos estão em:

- `modules/runtime/contracts/module-manifest.schema.json`;
- `modules/runtime/contracts/module-event.schema.json`.
- `modules/runtime/contracts/module-dashboard.schema.json`.
- `modules/runtime/contracts/module-action.schema.json`.

## EVA Module SDK

O entrypoint retorna uma implementação de `Eva\ModuleRuntime\ModuleInterface`:

```php
interface ModuleInterface
{
    public function id(): string;
    public function install(ModuleContext $context): void;
    public function handle(ModuleEvent $event, ModuleContext $context): void;
}
```

`ModuleContext` fornece:

- `manifest`: manifesto validado e imutável;
- `storage`: PDO SQLite exclusivo do módulo;
- `core`: API de leitura do Core limitada às capacidades declaradas;
- `language`: geração JSON por IA sem exposição da chave ao módulo.
- `query`: consulta documental escopada ao ator autenticado, disponível somente com `core.query.scoped` em contextos interativos.

Interfaces opcionais:

- `DashboardModuleInterface`: devolve `contract`, `html` e `css` pelo endpoint genérico de dashboard.
- `ModuleAccessInterface`: decide se o ator pode descobrir, abrir e executar o módulo; o superadmin mantém acesso administrativo.
- `ModuleActionInterface`: recebe ações autenticadas e devolve `eva.module.action/1` com um dashboard atualizado.

O Core não contém menus, textos, estilos, IDs ou renderizadores de um módulo específico. Quando um módulo ativo declara `dashboard.enabled`, a rota autenticada `GET /api/modules` publica somente seu descritor genérico (`id`, `name`, `order`). O frontend monta a navegação a partir desses descritores e entrega a resposta de `DashboardModuleInterface` a um único host visual.

O menu sempre utiliza exatamente `module.name`. Não existe `dashboard.label`, alias de navegação ou nome sobrescrito pelo Core. Assim, um manifesto com `"name": "Education"` produz automaticamente o menu **Education**.

A navegação não apresenta números ou índices visuais antes dos nomes. A posição continua determinada pelo layout fixo do Core e, entre módulos, por `dashboard.order`.

O HTML e o CSS pertencem ao pacote. O módulo pode manter sua folha em `assets/` e devolvê-la pelo contrato de dashboard. O host aplica esse CSS com um nonce único emitido pelo servidor e autorizado pela CSP; `unsafe-inline` permanece proibido. Para comportamentos básicos sem script específico no Core, o contrato visual reconhece atributos declarativos genéricos:

- `data-module-filter`: filtro remoto enviado novamente ao dashboard;
- `data-module-refresh`: atualização da interface;
- `data-module-content-filter` e `data-module-entry`: filtragem local;
- `data-module-accordion-toggle`: abertura exclusiva de um item.
- `data-module-action-form` e `data-module-action`: envio autenticado de formulários para uma ação do próprio módulo.

O dashboard deve retornar o contrato `eva.module.dashboard/1`. Um pacote removido leva consigo toda a apresentação que produzia; o host genérico permanece vazio e não conserva conhecimento do domínio removido.

## Ações interativas e autorização

Um módulo interativo implementa `ModuleActionInterface`. Formulários declarativos são enviados por:

```text
POST /api/modules/<module-id>/actions/<action-id>
```

O envelope contém somente `request_id` e `input`. O identificador permite que o próprio módulo implemente idempotência no SQLite. A resposta usa `eva.module.action/1`, inclui obrigatoriamente um novo `eva.module.dashboard/1` e pode incluir uma notificação neutra. O host não executa JavaScript fornecido pelo pacote.

Antes de listar o menu, abrir o dashboard ou executar uma ação, o Runtime consulta `ModuleAccessInterface` quando implementada. Módulos antigos permanecem acessíveis como antes; o superadmin sempre conserva o acesso necessário para configuração e recuperação.

Com a capacidade `core.query.scoped`, uma ação pode chamar `ModuleContext::scopedQuery()` para reutilizar o RAG do Core. O ator fica vinculado pelo Runtime e não pode ser substituído pelo módulo. Projetos e documentos são novamente autorizados pelo `ScopeAccessService`, e instruções complementares permanecem subordinadas às regras-base, evidências, citações e contrato de resposta do EVA.

Campos sensíveis são recusados recursivamente. O payload de entrada possui limite de 128 KiB; HTML e CSS retornados também possuem limites próprios. Perfis, preferências, histórico e demais relações de domínio continuam pertencendo ao SQLite privado do módulo, sem novas tabelas no MySQL.

Um módulo não pode chamar outro módulo. O processamento deve ser idempotente por `event_id`.

## Evento `interaction.completed`

```json
{
  "event_id": "EVA-EVT-0123456789ABCDEF01234567",
  "event_type": "interaction.completed",
  "contract_version": 1,
  "occurred_at": "2026-08-03T15:30:00-03:00",
  "actor": {"user_id": 18, "role": "user"},
  "scope": {
    "projects": [{"id": 3, "name": "Projeto A"}],
    "documents": [{"id": 12, "public_id": "EVA-D000012", "title": "Documento A"}]
  },
  "interaction": {
    "current_input": "Pergunta atual",
    "contextual_input": "Pergunta atual e contexto conversacional usado pelo Core",
    "answer": "Resposta final"
  },
  "evidences": [{"id": "EVA-E000120", "document": "Documento A"}],
  "limitations": []
}
```

O payload máximo é 1 MB. Chaves como `password`, `secret`, `token`, `api_key` e `authorization` são recusadas em qualquer nível.

## Consumo e retenção

Consumo único:

```powershell
C:\xampp\php\php.exe modules\runtime\bin\consume.php --limit=50
```

Consumo até esvaziar a caixa postal:

```powershell
C:\xampp\php\php.exe modules\runtime\bin\consume.php --limit=50 --drain
```

Esse comando pode ser agendado pelo Agendador de Tarefas ou cron. Para cada item já existente na caixa postal do Core, o módulo confirma no mesmo SQLite privado o processamento, o registro idempotente do evento e o avanço do cursor em uma única transação.

O agendamento é opcional para módulos que possuem dashboard: ao abrir ou atualizar o dashboard, o Runtime executa uma passagem de recuperação antes da leitura. O comando periódico continua disponível para processar eventos antecipadamente e para módulos sem interface de dashboard.

Remoção explícita de eventos anteriores ao período informado:

```powershell
C:\xampp\php\php.exe modules\runtime\bin\prune.php --days=90 --confirm
```

O histórico definitivo permanece no SQLite de cada módulo. Defina a retenção acima da maior janela aceitável de indisponibilidade de um módulo.

## Backup, atualização e remoção

1. Desative o módulo no painel.
2. Gere e verifique o backup:

```powershell
C:\xampp\php\php.exe modules\runtime\bin\backup.php --module=com.fornecedor.modulo
```

3. Para atualizar, substitua somente `modules/<module-id>/`. Nunca copie dados para dentro do pacote.
4. Reconecte o módulo. O mesmo `module.sqlite` será reutilizado.

Desativar nunca exclui dados. Para uma remoção definitiva, clique em **Excluir** e digite exatamente o ID solicitado no modal do sistema. Após a confirmação, o Runtime remove:

- `modules/<module-id>/`, com todo o pacote executável;
- `modules/.runtime/data/<module-id>/`, com `module.sqlite`, WAL, backups locais e todo o histórico;
- o registro de ativação em `modules/.runtime/state.json`.

A exclusão é irreversível e não gera backup automaticamente. Execute `backup.php` antes dela quando houver necessidade institucional de arquivamento.

## Módulo Education de referência

O pacote `com.eva.education` observa e interpreta `interaction.completed` no próprio processamento transacional do evento. Não há cron ou comando manual no fluxo normal. O Runtime confirma conjuntamente a interação, seu estado final, o registro idempotente do evento e o avanço do cursor. A interação termina como `completed` quando a análise é validada ou como `failed` se o módulo ou o provedor falhar; `pending` não é confirmado como estado final desse fluxo.

A governança ativa possui somente três dimensões descritivas: `conceptual_articulation`, `evidence_use` e `contextual_connection`. A antiga dimensão redundante `question_refinement` foi retirada. O schema 2 do SQLite remove essa observação de interpretações antigas sem apagar interações, perguntas, respostas, evidências, conceitos ou as demais observações.

Os textos, estados e rótulos exibidos no trajeto são produzidos no idioma de `current_input`. Os identificadores internos de dimensão e estado permanecem canônicos e não são apresentados quando os rótulos localizados estão disponíveis.

O rodapé de uma consulta direta omite o campo técnico de escopo e apresenta somente `Documento · Referências Diretas · Conceitos`. Rótulos produzidos pelo provedor são normalizados para texto humano com espaços, e o presenter interno do módulo também humaniza registros antigos que contenham underscore. `Referências Diretas` representa somente as referências explicitamente identificadas pelo Core e não deve ser apresentado como extração de conceitos.

O próprio pacote produz a interface interna de trajeto, seus estilos, os cards em acordeão, a formatação de data e os campos de filtro. No card de observação, o estado positivo padrão `observed` não é repetido no título; estados que alteram a leitura — ausência, insuficiência ou conflito — permanecem explícitos. As evidências são apresentadas em linha própria abaixo da descrição. O frontend do EVA recebe somente o nome `Education` pela descoberta genérica e não conhece o ID ou o domínio educacional. Desativar ou excluir o pacote remove o descritor da descoberta e, consequentemente, sua guia.

### Extração conceitual linguística

O Interpreter produz uma análise linguística versionada dentro de `observations_json`, sem nova tabela. A estrutura contém:

- unidades extraídas de trechos literais da pergunta ou da resposta;
- papéis canônicos `subject`, `predicate`, `object`, `complement`, `predicative`, `adjective` e `adverbial_modifier`;
- relações formadas exclusivamente por identificadores dessas unidades;
- conceitos normalizados obrigatoriamente vinculados às unidades linguísticas das quais foram derivados;
- referências de evidência limitadas aos IDs presentes no evento.

O validador descarta unidades ausentes da fonte declarada e remove relações ou conceitos que dependam delas. A análise somente é aceita se, após essa normalização, conservar a cobertura obrigatória de pergunta e resposta. IDs de evidência inexistentes, duplicidades e qualquer campo valorativo continuam sendo recusados. `Referências diretas` e `Conceitos` permanecem campos distintos no Dashboard.

Pergunta e resposta constituem conjuntamente o objeto da interação. A análise somente é concluída quando contém unidades linguísticas das duas fontes e quando o conjunto de conceitos referencia ao menos uma unidade de `current_input` e uma unidade da resposta. Se o provedor omitir a cobertura conceitual de uma das fontes, o validador a completa somente a partir de um núcleo linguístico já ancorado e validado — sujeito, predicado, objeto, complemento ou predicativo. Dessa forma, a resposta pode ampliar a pergunta, mas não substituí-la na trilha conceitual.

O comando abaixo permanece somente para recuperar interações antigas que ainda estejam pendentes:

```powershell
C:\xampp\php\php.exe modules\com.eva.education\bin\process.php --live --limit=10
```

O comando recusa chamadas sem `--live`, e o servidor também exige `AI_LIVE_ENABLED=true`. A governança aceita somente versão de protocolo, taxonomia, dimensões observáveis e política de evidências. Pontuações, pesos, notas, percentuais, confiança, níveis de domínio e rankings são recusados.

Os únicos estados de observação são `observed`, `not_observed`, `insufficient_evidence` e `conflicting_evidence`.

## Distribuição e ecossistema

O EVA Module Contract permite que instituições, desenvolvedores contratados e comunidades distribuam conectores como produtos independentes. Um pacote pode ser entregue diretamente, publicado em repositório próprio ou futuramente oferecido em marketplace. A instalação permanece manual e sob autoridade do superadmin da instituição self-hosted.

Um conector distribuível deve conter somente código, manifesto, assets, migrations internas e documentação do pacote. Nunca deve incluir:

- `.env`, tokens ou chaves de API;
- `module.sqlite`, WAL, logs ou histórico institucional;
- snapshots de usuários, projetos, documentos ou eventos reais;
- dependência de um ID de projeto específico;
- alteração manual no HTML, CSS ou JavaScript do Core.

Essa fronteira permite instalar módulos futuros que o EVA ainda não conhece. O Core reconhece o contrato e as capacidades declaradas, não o domínio do pacote.

## Proteção HTTP

Os `.htaccess` de `modules/` e `modules/.runtime/` negam acesso web direto, listagem de diretórios e execução na área de dados. Em Apache, manifesto, PHP interno, `state.json` e SQLite devem responder HTTP 403.
