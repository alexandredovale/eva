# Produto

## Escopo

A camada de produto oferece uma interface administrativa e de consulta sobre a memória documental. As respostas continuam sustentadas por evidências primárias, e as interações `simetry` ou `assimetry` são resultados transitórios sem pesos.

## Acesso

`GET /api/health` e `GET /api/branding` são públicos. As demais rotas exigem uma sessão autenticada de usuário ou `Authorization: Bearer` com o token definido por `ADMIN_API_TOKEN`, conforme a política da rota. Operações administrativas continuam restritas ao superadmin. O token administrativo precisa ter ao menos 24 caracteres, não é persistido no banco e nunca integra logs ou respostas.

Na interface, a credencial permanece apenas no `sessionStorage` da aba. Encerrar a sessão remove esse valor.

A raiz HTTP encaminha somente rotas virtuais e assets para `public`. Arquivos ou diretórios existentes fora dessa pasta recebem `403`; listagens, acesso direto ao template e métodos de alteração não utilizados pela API são bloqueados pelo Apache. Cabeçalhos de CSP, enquadramento, MIME, referência, permissões e política de recursos são enviados pela superfície pública. O método `TRACE` exige `TraceEnable Off` na configuração global do Apache, fora do escopo permitido para `.htaccess`.

## Rotas

| Método | Rota | Função |
|---|---|---|
| `GET` | `/api/health` | Diagnóstico da aplicação e do banco |
| `GET` | `/api/branding` | Identidade visual pública sanitizada |
| `GET` | `/api/documents` | Lista o acervo e contagens documentais |
| `POST` | `/api/documents` | Persiste Markdown, JSON ou XML |
| `POST` | `/api/documents/{id}/process` | Agenda sínteses e embeddings |
| `POST` | `/api/admin/queue/run` | Executa uma passagem confirmada do worker; exclusivo do superadmin |
| `GET` | `/api/jobs` | Lista o estado da fila |
| `POST` | `/api/jobs/{id}/retry` | Retoma explicitamente um trabalho com falha |
| `POST` | `/api/query` | Executa consulta documental validada |
| `GET` | `/api/metrics` | Retorna apenas contagens descritivas |
| `GET` | `/api/audit` | Retorna eventos administrativos sanitizados |

## Chat conversacional

O box de consulta funciona como um transcript durante a permanência na página. Cada input aparece como mensagem do usuário e cada resultado como resposta documental independente. Rodadas concluídas permanecem visíveis, a interface rola para a mais recente e cada resposta mantém sua própria ação de cópia com pergunta, resposta e referências.

Durante uma nova requisição, o transcript preserva as rodadas anteriores e acrescenta o estado `Consultando evidências…`, acompanhado por três pontos amarelos animados. A preferência de redução de movimento do navegador desativa a animação. Se a requisição falhar, as rodadas já concluídas permanecem no box. Em caso de sucesso, o input é limpo e recebe foco para a próxima mensagem.

O estado visual pode conter todas as rodadas da conversa atual, mas somente as três rodadas concluídas mais recentes participam do próximo `POST /api/query`. Elas são anexadas ao próprio campo `input`; nenhuma nova rota, tabela ou entidade de conversa foi criada. Se o limite de 20.000 bytes exigir redução, a rodada mais antiga é removida por inteiro.

**Reiniciar chat** limpa o transcript e o contexto curto sem desmarcar os projetos ou obras selecionados. O estado não sobrevive a logout, novo login ou recarregamento e não é persistido no banco, na auditoria ou no armazenamento do navegador.

## Módulos conectores

O EVA descobre zero, um ou vários módulos independentes em `modules/`. Cada pacote declara sua identidade, contrato, capacidades, eventos assinados e ponto de entrada em `module.json`. O Core conhece apenas o contrato genérico e nunca contém menu, função, regra pedagógica ou CSS específico de um módulo.

Módulos ativos podem receber o evento neutro de interação concluída e processá-lo imediatamente após a persistência transacional. Cada módulo mantém cursor, esquema e histórico em seu próprio SQLite sob `modules/.runtime/data/<module-id>/module.sqlite`; falhas ficam isoladas e não invalidam a resposta documental já concluída.

`GET /api/modules` produz os itens de interface dos módulos ativos, e `GET /api/modules/{id}/dashboard` entrega o painel genérico com HTML e CSS pertencentes ao pacote. O superadmin usa `GET /api/admin/modules` e `PATCH` ou `DELETE /api/admin/modules/{id}` para ativar, desativar ou excluir. A exclusão exige confirmação digitada e remove tanto o pacote quanto seus dados internos. O contrato completo está em [`16_MODULOS.md`](16_MODULOS.md).

## Projetos e perfis de respostas

O superadmin gerencia o campo **Perfil de respostas** no cadastro e na edição de projetos. Campo vazio mantém somente o comportamento padrão. O conteúdo configurado é usado como governança complementar e não é exibido aos usuários comuns nem gravado nos metadados de auditoria; a auditoria registra somente presença e quantidade de perfis ativos.

O perfil é ativado apenas quando o usuário marca o projeto na raiz da árvore do chat. Seleções individuais de obras não herdam perfis, mesmo quando a obra pertence a um ou mais projetos. Essa regra torna explícita a mudança de comportamento aplicada à consulta.

Projetos selecionados podem compartilhar documentos. Antes de executar a recuperação, a API transforma os documentos resolvidos em uma lista de IDs únicos. Portanto, marcar dois projetos que contenham a mesma obra não duplica recuperação, evidências ou consumo do limite global de contexto. Os perfis configurados dos dois projetos permanecem ativos porque a governança acompanha a seleção dos projetos, não a quantidade de cópias do documento.

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

As classes são nomeadas por capacidade: `EmbeddingProvider`, `SummaryProvider` e `QueryAnswerProvider`. `CognitiveProviderFactory` usa a leitura neutra da configuração para construir cada uma.

O `.env` é o único ponto operacional de vínculo gerencial entre capacidade, fornecedor, endpoint, modelo e nome da variável de credencial. `config/ai.php` contém apenas nomes genéricos e leitura dessas variáveis. Os vínculos não aparecem em nomes de classes, rotas, comandos, contratos de domínio ou respostas do produto.

Nome, descrição, cores e logotipo são configurados pelas variáveis `BRAND_*`. Cores são aceitas apenas no formato hexadecimal de seis dígitos; o logotipo aceita caminho iniciado por `/` ou URL HTTPS. Quando `BRAND_LOGO_URL` está vazio, a interface usa a marca tipográfica de fallback sem requisitar um arquivo inexistente.

## Auditoria e métricas

A auditoria registra tipo de evento, entidade, identificador e metadados operacionais. Tokens, senhas, chaves, prompts, inputs, corpos e conteúdos são substituídos por `[REDACTED]`. Endereços de rede são armazenados somente como hash.

Cada requisição pública recebe um `X-Request-Id` aleatório, também disponível ao diagnóstico interno. Falhas são classificadas por categorias seguras, como `ai_output_truncated`, `ai_provider_http`, `ai_transport`, `ai_invalid_response`, `database` e `application`. Mensagens de exceção, respostas brutas do provedor e conteúdo documental não são gravados como diagnóstico.

As métricas são contagens agrupadas de documentos, classes e tipos de evidência, derivações, embeddings e trabalhos. Interações transitórias não são convertidas em métricas persistentes. As contagens não produzem ranking, relevância, confiança, intensidade ou qualquer peso cognitivo.

A resposta de `POST /api/query` inclui `context_intelligence`. Em rotas semânticas, o campo permite auditar por documento a média, o desvio padrão, o CV, os limites e os candidatos de cada região do CIE. Em rotas não vetoriais, ele é uma lista vazia. O detalhamento pertence à resposta atual e não é persistido como métrica, evento ou memória.

## Limites operacionais

- listagens retornam no máximo 100 registros pela API atual;
- o upload respeita `DOCUMENT_MAX_BYTES`;
- o corpo JSON possui limite de 64 KiB;
- o input de consulta possui limite de 20.000 bytes;
- trabalhos são únicos por versão e processados individualmente;
- os scripts e estilos funcionais são locais; as fontes web podem ser carregadas somente dos domínios Google Fonts autorizados pela CSP.
