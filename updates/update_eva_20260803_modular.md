# EVA Platform Architecture

# Arquitetura Modular da Plataforma EVA

## Objetivo

A plataforma EVA passará a possuir um núcleo imutável (EVA Core) e uma camada de módulos especializados, totalmente independentes entre si, capazes de serem conectados ou removidos sem qualquer alteração na arquitetura principal.

Zero, um ou vários módulos poderão permanecer conectados simultaneamente. Cada módulo possui finalidade, governança, persistência e ciclo de vida próprios.

## Posição hierárquica dos módulos

A escala arquitetural de referência é:

- Core
  - Camada de Módulos
    - Projetos observados
      - Documentos observados

Essa representação indica alcance arquitetural, e não propriedade, contenção ou dependência relacional. O módulo está posicionado imediatamente após o Core porque recebe eventos globais da plataforma, mas não pertence a projetos e projetos não pertencem a módulos.

Projetos e documentos continuam sendo recursos independentes, gerenciados pelo EVA Core. Eles aparecem nos eventos somente como referências contextuais dos trajetos observados pelos módulos.

Não deverá existir associação estrutural como `projects.module_id`, `documents.module_id` ou qualquer obrigação de vincular um usuário, projeto ou documento a determinado módulo.

Quando um módulo é desconectado, apenas sua ponte com o Dispatcher é encerrada. O EVA Core, os demais módulos, os usuários, os projetos e os documentos continuam funcionando normalmente.

## Instalação e ativação

Cada módulo será instalado como código independente dentro do diretório `modules/`, por meio do processo normal de implantação da plataforma. Um manifesto declarativo `module.json` informará:

- identificador único, código e nome do módulo;
- fornecedor ou desenvolvedor responsável;
- versão;
- versão do EVA Module Contract exigida;
- eventos assinados;
- ponto de entrada responsável pela integração;
- capacidades requeridas;
- requisitos de persistência.

O painel superadmin permitirá ativar, desativar e excluir separadamente cada módulo instalado. Não haverá campo de configuração JSON: a governança e os parâmetros próprios pertencem aos arquivos-fonte do pacote.

Na primeira implementação não haverá upload de código executável pelo painel. Arquivos `.md` ou `.json` poderão descrever contratos e configurações, mas não substituirão a implementação executável do módulo.

O EVA Module Runtime localizará os diretórios instalados, validará os respectivos manifestos e carregará somente módulos compatíveis e ativos. Não será necessária uma tabela SQL para registrar módulos.

O estado de ativação será preservado em arquivo próprio dentro de `modules/.runtime/`, sem modificar o manifesto original distribuído pelo desenvolvedor.

Os dados operacionais produzidos por cada módulo permanecerão em um diretório de dados exclusivo sob `modules/.runtime/data/` e não serão misturados ao banco principal do EVA Core.

Essa abordagem garante estabilidade, escalabilidade, baixo acoplamento e evolução contínua da plataforma.

Numa analogia prática, considerando consoles de video-game, seria como colocar um cartucho de jogo específico num console. Exemplo: um jogo = módulo, o console Super Nintendo (EVA) executa o jogo (MÓDULO).

Nenhuma alteração no console.

Cada cartucho foi desenvolvido para conectar ao console, podendo ser ativado ou não apenas pelo superadmin. Vários cartuchos conceituais podem permanecer conectados ao mesmo tempo, sem que um dependa ou interfira no outro.

---

## Módulos como Produtos Distribuíveis

O ecossistema modular permitirá que desenvolvedores, empresas e comunidades criem módulos próprios para uso interno, distribuição gratuita ou comercialização direta para instituições que utilizem a EVA.

Um módulo poderá ser entregue como diretório ou, futuramente, como um pacote de distribuição com a extensão `.evamodule`:

```text
com.fornecedor.nome-do-modulo/
├── module.json
├── bootstrap.php
├── src/
├── public/
├── assets/
└── .htaccess
```

O pacote distribuível contém código e recursos do módulo, mas não contém dados operacionais da instituição. O Runtime cria e preserva esses dados separadamente durante a instalação.

Exemplo de manifesto:

```json
{
  "id": "com.eva.education",
  "name": "Education",
  "vendor": "EVA",
  "version": "1.1.0",
  "eva_contract": "1",
  "entrypoint": "bootstrap.php",
  "subscribed_events": [
    "interaction.completed"
  ],
  "capabilities": [
    "ai.language.generate",
    "core.read.users"
  ],
  "dashboard": {
    "enabled": true,
    "entrypoint": "dashboard.php",
    "order": 80
  },
  "storage": {
    "driver": "sqlite",
    "schema_version": 1
  }
}
```

O mesmo contrato atenderá dois cenários sem diferença arquitetural:

- módulos criados por desenvolvedores e comercializados em um marketplace;
- módulos exclusivos desenvolvidos por profissionais contratados diretamente pela instituição.

A instalação do pacote equivale a inserir um cartucho compatível no console EVA. O módulo conhece o EVA Module Contract, mas não deve depender da estrutura interna do EVA Core.


---


Exemplo real:

Arquitetura Geral

EVA Platform
│
├── EVA Core
│
└── Module Dispatcher
    │
    ├── Módulo Educacional
    │
    ├── Módulo Empresarial
    │
    ├── Módulo Institucional
    │
    ├── Módulo Compliance
    │
    ├── Módulo Pesquisa Científica
    │
    └── Outros módulos futuros...

O EVA Core permanece responsável exclusivamente pelas funcionalidades fundamentais da plataforma.

Todos os demais recursos especializados deverão ser implementados como módulos totalmente independentes, capazes de compreender os contratos emitidos pelo sistema.

O Module Dispatcher realiza distribuição em fan-out: um mesmo evento pode ser entregue a zero, um ou vários módulos ativos que tenham declarado interesse naquele tipo de evento.

Cada módulo recebe o evento de forma independente. A falha de um módulo não interrompe o Core, não altera a resposta ao usuário e não impede a execução dos demais módulos.

---

## EVA Core

O EVA Core constitui a base tecnológica da plataforma já construída e IMUTÁVEL e não possui conhecimento específico sobre educação, empresas, instituições ou qualquer domínio de negócio.

Suas responsabilidades são:

- gerenciamento de usuários;
- gerenciamento de projetos;
- indexação documental;
- processamento de embeddings;
- memória persistente;
- CNodes;
- recuperação semântica (RAG);
- gerenciamento das camadas cognitivas;
- geração de respostas pela IA;
- gerenciamento das evidências;
- APIs públicas;
- contratos de eventos;
- infraestrutura da plataforma.

O EVA Core nunca executa avaliações pedagógicas nem interpreta a evolução cognitiva dos usuários.

O EVA Core também não conhece regras internas, tabelas ou resultados especializados de nenhum módulo. Sua responsabilidade modular limita-se a emitir eventos neutros, versionados e padronizados por meio do Module Dispatcher.

---

## EVA Module Runtime

O EVA Module Runtime constitui a camada oficial entre o EVA Core e os módulos instalados.

Suas responsabilidades são:

- localizar módulos no diretório `modules/`;
- ler e validar o `module.json` de cada pacote;
- verificar compatibilidade com a versão do EVA Module Contract;
- ativar, desativar e excluir módulos;
- identificar os eventos assinados;
- distribuir eventos em fan-out;
- disponibilizar as APIs e capacidades declaradas pelo contrato;
- isolar falhas para preservar o Core e os demais módulos;
- disponibilizar APIs oficiais de consulta ao Core;
- integrar dashboards próprios quando declarados;
- preservar o estado operacional do módulo.

Estrutura inicial sugerida:

```text
modules/
├── runtime/
│   ├── ModuleBridge.php
│   ├── ModuleRegistry.php
│   ├── ModuleDispatcher.php
│   ├── ModuleEvent.php
│   └── ModuleInterface.php
├── .runtime/
│   ├── state.json
│   ├── .htaccess
│   └── data/
│       ├── com.eva.education/
│       │   └── module.sqlite
│       └── outros-modulos/
├── com.eva.education/
└── outros-modulos/
```

A única integração técnica admitida no Core será uma ponte genérica e estável para emissão dos contratos. Nenhuma regra específica de módulo será adicionada aos arquivos do Core.

Depois de estabelecida essa ponte, instalar novos módulos exigirá somente adicionar ou remover pacotes dentro de `modules/`.

### EVA Module SDK

Os desenvolvedores utilizarão interfaces estáveis fornecidas pelo Runtime, em vez de consultar diretamente tabelas internas do Core.

Exemplo conceitual:

```php
$event->actor();
$event->projects();
$event->documents();
$event->evidences();

$eva->users()->find($userId);
$eva->documents()->find($documentId);
$eva->evidences()->find($evidenceId);
```

Essa separação permite que a implementação interna do Core evolua sem quebrar módulos compatíveis com o mesmo contrato público.

---

## Contrato de Eventos dos Módulos

Os módulos comunicam-se com o EVA Core exclusivamente por contratos e eventos padronizados.

Exemplo simplificado de evento:

```json
{
  "event_id": "EVA-EVT-000001",
  "event_type": "interaction.completed",
  "contract_version": 1,
  "occurred_at": "2026-08-03T15:30:00-03:00",
  "actor": {
    "user_id": 18,
    "role": "user"
  },
  "scope": {
    "projects": [
      {
        "id": 3,
        "name": "Projeto A"
      }
    ],
    "documents": [
      {
        "id": 12,
        "public_id": "EVA-D000012",
        "title": "Documento A"
      }
    ]
  },
  "interaction": {
    "current_input": "Pergunta atual",
    "contextual_input": "Pergunta atual e contexto conversacional usado pelo Core",
    "answer": "Resposta produzida pela EVA"
  },
  "evidences": [
    {
      "id": "EVA-E000120"
    }
  ],
  "limitations": []
}
```

O contrato não contém regras pedagógicas, empresariais, institucionais ou de compliance. Cada módulo interpreta o evento conforme sua finalidade e sua própria governança.

Cada módulo declara quais eventos deseja consumir. Por exemplo:

```json
{
  "module": "education",
  "subscribed_events": [
    "interaction.completed"
  ]
}
```

```json
{
  "module": "compliance",
  "subscribed_events": [
    "interaction.completed",
    "document.uploaded",
    "user.permissions.changed"
  ]
}
```

O Dispatcher entrega o mesmo evento a todos os módulos ativos que o assinam. Nenhum módulo chama outro módulo, conhece suas regras ou depende de sua ordem de execução.

---

## Módulo Educacional

O primeiro módulo oficial da plataforma será o Módulo Educacional.

Quando conectado, ele atua globalmente sobre os eventos elegíveis da plataforma. Seu objetivo é transformar interações realizadas dentro do EVA em registros estruturados e evidências descritivas da aprendizagem, sem alterar qualquer componente do EVA Core e sem atribuir notas, pesos, rankings ou valores subjetivos às interações pedagógicas.

O Módulo Educacional não é ativado por usuário, projeto ou documento. Ele observa os trajetos dos usuários através de todos os projetos e documentos presentes nos eventos recebidos enquanto estiver ativo.

Projetos, documentos, conceitos, evidências e fontes constituem coordenadas contextuais do trajeto pedagógico. Nenhum desses elementos possui vínculo de propriedade com o módulo.

O módulo será composto por quatro componentes independentes.

---

1. Learning Observer

Objetivo

Observar continuamente todas as interações realizadas pelo usuário.

Responsabilidades

- consumir eventos emitidos pelo EVA Core;
- transformar cada interação em um pacote cognitivo estruturado;
- identificar o usuário responsável pela interação;
- registrar os projetos e documentos presentes no contexto do evento;
- identificar conceitos mobilizados;
- registrar obras consultadas;
- registrar evidências utilizadas;
- registrar padrões de raciocínio;
- preservar a continuidade do trajeto do usuário entre diferentes projetos e documentos;
- produzir um JSON Cognitivo padronizado.

O Learning Observer nunca realiza avaliações.

Sua única responsabilidade é observar e estruturar informações.

---

2. Learning Interpreter

Objetivo

Interpretar os pacotes cognitivos produzidos pelo Learning Observer.

Responsabilidades

- receber o JSON Cognitivo;
- aplicar o protocolo de interpretação definido pela Governança;
- identificar manifestações cognitivas observáveis;
- produzir descrições cognitivas vinculadas às respectivas evidências;
- registrar recorrências, transições e padrões para acompanhamento longitudinal;
- preservar evidências insuficientes ou conflitantes sem forçar uma conclusão.

O Learning Interpreter não atribui notas, pesos, mérito, domínio ou valor às interações observadas.

O Learning Interpreter também não define qual protocolo pedagógico será utilizado.

Essa definição pertence exclusivamente à camada de Governança.

---

3. Pedagogical Governance

Objetivo

Definir os protocolos pedagógicos e o vocabulário de observação que serão utilizados pelo Learning Interpreter.

A Governança não executa avaliações diretamente e não atribui valores subjetivos às interações pedagógicas.

Sua função consiste em fornecer o conjunto de protocolos, taxonomias, critérios observáveis, requisitos de evidência e regras de interpretação descritiva adotados por cada instituição.

A Governança define como as evidências devem ser identificadas, registradas, contextualizadas, interpretadas e apresentadas. Ela não determina quanto uma evidência vale e não utiliza notas, pesos, rankings ou agregações valorativas.

Cada instituição poderá adotar sua própria política educacional por meio dos arquivos-fonte do módulo, sem modificar qualquer componente do EVA Core.

Exemplo de contrato de Governança:

```yaml
governance:
  protocol: observational
  taxonomy_version: 2

  observable_dimensions:
    - remember
    - understand
    - apply
    - analyze
    - evaluate
    - create

  evidence_policy:
    provenance_required: true
    source_reference_required: true
    interaction_context_required: true
    reasoning_trace_required: false

  interpretation_policy:
    mode: descriptive
    numeric_scoring: false
    weighted_aggregation: false
    ranking: false
    automatic_judgment: false

  longitudinal_policy:
    preserve_history: true
    register_recurrence: true
    register_transitions: true
    allow_human_review: true

  output_states:
    - observed
    - not_observed
    - insufficient_evidence
    - conflicting_evidence
```

Os estados de saída possuem significado estritamente descritivo:

- `observed`: existe evidência correspondente à manifestação analisada, sem significar que a interação seja boa, correta ou superior;
- `not_observed`: a manifestação não foi identificada na interação analisada, sem significar incapacidade do usuário;
- `insufficient_evidence`: as evidências disponíveis não permitem produzir uma descrição fundamentada;
- `conflicting_evidence`: existem evidências divergentes, que devem ser preservadas sem aplicação de médias, compensações ou conclusões forçadas.

A Governança representa um contrato institucional.

Dessa forma, diferentes organizações poderão utilizar protocolos e taxonomias pedagógicas distintas mantendo exatamente a mesma arquitetura tecnológica.

A Governança define o vocabulário e o protocolo de leitura. O módulo identifica e organiza evidências. Nenhuma camada atribui valor, peso ou mérito à interação.


---

4. Cognitive Dashboard

Objetivo

Apresentar visualmente as evidências cognitivas e seu histórico descritivo produzidos pelo módulo.

Responsabilidades

- exibir manifestações cognitivas observadas;
- apresentar linhas do tempo e recorrências;
- apresentar trilhas cognitivas transversais entre projetos e documentos;
- apresentar conceitos mobilizados e fontes consultadas;
- apresentar evidências utilizadas;
- apresentar estados de evidência insuficiente ou conflitante;
- apresentar relatórios para professores e alunos.

O Dashboard nunca executa cálculos, avaliações ou ponderações.

Toda informação exibida é proveniente do Learning Interpreter e permanece vinculada às evidências que a sustentam.

---

## Persistência dos Módulos

O MySQL atual continuará sendo o banco exclusivo do EVA Core. Usuários, projetos, documentos, evidências, CNodes e demais estruturas existentes permanecerão exatamente onde estão.

Os módulos não criarão tabelas especializadas nesse banco. Cada módulo utilizará um banco SQLite independente, armazenado em um único arquivo `module.sqlite` sob seu diretório de dados no Runtime.

```text
modules/.runtime/data/
├── com.eva.education/
│   └── module.sqlite
├── com.fornecedor.compliance/
│   └── module.sqlite
└── com.fornecedor.enterprise/
    └── module.sqlite
```

SQLite é um banco relacional embarcado. O arquivo `module.sqlite` pode conter tabelas, índices, transações e chaves estrangeiras sem exigir servidor, porta, usuário ou senha próprios.

A extensão `pdo_sqlite` será requisito do EVA Module Runtime. A instalação atual da EVA já possui esse driver habilitado.

### Separação das responsabilidades

| Armazenamento | Responsabilidade |
|---|---|
| MySQL atual | Dados e operações do EVA Core |
| `module_events` no MySQL | Caixa postal neutra e compartilhada de eventos padronizados |
| `module.sqlite` | Histórico, configuração operacional, cursor e resultados exclusivos de um módulo |

O `module.sqlite` do Módulo Educacional armazenará somente dados pertencentes ao próprio módulo. Ele não substituirá nem duplicará integralmente as tabelas do Core.

Uma estrutura inicial simples poderá conter:

```text
interactions
interpretations
event_cursor
module_settings
processing_failures
```

Exemplo conceitual de `interactions`:

```text
id
event_id
user_id
occurred_at
input
answer
scope_json
evidences_json
limitations_json
```

Exemplo conceitual de `interpretations`:

```text
id
interaction_id
protocol
interpretation_json
processing_status
processed_at
last_error
```

Exemplo conceitual de `event_cursor`:

```text
module_code
last_event_row_id
updated_at
```

As referências a usuários, projetos, documentos e evidências serão recebidas pelo EVA Module Contract e registradas como identificadores e snapshots contextuais. Não existirão chaves estrangeiras entre o SQLite do módulo e o MySQL do Core.

Projetos e documentos registram por onde o usuário transitou. Eles não conectam o módulo estruturalmente a esses recursos.

### Única tabela adicional no banco principal

Será admitida somente uma nova tabela neutra no MySQL:

```text
module_events
-------------
id
event_id UNIQUE
event_type
contract_version
payload_json
created_at
```

Essa tabela funciona exclusivamente como caixa postal do EVA Module Contract. Ela não registra configurações, interpretações pedagógicas ou dados internos de módulos.

Somente o EVA Module Runtime acessa `module_events` no MySQL. Os módulos recebem objetos do contrato entregues pelo Dispatcher e não consultam diretamente o banco do Core.

O Core grava cada evento uma única vez. Zero, um ou vários módulos podem consumir esse mesmo registro de forma independente.

Cada módulo mantém em seu próprio `module.sqlite` o último `module_events.id` consumido, registrado como `last_event_row_id`. Dessa forma, módulos diferentes podem avançar em ritmos distintos sem criar colunas ou controles específicos no banco principal.

O identificador do evento também será único dentro do SQLite para impedir que o mesmo módulo processe uma interação duas vezes.

### Fluxo de persistência

1. O Core conclui uma operação e produz um contrato versionado.
2. O evento é inserido uma única vez em `module_events`.
3. O Module Dispatcher identifica os módulos ativos que assinam aquele evento.
4. O Runtime consulta o cursor de cada módulo e entrega somente os eventos com `module_events.id` posterior a `last_event_row_id`.
5. O módulo grava os dados necessários em seu `module.sqlite`.
6. O módulo atualiza seu `last_event_row_id` na mesma transação.
7. O dashboard do módulo consulta exclusivamente seu banco SQLite.

Se um módulo estiver desconectado, ele não processará novos eventos. Ao ser reconectado, sua política poderá continuar a partir do cursor anterior ou começar a partir do evento vigente, conforme sua configuração local.

É esperado que módulos diferentes armazenem cópias próprias dos elementos do evento de que necessitam. Essa duplicação é intencional e garante independência: remover o SQLite de um módulo não afeta os registros dos demais.

### Retenção da caixa postal

A tabela `module_events` constitui transporte persistente, não arquivo histórico definitivo. O histórico especializado permanece nos bancos SQLite.

O Runtime aplicará uma política configurável de retenção aos eventos antigos. Um módulo recém-instalado iniciará, por padrão, no evento vigente; eventual reprocessamento histórico dependerá de eventos ainda retidos e de ação explícita do superadmin.

Se o cursor de um módulo estiver anterior ao primeiro evento ainda disponível, o Runtime registrará a lacuna operacional e reiniciará o consumo no primeiro evento existente, sem fabricar dados ausentes.

### Concorrência e integridade

O Runtime deverá abrir cada SQLite com as seguintes configurações:

```sql
PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 5000;
```

O modo WAL permite leituras do dashboard enquanto novas interações são gravadas. O `busy_timeout` reduz falhas causadas por pequenas disputas de escrita.

SQLite é adequado ao cenário self-hosted institucional, no qual cada interação concluída normalmente gera uma gravação e o dashboard realiza múltiplas leituras.

Se uma instalação futura operar com múltiplos servidores web, armazenamento em rede ou volume muito elevado de escritas simultâneas, o contrato do módulo poderá permitir outro driver de armazenamento. Essa possibilidade não integra o MVP.

### Atualização, remoção e backup

Código distribuível e dados institucionais permanecerão separados. Atualizar o diretório do pacote não poderá sobrescrever `modules/.runtime/data/<module-id>/module.sqlite`.

Desativar um módulo preserva seu SQLite. A ação **Excluir** exige confirmação explícita pelo ID e remove definitivamente tanto `modules/<module-id>/` quanto `modules/.runtime/data/<module-id>/`, incluindo todo o histórico. A exclusão não produz backup automático.

O backup da EVA deverá incluir `modules/.runtime/data/`. Bancos em uso deverão ser copiados por procedimento compatível com SQLite/WAL para preservar transações ainda presentes no arquivo de log.

Nenhuma tabela atual do EVA Core será alterada e nenhuma tabela especializada de módulo será criada no banco principal.

---

## Modelo de Confiança e Segurança Inicial

A EVA é uma aplicação self-hosted, baixada e administrada pela própria instituição. Não se trata de um serviço SaaS compartilhado entre instituições.

Somente o superadmin da instalação poderá adicionar, conectar, atualizar ou remover manualmente um módulo. A instalação representa uma decisão explícita de confiança da instituição no desenvolvedor e no código recebido.

Por esse motivo, a primeira implementação não exigirá:

- sandbox de PHP;
- containers individuais por módulo;
- isolamento entre processos;
- assinatura digital obrigatória;
- revisão centralizada de marketplace;
- sistema complexo de permissões em tempo de execução.

As capacidades declaradas no `module.json` servem inicialmente como contrato técnico e documentação do que o módulo utiliza, e não como uma camada complexa de sandbox.

A proteção inicial será compatível com o modelo atual de implantação e utilizará `.htaccess` para:

- impedir listagem de diretórios;
- bloquear acesso HTTP direto a `src/`, `modules/.runtime/`, configurações e arquivos internos;
- permitir exposição somente dos pontos públicos expressamente declarados;
- impedir execução web dentro de `modules/.runtime/data/`;
- proteger bancos SQLite, JSONs operacionais, logs e manifestos contra download direto.

O `.htaccess` protege a superfície web do diretório, mas não restringe o que um PHP autorizado pode executar no servidor. No modelo adotado, essa limitação é aceitável porque somente o superadmin instala manualmente módulos previamente confiados pela instituição.

Nenhum módulo deverá incluir ou expor chaves de API, tokens, senhas ou segredos do Core. Credenciais necessárias deverão permanecer na configuração segura da instalação.

Camadas adicionais de verificação poderão ser avaliadas futuramente caso a instalação automática de módulos de terceiros desconhecidos passe a fazer parte do produto. Elas não integram o escopo inicial.

---

## Fluxo Arquitetural

EVA Core
        │
        │ Eventos Padronizados
        ▼
┌──────────────────────────────────────────────┐
│           MySQL: module_events               │
└──────────────────────┬───────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────┐
│              Module Dispatcher               │
└──────────────────────┬───────────────────────┘
                       │ Fan-out
          ┌────────────┼────────────┐
          │            │            │
          ▼            ▼            ▼
┌───────────────┐ ┌───────────┐ ┌───────────────┐
│  Educacional  │ │Compliance │ │  Empresarial  │
└───────┬───────┘ └─────┬─────┘ └───────┬───────┘
        │               │               │
        ▼               ▼               ▼
 module.sqlite     module.sqlite     module.sqlite

Fluxo interno do Módulo Educacional:

┌─────────────────────────────────────────────┐
│  1. Learning Observer                       │
│                 │                           │
│                 ▼                           │
│  2. Learning Interpreter ◄── 3. Pedagogical │
│                               Governance    │
│                 │                           │
│                 ▼                           │
│  4. Cognitive Dashboard                     │
└─────────────────────────────────────────────┘

---

Princípios Arquiteturais

- O EVA Core permanece imutável.
- Todo módulo comunica-se exclusivamente por contratos e eventos padronizados.
- Nenhum módulo modifica diretamente o comportamento interno do Core.
- Zero, um ou vários módulos podem permanecer conectados simultaneamente.
- Um mesmo evento pode ser entregue a vários módulos ativos.
- Módulos não pertencem a usuários, projetos ou documentos.
- Projetos e documentos são referências contextuais dos eventos observados.
- Não existe dependência ou comunicação direta entre módulos.
- A falha de um módulo não interrompe o Core nem os demais módulos.
- A ordem de execução dos módulos não altera o resultado produzido pelo Core.
- Cada módulo possui sua própria governança e persistência.
- Cada módulo possui um banco SQLite independente e um cursor próprio de eventos.
- O banco MySQL do Core não recebe tabelas especializadas de módulos.
- Código distribuível e dados institucionais dos módulos permanecem separados.
- Cada módulo possui ciclo de vida independente.
- Cada módulo pode ser ativado ou removido sem impacto estrutural na plataforma.
- A desconexão de um módulo encerra somente o recebimento de novos eventos e preserva seus dados anteriores.
- A Governança é configurável por instituição.
- A arquitetura é orientada à observação, interpretação e evolução do conhecimento, e não apenas à geração de respostas.

---

## Implementação Inicial sem Overengineering

A implementação inicial deverá conter somente:

1. um diretório `modules/` para receber vários módulos independentes;
2. um `module.json` obrigatório em cada módulo;
3. um EVA Module Runtime para descoberta e carregamento dos pacotes;
4. um Module Dispatcher interno para distribuir eventos padronizados em fan-out;
5. um contrato versionado de eventos;
6. uma interface mínima para que cada módulo declare os eventos que assina e receba os respectivos pacotes;
7. um `module.sqlite` próprio para cada módulo em `modules/.runtime/data/<module-id>/`;
8. uma única tabela neutra `module_events`, sem alterar nenhuma tabela atual;
9. APIs administrativas para listar, ativar, desativar e excluir módulos;
10. APIs próprias de leitura para os resultados produzidos por cada módulo;
11. gestão manual dos módulos pelo superadmin;
12. proteção básica dos diretórios por `.htaccess`;
13. validação da disponibilidade de `pdo_sqlite` pelo Runtime.

O encaixe permanente no EVA Core é exclusivamente genérico: Dispatcher de eventos, descoberta autenticada das interfaces ativas e um host visual neutro. Depois desses contratos, novos módulos poderão ser instalados sem adicionar menus, IDs, estilos, renderizadores ou regras de domínio ao Core.

### Implementação concretizada em 3 de agosto de 2026

O MVP descrito neste documento foi materializado com os seguintes componentes:

- schemas do EVA Module Contract v1 em `modules/runtime/contracts/`;
- descoberta, validação, estado, fan-out, cursor e isolamento em `modules/runtime/src/`;
- única migration `database/migrations/20260803_010_module_events.sql`;
- consumidor e retenção por CLI em `modules/runtime/bin/`;
- recuperação automática dos eventos pendentes antes da leitura de um dashboard modular;
- bridge genérico após a consulta concluída, sem alterar o contrato público da resposta;
- despacho imediato do evento aos módulos ativos na mesma requisição, com falhas isoladas da resposta principal;
- painel superadmin limitado a listar, ativar, desativar e excluir módulos;
- dashboard modular genérico disponível aos atores autenticados;
- pacote de referência `modules/com.eva.education/` com Observer, Governance, Interpreter e Dashboard;
- SQLite educacional privado com WAL, foreign keys, busy timeout, cursor, interações, interpretações, configurações e falhas;
- separação entre `current_input` e `contextual_input` desde o navegador até o evento;
- proteção HTTP confirmada por respostas 403 para manifesto, PHP interno e estado local;
- documentação operacional em `docs/16_MODULOS.md`.

A interpretação pedagógica é executada automaticamente pelo próprio módulo durante o processamento transacional de cada novo evento. O Core apenas aciona o Dispatcher genérico e não conhece regras educacionais. Não há dependência de cron ou comando manual no fluxo normal. O SQLite confirma conjuntamente a interação, seu estado final, a idempotência do evento e o avanço do cursor; portanto, `pending` não permanece como resultado confirmado. A chamada pode acrescentar o tempo de uma segunda operação de IA à interação, mas qualquer falha modular permanece isolada e não altera a resposta principal do EVA. O comando do Interpreter continua disponível apenas para recuperação explícita de registros antigos pendentes.

Toda saída humana do Interpreter — descrições, limitações, estados e rótulos do trajeto — deve utilizar o idioma identificado em `current_input`. Dimensões e estados canônicos permanecem como identificadores internos para validação da governança, acompanhados de rótulos localizados para apresentação.

O Dashboard deve distinguir o escopo efetivamente utilizado de associações indiretas. Em consultas diretas, o rodapé omite o campo técnico de escopo e apresenta `Documento · Referências Diretas · Conceitos`. Rótulos humanos são normalizados para remover underscores antes da persistência e novamente na apresentação para compatibilidade com registros anteriores. A trilha de conceitos é produzida separadamente pelo módulo mediante análise linguística estruturada, e não por inferência visual ou lista cega de palavras-chave.

Essa trilha conceitual foi concretizada no Interpreter v1.2.0 sem alteração SQL. Cada conceito é persistido no envelope versionado de `observations_json` e deve referenciar uma ou mais unidades linguísticas extraídas literalmente de `current_input` ou da resposta. As unidades declaram origem e papel sintático; as relações aceitam somente combinações compatíveis de sujeitos, predicados, complementos, predicativos, adjetivos e modificadores. O Dashboard apresenta os conceitos normalizados separadamente de `direct_references`.

A partir do Interpreter v1.2.1, pergunta e resposta são validadas como partes inseparáveis do objeto da interação. O processamento exige unidades linguísticas originadas nas duas fontes e cobertura conceitual de ambas; uma análise formada somente a partir da resposta é recusada.

No Interpreter v1.2.2, a complementação determinística de conceitos prioriza objetos, complementos, sujeitos e predicativos antes de predicados verbais genéricos. Conceitos canônicos coincidentes entre pergunta e resposta são unificados, preservando os identificadores das unidades de ambas as fontes.

Para suportar trajetos extensos, o pacote Education produz integralmente o HTML da sua interface por meio de `EducationDashboardPresenter` e mantém o layout em `modules/com.eva.education/assets/dashboard.css`. Nele permanecem `article.card.learning-entry`, acordeão, pesquisa local, rótulos pedagógicos e formatação `d-m-Y H:i:s`. Dentro das observações, evidências aparecem em uma linha própria abaixo da descrição; o estado `observed` é omitido por ser o padrão positivo, enquanto `not_observed`, `insufficient_evidence` e `conflicting_evidence` continuam visíveis por alterarem o significado do card. O host aplica a folha modular com nonce individual autorizado pela CSP, sem `unsafe-inline` e sem mover seletores educacionais para o Core.

O Core não possui condição, menu, função, estilo ou endpoint fixo para `com.eva.education`. Ele conhece somente o contrato `eva.module.dashboard/1`, lista descritores de todos os dashboards ativos por `GET /api/modules`, monta os links dinamicamente e fornece um host visual único. O texto do menu é sempre `module.name`; não existe alias `dashboard.label`, nem numeração visual antes dos itens. Filtros, atualização e acordeões são acionados por atributos `data-module-*` genéricos. Essa correção preserva o white label e garante que um desenvolvedor externo possa conectar um módulo desconhecido pelo EVA sem alterar `app.html`, `app.js` ou `app.css`.

### Consolidação em 4 de agosto de 2026

A arquitetura foi consolidada para a release EVA 1.2.0. O pacote de referência passou a usar o identificador neutro `com.eva.education` e o nome `Education`. Sua governança ativa observa somente articulação conceitual, uso de evidências e conexão contextual, sem notas, pesos ou valores subjetivos. A dimensão redundante `question_refinement` foi aposentada pelo schema 2, com migração que preserva as demais interpretações históricas.

O Interpreter responde no idioma detectado na pergunta e extrai conceitos do objeto completo pergunta + resposta mediante unidades linguísticas com origem e papel sintático declarados. O Dashboard apresenta cards em acordeão, filtro local dinâmico, datas `d-m-Y H:i:s`, evidências em linha própria e rótulos humanos sem identificadores técnicos. Em consultas diretas, exibe `Documento` e `Referências Diretas`; projetos ausentes não são apresentados como erro ou como associação inexistente.

No fluxo principal, o estado de espera `Consultando evidências…` recebeu três pontos amarelos animados, respeitando `prefers-reduced-motion`. Respostas rejeitadas continuam limitadas a três tentativas; da segunda em diante, o provedor recebe somente um código corretivo seguro e, quando necessário, o identificador de uma evidência já eleita. O aviso de acessibilidade causado por foco retido sob `aria-hidden` foi eliminado com retirada prévia de foco e uso de `inert` no painel oculto.

As variáveis `EVA_LANGUAGE_API_KEY`, `EVA_QUERY_API_KEY` e `EVA_SUMMARY_API_KEY` foram removidas por não possuírem consumidor. A instalação permanece com as credenciais efetivamente usadas por capacidade, sem código morto ou configuração órfã. Bancos SQLite, estado do Runtime, logs, arquivos enviados, dumps, backups e `.env` permanecem fora da distribuição pública.

Não serão necessários nesta etapa:

- vínculo de módulo com projeto ou documento;
- marketplace de módulos;
- upload de código executável;
- tabela SQL para registrar os módulos instalados;
- tabelas especializadas de módulos no banco principal;
- comunicação direta entre módulos;
- fila ou barramento externo de eventos;
- Redis, RabbitMQ ou serviço adicional de mensageria;
- uma tabela genérica misturando os resultados de todos os módulos;
- alterações nas respostas do Core para atender regras particulares de módulos;
- sandbox, container ou isolamento de processo para módulos instalados manualmente pelo superadmin.

O painel superadmin deverá apresentar cada módulo separadamente, permitindo que vários permaneçam ativos ao mesmo tempo:

```text
Módulo Educacional     ativo
Módulo Compliance      ativo
Módulo Empresarial     inativo
```

Conectar ou desconectar um desses módulos não altera o estado dos demais.

---

## Visão de Longo Prazo

O Módulo Educacional representa a primeira implementação da arquitetura modular do EVA.

A mesma estrutura permitirá o desenvolvimento e a execução simultânea de módulos especializados para diferentes domínios, preservando um núcleo tecnológico único, estável e extensível.

O EVA Module Contract permitirá que desenvolvedores independentes e comunidades criem módulos reutilizáveis, distribuídos diretamente a instituições ou comercializados futuramente em um marketplace.

O formato `.evamodule` representará um pacote instalável compatível com a plataforma. Sua instalação continuará sendo uma decisão local e manual do superadmin responsável pela EVA em cada instituição.

Essa abordagem posiciona o EVA como uma Plataforma Cognitiva Modular, capaz de atender diferentes contextos organizacionais por meio de módulos independentes, governáveis e interoperáveis.

---
