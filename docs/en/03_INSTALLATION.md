# Installation

## 1. Platform

Install PHP 8.2+, MariaDB/MySQL, and the required PHP extensions: `curl`, `dom`, `json`, `mbstring`, `pdo`, and `pdo_mysql`.

## 2. Environment

Copy `.env.example` to `.env`. Set application URL, database connection, branding, query limits, and queue identity. Generate a unique `ADMIN_API_TOKEN` with at least 24 characters.

Leave provider fields empty and `AI_LIVE_ENABLED=false` until the local installation and offline tests are complete.

## 3. Database

Create an empty database and import `database/schema.sql`. The repository does not include an operational dump, user records, uploaded sources, or generated evidence.

For an existing installation, apply migrations in filename order and back up the private database before any migration.

## 4. Storage permissions

The PHP process needs read/write access to:

```text
storage/documents/
storage/logs/
```

These directories contain only `.gitkeep` in the public repository. Runtime contents are ignored by Git.

## 5. Web server

The preferred document root is `public/`. With the project directly inside an Apache/XAMPP `htdocs` directory, the root `.htaccess` forwards virtual routes to `public/` while denying direct access to real private paths.

Enable `mod_rewrite`, `mod_headers`, and `AllowOverride All`. Production Apache configuration should also apply global directives such as `TraceEnable Off` and reduced server signatures.

## 6. First checks

1. Open `GET /api/health`.
2. Open `/` and provide the superadmin token.
3. Create a normal user if needed.
4. Ingest a small original Markdown, JSON, or XML document.
5. Verify the document tree and queue state before enabling live providers.

## 7. Provider activation

For each capability, configure provider identifier, endpoint, model, and credential-variable name. Store the actual credential only in the named local environment variable.

Real calls require `AI_LIVE_ENABLED=true`; CLI build and query commands additionally require `--live`. This double confirmation prevents accidental external consumption.

