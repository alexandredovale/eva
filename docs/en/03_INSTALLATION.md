# Installation

## 1. Platform

Install PHP 8.2+, MariaDB 10.4+ or a compatible MySQL release, and the required PHP extensions: `curl`, `dom`, `json`, `mbstring`, `pdo`, and `pdo_mysql`.

The application has no Composer or Node.js runtime dependency. Apache with `mod_rewrite` and `mod_headers` is the documented server; another server is acceptable only when it exposes the same `public/` boundary and security behavior.

## 2. Environment

Copy `.env.example` to `.env`. Set application URL, database connection, branding, query limits, and queue identity. Generate a unique `ADMIN_API_TOKEN` with at least 24 characters.

Set `QUERY_CANDIDATE_LIMIT=20` for the default CIE Top-k. This limit is per document and independent from the final `QUERY_MAX_EVIDENCE` context cap.

Leave provider fields empty and `AI_LIVE_ENABLED=false` until the local installation and offline tests are complete.

Never copy production secrets into examples, issue reports, test output, or documentation. Provider configuration stores the *name* of the credential environment variable; the credential itself belongs only in that local variable.

## 3. Database

Create an empty UTF-8 database and import `database/schema.sql`. The consolidated schema creates all 14 current main-database tables, including the Module Runtime mailbox. The repository does not require seed data and does not include an operational dump, user records, uploaded sources, or generated evidence.

```bash
mysql -u root -p -e "CREATE DATABASE eva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p eva < database/schema.sql
```

For an existing installation, apply outstanding migrations in filename order and back up the private database before any migration. Migration `20260803_010_module_events.sql` remains the upgrade path for databases created before `module_events` entered the consolidated schema.

## 4. Storage permissions

The PHP process needs read/write access to:

```text
storage/documents/
storage/logs/
```

These directories contain only `.gitkeep` in the public repository. Runtime contents are ignored by Git.

Do not make the entire project writable. Keep `.env`, application source, database artifacts, and server configuration readable only by the accounts that need them.

## 5. Web server

The preferred document root is `public/`. With the project directly inside an Apache/XAMPP `htdocs` directory, the root `.htaccess` forwards virtual routes to `public/` while denying direct access to real private paths.

Enable `mod_rewrite`, `mod_headers`, and `AllowOverride All`. Production Apache configuration should also apply global directives such as `TraceEnable Off` and reduced server signatures.

## 6. First checks

1. Open `GET /api/health`.
2. Open `/` and provide the superadmin token.
3. Create a normal user if needed.
4. Ingest a small original Markdown, JSON, or XML document.
5. Verify the document tree and queue state before enabling live providers.

Run relevant offline tests against an isolated empty test database. Tests must remain under `AI_LIVE_ENABLED=false` unless both the test and its command explicitly document live behavior.

## 7. Provider activation

For each capability, configure provider identifier, endpoint, model, and credential-variable name. Store the actual credential only in the named local environment variable.

Real calls require `AI_LIVE_ENABLED=true`; CLI build and query commands additionally require `--live`. This double confirmation prevents accidental external consumption.

```powershell
php bin\build-cognitive.php <document-id> --stage=summaries --live
php bin\build-cognitive.php <document-id> --stage=embeddings --live
php bin\query-document.php <document-id> --live "your question"
```

For queue operation, deployment hardening, backup requirements, and post-upload verification, continue with [API and operations](06_API_AND_OPERATIONS.md) and [Security and deployment](07_SECURITY_AND_DEPLOYMENT.md).
