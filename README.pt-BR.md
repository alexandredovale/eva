# EVA

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.21500611.svg)](https://doi.org/10.5281/zenodo.21500611)

**Versão atual:** [v2.0.0](https://github.com/alexandredovale/eva/releases/tag/v2.0.0)

O EVA é uma plataforma para construir, organizar e consultar memória cognitiva documental verificável. O EVA (Evidence Algorithm) transforma documentos estruturados em evidências hierárquicas. Cnode é a compreensão transitória de uma interação explícita entre essas evidências durante a consulta, não uma entidade persistente.

## Estado atual

A base atual contém:

- documentação funcional e arquitetural;
- estrutura PHP mínima e executável;
- conexão segura com o banco por variáveis de ambiente;
- esquema versionado do banco de dados;
- endpoint de diagnóstico e upload documental;
- parsers funcionais de Markdown, JSON e XML com árvore normalizada comum;
- persistência transacional da fonte, da árvore e das evidências primárias;
- adaptadores substituíveis por capacidade: embeddings, sínteses e respostas;
- resumos ascendentes rastreáveis e embeddings contextuais versionados;
- recuperação vetorial transitória sobre evidências primárias e derivadas;
- Context Intelligence Engine determinístico entre o Retriever e as camadas cognitivas;
- consulta adaptativa que produz interações `simetry`/`assimetry` validadas por fragmentos literais;
- chat conversacional com transcript temporário completo e contexto limitado às três rodadas anteriores;
- produto white label com interface administrativa, API autenticada, fila e retomada explícita;
- métricas descritivas e auditoria sanitizada, sem exposição de segredos ou conteúdo consultado;
- execução real de IA bloqueada por padrão e reaproveitamento por modelo/hash;
- Runtime de módulos conectores independentes, com descoberta, ativação, desativação, exclusão confirmada e SQLite próprio por pacote;
- módulo `com.eva.education` de referência, sem notas ou pesos e sem conhecimento educacional no Core.

As Fases 1 a 5 estão funcionais e o roadmap original está concluído. O estado de cada entrega está em [docs/09_ROADMAP.md](docs/09_ROADMAP.md).

## Princípio cognitivo

Embeddings são gerados por unidades documentais previamente organizadas, nunca por cortes arbitrários de caracteres ou tokens.

Um Cnode apenas descreve uma interação semântica explícita no contexto consultado. Ele não possui identidade persistente, nota de importância, confiança ou intensidade. A quantidade de interações retornadas é descritiva e não determina verdade, qualidade, prioridade ou relevância.

## Requisitos

- PHP 8.2 ou superior;
- MariaDB 10.4 ou MySQL compatível;
- extensões PHP `pdo`, `pdo_mysql`, `pdo_sqlite`, `json`, `dom`, `mbstring` e `curl`.

## Configuração local

1. Revise o `.env`, que contém o inventário completo e comentado das configurações reconhecidas pelo sistema.
2. Ajuste os valores para o ambiente atual, inclusive aplicação, branding, banco, IA, consulta, ingestão e fila.
3. Configure no próprio `.env` o fornecedor, endpoint, modelo e nome da variável de credencial para cada capacidade.
4. Configure o Apache para servir a pasta `public` como raiz pública.
5. Acesse `/` para abrir o produto. O diagnóstico público está em `GET /api/health`.

Em uma instalação vazia, importe somente `database/schema.sql`, que já contém as 14 tabelas atuais, inclusive `module_events`. Bancos existentes devem receber, em ordem, apenas as migrations ainda ausentes; a migration `20260803_010_module_events.sql` permanece necessária para instalações criadas antes dessa consolidação.

Nenhuma chave de API deve ser gravada no código, na documentação, nos logs ou no repositório. O `.env` é a única fonte local de configuração e contém os placeholders comentados das credenciais neutras; cada instalação deve preencher esses valores somente em seu arquivo privado.

As classes da aplicação são white label e conhecem apenas capacidades. Todos os vínculos com fornecedores, endpoints, modelos e variáveis de credencial ficam exclusivamente no `.env`; `config/ai.php` apenas lê essas variáveis genéricas. Os testes usam provedores simulados e não consomem créditos.

## Uso dos parsers

```php
use Eva\Application\Ingestion\Parser\ParserFactory;

$parser = ParserFactory::forFilename($filename);
$document = $parser->parse($content, $title);
$normalizedTree = $document->toArray();
```

O parser recebe o conteúdo já lido e nunca acessa caminhos de arquivo por conta própria.

## Upload HTTP

O endpoint `POST /api/documents` recebe `multipart/form-data`:

- `document`: arquivo obrigatório `.md`, `.json` ou `.xml`;
- `title`: título opcional; quando ausente, deriva do nome do arquivo.

Sucesso retorna HTTP `201`. Arquivos ausentes, incompletos, acima do limite, com estrutura multipart inválida ou conteúdo incompatível são rejeitados antes da persistência.

## Persistência documental

`DocumentIngestionService` seleciona o parser, armazena a fonte fora da pasta pública, persiste a árvore e gera evidências primárias literais. O limite padrão é 10 MB e pode ser ajustado por `DOCUMENT_MAX_BYTES`.

A ingestão não produz resumos ou embeddings. A construção cognitiva é uma etapa posterior e explícita.

## Construção cognitiva

`HierarchicalSummaryService` percorre a árvore de baixo para cima. Cada resumo derivado registra modelo, hash da entrada estrutural e evidências de origem. Nós sem conteúdo próprio nem descendente permanecem sem síntese.

`EvidenceEmbeddingService` vetoriza evidências primárias e resumos derivados com título do documento, caminho, nó e conteúdo organizado. Antes de chamar o provedor, todas as unidades pendentes são validadas contra `AI_EMBEDDING_MAX_INPUT_TOKENS`, com margem preventiva de 10%. Lotes técnicos agrupam unidades completas e nunca fragmentam nenhuma delas. Se uma primária exceder o limite, uma síntese derivada válida e rastreável assume sua rota semântica; sem essa síntese, a etapa para com o identificador da evidência e exige subdivisão estrutural real. Uma versão já existente para o mesmo modelo e hash é reutilizada antes de chamar o provedor.

Na consulta conceitual ou relacional, `DocumentContextRetriever` compara o embedding transitório do input com evidências primárias e derivadas. O Retriever produz um Top-k de 20 candidatos por padrão; o CIE calcula média, desvio padrão populacional e CV, identifica o núcleo principal e a convergência complementar e só então resolve sínteses por `evidence_derivations` até suas fontes primárias. Quando não há núcleo, a convergência assume o papel principal. As fontes primárias resolvidas formam o contexto disponível: a LLM pode usar somente o subconjunto que efetivamente contribuir para a resposta, com citação visível; candidatas não citadas são descartadas da base final. Similaridades e estatísticas permanecem transitórias.

`QueryAnswerProvider` pode declarar interações `simetry` ou `assimetry` na mesma chamada que produz a resposta. `DocumentQueryService` aceita cada interação somente quando os participantes pertencem ao contexto, foram citados e seus fragmentos existem literalmente nas evidências. Nada disso é persistido como Cnode.

Projetos podem definir um **Perfil de respostas** administrado pelo superadmin. Quando um projeto é marcado explicitamente no chat, esse perfil é acrescentado ao `SYSTEM_PROMPT` como uma camada complementar identificada pelo projeto e por suas obras. Selecionar somente uma obra não ativa implicitamente o perfil do projeto. Em consultas com vários projetos, os perfis são enviados separadamente e permanecem subordinados às regras de evidência, citação, limitação e saída JSON da EVA.

| Seleção no chat | Obras consultadas | Perfis aplicados |
|---|---|---|
| Uma ou mais obras marcadas individualmente | Somente as obras marcadas | Nenhum perfil de projeto |
| Projeto marcado na raiz | Todas as obras prontas do projeto | Perfil desse projeto, quando configurado |
| Vários projetos marcados | União das obras prontas dos projetos | Perfis configurados de todos os projetos marcados |
| Projeto marcado e obra avulsa | Obras do projeto mais a obra avulsa | Somente o perfil do projeto marcado |

Quando dois projetos marcados compartilham a mesma obra, o ID documental é deduplicado antes da recuperação: a obra e suas evidências são consultadas uma única vez, enquanto os perfis dos dois projetos continuam ativos. Orientações compatíveis podem ser combinadas; se incidirem sobre o mesmo aspecto de forma incompatível, prevalecem as regras-base e a resposta adota formulação neutra.

## Execução cognitiva responsável

A construção com provedores reais pode ser iniciada pela linha de comando ou pela interface administrativa e sempre exige confirmação explícita:

```powershell
php bin/build-cognitive.php <document-id> --stage=summaries --live
php bin/build-cognitive.php <document-id> --stage=embeddings --live
```

Além de `--live`, `AI_LIVE_ENABLED=true` deve estar configurado. Resumos criam no máximo 5 novas versões por execução por padrão (`AI_MAX_NEW_SUMMARIES_PER_RUN`). O limite pode ser reduzido na chamada com `--summary-limit=N`.

## Consulta documental

`InputTypeDetector` reconhece inputs diretos, estruturais, conceituais, relacionais e amplos sem consumir IA. `DocumentContextRetriever` escolhe a rota correspondente, usa embedding transitório e CIE apenas nas buscas conceituais/relacionais e retorna evidências primárias completas por acesso direto ou pela linhagem das sínteses derivadas.

`DocumentQueryService` aceita somente identificadores pertencentes ao contexto recuperado. Citações desconhecidas, marcadores omitidos e inventários sem incorporação analítica são rejeitados; a aplicação não acrescenta citações ausentes para aparentar conformidade. Uma saída rejeitada pela validação local é regenerada silenciosamente até o limite de três tentativas totais com o mesmo contexto eleito. Da segunda tentativa em diante, o provedor recebe somente um código seguro da falha e, quando aplicável, o ID de uma evidência já eleita que deixou de ser incorporada. Somente a terceira falha consecutiva chega ao usuário como mensagem genérica, sem identificador de evidência. Evidências utilizadas, `simetry`, `assimetry` e limitações permanecem em campos separados.

Na interface web, todas as rodadas concluídas permanecem visíveis no box do chat durante a sessão atual. A partir da segunda consulta, o navegador anexa ao input no máximo as três rodadas anteriores, em ordem cronológica, para que a IA avalie se o pedido atual é continuidade. Esse histórico auxilia referências conversacionais, mas não constitui evidência: perguntas e respostas anteriores nunca substituem nem ampliam as evidências primárias recuperadas para a consulta atual.

O botão **Reiniciar chat** limpa o transcript, o contexto conversacional e o input, preservando o escopo de projetos e obras selecionado. Durante a espera, o estado **Consultando evidências** apresenta três pontos amarelos animados e respeita a preferência de movimento reduzido. O histórico não é enviado para tabelas, auditoria ou `sessionStorage`; logout, novo login ou recarregamento da aplicação iniciam uma conversa vazia.

A consulta real também exige dupla confirmação:

```powershell
php bin/query-document.php <document-id> --live "pergunta"
```

Os limites padrão são Top-k de 20 candidatos vetoriais por documento, 10 evidências primárias no contexto final e 20 interações transitórias. Na API e na interface web, configure-os por `QUERY_CANDIDATE_LIMIT`, `QUERY_MAX_EVIDENCE` e `QUERY_MAX_INTERACTIONS`. Na linha de comando, os dois limites finais também podem ser substituídos na execução por `--evidence-limit=N` e `--interaction-limit=N`.

## Módulos conectores

Um módulo funciona como um cartucho instalável: é um pacote independente em `modules/<module-id>/`, hierarquicamente acima de projetos, mas sem pertencer a projeto, documento ou usuário. Zero, um ou vários módulos podem permanecer ativos e observar o mesmo evento versionado de forma independente.

Cada pacote declara `module.json`, implementa o EVA Module Contract v1 e grava seu histórico privado em `modules/.runtime/data/<module-id>/module.sqlite`. O Core persiste uma única caixa postal neutra em `module_events`; cada assinante mantém cursor próprio. O módulo recebe uma API de leitura limitada e uma capacidade de linguagem neutra, nunca chaves de IA ou credenciais do banco principal.

O superadmin apenas ativa, desativa ou exclui definitivamente os pacotes encontrados em `modules/`. Desativar preserva o código e o SQLite. Excluir exige confirmação digitada e remove pacote e histórico. Para atualizar, substitui-se somente `modules/<module-id>/`; os dados permanecem fora do pacote.

Interfaces modulares fornecem o próprio HTML e CSS. O Core conhece somente o contrato genérico de dashboard e o nome do manifesto. Assim, um conector futuro pode acrescentar sua própria interface sem inserir menu, renderer, estilo ou regra de domínio específica no Core.

O pacote `com.eva.education` demonstra o contrato: observa interações concluídas, mapeia trajetos, produz observações descritivas ancoradas em evidências e extrai conceitos linguísticos do objeto completo pergunta+resposta. Não utiliza pontuações, pesos, percentuais, confiança ou rankings. Sua governança atual possui somente articulação conceitual, uso de evidências e conexão contextual.

Consulte [Módulos do EVA](docs/16_MODULOS.md) para instalação, contratos, operação, backup, atualização e remoção.

## Produto administrativo

A interface web é servida em `/` e usa a mesma origem da API. Configure um `ADMIN_API_TOKEN` com pelo menos 24 caracteres antes do primeiro acesso. O navegador conserva o token somente em `sessionStorage`, e as rotas administrativas exigem `Authorization: Bearer`.

O token identifica o superadmin. Pela área **Usuários**, ele cadastra username e senha, redefine senhas, ativa ou desativa contas e concede acesso por projeto completo ou por obra individual. Pela área **Projetos**, documentos podem ser agrupados sem alterar sua ingestão ou estrutura cognitiva, e um perfil complementar de respostas pode ser gerenciado para cada projeto.

Usuários cadastrados acessam apenas o chat e a alteração de senha. Cada senha é persistida exclusivamente por `password_hash()`. Na criação, redefinição ou rotação é exibido uma única vez um código de recuperação de 16 caracteres, cujo valor também é armazenado somente como hash. Sem SMTP, a recuperação exige username, código vigente e nova senha; ao concluir, todas as sessões anteriores são revogadas e um novo código de recuperação é emitido.

As tabelas dessa camada estão na migração `database/migrations/20260721_008_user_access.sql`; o perfil de respostas foi acrescentado por `database/migrations/20260731_009_project_response_profile.sql`. A permissão de projeto inclui todas as obras associadas a ele; a permissão de obra não libera as demais obras do mesmo projeto.

O mapa completo de cardinalidades, chaves estrangeiras, tabelas de junção, relações lógicas e cascatas está em [Relacionamento do banco de dados](docs/17_RELACIONAMENTO_BANCO_DADOS.md).

O superadmin também pode excluir uma obra ou um projeto mediante confirmação digitada. Excluir uma obra remove em cascata seus nós, evidências, derivações, embeddings, trabalhos de processamento, permissões e vínculos com projetos. Excluir um projeto remove igualmente todas as obras nele contidas, mesmo que alguma também esteja associada a outro projeto, e depois remove suas fontes do armazenamento privado.

Para preservar uma obra compartilhada antes de excluir um projeto, o superadmin deve editar esse projeto, desmarcar a obra, salvar o projeto sem o vínculo e somente então excluí-lo. O modal de exclusão identifica as obras compartilhadas e apresenta essa orientação antes da confirmação destrutiva.

No XAMPP com o projeto dentro de `htdocs`, a raiz `http://localhost/eva.oceanno.com.br/` é encaminhada internamente para `public`. O `.htaccess` da raiz bloqueia qualquer arquivo ou diretório real fora dessa superfície, desativa listagens e impede acesso HTTP a credenciais, código, banco, documentação, testes e armazenamento. Essa proteção exige `mod_rewrite`, `mod_headers` e `AllowOverride All`.

Diretivas globais do Apache, como `TraceEnable Off` e `ServerTokens Prod`, devem ser configuradas no servidor quando o ambiente deixar de ser exclusivamente local; elas não são permitidas em `.htaccess`.

O produto permite enviar e listar documentos, agendar sínteses e embeddings, consultar evidências, acompanhar trabalhos, retomar falhas permitidas e inspecionar auditoria e métricas. Fornecedor, endpoint, modelo e variável de credencial são definidos exclusivamente no `.env` e não aparecem na interface ou nas respostas administrativas.

Cada execução do worker processa no máximo um trabalho:

```powershell
php bin/queue-worker.php --live
```

Para consumir toda a fila atual em uma única sessão explícita, use `--drain`. Esse modo pode realizar muitas chamadas reais e deve permanecer aberto enquanto a interface acompanha o progresso:

```powershell
php bin/queue-worker.php --live --drain
```

A execução continua protegida pela dupla confirmação `AI_LIVE_ENABLED=true` e `--live`.

Na área administrativa, o botão **Processar fila no navegador** chama `POST /api/admin/queue/run` repetidamente até a fila ficar ociosa. Cada requisição executa uma única passagem do worker e exige `confirm_live: true`; o servidor continua exigindo `AI_LIVE_ENABLED=true`. A aba deve permanecer aberta durante o processamento.

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
