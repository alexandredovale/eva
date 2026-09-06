# Instalação

## 1. Plataforma

Instale PHP 8.2 ou superior, MariaDB 10.4 ou superior — ou uma versão compatível do MySQL — e as extensões PHP obrigatórias: `curl`, `dom`, `json`, `mbstring`, `pdo` e `pdo_mysql`.

A aplicação não possui dependência de runtime do Composer ou Node.js. Apache com `mod_rewrite` e `mod_headers` é o servidor documentado; outro servidor é aceitável somente quando reproduz a mesma fronteira `public/` e o mesmo comportamento de segurança.

## 2. Ambiente

Copie `.env.example` para `.env`. Configure a URL da aplicação, conexão com o banco, identidade visual, limites de consulta e identidade da fila. Gere um `ADMIN_API_TOKEN` exclusivo com pelo menos 24 caracteres.

`QUERY_NON_SEMANTIC_MAX_EVIDENCE` aplica-se somente às rotas sem CIE. Rotas semânticas usam κq e o primeiro CIE diretamente sobre evidências primárias, seguidos por κe, CIE primário e CIE global, sem uma quantidade configurada de evidências.

Mantenha os campos de provedor vazios e `AI_LIVE_ENABLED=false` até concluir a instalação local e os testes offline.

Em produção, use `APP_ENV=production` e `APP_DEBUG=false`.

Nunca copie segredos de produção para exemplos, relatos de incidentes, saída de testes ou documentação. A configuração de provedor armazena o nome da variável de ambiente da credencial; a credencial em si pertence somente a essa variável local.

## 3. Banco de dados

Crie um banco vazio em UTF-8 e importe `database/schema.sql`. O schema consolidado cria as 14 tabelas atuais do banco principal, inclusive a caixa postal do Module Runtime. O repositório não exige dados iniciais e não inclui dump operacional, usuários, fontes enviadas ou evidências geradas.

```bash
mysql -u root -p -e "CREATE DATABASE eva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p eva < database/schema.sql
```

Em uma instalação existente, aplique as migrations pendentes na ordem dos nomes de arquivo e faça backup do banco privado antes de qualquer migration. `20260803_010_module_events.sql` permanece como caminho de atualização para bancos criados antes da consolidação de `module_events` no schema principal.

## 4. Permissões de armazenamento

O processo PHP precisa do acesso necessário de leitura e escrita a:

```text
storage/documents/
storage/figures/
storage/logs/
```

Esses diretórios contêm apenas `.gitkeep` no repositório público; seu conteúdo de runtime é ignorado pelo Git. A ingestão e o processamento não criam diretórios de figuras documentais. Depois que a obra recebe seu identificador `EVA-D...`, o gestor autorizado cria manualmente `storage/figures/{EVA-D...}/` e coloca ali somente os arquivos aprovados. O processo PHP ainda precisa de acesso suficiente para servir figuras autorizadas e remover o diretório privado durante a exclusão gerenciada da obra.

Não torne o projeto inteiro gravável. Mantenha `.env`, código da aplicação, artefatos de banco e configuração do servidor legíveis somente pelas contas que realmente necessitam.

## 5. Servidor web

A raiz de documentos preferencial é `public/`. Quando o projeto está diretamente em um diretório `htdocs` do Apache/XAMPP, o `.htaccess` da raiz encaminha rotas virtuais para `public/` e nega acesso direto a caminhos privados reais.

Ative `mod_rewrite`, `mod_headers` e `AllowOverride All`. A configuração de produção do Apache também deve aplicar diretivas globais como `TraceEnable Off` e assinaturas reduzidas do servidor.

## 6. Primeiras verificações

1. Abra `GET /api/health`.
2. Abra `/` e informe o token de superadmin.
3. Crie um usuário comum, se necessário.
4. Ingira um documento pequeno e original em Markdown, JSON ou XML.
5. Verifique a árvore documental e o estado da fila antes de ativar provedores reais.

Execute os testes offline relevantes em um banco de testes vazio e isolado. Os testes devem permanecer sob `AI_LIVE_ENABLED=false`, salvo quando o próprio teste e seu comando documentarem explicitamente o comportamento ao vivo.

## 7. Ativação dos provedores

Para cada capacidade, configure identificador do provedor, endpoint, modelo e nome da variável de credencial. Armazene a credencial real somente na variável de ambiente local indicada.

Chamadas reais exigem `AI_LIVE_ENABLED=true`; comandos CLI de construção e consulta exigem adicionalmente `--live`. Essa dupla confirmação evita consumo externo acidental.

```powershell
php bin\build-cognitive.php <document-id> --stage=summaries --live
php bin\build-cognitive.php <document-id> --stage=embeddings --live
php bin\query-document.php <document-id> --live "sua pergunta"
```

Para operação da fila, endurecimento do deploy, requisitos de backup e verificação pós-upload, continue em [Produto](10_PRODUTO.md) e [Homologação pré-deploy](12_HOMOLOGACAO_PRE_DEPLOY.md).
