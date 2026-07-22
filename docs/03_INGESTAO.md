# Ingestão

## Objetivo

Transformar Markdown, JSON e XML em uma árvore interna única, mantendo conteúdo, ordem, hierarquia e referência à fonte, e persistir essa estrutura sem gerar inferências cognitivas.

## Validação

Antes do processamento, o sistema confirma:

- extensão compatível com Markdown, JSON ou XML;
- conteúdo dentro do limite configurado;
- nome e título válidos;
- UTF-8 válido para Markdown e JSON;
- JSON ou XML bem-formado;
- ausência de `DOCTYPE` no XML;
- hash SHA-256 da fonte.

## Parsers

- **Markdown:** títulos definem níveis; blocos numerados autorais de primeiro nível formam subunidades `item`; o texto contínuo permanece no nó estrutural correspondente. Numerações dentro de blocos de código não alteram a árvore.
- **JSON:** objetos e listas formam a árvore, preservando chaves e ordem.
- **XML:** elementos formam a árvore, preservando nomes, atributos e ordem.

Os parsers produzem o mesmo contrato normalizado e não geram resumos, embeddings ou interações cognitivas.

## Contrato normalizado

Cada documento contém formato, título, hash SHA-256 da fonte e nó raiz. Cada nó contém:

- tipo e título;
- caminho estrutural único;
- profundidade e ordem;
- conteúdo pertencente diretamente ao nó;
- referência precisa à origem;
- metadados próprios do formato;
- filhos em ordem documental.

As referências usam linhas no Markdown, JSON Pointer no JSON e XPath no XML.

## Upload HTTP implementado

`POST /api/documents` recebe um formulário multipart com o arquivo no campo `document` e título opcional no campo `title`.

A validação rejeita:

- ausência de arquivo;
- upload parcial ou erro informado pelo PHP;
- múltiplos arquivos no mesmo campo;
- arquivo que não veio de upload HTTP legítimo;
- tamanho vazio ou superior ao limite configurado;
- extensão diferente de Markdown, JSON ou XML;
- conteúdo incompatível com o parser selecionado;
- título inválido ou superior a 255 caracteres.

A aplicação nunca utiliza o nome original como caminho físico. O conteúdo é lido integralmente do arquivo temporário, processado pelo parser e armazenado com identificador interno fora da pasta pública.

Os logs registram identificador, formato, tamanho e contagens. Conteúdo documental, senhas, tokens e chaves não são registrados.
## Persistência implementada

O fluxo ocorre nesta ordem:

1. validar nome, tamanho e formato;
2. executar o parser correspondente;
3. registrar o documento com estado `received`;
4. armazenar a fonte original em `storage/documents`;
5. iniciar uma transação no banco;
6. persistir recursivamente o nó raiz e seus filhos;
7. gerar evidências primárias para conteúdos diretos utilizáveis;
8. finalizar o documento com estado `ready`;
9. reverter a árvore e marcar o documento como `failed` se a transação falhar.

A fonte fica fora da pasta pública e recebe um nome interno derivado do identificador permanente do documento. O nome original permanece no banco apenas como metadado de proveniência.

## Evidências primárias

Uma evidência primária é criada somente quando o nó possui conteúdo documental direto.

Não geram evidência primária:

- nós usados apenas para organizar filhos;
- conteúdo vazio ou composto somente por espaços;
- objetos JSON vazios `{}`;
- listas JSON vazias `[]`.

A evidência copia literalmente o conteúdo e o hash do nó de origem. Ela recebe classe `primary`, tipo `node_content`, resumo nulo e estado `validated`. Nesse contexto, `validated` confirma a rastreabilidade da extração, não a verdade do conteúdo.

Identificadores seguem os formatos:

```text
Documento: EVA-D000001
Evidência: EVA-E000001
```

## Limites desta etapa

A ingestão não gera:

- resumos;
- evidências derivadas;
- embeddings;
- interações `simetry` ou `assimetry`.

Essas operações pertencem às etapas cognitivas posteriores.

## Public regression fixture

The public repository uses `tests/fixtures/synthetic_systems_manual.md`, an original synthetic Markdown fixture distributed under Apache License 2.0. Its tests verify the source hash, structural paths, complete node content, literal evidence, and preservation of a semantic unit longer than 5,000 characters without redistributing third-party books or private operational documents.
