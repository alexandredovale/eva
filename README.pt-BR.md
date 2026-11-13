# EVA — Evidence Algorithm

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.21500611.svg)](https://doi.org/10.5281/zenodo.21500611)

**Versão atual:** `8.0.2`

**Destaque da versão:** o EVA 8.0.2 publica um benchmark reproduzível de cinco consultas que compara a recuperação do EVA ao contexto documental integral, completa os espelhos da documentação em português e inglês e atualiza a apresentação do produto para refletir somente os módulos presentes no repositório público.

O EVA é um sistema PHP neutro em relação a fornecedores para construir, organizar e consultar **memória cognitiva documental verificável**. O EVA (Evidence Algorithm) preserva a hierarquia dos documentos, armazena evidências literais das fontes e valida cada resposta contra as evidências primárias recuperadas para a consulta atual.

> Esta é a apresentação em português. A versão equivalente em inglês está em [`README.md`](README.md).

## Por que EVA

A proliferação de modelos de linguagem de larga escala tem imposto desafios sem precedentes à infraestrutura computacional global, manifestados no consumo intensivo de recursos hídricos e energéticos para resfriamento de data centers. A abordagem tradicional, que depende da manutenção de extensas janelas de contexto e do reprocessamento contínuo, exacerba a pegada de carbono e perpetua vulnerabilidades operacionais, como alucinações e ineficiências decorrentes do letramento digital deficitário. Em contrapartida, sistemas de Geração Aumentada por Recuperação (RAG) estabelecem um paradigma de eficiência cirúrgica ao desacoplar o armazenamento vetorial da inferência generativa e recuperar somente os fragmentos semânticos pertinentes, reduzindo o volume de tokens processados em cada interação. A técnica EVA amplia esse benefício: antes da recuperação, transforma a estrutura documental em unidades semânticas completas, literais e rastreáveis; durante a consulta, calcula uma fronteira local κq e aplica o Context Intelligence Engine (CIE) para refinar deterministicamente a população; depois da geração, aceita apenas respostas, citações e interações sustentadas pelas evidências eleitas. Assim, o RAG controla **quanto contexto** chega ao modelo, enquanto o EVA controla **como esse contexto é formado, selecionado e comprovado**. A atuação conjunta reduz processamento redundante, consumo energético e custos computacionais, ao mesmo tempo que aumenta precisão, auditabilidade e resistência a alucinações e às limitações de formulação do usuário. RAG e EVA tornam-se, portanto, componentes complementares para ecossistemas de inteligência artificial sustentáveis, confiáveis e operados sob controle documental.

Muitos sistemas de recuperação começam com cortes arbitrários de tokens e tentam reconstruir o contexto posteriormente. O EVA começa pela estrutura documental:

```text
Fonte → árvore normalizada → evidência primária literal → embeddings contextuais

Consulta semântica → roteamento local → evidências primárias completas → κq → CIE do núcleo superior → fontes primárias
      → uma resposta delimitada → validação local de citações e interações
```

## Como o EVA funciona

- Markdown, JSON e XML convergem para uma única árvore documental normalizada.
- Cada evidência operacional é literal, primária e vinculada à sua origem.
- Embeddings representam unidades semânticas completas e previamente organizadas, nunca cortes arbitrários de caracteres ou tokens.
- Consultas diretas, estruturais e amplas podem dispensar o embedding transitório da pergunta.
- Consultas conceituais e relacionais calculam a distribuição semântica inicial diretamente sobre embeddings validados de evidências primárias.
- A fronteira query-local κq emerge da distribuição completa de similaridades elegíveis; o núcleo superior passa pelos estágios de refinamento primário e multidocumental, sem Top-k semântico ou quantidade configurada de evidências.
- O CIE global mantém o núcleo (`s ≥ μ + σ`) como corte e acrescenta a convergência (`μ ≤ s < μ + σ`) somente como contexto complementar para o provedor de respostas.
- O modelo pode reter apenas o subconjunto relevante do contexto primário disponível. Cada perspectiva analítica ocupa um novo parágrafo, toda fonte mantida é citada onde contribui e inventários apenas citacionais são rejeitados.
- Gerações rejeitadas localmente são refeitas silenciosamente até o limite de três tentativas totais; somente a terceira falha consecutiva produz um erro genérico para o usuário.
- A geração é ignorada quando nenhuma evidência primária é recuperada.
- Interações `simetry` e `assimetry` existem apenas na consulta atual e exigem duas fontes primárias citadas com fragmentos literais.
- Fornecedores, endpoints, modelos e nomes de variáveis de credencial permanecem na configuração do ambiente.
- O uso real de IA fica desativado por padrão e requer duas confirmações explícitas.
- O chat aceita uma pergunta independente por vez, sem herdar input, população, embedding, CIE, resposta ou interação da consulta anterior.
- Perfis de resposta administrados pelo superadmin especializam o comportamento por projeto sem enfraquecer as regras de evidência, citação ou saída.
- Zero, um ou vários módulos conectores independentes podem observar interações concluídas sem acrescentar conhecimento de domínio ao Core.
- O produto white label inclui interface administrativa, API autenticada, fila, retomada explícita, métricas descritivas e auditoria sanitizada.

As Fases 1 a 5 estão funcionais e o roadmap original está concluído. O estado de cada entrega está em [Roadmap](docs/09_ROADMAP.md).

## Princípio cognitivo

Embeddings são gerados por unidades documentais previamente organizadas. Um `Cnode` é somente a compreensão transitória de uma interação semântica explícita entre evidências recuperadas. Ele não é entidade persistente, pontuação, aresta de grafo ou embedding e não possui identidade duradoura, nota de importância, confiança ou intensidade. A quantidade de interações retornadas é descritiva e não determina verdade, qualidade, prioridade ou relevância.

## Requisitos

- PHP 8.2 ou superior;
- MariaDB 10.4 ou MySQL compatível;
- extensões PHP `curl`, `dom`, `json`, `mbstring`, `pdo`, `pdo_mysql` e `pdo_sqlite`;
- Apache com `mod_rewrite` e `mod_headers`, ou outro servidor configurado para expor somente `public/`.

O projeto não possui dependência de runtime do Composer ou Node.js.

## Início rápido

1. Clone o repositório e entre no diretório:

   ```bash
   git clone https://github.com/alexandredovale/eva.git
   cd eva
   ```

2. Crie o arquivo local de ambiente:

   ```bash
   cp .env.example .env
   ```

   No PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Crie um banco vazio e importe o esquema público consolidado:

   ```bash
   mysql -u root -p -e "CREATE DATABASE eva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p eva < database/schema.sql
   ```

   Instalações existentes devem aplicar apenas as migrations ainda ausentes, na ordem dos nomes dos arquivos. A migration `010` permanece disponível para bancos criados antes da consolidação de `module_events` no esquema.

4. Revise o `.env`, que contém o inventário comentado das configurações reconhecidas, e ajuste aplicação, branding, banco, IA, consulta, ingestão e fila. Gere um token exclusivo de superadmin com pelo menos 24 caracteres, por exemplo:

   ```bash
   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
   ```

   Armazene o resultado apenas em `ADMIN_API_TOKEN` no `.env`. Nenhuma chave ou credencial deve ser gravada no código, na documentação, nos logs ou no repositório.

5. Configure o servidor para servir `public/` como raiz pública. No XAMPP, com o projeto em `htdocs`, o `.htaccess` da raiz encaminha rotas virtuais para `public/` e bloqueia acesso direto aos arquivos privados.

6. Mantenha `AI_LIVE_ENABLED=false` na instalação e nos testes offline. Acesse `/api/health` e depois `/`.

Consulte [Instalação](docs/18_INSTALACAO.md) e [Produto e implantação](docs/10_PRODUTO.md) para a sequência completa.

## Configuração de capacidades de IA

O EVA nomeia capacidades, não fornecedores:

- `EmbeddingProvider`;
- `QueryAnswerProvider`.

Configure cada capacidade no `.env` com o identificador do fornecedor, endpoint, modelo e o **nome** da variável de ambiente que contém a credencial. `config/ai.php` apenas lê essas variáveis genéricas. As classes da aplicação conhecem capacidades, e os testes usam provedores simulados sem consumir créditos.

## Ingestão e persistência documental

O endpoint `POST /api/documents` recebe `multipart/form-data` com `document`, um arquivo obrigatório `.md`, `.json` ou `.xml`, e `title`, um título opcional derivado do nome do arquivo quando ausente. O sucesso retorna HTTP `201`. Arquivos ausentes, incompletos, acima do limite, com multipart inválido ou conteúdo incompatível são rejeitados antes da persistência.

O parser recebe o conteúdo já lido e nunca acessa caminhos por conta própria:

```php
use Eva\Application\Ingestion\Parser\ParserFactory;

$parser = ParserFactory::forFilename($filename);
$document = $parser->parse($content, $title);
$normalizedTree = $document->toArray();
```

`DocumentIngestionService` seleciona o parser, armazena a fonte fora da pasta pública, persiste a árvore e gera evidências primárias literais. O limite padrão é 10 MB e pode ser ajustado por `DOCUMENT_MAX_BYTES`. A ingestão não produz embeddings; a construção cognitiva é uma etapa posterior e explícita.

## Construção cognitiva e recuperação

`EvidenceEmbeddingService` vetoriza exclusivamente evidências `primary:node_content` com título do documento, caminho, nó e conteúdo literal organizado. Antes de chamar o provedor, todas as unidades pendentes são validadas contra `AI_EMBEDDING_MAX_INPUT_TOKENS`, com margem preventiva de 10%. Lotes técnicos agrupam unidades completas e nunca as fragmentam. Unidades maiores exigem subdivisão estrutural real. Uma versão já existente para o mesmo modelo e hash é reutilizada antes de chamar o provedor.

Na consulta conceitual ou relacional, `DocumentContextRetriever` compara o embedding transitório do input com todas as evidências primárias elegíveis. A distribuição determina κq; quando não há ruptura identificável, a população completa segue ao primeiro CIE. Esse estágio calcula média, desvio padrão populacional e coeficiente de variação e encaminha o núcleo (`s ≥ μ + σ`), usando a convergência (`μ ≤ s < μ + σ`) como fallback somente se o núcleo estiver vazio. O CIE global mantém o corte e acrescenta sua convergência apenas como contexto complementar. Similaridades e estatísticas são transitórias.

`QueryAnswerProvider` pode declarar `simetry` ou `assimetry` na mesma chamada que produz a resposta. `DocumentQueryService` aceita uma interação somente quando os participantes pertencem ao contexto, foram citados e seus fragmentos existem literalmente nas evidências. Nada disso é persistido como `Cnode`.

## Governança das respostas por projeto

O superadmin pode atribuir a cada projeto um **Perfil de respostas** opcional. O perfil pode especializar público, papel assistencial, vocabulário, tom, foco ou apresentação, mas permanece subordinado às regras-base de evidência primária, citação, limitação, validação de interações e saída JSON.

| Seleção no chat | Obras consultadas | Perfis aplicados |
|---|---|---|
| Uma ou mais obras marcadas individualmente | Somente as obras marcadas | Nenhum perfil de projeto |
| Projeto marcado na raiz | Todas as obras prontas do projeto | Perfil desse projeto, quando configurado |
| Vários projetos marcados | União das obras prontas dos projetos | Perfis configurados de todos os projetos marcados |
| Projeto marcado e obra avulsa | Obras do projeto mais a obra avulsa | Somente o perfil do projeto marcado |

Quando projetos selecionados compartilham uma obra, o ID documental é deduplicado antes da recuperação e as evidências são consultadas uma única vez, enquanto todos os perfis continuam ativos. Orientações compatíveis podem ser combinadas; conflitos sobre o mesmo aspecto retornam às regras-base e a uma formulação neutra.

## Consulta documental

`InputTypeDetector` reconhece inputs diretos, estruturais, conceituais, relacionais e amplos sem consumir IA. `DocumentContextRetriever` escolhe a rota, usa embedding transitório e CIE somente nas buscas conceituais e relacionais e retorna evidências primárias completas por acesso direto ou recuperação vetorial.

`DocumentQueryService` aceita somente identificadores do contexto recuperado. Citações desconhecidas, marcadores omitidos e inventários sem incorporação analítica são rejeitados; a aplicação não acrescenta citações ausentes. Uma saída rejeitada é regenerada silenciosamente até três tentativas com o mesmo contexto. Da segunda tentativa em diante, o provedor recebe apenas um código seguro da falha e, quando aplicável, o ID de uma evidência já eleita que não foi incorporada. Somente a terceira falha consecutiva chega ao usuário como mensagem genérica. Evidências usadas, `simetry`, `assimetry` e limitações permanecem em campos separados.

Na interface web, o compositor desaparece imediatamente após o envio. Depois da resposta, **Fazer outra pergunta** preserva o escopo de projetos e obras, limpa o resultado, reexibe o formulário e inicia uma recuperação do zero. Durante a espera, **Consultando evidências** apresenta três pontos amarelos animados e respeita movimento reduzido. O resultado não é persistido como estado de chat no banco, na auditoria ou em `sessionStorage`; logout, novo login ou recarregamento iniciam uma consulta vazia.

A consulta real exige dupla confirmação:

```bash
php bin/query-document.php <document-id> --live "sua pergunta"
```

Não existe Top-k nem limite numérico configurável para evidências semânticas. κq, o primeiro CIE sobre fontes, κe, CIE primário e CIE global determinam a população query-local. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` e `--evidence-limit=N` atuam apenas nas rotas direta, estrutural e ampla. `QUERY_MAX_INTERACTIONS` e `--interaction-limit=N` limitam somente interações transitórias.

## Execução cognitiva responsável e fila

A construção com provedores reais exige `AI_LIVE_ENABLED=true`, a opção `--live` e confirmação explícita:

```bash
php bin/build-cognitive.php <document-id> --stage=embeddings --live
```

Cada execução do worker processa no máximo um trabalho:

```bash
php bin/queue-worker.php --live
```

Use `--drain` somente para consumir intencionalmente toda a fila atual. Na área administrativa, **Processar fila no navegador** chama `POST /api/admin/queue/run` repetidamente até a fila ficar ociosa. Cada requisição realiza uma passagem, exige `confirm_live: true` e continua sujeita a `AI_LIVE_ENABLED=true`. A aba deve permanecer aberta durante o processamento.

## Módulos conectores

Um módulo funciona como cartucho instalável: é um pacote independente em `modules/<module-id>/`, hierarquicamente acima de projetos, mas sem pertencer a projeto, documento ou usuário. Zero, um ou vários módulos podem observar o mesmo evento versionado de forma independente.

Cada pacote declara `module.json`, implementa o EVA Module Contract v1 e grava seu histórico privado em `modules/.runtime/data/<module-id>/module.sqlite`. O Core persiste cada evento neutro uma única vez em `module_events`; assinantes ativos mantêm cursores próprios. O módulo recebe uma API de leitura limitada e uma capacidade de linguagem neutra, nunca credenciais do banco ou chaves de IA.

O superadmin pode ativar, desativar ou excluir definitivamente pacotes descobertos em `modules/`. Desativar preserva pacote e SQLite; excluir exige confirmação digitada e remove ambos. Para atualizar, substitui-se somente `modules/<module-id>/`, pois os dados permanecem fora do pacote.

Interfaces modulares fornecem seu próprio HTML e CSS. O Core conhece apenas o contrato genérico de dashboard e o nome do manifesto. A distribuição pública inclui `com.eva.explorer` como conector independente de referência; removê-lo não invalida o produto nem testes não relacionados. Consulte [Módulos do EVA](docs/16_MODULOS.md).

## Produto administrativo e segurança

A interface é servida em `/` e usa a mesma origem da API. O navegador conserva o token de superadmin somente em `sessionStorage`, e rotas administrativas exigem `Authorization: Bearer`. O superadmin cadastra usuários, redefine senhas, ativa ou desativa contas e concede acesso por projeto ou obra. Usuários comuns acessam apenas o chat e a alteração de senha.

Senhas são persistidas exclusivamente por `password_hash()`. Na criação, redefinição ou rotação, um código de recuperação de 16 caracteres é exibido uma única vez e armazenado somente como hash. Sem SMTP, a recuperação exige username, código vigente e nova senha; a conclusão revoga sessões anteriores e emite novo código.

As tabelas de acesso estão em `database/migrations/20260721_008_user_access.sql`, e perfis de resposta em `database/migrations/20260731_009_project_response_profile.sql`. A permissão de projeto inclui todas as obras associadas; a permissão de obra não libera as demais obras do projeto. O mapa de cardinalidades, chaves, relações e cascatas está em [Relacionamento do banco de dados](docs/17_RELACIONAMENTO_BANCO_DADOS.md).

O superadmin pode excluir obra ou projeto mediante confirmação digitada. Excluir uma obra remove em cascata nós, evidências, embeddings, trabalhos, permissões e vínculos. Excluir um projeto remove também todas as obras nele contidas, mesmo se compartilhadas. Para preservar uma obra compartilhada, remova antes o vínculo no projeto; o modal identifica esses casos e apresenta a orientação.

No XAMPP, o `.htaccess` da raiz bloqueia arquivos e diretórios reais fora de `public/`, desativa listagens e impede acesso HTTP a credenciais, código, banco, documentação, testes e armazenamento. Essa proteção exige `mod_rewrite`, `mod_headers` e `AllowOverride All`. Diretivas globais como `TraceEnable Off` e `ServerTokens Prod` devem ser configuradas no servidor, não no `.htaccess`. Fornecedor, endpoint, modelo e nome da variável de credencial permanecem no `.env` e não aparecem na interface nem nas respostas administrativas.

## Superfície da API

| Método | Rota | Finalidade |
|---|---|---|
| `GET` | `/api/health` | Saúde pública da aplicação e do banco |
| `GET` | `/api/branding` | Branding público sanitizado |
| `POST` | `/api/auth/login` | Iniciar sessão de usuário comum |
| `POST` | `/api/auth/recover` | Recuperar senha de usuário comum |
| `GET` | `/api/scopes` | Listar projetos e obras disponíveis ao ator autenticado |
| `POST` | `/api/query` | Executar consulta validada nos escopos autorizados |
| `GET` | `/api/modules` | Descobrir dashboards modulares ativos |
| `GET` | `/api/modules/{id}/dashboard` | Renderizar um dashboard pelo contrato genérico |
| `GET` | `/api/admin/modules` | Inspecionar conectores instalados (superadmin) |
| `PATCH` | `/api/admin/modules/{id}` | Ativar ou desativar conector (superadmin) |
| `DELETE` | `/api/admin/modules/{id}` | Excluir conector e dados após confirmação (superadmin) |
| `GET` | `/api/documents` | Listar obras e contagens (superadmin) |
| `POST` | `/api/documents` | Ingerir Markdown, JSON ou XML (superadmin) |
| `POST` | `/api/documents/{id}/process` | Enfileirar embeddings primários (superadmin) |
| `POST` | `/api/admin/queue/run` | Executar uma passagem confirmada do worker (superadmin) |
| `GET` | `/api/jobs` | Inspecionar a fila (superadmin) |
| `POST` | `/api/jobs/{id}/retry` | Retomar explicitamente um trabalho permitido (superadmin) |
| `GET` | `/api/metrics` | Retornar contagens descritivas (superadmin) |
| `GET` | `/api/audit` | Retornar eventos administrativos sanitizados (superadmin) |

Saúde, branding, login e recuperação são públicos conforme seus contratos. As demais rotas exigem sessão autenticada ou token administrativo e aplicam políticas específicas de papel e escopo. A matriz completa está em [Produto e operações](docs/10_PRODUTO.md).

## Pastas

```text
app/            Código da aplicação
bin/            Comandos locais explícitos
bootstrap/      Inicialização e autoload
config/         Configurações
database/       Esquema versionado do banco
docs/           Especificação oficial
modules/        Runtime e pacotes conectores independentes
public/         Única pasta exposta pelo servidor web
storage/        Documentos e logs locais não versionados
tests/          Testes automatizados
```

## Testes

O repositório inclui testes offline com provedores simulados. A fixture pública [`tests/fixtures/synthetic_systems_manual.md`](tests/fixtures/synthetic_systems_manual.md) é material original; livros de terceiros, fontes enviadas, bancos de produção e logs privados não são distribuídos.

Valide a sintaxe:

```bash
find app bin bootstrap config modules public tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Execute os testes relevantes individualmente após configurar um banco isolado, por exemplo:

```bash
php tests/ParsersTest.php
php tests/DocumentIngestionTest.php
php tests/CognitiveBuildTest.php
php tests/ContextIntelligenceEngineTest.php
php tests/ContextIntelligenceIntegrationTest.php
php tests/QueryTest.php
```

Testes devem usar `AI_LIVE_ENABLED=false`, salvo quando documentarem explicitamente comportamento real.

## Limites do repositório

A distribuição pública exclui intencionalmente:

- `.env` com configurações e credenciais locais;
- dumps de bancos operacionais;
- documentos enviados e logs de runtime;
- estado de ativação, SQLite, WAL e logs dos módulos;
- usuários privados e registros de acesso;
- corpora de livros de terceiros usados em regressões privadas.

O esquema público e todas as migrations versionadas permanecem incluídos para instalações vazias.

## Documentação

- [Índice da documentação](docs/README.md)
- [Visão geral](docs/01_VISAO_GERAL.md)
- [Arquitetura](docs/02_ARQUITETURA.md)
- [Instalação](docs/18_INSTALACAO.md)
- [Ingestão](docs/03_INGESTAO.md)
- [Construção cognitiva](docs/04_CONSTRUCAO_COGNITIVA.md)
- [Consulta](docs/05_CONSULTA.md)
- [Context Intelligence Engine](docs/14_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Cnode](docs/06_CNODE.md)
- [Banco de dados](docs/07_BANCO_DE_DADOS.md)
- [Relacionamentos do banco](docs/17_RELACIONAMENTO_BANCO_DADOS.md)
- [Regras obrigatórias](docs/08_REGRAS.md)
- [Sustentabilidade energética](docs/13_SUSTENTABILIDADE_ENERGETICA.md)
- [Roadmap](docs/09_ROADMAP.md)
- [Validação de prontidão](docs/11_VALIDACAO_GO_LIVE.md)
- [Homologação pré-implantação](docs/12_HOMOLOGACAO_PRE_DEPLOY.md)
- [Visão e impacto](docs/15_VISAO.md)
- [Módulos conectores](docs/16_MODULOS.md)
- [Benchmark EVA versus contexto integral](docs/19_BENCHMARK_EVA_VS_CONTEXTO_INTEGRAL.md)
- [Produto, aplicações de mercado e impacto](docs/EVA_MARKETING.md)
- [Material científico e filosófico](philosophy/README.md)

## Contribuição e segurança

Leia [`CONTRIBUTING.md`](CONTRIBUTING.md) antes de abrir uma contribuição. Relate vulnerabilidades de forma privada conforme [`SECURITY.md`](SECURITY.md). A participação da comunidade segue [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## Licença

Copyright 2026 EVA Project contributors.

Licenciado sob a [Apache License 2.0](LICENSE). A licença permite uso, modificação e distribuição nos termos estabelecidos, mas não concede direitos sobre marcas; consulte [`NOTICE`](NOTICE).
