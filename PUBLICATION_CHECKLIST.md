# Publication checklist

Use this checklist for every public release. Always start from a fresh clone of the canonical GitHub repository in an exclusive local analysis directory, inspect the remote state, and copy only an explicit source allowlist.

## Release v1.2.0

- [x] Create a fresh clone from `alexandredovale/eva` under `.00-analise/` and verify `origin/main` before copying files.
- [ ] Copy only source, public documentation, empty schema, versioned migrations, and tests.
- [ ] Confirm that `.env`, operational databases, module SQLite files, Runtime state, uploaded documents, logs, dumps, backups, and private corpora are absent.
- [ ] Review the complete diff and run the offline regression suite in the release clone.
- [ ] Confirm that `database/` contains only `schema.sql`, versioned migrations, and tracked placeholders.
- [ ] Confirm that `modules/.runtime/` contains only HTTP protections and empty tracked placeholders.
- [ ] Review attribution, `CHANGELOG.md`, `CITATION.cff`, and the public version.
- [ ] Commit, create annotated tag `v1.2.0`, push `main` and the tag, then verify the remote commit.

## Recommended repository metadata

**Description**

> Provider-neutral evidence architecture for verifiable documentary memory, traceable RAG, and query-scoped semantic interactions.

**Topics**

```text
rag php artificial-intelligence document-intelligence semantic-search
evidence traceability mysql knowledge-retrieval explainable-ai
```

## Files intentionally excluded

The public repository is complete at the source-code and schema level. It intentionally excludes credentials, runtime configuration, operational databases, user data, uploaded sources, logs, private regression corpora, and third-party books.
