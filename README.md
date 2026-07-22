# EVA — Evidence Algorithm

EVA is a provider-neutral PHP system for building and querying **verifiable documentary memory**. It preserves document hierarchy, keeps literal source evidence separate from generated summaries, and validates every answer against the primary evidence recovered for the current query.

`Cnode` is the transient understanding of an explicit semantic interaction between recovered evidence. It is not a persistent entity, score, graph edge, or embedding.

> The repository is English-first. The original Portuguese documentation remains available in [`docs/`](docs/), and the Portuguese project overview is available as [`README.pt-BR.md`](README.pt-BR.md).

## Why EVA

Many retrieval systems begin with arbitrary token chunks and later try to reconstruct context. EVA begins with documentary structure:

```text
Source → normalized tree → literal primary evidence
       → traceable hierarchical summaries → contextual embeddings

Query → local input routing → recovered evidence → primary sources
      → one bounded answer → local citation and interaction validation
```

Core properties:

- Markdown, JSON, and XML converge into one normalized document tree.
- Primary evidence is literal; derived evidence is generated and explicitly linked to its sources.
- Embeddings represent complete, previously organized semantic units rather than arbitrary cuts.
- Direct, structural, and broad queries can avoid a transient query embedding.
- Conceptual and relational queries use semantic retrieval and resolve summaries back to primary evidence.
- Answer generation is skipped when no primary evidence is recovered.
- `simetry` and `assimetry` interactions exist only for the current query and require two cited primary sources with literal excerpts.
- Provider endpoints, models, and credential-variable names remain environment configuration.
- Real AI use is disabled by default and requires two explicit confirmations.
- The chat displays the current transcript while sending at most the three previous completed turns as temporary conversational context.

## Requirements

- PHP 8.2 or newer
- MariaDB 10.4+ or compatible MySQL
- PHP extensions: `curl`, `dom`, `json`, `mbstring`, `pdo`, and `pdo_mysql`
- Apache with `mod_rewrite` and `mod_headers`, or another web server configured to expose only `public/`

The project has no Composer or Node.js runtime dependency.

## Quick start

1. Clone the repository and enter it:

   ```bash
   git clone https://github.com/YOUR-ACCOUNT/eva.git
   cd eva
   ```

2. Create a local environment file:

   ```bash
   cp .env.example .env
   ```

   On PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Create an empty database and import the public schema:

   ```bash
   mysql -u root -p -e "CREATE DATABASE eva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p eva < database/schema.sql
   ```

4. Edit `.env` with local database credentials. Generate a unique superadmin token of at least 24 characters, for example:

   ```bash
   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
   ```

   Store the result only in `ADMIN_API_TOKEN` inside `.env`.

5. Configure the web server so `public/` is the public document root. When using XAMPP with the project inside `htdocs`, the root `.htaccess` forwards virtual routes to `public/` and blocks direct access to private project files.

6. Keep `AI_LIVE_ENABLED=false` for installation and offline testing. Open `/api/health`, then `/`.

For a complete setup and deployment sequence, see [`docs/en/03_INSTALLATION.md`](docs/en/03_INSTALLATION.md) and [`docs/en/07_SECURITY_AND_DEPLOYMENT.md`](docs/en/07_SECURITY_AND_DEPLOYMENT.md).

## AI capability configuration

EVA names capabilities rather than vendors:

- `EmbeddingProvider`
- `SummaryProvider`
- `QueryAnswerProvider`

Configure each capability in `.env` with a provider identifier, endpoint, model, and the **name** of the environment variable containing its credential. Never place real credentials in code or documentation.

Real cognitive build commands require both `AI_LIVE_ENABLED=true` and `--live`:

```bash
php bin/build-cognitive.php <document-id> --stage=summaries --live
php bin/build-cognitive.php <document-id> --stage=embeddings --live
```

Real CLI queries use the same double confirmation:

```bash
php bin/query-document.php <document-id> --live "your question"
```

The queue worker processes one job by default:

```bash
php bin/queue-worker.php --live
```

Use `--drain` only when you intentionally want to process the complete current queue.

## API surface

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/api/health` | Public application and database health |
| `GET` | `/api/branding` | Sanitized public branding |
| `GET` | `/api/documents` | List documents allowed by the current scope |
| `POST` | `/api/documents` | Ingest Markdown, JSON, or XML |
| `POST` | `/api/documents/{id}/process` | Queue summaries and embeddings |
| `GET` | `/api/jobs` | Inspect queue state |
| `POST` | `/api/jobs/{id}/retry` | Explicitly retry an allowed failed job |
| `POST` | `/api/query` | Run a validated documentary query |
| `GET` | `/api/metrics` | Return descriptive counts |
| `GET` | `/api/audit` | Return sanitized administrative events |

Except for health and public branding, routes require an authenticated user session or the administrative bearer token according to route policy.

## Tests

The repository includes offline tests with simulated AI providers. The public integration fixture at [`tests/fixtures/synthetic_systems_manual.md`](tests/fixtures/synthetic_systems_manual.md) is original project material; no third-party books, uploaded sources, production databases, or private logs are distributed.

Validate syntax:

```bash
find app bin bootstrap config public tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run relevant tests individually after configuring an isolated test database, for example:

```bash
php tests/ParsersTest.php
php tests/DocumentIngestionTest.php
php tests/CognitiveBuildTest.php
php tests/QueryTest.php
```

Tests must run with `AI_LIVE_ENABLED=false` unless a test and command explicitly document live behavior.

## Repository boundaries

This public distribution intentionally excludes:

- `.env` and `api_key.md`;
- operational database dumps;
- uploaded documents and runtime logs;
- private user or access records;
- third-party book corpora used in private regression environments.

The public schema and all versioned migrations remain included so a new installation can create its own empty database.

## Documentation

- [Documentation index](docs/README.md)
- [Architecture](docs/en/02_ARCHITECTURE.md)
- [Installation](docs/en/03_INSTALLATION.md)
- [Ingestion and cognitive build](docs/en/04_INGESTION_AND_BUILD.md)
- [Query and conversational continuity](docs/en/05_QUERY_AND_CHAT.md)
- [API and operations](docs/en/06_API_AND_OPERATIONS.md)
- [Security and deployment](docs/en/07_SECURITY_AND_DEPLOYMENT.md)
- [Scientific scope and energy sustainability](docs/en/08_SCIENTIFIC_AND_ENERGY.md)
- [Scientific and philosophical material](philosophy/README.md)

## Contributing and security

Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a pull request. Report vulnerabilities privately according to [`SECURITY.md`](SECURITY.md). Community participation is governed by [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## License

Copyright 2026 EVA Project contributors.

Licensed under the [Apache License 2.0](LICENSE). The license permits use, modification, and distribution subject to its terms. It does not grant trademark rights; see [`NOTICE`](NOTICE).

