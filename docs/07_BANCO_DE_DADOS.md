# Banco de dados

## Objetivo

Persistir a memória documental com integridade e rastreabilidade, sem duplicar relações transitórias nem armazenar julgamento ou peso.

As cardinalidades, chaves estrangeiras, vínculos lógicos e efeitos de exclusão estão detalhados em [`17_RELACIONAMENTO_BANCO_DADOS.md`](17_RELACIONAMENTO_BANCO_DADOS.md).

## Entidades persistentes

- **documents:** fonte, formato, hash, caminho privado e estado.
- **document_nodes:** árvore normalizada, conteúdo, metadados e caminho estrutural.
- **evidences:** unidades `primary` ou `derived`, classificadas também por `evidence_type`.
- **evidence_derivations:** linhagem das evidências derivadas.
- **evidence_embeddings:** vetores versionados usados para localização.
- **processing_jobs:** fila das etapas `summaries` e `embeddings`.
- **audit_events:** eventos administrativos sanitizados.
- **users:** identidades normais, hashes de senha e de recuperação e estado de acesso.
- **user_sessions:** sessões autenticadas com token armazenado somente como hash e expiração.
- **projects:** agrupamentos de obras, estado ativo e perfil complementar de respostas administrado pelo superadmin.
- **project_documents:** relação muitos-para-muitos entre projetos e obras.
- **user_projects:** concessões de acesso integral a projetos.
- **user_documents:** concessões de acesso direto a obras individuais.
- **module_events:** caixa postal neutra e orientada a acréscimos de eventos permitidos pelo contrato de módulos, com retenção administrativa explícita.

Não existem tabelas `cnodes`, `cnode_evidences`, `cnode_embeddings` ou `interaction_analyses`.

## Persistência dos módulos

`module_events` é a única tabela adicional do banco principal necessária ao Runtime. Ela integra `database/schema.sql` nas instalações novas e não contém regra, estado analítico ou esquema específico de qualquer módulo. A migration `database/migrations/20260803_010_module_events.sql` é mantida para atualizar bancos criados antes dessa consolidação. Depois que a resposta é validada e a auditoria é registrada, o evento sanitizado é inserido idempotentemente na caixa postal antes da resposta HTTP; em seguida, o Runtime tenta entregá-lo aos módulos ativos assinantes. Essa inserção não faz parte de uma transação de mutação da consulta, que é somente leitura em relação ao acervo. Se uma instalação incompleta não possuir a tabela ou se o despacho falhar, `ProductApi` registra aviso seguro e preserva a resposta documental já validada.

Cada módulo persiste seu próprio estado em `modules/.runtime/data/<module-id>/module.sqlite`. Esses bancos SQLite são privados, independentes do MySQL, versionados pelo próprio pacote e nunca enviados ao Git. Não existem chaves estrangeiras ou alterações nas tabelas preexistentes do Core para acomodar um módulo.

## Evidências

Nós com conteúdo direto geram evidências `primary` do tipo `node_content`. Elas mantêm conteúdo e hash idênticos à origem e recebem identificador público `EVA-E`.

Sínteses geram evidências `derived` do tipo `node_summary`. `generation_model` e `generation_input_hash` versionam tecnicamente o conteúdo gerado. `evidence_derivations` conecta cada síntese ao conteúdo próprio e às sínteses filhas que a originaram.

## Embeddings

Cada vetor referencia uma evidência persistida e registra modelo, dimensão e hash do texto estruturado. Vetores de versões diferentes não são misturados. Similaridades, média, desvio padrão, coeficiente de variação, regiões do CIE e contexto final calculados em consulta não são armazenados.

## Interações cognitivas

`simetry` e `assimetry` não são registros da memória documental. Elas são produzidas na mesma chamada que formula a resposta sempre que o contexto disponível contém ao menos duas evidências e o limite de interações é positivo, independentemente do tipo inicial do input. Depois, são validadas contra evidências primárias citadas. O banco não mantém pares, papéis, descrições, fragmentos ou resultados negativos de interação.

Para observabilidade operacional, o evento sanitizado `document_queried` registra em `audit_events` somente as contagens `simetry_count` e `assimetry_count` da consulta concluída. Essas contagens não permitem reconstruir uma interação e não integram a memória cognitiva. Se houver módulo ativo assinante, o Runtime também pode persistir na caixa postal `module_events` o envelope permitido de `interaction.completed`; esse evento contém input, resposta, referências públicas de evidência e limitações, mas não persiste os objetos `simetry`/`assimetry` do Core.

## Ausência de pesos

O esquema não armazena confiança, pontuação, similaridade cognitiva, intensidade, prioridade, importância ou contagem de conectividade.

## Regras

- Identificadores públicos são estáveis e diferentes das chaves internas.
- Conteúdo original nunca é substituído por síntese.
- A fonte permanece fora da pasta pública.
- Exclusão de documento remove seus registros dependentes; o arquivo exige tratamento explícito.
- Operações que alteram árvore e evidências usam transação.
- Interações de consulta nunca alteram o núcleo documental persistente; somente contagens sanitizadas de auditoria e eventos modulares permitidos podem registrar a ocorrência operacional.
- O perfil de respostas de um projeto orienta a geração somente quando esse projeto é selecionado explicitamente e não substitui as regras documentais do sistema.
- Eventos de módulos são sanitizados, rejeitam campos sensíveis e não autorizam escrita de volta no núcleo documental.

O esquema consolidado está em `database/schema.sql` e cria todas as 14 tabelas atuais, inclusive `module_events`. Instalações novas importam somente esse arquivo. Instalações existentes aplicam em ordem todas as migrations ainda ausentes; a migration `010` continua sendo o caminho de upgrade para bancos anteriores à consolidação da caixa postal modular.
