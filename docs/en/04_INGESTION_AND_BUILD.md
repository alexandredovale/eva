# Ingestion and cognitive build

## Input validation

The upload layer accepts Markdown, JSON, and XML. It verifies extension, configured size, name/title constraints, UTF-8 where applicable, well-formed JSON/XML, absence of XML `DOCTYPE`, and the SHA-256 source hash.

The original filename is metadata only. Physical storage uses an internal identifier outside the public web directory.

`POST /api/documents` accepts a multipart form with the file in `document` and an optional title in `title`. Validation rejects a missing file, a partial or PHP-reported upload error, multiple files in the same field, a file that is not a legitimate HTTP upload, an empty or oversized file, an unsupported extension, content incompatible with the selected parser, and an invalid title or one longer than 255 characters.

Application logs may contain identifiers, format, size, and counts. Documentary content, passwords, tokens, and keys are not logged.

## Parsers

- **Markdown:** headings define levels. Authorial numbered blocks at the first level form `item` subunits; continuous text remains on its corresponding structural node. Numbering inside code blocks does not change the tree.
- **JSON:** objects and arrays form the tree while preserving keys and order.
- **XML:** elements form the tree while preserving names, attributes, and order.

All parsers produce the same normalized contract. They do not create summaries, embeddings, or cognitive interactions.

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

Ingestion alone does not generate summaries, derived evidence, embeddings, `simetry`, or `assimetry`. Those operations belong to the later cognitive stages.

## Hierarchical summaries

`HierarchicalSummaryService` walks from leaves to the root. A parent summary receives its own content plus child summaries and records every originating evidence in `evidence_derivations`. Model and structural-input hash identify reusable versions.

```text
fragments → subtitles → chapters → sections → parts → work
```

The persistent semantic contract has only these implemented combinations:

- `primary` + `node_content`: literal content extracted from one node;
- `derived` + `node_summary`: a hierarchical summary generated from identified evidence.

Generated and original content therefore remain distinguishable throughout retrieval and lineage resolution.

## Embeddings

`EvidenceEmbeddingService` builds structured text containing document title, path, evidence class/type, and complete content. It batches complete units without dividing an individual unit.

`EmbeddingInputGuard` reserves a safety margin under the configured provider limit. An incompatible primary unit is never truncated. A directly traceable compatible derived summary may represent its semantic route; otherwise the build stops before the provider call and requires a real structural subdivision.

The nominal limit is `AI_EMBEDDING_MAX_INPUT_TOKENS`; the guard uses 90% as a preventive margin for tokenizer differences. It validates all pending units before sending the first batch.

When a valid compatible `derived` + `node_summary` record directly linked through `evidence_derivations` represents an oversized primary unit, retrieval still resolves that route back to the complete primary content, identifier, and lineage. If no such derived representation exists, the diagnostic identifies the public evidence ID and stops. Increasing the batch, cutting text, or creating artificial fragments is not an allowed correction.

Model, dimension, and content hash identify the vector version. Similarity is used only during retrieval and is discarded after transient analysis.

## Persistent boundary

The build ends with evidence, derivations, and embeddings. It never precomputes evidence pairs, interaction analyses, relationship embeddings, or persistent Cnodes.

`HierarchicalSummaryService` reuses an identical version by model and input hash. `EvidenceEmbeddingService` persists complete units in technical batches. Its result reports `represented_by_derived`, making the number of oversized primary units represented through traceable summaries auditable.

The CLI exposes only the persistent cognitive stages:

```powershell
php bin\build-cognitive.php <document-id> --stage=summaries --live
php bin\build-cognitive.php <document-id> --stage=embeddings --live
```

Both commands require `AI_LIVE_ENABLED=true` in addition to `--live`.

## Public regression fixture

The public repository uses [`tests/fixtures/synthetic_systems_manual.md`](../../tests/fixtures/synthetic_systems_manual.md), an original synthetic Markdown fixture under Apache License 2.0. Tests verify source hash, structural paths, complete node content, literal evidence, and preservation of a semantic unit longer than 5,000 characters. Third-party books and private operational corpora are not distributed.
