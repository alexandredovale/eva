# EVA — Evidence Algorithm

[![DOI](https://zenodo.org/badge/DOI/10.5281/zenodo.21500611.svg)](https://doi.org/10.5281/zenodo.21500611)

**Current version:** `8.0.1`

**Version highlight:** EVA 8.0.1 consolidates the Portuguese and English project presentations as editorial mirrors, combines their previously complementary information without repetition, and makes explicit the joint contribution of the EVA technique and RAG to AI efficiency, sustainability, and control.

EVA is a provider-neutral PHP system for building, organizing, and querying **verifiable documentary cognitive memory**. EVA (Evidence Algorithm) preserves document hierarchy, stores literal source evidence, and validates every answer against the primary evidence recovered for the current query.

> This is the English presentation. The equivalent Portuguese version is available in [`README.pt-BR.md`](README.pt-BR.md).

## Why EVA

The proliferation of large-scale language models has imposed unprecedented challenges on global computational infrastructure, manifested in the intensive consumption of water and energy resources for data center cooling. The traditional approach, which depends on maintaining extensive context windows and continuous reprocessing, exacerbates the carbon footprint and perpetuates operational vulnerabilities, such as hallucinations and inefficiencies resulting from limited digital literacy. In contrast, Retrieval-Augmented Generation (RAG) systems establish a paradigm of surgical efficiency by decoupling vector storage from generative inference and retrieving only the pertinent semantic fragments, reducing the volume of tokens processed in each interaction. The EVA technique extends this benefit: before retrieval, it transforms documentary structure into complete, literal, and traceable semantic units; during the query, it calculates a local κq boundary and applies the Context Intelligence Engine (CIE) to refine the population deterministically; after generation, it accepts only answers, citations, and interactions supported by the elected evidence. RAG therefore controls **how much context** reaches the model, while EVA controls **how that context is formed, selected, and proven**. Their joint operation reduces redundant processing, energy consumption, and computational costs while increasing accuracy, auditability, and resistance to hallucinations and user formulation limitations. RAG and EVA thus become complementary components for sustainable, reliable artificial intelligence ecosystems operated under documentary control.

Many retrieval systems begin with arbitrary token chunks and later try to reconstruct context. EVA begins with documentary structure:

```text
Source → normalized tree → literal primary evidence → contextual embeddings

Semantic query → local routing → complete primary evidence → κq → upper-core CIE → primary sources
      → one bounded answer → local citation and interaction validation
```

## How EVA works

- Markdown, JSON, and XML converge into one normalized document tree.
- Every operational evidence item is literal, primary, and linked to its source.
- Embeddings represent complete, previously organized semantic units, never arbitrary character or token cuts.
- Direct, structural, and broad queries can avoid a transient question embedding.
- Conceptual and relational queries calculate their initial semantic distribution directly over validated primary-evidence embeddings.
- The query-local κq boundary emerges from the complete eligible similarity distribution; the upper core then passes through the primary and multidocument refinement stages, with no semantic Top-k or configured evidence count.
- The global CIE keeps core (`s ≥ μ + σ`) as the cutoff and adds convergence (`μ ≤ s < μ + σ`) only as complementary context for the answer provider.
- The model may retain only the relevant subset of the available primary context. Each analytical perspective occupies a new paragraph, every retained source is cited where it contributes, and citation-only inventories are rejected.
- Locally rejected generations are retried silently up to three total attempts; only the third consecutive failure produces a generic error for the user.
- Generation is skipped when no primary evidence is recovered.
- `simetry` and `assimetry` interactions exist only for the current query and require two cited primary sources with literal excerpts.
- Provider endpoints, models, and credential-variable names remain in environment configuration.
- Real AI use is disabled by default and requires two explicit confirmations.
- The chat accepts one independent question at a time, without inheriting input, population, embedding, CIE, answer, or interaction from the previous query.
- Superadmin-managed response profiles specialize project behavior without weakening evidence, citation, or output rules.
- Zero, one, or many independent connector modules can observe completed interactions without adding domain knowledge to the Core.
- The white-label product includes an administrative interface, authenticated API, queue, explicit retries, descriptive metrics, and sanitized audit data.

Phases 1 through 5 are operational, and the original roadmap is complete. The status of each deliverable is available in [Roadmap](docs/en/13_ROADMAP.md).

## Cognitive principle

Embeddings are generated from previously organized documentary units. A `Cnode` is only the transient understanding of an explicit semantic interaction between recovered evidence. It is not a persistent entity, score, graph edge, or embedding and has no lasting identity, importance rating, confidence, or intensity. The number of returned interactions is descriptive and does not determine truth, quality, priority, or relevance.

## Requirements

- PHP 8.2 or newer;
- MariaDB 10.4 or compatible MySQL;
- PHP extensions `curl`, `dom`, `json`, `mbstring`, `pdo`, `pdo_mysql`, and `pdo_sqlite`;
- Apache with `mod_rewrite` and `mod_headers`, or another server configured to expose only `public/`.

The project has no Composer or Node.js runtime dependency.

## Quick start

1. Clone the repository and enter the directory:

   ```bash
   git clone https://github.com/alexandredovale/eva.git
   cd eva
   ```

2. Create the local environment file:

   ```bash
   cp .env.example .env
   ```

   On PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Create an empty database and import the consolidated public schema:

   ```bash
   mysql -u root -p -e "CREATE DATABASE eva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p eva < database/schema.sql
   ```

   Existing installations must apply only outstanding migrations in filename order. Migration `010` remains available for databases created before `module_events` was consolidated into the schema.

4. Review `.env`, which contains the commented inventory of recognized settings, and configure the application, branding, database, AI, query, ingestion, and queue. Generate a unique superadmin token with at least 24 characters, for example:

   ```bash
   php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
   ```

   Store the result only in `ADMIN_API_TOKEN` inside `.env`. No key or credential should be written to code, documentation, logs, or the repository.

5. Configure the server to expose `public/` as the public root. On XAMPP, with the project inside `htdocs`, the root `.htaccess` forwards virtual routes to `public/` and blocks direct access to private files.

6. Keep `AI_LIVE_ENABLED=false` during installation and offline tests. Open `/api/health`, then `/`.

See [Installation](docs/en/03_INSTALLATION.md) and [Security and deployment](docs/en/07_SECURITY_AND_DEPLOYMENT.md) for the complete sequence.

## AI capability configuration

EVA names capabilities rather than vendors:

- `EmbeddingProvider`;
- `QueryAnswerProvider`.

Configure each capability in `.env` with its provider identifier, endpoint, model, and the **name** of the environment variable containing the credential. `config/ai.php` only reads these generic variables. Application classes know capabilities, and tests use simulated providers without consuming credits.

## Documentary ingestion and persistence

The `POST /api/documents` endpoint receives `multipart/form-data` with `document`, a required `.md`, `.json`, or `.xml` file, and `title`, an optional title derived from the filename when absent. Success returns HTTP `201`. Missing, incomplete, oversized, malformed multipart, or incompatible files are rejected before persistence.

The parser receives content that has already been read and never accesses file paths itself:

```php
use Eva\Application\Ingestion\Parser\ParserFactory;

$parser = ParserFactory::forFilename($filename);
$document = $parser->parse($content, $title);
$normalizedTree = $document->toArray();
```

`DocumentIngestionService` selects the parser, stores the source outside the public directory, persists the tree, and generates literal primary evidence. The default limit is 10 MB and can be changed with `DOCUMENT_MAX_BYTES`. Ingestion does not produce embeddings; cognitive construction is a later, explicit stage.

## Cognitive construction and retrieval

`EvidenceEmbeddingService` vectorizes only `primary:node_content` evidence with document title, path, node, and organized literal content. Before calling the provider, every pending unit is validated against `AI_EMBEDDING_MAX_INPUT_TOKENS`, with a preventive 10% margin. Technical batches group complete units and never fragment them. Larger units require genuine structural subdivision. An existing version for the same model and hash is reused before calling the provider.

For conceptual or relational queries, `DocumentContextRetriever` compares the transient input embedding with every eligible primary evidence item. The distribution determines κq; when no identifiable break exists, the complete population proceeds to the first CIE. This stage calculates the mean, population standard deviation, and coefficient of variation and forwards the core (`s ≥ μ + σ`), using convergence (`μ ≤ s < μ + σ`) as a fallback only when the core is empty. The global CIE retains the cutoff and adds its convergence only as complementary context. Similarities and statistics are transient.

`QueryAnswerProvider` may declare `simetry` or `assimetry` in the same call that produces the answer. `DocumentQueryService` accepts an interaction only when its participants belong to the context, were cited, and have fragments that exist literally in the evidence. None of this is persisted as a `Cnode`.

## Project response governance

The superadmin can assign an optional **Response profile** to each project. A profile may specialize audience, assistance role, vocabulary, tone, focus, or presentation, but remains subordinate to the base primary-evidence, citation, limitation, interaction-validation, and JSON-output rules.

| Chat selection | Documents queried | Profiles applied |
|---|---|---|
| One or more individually selected works | Only the selected works | No project profile |
| A project root | Every ready work in the project | That project's profile, when configured |
| Multiple project roots | The union of their ready works | Every configured profile from the selected projects |
| A project root plus an individual work | Project works plus the individual work | Only the selected project's profile |

When selected projects share a work, the document ID is deduplicated before retrieval and its evidence is queried once, while all profiles remain active. Compatible instructions may be combined; conflicts over the same aspect fall back to the base rules and neutral wording.

## Documentary query

`InputTypeDetector` recognizes direct, structural, conceptual, relational, and broad inputs without consuming AI. `DocumentContextRetriever` selects the route, uses a transient embedding and CIE only for conceptual and relational searches, and returns complete primary evidence through direct access or vector retrieval.

`DocumentQueryService` accepts only identifiers from the recovered context. Unknown citations, omitted markers, and inventories without analytical incorporation are rejected; the application does not add missing citations. A rejected output is regenerated silently up to three attempts with the same context. From the second attempt onward, the provider receives only a safe failure code and, when applicable, the ID of an already elected evidence item that was not incorporated. Only the third consecutive failure reaches the user as a generic message. Used evidence, `simetry`, `assimetry`, and limitations remain in separate fields.

In the web interface, the composer disappears immediately after submission. After the answer, **Ask another question** preserves the project and work scope, clears the result, restores the form, and starts retrieval from zero. While waiting, **Consulting evidence** displays three animated yellow dots and respects reduced-motion preferences. The result is not persisted as chat state in the database, audit trail, or `sessionStorage`; logout, a new login, or reload starts with an empty query.

A real query requires double confirmation:

```bash
php bin/query-document.php <document-id> --live "your question"
```

There is no Top-k or configurable numerical limit for semantic evidence. κq, the first source CIE, κe, primary CIE, and global CIE determine the query-local population. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` and `--evidence-limit=N` apply only to direct, structural, and broad routes. `QUERY_MAX_INTERACTIONS` and `--interaction-limit=N` limit only transient interactions.

## Responsible cognitive execution and queue

Construction with real providers requires `AI_LIVE_ENABLED=true`, the `--live` option, and explicit confirmation:

```bash
php bin/build-cognitive.php <document-id> --stage=embeddings --live
```

Each worker execution processes at most one job:

```bash
php bin/queue-worker.php --live
```

Use `--drain` only to intentionally consume the entire current queue. In the administrative area, **Process queue in browser** calls `POST /api/admin/queue/run` repeatedly until the queue is idle. Each request performs one pass, requires `confirm_live: true`, and remains subject to `AI_LIVE_ENABLED=true`. The browser tab must remain open during processing.

## Connector modules

A module behaves like an installable cartridge: it is an independent package under `modules/<module-id>/`, hierarchically above projects but never owned by a project, document, or user. Zero, one, or many modules may observe the same versioned event independently.

Each package declares `module.json`, implements the EVA Module Contract v1, and stores its private history in `modules/.runtime/data/<module-id>/module.sqlite`. The Core persists each neutral event once in `module_events`; active subscribers maintain independent cursors. A module receives a constrained read API and provider-neutral language capability, never database credentials or AI keys.

The superadmin can activate, deactivate, or definitively delete packages discovered in `modules/`. Deactivation preserves the package and SQLite; deletion requires typed confirmation and removes both. Updating means replacing only `modules/<module-id>/`, because data remains outside the package.

Module interfaces supply their own HTML and CSS. The Core knows only the generic dashboard contract and manifest name. The public distribution includes `com.eva.explorer` as an independent reference connector; removing it does not invalidate the product or unrelated tests. See [Connector modules](docs/en/17_MODULE_CONNECTORS.md).

## Administrative product and security

The interface is served at `/` and uses the same origin as the API. The browser retains the superadmin token only in `sessionStorage`, and administrative routes require `Authorization: Bearer`. The superadmin creates users, resets passwords, activates or deactivates accounts, and grants access by project or work. Normal users access only chat and password changes.

Passwords are persisted exclusively through `password_hash()`. On creation, reset, or rotation, a 16-character recovery code is displayed once and stored only as a hash. Without SMTP, recovery requires username, the current code, and a new password; completion revokes earlier sessions and issues a new code.

Access tables are defined in `database/migrations/20260721_008_user_access.sql`, and response profiles in `database/migrations/20260731_009_project_response_profile.sql`. Project permission includes every associated work; work permission does not grant the project's other works. The map of cardinalities, keys, relationships, and cascades is available in [Database relationships](docs/en/18_DATABASE_RELATIONSHIPS.md).

The superadmin can delete a work or project after typed confirmation. Deleting a work cascades through nodes, evidence, embeddings, jobs, permissions, and links. Deleting a project also removes every work it contains, even if shared. To preserve a shared work, first remove its project link; the deletion dialog identifies these cases and explains the procedure.

On XAMPP, the root `.htaccess` blocks real files and directories outside `public/`, disables listings, and prevents HTTP access to credentials, code, database files, documentation, tests, and storage. This protection requires `mod_rewrite`, `mod_headers`, and `AllowOverride All`. Global directives such as `TraceEnable Off` and `ServerTokens Prod` belong in server configuration, not `.htaccess`. Provider, endpoint, model, and credential-variable name remain in `.env` and do not appear in the interface or administrative responses.

## API surface

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/api/health` | Public application and database health |
| `GET` | `/api/branding` | Sanitized public branding |
| `POST` | `/api/auth/login` | Start a normal-user session |
| `POST` | `/api/auth/recover` | Recover a normal-user password |
| `GET` | `/api/scopes` | List projects and works available to the authenticated actor |
| `POST` | `/api/query` | Run a validated query over authorized scopes |
| `GET` | `/api/modules` | Discover active module dashboards |
| `GET` | `/api/modules/{id}/dashboard` | Render a dashboard through the generic contract |
| `GET` | `/api/admin/modules` | Inspect installed connectors (superadmin) |
| `PATCH` | `/api/admin/modules/{id}` | Activate or deactivate a connector (superadmin) |
| `DELETE` | `/api/admin/modules/{id}` | Delete a confirmed connector and its data (superadmin) |
| `GET` | `/api/documents` | List works and counts (superadmin) |
| `POST` | `/api/documents` | Ingest Markdown, JSON, or XML (superadmin) |
| `POST` | `/api/documents/{id}/process` | Queue primary embeddings (superadmin) |
| `POST` | `/api/admin/queue/run` | Run one confirmed worker pass (superadmin) |
| `GET` | `/api/jobs` | Inspect queue state (superadmin) |
| `POST` | `/api/jobs/{id}/retry` | Explicitly retry an allowed job (superadmin) |
| `GET` | `/api/metrics` | Return descriptive counts (superadmin) |
| `GET` | `/api/audit` | Return sanitized administrative events (superadmin) |

Health, branding, login, and recovery are public according to their contracts. Other routes require an authenticated session or administrative token and apply route-specific role and scope policies. The complete matrix is available in [API and operations](docs/en/06_API_AND_OPERATIONS.md).

## Directories

```text
app/            Application code
bin/            Explicit local commands
bootstrap/      Initialization and autoloading
config/         Configuration
database/       Versioned database schema
docs/           Official specification
modules/        Runtime and independent connector packages
public/         The only web-exposed directory
storage/        Unversioned local documents and logs
tests/          Automated tests
```

## Tests

The repository includes offline tests with simulated providers. The public fixture [`tests/fixtures/synthetic_systems_manual.md`](tests/fixtures/synthetic_systems_manual.md) is original material; third-party books, uploaded sources, production databases, and private logs are not distributed.

Validate syntax:

```bash
find app bin bootstrap config modules public tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run relevant tests individually after configuring an isolated database, for example:

```bash
php tests/ParsersTest.php
php tests/DocumentIngestionTest.php
php tests/CognitiveBuildTest.php
php tests/ContextIntelligenceEngineTest.php
php tests/ContextIntelligenceIntegrationTest.php
php tests/QueryTest.php
```

Tests must use `AI_LIVE_ENABLED=false` unless they explicitly document live behavior.

## Repository boundaries

The public distribution intentionally excludes:

- `.env` with local settings and credentials;
- operational database dumps;
- uploaded documents and runtime logs;
- module activation state, SQLite, WAL, and module logs;
- private users and access records;
- third-party book corpora used in private regressions.

The public schema and all versioned migrations remain included for empty installations.

## Documentation

- [Documentation index](docs/README.md)
- [Overview](docs/en/01_OVERVIEW.md)
- [Architecture](docs/en/02_ARCHITECTURE.md)
- [Installation](docs/en/03_INSTALLATION.md)
- [Ingestion and cognitive build](docs/en/04_INGESTION_AND_BUILD.md)
- [Query and single-turn chat](docs/en/05_QUERY_AND_CHAT.md)
- [Context Intelligence Engine](docs/en/09_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Cnode](docs/en/10_CNODE.md)
- [Database](docs/en/11_DATABASE.md)
- [Database relationships](docs/en/18_DATABASE_RELATIONSHIPS.md)
- [Mandatory rules](docs/en/12_MANDATORY_RULES.md)
- [Scientific scope and energy sustainability](docs/en/08_SCIENTIFIC_AND_ENERGY.md)
- [Roadmap](docs/en/13_ROADMAP.md)
- [Go-live readiness validation](docs/en/14_GO_LIVE_VALIDATION.md)
- [Pre-deployment acceptance](docs/en/15_PRE_DEPLOYMENT_ACCEPTANCE.md)
- [Project vision and impact assessment](docs/en/16_VISION.md)
- [Connector modules](docs/en/17_MODULE_CONNECTORS.md)
- [Scientific and philosophical material](philosophy/README.md)

## Contributing and security

Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before contributing. Report vulnerabilities privately according to [`SECURITY.md`](SECURITY.md). Community participation follows [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## License

Copyright 2026 EVA Project contributors.

Licensed under the [Apache License 2.0](LICENSE). The license permits use, modification, and distribution under its terms but does not grant trademark rights; see [`NOTICE`](NOTICE).
