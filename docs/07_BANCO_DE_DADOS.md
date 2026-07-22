# Banco de dados

## Objetivo

Persistir a memória documental com integridade e rastreabilidade, sem duplicar relações transitórias nem armazenar julgamento ou peso.

## Entidades persistentes

- **documents:** fonte, formato, hash, caminho privado e estado.
- **document_nodes:** árvore normalizada, conteúdo, metadados e caminho estrutural.
- **evidences:** unidades `primary` ou `derived`, classificadas também por `evidence_type`.
- **evidence_derivations:** linhagem das evidências derivadas.
- **evidence_embeddings:** vetores versionados usados para localização.
- **processing_jobs:** fila das etapas `summaries` e `embeddings`.
- **audit_events:** eventos administrativos sanitizados.

Não existem tabelas `cnodes`, `cnode_evidences`, `cnode_embeddings` ou `interaction_analyses`.

## Evidências

Nós com conteúdo direto geram evidências `primary` do tipo `node_content`. Elas mantêm conteúdo e hash idênticos à origem e recebem identificador público `EVA-E`.

Sínteses geram evidências `derived` do tipo `node_summary`. `generation_model` e `generation_input_hash` versionam tecnicamente o conteúdo gerado. `evidence_derivations` conecta cada síntese ao conteúdo próprio e às sínteses filhas que a originaram.

## Embeddings

Cada vetor referencia uma evidência persistida e registra modelo, dimensão e hash do texto estruturado. Vetores de versões diferentes não são misturados. Similaridades calculadas em consulta não são armazenadas.

## Interações cognitivas

`simetry` e `assimetry` não são registros do banco. Elas são montadas durante consultas relacionais e validadas contra evidências primárias. Portanto, o banco não mantém pares, papéis, descrições, fragmentos ou resultados negativos de interação.

## Ausência de pesos

O esquema não armazena confiança, pontuação, similaridade cognitiva, intensidade, prioridade, importância ou contagem de conectividade.

## Regras

- Identificadores públicos são estáveis e diferentes das chaves internas.
- Conteúdo original nunca é substituído por síntese.
- A fonte permanece fora da pasta pública.
- Exclusão de documento remove seus registros dependentes; o arquivo exige tratamento explícito.
- Operações que alteram árvore e evidências usam transação.
- Interações de consulta nunca alteram o núcleo persistente.

O esquema inicial está em `database/schema.sql` e evolui por migrações incrementais.
