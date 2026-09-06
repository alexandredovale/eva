# Ingestion and cognitive build

## Input validation

The upload layer accepts Markdown, JSON, and XML. It verifies extension, configured size, name/title constraints, UTF-8 where applicable, well-formed JSON/XML, absence of XML `DOCTYPE`, valid figure hierarchy, and the SHA-256 source hash. Every `Figura`/`Figure` node must have an immediate non-visual thematic node as its parent.

The original filename is metadata only. Physical storage uses an internal identifier outside the public web directory.

`POST /api/documents` accepts a multipart form with the file in `document` and an optional title in `title`. Validation rejects a missing file, a partial or PHP-reported upload error, multiple files in the same field, a file that is not a legitimate HTTP upload, an empty or oversized file, an unsupported extension, content incompatible with the selected parser, and an invalid title or one longer than 255 characters.

Application logs may contain identifiers, format, size, and counts. Documentary content, passwords, tokens, and keys are not logged.

## Parsers

- **Markdown:** headings define levels. Authorial numbered blocks at the first level form `item` subunits; continuous text remains on its corresponding structural node. Numbering inside code blocks does not change the tree. A heading beginning with `Figura` or `Figure` is accepted only as the immediate child of another non-visual thematic section; a figure directly under the document root or under another figure rejects ingestion.
- **JSON:** objects and arrays form the tree while preserving keys and order. A `Figura...` or `Figure...` property must belong immediately to a thematic object, with contract fields as child properties.
- **XML:** elements form the tree while preserving names, attributes, and order. A `<figura>` or `<figure>` element must belong immediately to a thematic element, with contract fields as child elements.

All parsers produce the same normalized contract. They do not create summaries, embeddings, or cognitive interactions.

The complete bilingual contract and all six format/language templates are documented in [`06_API_AND_OPERATIONS.md`](06_API_AND_OPERATIONS.md#document-figures) and [`../examples/figure-contracts/`](../examples/figure-contracts/README.md).

## Normalized document contract

Every document records format, title, source hash, and a root node. Every node records:

- node type and title;
- unique structural path;
- depth and documentary order;
- content belonging directly to that node;
- exact source reference;
- format-specific metadata;
- ordered children.

Markdown uses line references, JSON uses JSON Pointer, and XML uses XPath.

## Persistence order

Ingestion proceeds in this order:

1. validate filename, size, and format;
2. run the corresponding parser;
3. create the document with status `received`;
4. store the original source under `storage/documents/`;
5. start a database transaction;
6. recursively persist the root node and its descendants;
7. create primary evidence for usable direct content;
8. finish the document with status `ready`;
9. roll the tree back and mark the document `failed` if the transaction fails.

The source filename stored on disk is derived from the permanent internal identifier. The original filename remains only as provenance metadata.

## Primary evidence

A primary evidence record is created only for direct usable node content. Empty nodes, whitespace-only content, `{}`, and `[]` do not produce primary evidence. The record copies the content and source hash literally and receives `evidence_class=primary`, `evidence_type=node_content`, and `status=validated`.

`validated` confirms extraction traceability; it does not assert that the source statement is universally true.

Public identifiers follow these forms:

```text
Document: EVA-D000001
Evidence: EVA-E000001
```

Ingestion alone does not generate embeddings, `simetry`, or `assimetry`. Those operations belong to the later cognitive stage.

The version 6.0.0 persistent semantic contract has one operational combination:

- `primary` + `node_content`: literal content extracted from one node.

## Embeddings

`EvidenceEmbeddingService` builds structured text containing document title, path, evidence class/type, and complete content. It batches complete units without dividing an individual unit.

`EmbeddingInputGuard` reserves a safety margin under the configured provider limit. An incompatible primary unit is never truncated and requires real structural subdivision to enter the semantic population.

The nominal limit is `AI_EMBEDDING_MAX_INPUT_TOKENS`; the guard uses 90% as a preventive margin for tokenizer differences. It validates all pending units before sending the first batch.

An oversized primary unit without its own embedding is outside the vector population until the document receives real structural subdivision. Increasing the batch, cutting text, or creating artificial fragments is not an allowed correction.

Model, dimension, and content hash identify the vector version. Similarity is used only during retrieval and is discarded after transient analysis.

## Persistent boundary

The build ends with primary evidence embeddings. It does not materialize Cnode, because that EVA conceptual derivation exists only during a query. It never precomputes evidence pairs, interaction analyses, relationship embeddings, or interaction caches.

`EvidenceEmbeddingService` persists complete primary units in technical batches and reuses identical model-and-hash versions.

The CLI exposes only the persistent cognitive stage:

```powershell
php bin\build-cognitive.php <document-id> --stage=embeddings --live
```

The command requires `AI_LIVE_ENABLED=true` in addition to `--live`.

## Public regression fixture

The public repository uses [`tests/fixtures/synthetic_systems_manual.md`](../../tests/fixtures/synthetic_systems_manual.md), an original synthetic Markdown fixture under Apache License 2.0. Tests verify source hash, structural paths, complete node content, literal evidence, and preservation of a semantic unit longer than 5,000 characters. Third-party books and private operational corpora are not distributed.
