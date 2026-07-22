# Contributing to EVA

Thank you for helping improve verifiable documentary intelligence.

## Before opening a change

1. Search existing issues and discussions.
2. For significant architectural changes, open a proposal before implementation.
3. Keep the Evidence Algorithm's persistent core limited to documents, normalized nodes, evidence, derivations, and embeddings.
4. Do not introduce cognitive scores, confidence, importance, or persistent interaction graphs without an accepted architectural proposal.

## Local setup

1. Copy `.env.example` to `.env` and provide local database settings.
2. Create an empty MariaDB/MySQL database.
3. Import `database/schema.sql`.
4. Serve `public/` as the web root.
5. Keep `AI_LIVE_ENABLED=false` while running the offline test suite.

## Coding rules

- Target PHP 8.2 or newer and use `declare(strict_types=1)`.
- Preserve provider-neutral capability boundaries.
- Keep original documentary content immutable and traceable.
- Add regression coverage for behavioral changes.
- Never commit credentials, uploaded documents, logs, database dumps, or personal data.
- Keep changes focused and avoid unrelated rewrites.

## Validation

Run PHP syntax validation before submitting a pull request:

```bash
find app bin bootstrap config public tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run relevant tests individually with an isolated test database. Tests use simulated AI providers and must not consume external credits unless a command explicitly documents and requires `--live`.

## Pull requests

Describe:

- the problem and intended behavior;
- the files and contracts affected;
- tests performed;
- database or configuration impact;
- security, privacy, and evidence-traceability considerations.

By submitting a contribution, you agree that it is licensed under Apache License 2.0.

