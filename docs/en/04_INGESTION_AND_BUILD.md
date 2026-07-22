# Ingestion and cognitive build

## Input validation

The upload layer accepts Markdown, JSON, and XML. It verifies extension, configured size, name/title constraints, UTF-8 where applicable, well-formed JSON/XML, absence of XML `DOCTYPE`, and the SHA-256 source hash.

The original filename is metadata only. Physical storage uses an internal identifier outside the public web directory.

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

## Primary evidence

A primary evidence record is created only for direct usable node content. Empty nodes, whitespace-only content, `{}`, and `[]` do not produce primary evidence. The record copies the content and source hash literally and receives `evidence_class=primary`, `evidence_type=node_content`, and `status=validated`.

`validated` confirms extraction traceability; it does not assert that the source statement is universally true.

## Hierarchical summaries

`HierarchicalSummaryService` walks from leaves to the root. A parent summary receives its own content plus child summaries and records every originating evidence in `evidence_derivations`. Model and structural-input hash identify reusable versions.

## Embeddings

`EvidenceEmbeddingService` builds structured text containing document title, path, evidence class/type, and complete content. It batches complete units without dividing an individual unit.

`EmbeddingInputGuard` reserves a safety margin under the configured provider limit. An incompatible primary unit is never truncated. A directly traceable compatible derived summary may represent its semantic route; otherwise the build stops before the provider call and requires a real structural subdivision.

## Persistent boundary

The build ends with evidence, derivations, and embeddings. It never precomputes evidence pairs, interaction analyses, relationship embeddings, or persistent Cnodes.

