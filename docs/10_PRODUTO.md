# Produto

## Escopo

A camada de produto oferece uma interface administrativa e de consulta sobre a memória documental. As respostas continuam sustentadas por evidências primárias, e as interações `simetry` ou `assimetry` são resultados transitórios sem pesos.

## Acesso

`GET /api/health` e `GET /api/branding` são públicos. As demais rotas exigem uma sessão autenticada de usuário ou `Authorization: Bearer` com o token definido por `ADMIN_API_TOKEN`, conforme a política da rota. Operações administrativas continuam restritas ao superadmin. O token administrativo precisa ter ao menos 24 caracteres, não é persistido no banco e nunca integra logs ou respostas.

Na interface, a credencial permanece apenas no `sessionStorage` da aba. Encerrar a sessão remove esse valor.

A raiz HTTP encaminha somente rotas virtuais e assets para `public`. Arquivos ou diretórios existentes fora dessa pasta recebem `403`; listagens, acesso direto ao template e métodos de alteração não utilizados pela API são bloqueados pelo Apache. Cabeçalhos de CSP, enquadramento, MIME, referência, permissões e política de recursos são enviados pela superfície pública. O método `TRACE` exige `TraceEnable Off` na configuração global do Apache, fora do escopo permitido para `.htaccess`.

## Rotas

A tabela reflete o dispatcher implementado. Os corpos de requisição e resposta são JSON, exceto o upload multipart indicado.

| Acesso | Método | Rota | Função |
|---|---|---|---|
| Público | `GET` | `/api/health` | Diagnóstico da aplicação e do banco |
| Público | `GET` | `/api/branding` | Identidade visual pública sanitizada |
| Público | `POST` | `/api/auth/login` | Autentica usuário comum e emite sessão |
| Público | `POST` | `/api/auth/recover` | Redefine senha com código de recuperação |
| Autenticado | `GET` | `/api/me` | Retorna identidade e papel do ator atual |
| Autenticado | `POST` | `/api/logout` | Invalida a sessão atual |
| Autenticado | `POST` | `/api/me/password` | Altera a senha do usuário comum atual |
| Autenticado | `POST` | `/api/me/recovery-code` | Rotaciona o código de recuperação após confirmar a senha |
| Autenticado | `GET` | `/api/scopes` | Lista projetos e obras disponíveis ao ator atual |
| Autenticado | `POST` | `/api/query` | Consulta os escopos selecionados e autorizados |
| Autenticado | `GET` | `/api/documents/{EVA-D...}/figures/{arquivo}` | Entrega uma figura local da obra autorizada |
| Autenticado | `GET` | `/api/modules` | Descobre entradas de interface dos módulos ativos |
| Autenticado | `GET` | `/api/modules/{id}/dashboard` | Carrega o dashboard genérico pertencente ao módulo |
| Autenticado | `POST` | `/api/modules/{id}/actions/{action-id}` | Executa uma ação autenticada pertencente ao módulo |
| Superadmin | `POST` | `/api/admin/queue/run` | Executa uma passagem explicitamente confirmada do worker |
| Superadmin | `GET` | `/api/admin/modules` | Lista pacotes descobertos em `modules/` |
| Superadmin | `PATCH` | `/api/admin/modules/{id}` | Ativa ou desativa um módulo |
| Superadmin | `DELETE` | `/api/admin/modules/{id}` | Exclui definitivamente um pacote confirmado e seus dados privados |
| Superadmin | `GET`, `POST` | `/api/admin/users` | Lista usuários ou cria um usuário comum |
| Superadmin | `PATCH` | `/api/admin/users/{id}` | Renomeia, ativa ou desativa um usuário comum |
| Superadmin | `POST` | `/api/admin/users/{id}/reset-password` | Redefine senha e retorna novo código de recuperação |
| Superadmin | `PUT` | `/api/admin/users/{id}/permissions` | Substitui concessões de projetos e obras individuais |
| Superadmin | `GET`, `POST` | `/api/admin/projects` | Lista ou cria projetos |
| Superadmin | `PUT`, `DELETE` | `/api/admin/projects/{id}` | Atualiza ou exclui um projeto |
| Superadmin | `GET` | `/api/documents` | Lista obras e contagens descritivas |
| Superadmin | `POST` | `/api/documents` | Ingere upload multipart Markdown, JSON ou XML |
| Superadmin | `DELETE` | `/api/documents/{id}` | Exclui uma obra e seu estado dependente |
| Superadmin | `POST` | `/api/documents/{id}/process` | Agenda sínteses e embeddings de forma idempotente |
| Superadmin | `GET` | `/api/jobs` | Lista o estado da fila |
| Superadmin | `POST` | `/api/jobs/{EVA-J...}/retry` | Retoma explicitamente um trabalho elegível com falha |
| Superadmin | `GET` | `/api/metrics` | Retorna apenas contagens descritivas |
| Superadmin | `GET` | `/api/audit` | Retorna eventos administrativos sanitizados |

Rotas desconhecidas retornam 404. Rotas conhecidas recusam métodos não suportados com 405 e o cabeçalho `Allow`. Autenticação, autorização, validação, conflitos de fila, violações do contrato de consulta, indisponibilidade do provedor e falhas inesperadas permanecem condições HTTP distintas com mensagens seguras ao cliente.

## Chat conversacional

O box de consulta funciona como um transcript durante a permanência na página. Cada input aparece como mensagem do usuário e cada resultado como resposta documental independente. Rodadas concluídas permanecem visíveis, a interface rola para a mais recente e cada resposta mantém sua própria ação de cópia com pergunta, resposta e referências.

Durante uma nova requisição, o transcript preserva as rodadas anteriores e acrescenta o estado `Consultando evidências…`, acompanhado por três pontos amarelos animados. A preferência de redução de movimento do navegador desativa a animação. Se a requisição falhar, as rodadas já concluídas permanecem no box. Em caso de sucesso, o input é limpo e recebe foco para a próxima mensagem.

O estado visual pode conter todas as rodadas da conversa atual, mas somente as três rodadas concluídas mais recentes participam do próximo `POST /api/query`. Elas são anexadas ao próprio campo `input`; nenhuma nova rota, tabela ou entidade de conversa foi criada. Se o limite de 20.000 bytes exigir redução, a rodada mais antiga é removida por inteiro.

**Reiniciar chat** limpa o transcript e o contexto curto sem desmarcar os projetos ou obras selecionados. O estado não sobrevive a logout, novo login ou recarregamento e não é persistido no banco, na auditoria ou no armazenamento do navegador.

## Figuras documentais

Markdown, JSON e XML aceitam contratos de figura em português ou inglês. O nó visual usa `Figura` ou `Figure`, deve ser filho imediato de um tópico temático não visual e não pode ficar diretamente na raiz documental nem sob outra figura. O conteúdo descritivo participa normalmente da indexação; a imagem não é interpretada por OCR ou por modelo multimodal.

### Campos bilíngues

| Valor | Português | English | Obrigatório |
|---|---|---|---|
| caminho lógico da imagem | `Arquivo` | `File` | sim |
| classificação documental | `Tipo` | `Type` | não |
| descrição objetiva | `Descrição factual` | `Factual description` | não |
| transcrição presente na imagem | `Texto visível` | `Visible text` | não |
| relações explicitamente desenhadas | `Relações representadas` | `Represented relationships` | não |

Maiúsculas e minúsculas não alteram o reconhecimento. Em JSON, podem ser usados os nomes com espaços mostrados na tabela. Em XML, use `_` ou `-` no lugar dos espaços, como `descricao_factual` e `factual_description`. Os campos dos dois idiomas são equivalentes; recomenda-se não misturá-los no mesmo contrato.

### Estrutura por formato

- **Markdown:** um heading `Figura...`/`Figure...` contém as linhas `Campo: valor` e é filho imediato de outro heading temático.
- **JSON:** uma propriedade `Figura...`/`Figure...` contém um objeto com os campos e é filha imediata de outro objeto temático.
- **XML:** um elemento `<figura titulo="Figura...">` ou `<figure title="Figure...">` contém os campos como elementos filhos e é filho imediato de outro elemento temático.

Quando uma evidência do contrato é citada, o resolvedor usa o bloco Markdown ou reúne somente os campos filhos diretos do objeto JSON/elemento XML. Essa reunião não cria nem altera evidências; a árvore, o conteúdo e a referência da fonte permanecem os produzidos pelo parser correspondente.

Há um documento mínimo independente para cada combinação de formato e idioma:

| Idioma | Markdown | JSON | XML |
|---|---|---|---|
| Português | [`figure.md`](examples/figure-contracts/pt-BR/figure.md) | [`figure.json`](examples/figure-contracts/pt-BR/figure.json) | [`figure.xml`](examples/figure-contracts/pt-BR/figure.xml) |
| English | [`figure.md`](examples/figure-contracts/en/figure.md) | [`figure.json`](examples/figure-contracts/en/figure.json) | [`figure.xml`](examples/figure-contracts/en/figure.xml) |

Exemplo Markdown em português:

```md
## Sistema solar

### Figura 4 — Movimento de translação

Arquivo: figuras/translacao-terra.png
Tipo: diagrama didático
Descrição factual: A Terra aparece em quatro posições ao redor do Sol.
Texto visível: março, junho, setembro e dezembro.
Relações representadas: as setas indicam movimento orbital no sentido anti-horário.

---
```

No Markdown, o contrato e o conteúdo explicativo devem permanecer diretamente no nó visual; um novo subtítulo inicia outra evidência e não herda o contrato. Em JSON/XML, os campos devem ser filhos diretos do objeto/elemento visual. Essa composição mantém tópico, caminho estrutural, evidência e arquivo na mesma cadeia documental.

Após a ingestão, o arquivo deve ser colocado manualmente em `storage/figures/{EVA-D...}/translacao-terra.png`. Os prefixos lógicos aceitos `figuras/` e `figures/` não são repetidos dentro do diretório do documento. Quando uma evidência do contrato for efetivamente citada e o arquivo existir, `POST /api/query` inclui a figura em `query.figures` e o chat a carrega no corpo da resposta usando a sessão autenticada.

Como o elemento `<img>` não envia o cabeçalho Bearer, o frontend busca a rota da figura com a credencial da sessão, valida o MIME `image/*` e cria uma URL local `blob:` para exibição. A CSP permite `blob:` somente em `img-src`; scripts, conexões e demais recursos preservam suas diretivas restritivas. Essas URLs locais são revogadas ao reiniciar o chat ou encerrar a sessão.

A disponibilização física da figura é uma operação deliberadamente manual e controlada. O processamento reconhece e indexa o contrato textual, mas não cria `storage/figures/{EVA-D...}/`, não copia imagens do diretório de origem, não importa URLs e não publica arquivos automaticamente. Depois que a ingestão atribui o identificador público da obra, o gestor autorizado cria o diretório correspondente e coloca somente as figuras aprovadas. Essa exclusividade operacional é sustentada pelas permissões do sistema de arquivos e pelo procedimento institucional, não apenas pelo papel de superadmin da aplicação; a escrita em `storage/figures/` deve permanecer restrita aos responsáveis autorizados e ao processo PHP somente no limite necessário para leitura e exclusão gerenciada.

Somente PNG, JPEG e WebP são aceitos. O MIME real é conferido, URLs externas e caminhos absolutos ou com `..` são rejeitados, e `FIGURE_MAX_BYTES` limita o tamanho individual. Arquivo ausente ou inválido não interrompe a resposta: a referência visual é simplesmente omitida. Excluir a obra remove também seu diretório privado de figuras.

## Módulos conectores

O EVA descobre zero, um ou vários módulos independentes em `modules/`. Cada pacote declara sua identidade, contrato, capacidades, eventos assinados e ponto de entrada em `module.json`. O Core conhece apenas o contrato genérico e nunca contém menu, função, regra pedagógica ou CSS específico de um módulo.

Módulos ativos podem receber o evento neutro de interação concluída. Depois da validação da resposta, o Core insere o evento idempotentemente em `module_events`, incluída no schema consolidado, e tenta processá-lo antes de devolver o HTTP. Cada módulo mantém cursor, esquema e histórico em seu próprio SQLite sob `modules/.runtime/data/<module-id>/module.sqlite`; processamento, registro idempotente e avanço do cursor são confirmados juntos nessa transação privada. Instalações legadas usam a migration `20260803_010_module_events.sql`. Ausência da tabela em um deploy incompleto ou falhas de módulo ficam isoladas e não invalidam a resposta documental já concluída.

`GET /api/modules` produz os itens de interface dos módulos ativos, e `GET /api/modules/{id}/dashboard` entrega o painel genérico com HTML e CSS pertencentes ao pacote. O superadmin usa `GET /api/admin/modules` e `PATCH` ou `DELETE /api/admin/modules/{id}` para ativar, desativar ou excluir. A exclusão exige confirmação digitada e remove tanto o pacote quanto seus dados internos. O contrato completo está em [`16_MODULOS.md`](16_MODULOS.md).

## Projetos e perfis de respostas

O superadmin gerencia o campo **Perfil de respostas** no cadastro e na edição de projetos. Campo vazio mantém somente o comportamento padrão. O conteúdo configurado é usado como governança complementar e não é exibido aos usuários comuns nem gravado nos metadados de auditoria; a auditoria registra somente presença e quantidade de perfis ativos.

O perfil é ativado apenas quando o usuário marca o projeto na raiz da árvore do chat. Seleções individuais de obras não herdam perfis, mesmo quando a obra pertence a um ou mais projetos. Essa regra torna explícita a mudança de comportamento aplicada à consulta.

Projetos selecionados podem compartilhar documentos. Antes de executar a recuperação, a API transforma os documentos resolvidos em uma lista de IDs únicos. Portanto, marcar dois projetos que contenham a mesma obra não duplica recuperação nem candidatos nos CIEs locais ou global. Os perfis configurados dos dois projetos permanecem ativos porque a governança acompanha a seleção dos projetos, não a quantidade de cópias do documento.

| Escopo selecionado | Resultado |
|---|---|
| Obra individual | Consulta a obra sem perfil de projeto |
| Projeto completo | Consulta suas obras e aplica seu perfil |
| Dois projetos com obra compartilhada | Consulta a obra compartilhada uma vez e aplica os dois perfis |
| Projeto completo mais obra individual | Aplica o perfil apenas ao projeto completo |

## Fila

O agendamento é idempotente por documento, etapa e versão gerencial da capacidade. Repetir o agendamento com a mesma configuração devolve os mesmos trabalhos. A chave interna de versão não é exposta pela API.

O worker reivindica um trabalho por execução. Sínteses interrompidas pelo limite seguro voltam à fila com seu progresso preservado. Falhas não são retomadas automaticamente: a retomada precisa ser solicitada e respeita `QUEUE_MAX_FAILURES`.

Chamadas externas continuam exigindo simultaneamente `AI_LIVE_ENABLED=true` e a opção `--live` no comando:

```powershell
php bin/queue-worker.php --live
```

A opção adicional `--drain` executa sucessivamente os trabalhos até esvaziar a fila atual. Como esse modo pode realizar muitas chamadas reais, ele continua condicionado às duas confirmações e deve ser iniciado conscientemente:

```powershell
php bin/queue-worker.php --live --drain
```

A interface consulta a fila automaticamente a cada três segundos enquanto houver etapas `queued` ou `running`. O progresso de sínteses é calculado pelas unidades hierárquicas persistidas; embeddings são persistidos por lote para que a barra avance durante o processamento.

O superadmin também pode usar **Processar fila no navegador**. A interface confirma o possível consumo real e envia `POST /api/admin/queue/run` com `confirm_live: true` sucessivamente até o worker retornar `idle`. Cada requisição executa somente uma passagem, `AI_LIVE_ENABLED=true` continua obrigatório no servidor e a aba deve permanecer aberta. A rota instancia `CognitiveQueueWorker` diretamente e não expõe execução arbitrária de comandos.

## White label

As classes são nomeadas por capacidade: `EmbeddingProvider` e `QueryAnswerProvider`. `CognitiveProviderFactory` usa a leitura neutra da configuração para construir cada uma.

O `.env` é o único ponto operacional de vínculo gerencial entre capacidade, fornecedor, endpoint, modelo e nome da variável de credencial. `config/ai.php` contém apenas nomes genéricos e leitura dessas variáveis. Os vínculos não aparecem em nomes de classes, rotas, comandos, contratos de domínio ou respostas do produto.

Nome, descrição, cores e logotipo são configurados pelas variáveis `BRAND_*`. Cores são aceitas apenas no formato hexadecimal de seis dígitos; o logotipo aceita caminho iniciado por `/` ou URL HTTPS. Quando `BRAND_LOGO_URL` está vazio, a interface usa a marca tipográfica de fallback sem requisitar um arquivo inexistente.

## Auditoria e métricas

A auditoria registra tipo de evento, entidade, identificador e metadados operacionais. Tokens, senhas, chaves, prompts, inputs, corpos e conteúdos são substituídos por `[REDACTED]`. Endereços de rede são armazenados somente como hash.

Cada requisição pública recebe um `X-Request-Id` aleatório, também disponível ao diagnóstico interno. Falhas são classificadas por categorias seguras, como `ai_output_truncated`, `ai_provider_http`, `ai_transport`, `ai_invalid_response`, `database` e `application`. Mensagens de exceção, respostas brutas do provedor e conteúdo documental não são gravados como diagnóstico.

As métricas são contagens agrupadas de documentos, classes e tipos de evidência, derivações, embeddings e trabalhos. Interações transitórias não são convertidas em métricas persistentes. As contagens não produzem ranking, relevância, confiança, intensidade ou qualquer peso cognitivo.

A resposta de `POST /api/query` inclui `context_intelligence`. Em rotas semânticas, o campo contém análises `hierarchical` por obra, até duas análises `primary` por obra e uma análise `global`; cada item expõe população, média, desvio padrão, CV, limites, regiões e diagnóstico κ quando aplicável. Em rotas não vetoriais, ele é uma lista vazia. O detalhamento pertence à resposta atual e não é persistido como métrica, evento ou memória.

## Exclusão

Excluir uma obra remove em cascata seus nós, evidências, derivações, embeddings, trabalhos, permissões e vínculos com projetos; depois, remove o arquivo-fonte privado e o diretório privado de figuras. Excluir um projeto pela camada de produto também remove todas as obras ainda vinculadas a ele, inclusive obras compartilhadas com outro projeto. Uma obra compartilhada que precise sobreviver deve ser desvinculada e preservada antes da exclusão do projeto.

## Limites operacionais

- listagens retornam no máximo 100 registros pela API atual;
- o upload respeita `DOCUMENT_MAX_BYTES`;
- cada figura local respeita `FIGURE_MAX_BYTES`;
- o corpo JSON possui limite de 64 KiB;
- o input de consulta possui limite de 20.000 bytes;
- trabalhos são únicos por versão e processados individualmente;
- os scripts e estilos funcionais são locais; as fontes web podem ser carregadas somente dos domínios Google Fonts autorizados pela CSP.
