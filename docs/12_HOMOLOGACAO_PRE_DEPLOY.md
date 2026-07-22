# Homologação pré-deploy

## Parecer

**Resultado em 22/07/2026: APROVADO PARA UPLOAD CONTROLADO.**

Este é um parecer de prontidão anterior ao upload. A aplicação ainda não está publicada em `https://eva.oceanno.com.br`; portanto, a página atualmente servida pelo domínio não foi tratada como falha da aplicação. O aceite online será concluído depois do upload, executando o verificador descrito neste documento.

## Smoke test visual

O teste foi executado na aplicação real servida pelo Apache local por HTTPS, em desktop, tablet e dispositivo móvel.

Foram validados:

- login e logout de superadmin e usuário cadastrado;
- árvore responsiva de projetos e obras na atribuição de permissões;
- herança visual e funcional das obras ao marcar um projeto;
- concessão isolada de `O Livro dos Espíritos`, sem exposição de `O Livro dos Médiuns` ou do projeto completo no chat;
- seleção por checkbox no chat;
- pergunta real e exibição da resposta com evidências;
- remoção da pergunta e resposta da tela após logout;
- ausência de restauração da conversa anterior no novo login;
- larguras de 1440 × 1000, 768 × 1024 e 390 × 844, sem rolagem horizontal indevida;
- ausência de erros HTTP e de console após as correções.

O smoke identificou e corrigiu dois defeitos de interface:

1. o botão de sair ficava oculto em telas com até 820 px;
2. o fallback de marca requisitava `/logo/logo.svg`, arquivo inexistente, e gerava HTTP 404.

As capturas da homologação estão em:

`C:\Users\alexandre.vale\.codex\visualizations\2026\07\22\019f8947-c983-7300-a75c-4f524036b49d\final-smoke`

O usuário temporário, suas permissões, sessões e eventos de auditoria exclusivos do smoke foram removidos ao final. As contagens de usuários e sessões retornaram ao estado inicial.

## Homologação da infraestrutura local

O verificador automatizado local aprovou **18 de 18 verificações**:

```powershell
php bin\verify-deployment.php https://localhost/eva.oceanno.com.br --local
```

Resultados confirmados:

- Apache em 80/443 e MySQL em 3306;
- `/api/health` pronto e banco disponível;
- CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Resource-Policy` e `Cache-Control` presentes;
- `X-Request-Id` aleatório e `X-Powered-By` removido;
- `.env`, `api_key.md`, logs, dump SQL e `.git` bloqueados pela superfície HTTP;
- tentativa de travessia para `.env` não encontrou o arquivo;
- fila sem trabalhos pendentes, em execução ou com falha;
- configuração duplicada do OpenSSL no PHP CLI corrigida e `httpd -t` aprovado.

### Backup e restauração

O teste criou um dump real do banco, restaurou-o em banco temporário, comparou 13 tabelas e suas contagens, arquivou `storage/documents` e comparou os hashes dos arquivos extraídos. O banco, diretório e artefatos temporários foram removidos no bloco de limpeza.

Esse teste comprova o procedimento técnico, mas produção ainda precisa de agenda de backups, retenção e cópia externa ao servidor.

### Concorrência básica

- 100 requisições concorrentes ao health check: 100% HTTP 200, p95 de 112 ms;
- 50 requisições autenticadas concorrentes a `/api/me`: 100% HTTP 200, p95 de 41 ms.

Esses números são um smoke de concorrência no ambiente local, não uma previsão de capacidade do plano de hospedagem.

## Infraestrutura do domínio antes do upload

Mesmo sem a aplicação publicada, a camada atual do domínio confirmou:

- redirecionamento HTTP para HTTPS;
- TLS 1.3 e certificado válido para `eva.oceanno.com.br`;
- método `TRACE` recusado com HTTP 405.

Depois do upload, esses itens e os cabeçalhos da aplicação precisam ser validados em conjunto. O código envia HSTS somente em HTTPS e fora de hosts locais.

## Diagnóstico seguro nos logs

O diagnóstico passou a registrar a categoria operacional da falha sem registrar o conteúdo sensível. Foram cobertos:

- saída de IA truncada;
- HTTP do provedor, preservando somente o status numérico seguro;
- transporte e timeout;
- resposta inválida ou falha de serialização;
- configuração de IA;
- banco de dados;
- falha genérica da aplicação.

O teste de segurança confirmou a remoção de senha, segredo, token, chave de API, cabeçalho Bearer, padrões de chave, prompt, entrada, conteúdo e corpos de requisição/resposta. O cliente continua recebendo mensagens genéricas.

## Regressão final

Foram executadas 15 suítes sem chamadas pagas ao provedor, com **883 asserções aprovadas**. Também passaram a validação sintática do JavaScript, o inventário completo e comentado das 46 variáveis do `.env`, o teste real de backup/restauração e o verificador de deploy local.

## Procedimento obrigatório depois do upload

1. Publicar o projeto preservando os arquivos `.htaccess` e sem expor `.env`, chaves, logs, dumps ou `.git`.
2. Configurar o `.env` de produção, permissões graváveis de `storage/documents` e `storage/logs` e o worker/cron da fila.
3. Configurar backup recorrente do banco e dos documentos, com retenção e cópia externa.
4. Executar, a partir de uma máquina que acesse o domínio:

```powershell
php bin\verify-deployment.php https://eva.oceanno.com.br
```

5. Exigir zero falhas no verificador e realizar um último login de superadmin e de usuário comum no domínio publicado.

Se o verificador tiver qualquer falha, a publicação deve permanecer em homologação até a correção. Não é necessário repetir toda a matriz paga de IA se código, banco e configuração forem exatamente os homologados; basta o smoke online final e uma consulta controlada por perfil.
