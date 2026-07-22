# Publication checklist

This directory is a sanitized public distribution. Upload its **contents** as the repository root so `README.md`, `LICENSE`, `.gitignore`, and `.github/` remain at the top level.

## Before the first public release

- [ ] Create an empty GitHub repository without an automatically generated README, license, or `.gitignore`.
- [ ] Upload or push only the contents of this directory.
- [ ] Confirm that GitHub detects **Apache License 2.0**.
- [ ] Confirm that `.env`, `api_key.md`, `database/actual/eva.sql`, `docs/test/`, `docs/doc_test.md`, uploaded documents, and logs are absent.
- [ ] Confirm that `database/` contains only `schema.sql` and versioned migrations.
- [ ] Enable GitHub Actions and verify the **PHP quality** workflow.
- [ ] Enable secret scanning, push protection, and private vulnerability reporting.
- [ ] Protect `main` with pull requests and required status checks.
- [ ] Review the project attribution in `NOTICE` and `CITATION.cff`.
- [ ] Replace the example clone URL in `README.md` after choosing the final owner and repository name.
- [ ] Create a clean clone, copy `.env.example` to `.env`, import the empty schema, and validate installation.
- [ ] Publish release `v1.0.0` with release notes derived from `CHANGELOG.md`.

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

