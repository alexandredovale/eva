# Pre-deployment acceptance

## Decision record

**Result on July 22, 2026: APPROVED FOR CONTROLLED UPLOAD.**

This is a readiness decision from before the application upload. At the time of execution, the application was not yet published at `https://eva.oceanno.com.br`; the page then served by that domain was therefore not treated as an application failure. Online acceptance must be completed after upload by running the verifier described below.

This document preserves the original environment-specific record. It does not certify a later build, database, host, or provider configuration.

## Visual smoke test

The real application was served by local Apache over HTTPS and checked at desktop, tablet, and mobile dimensions.

The test validated:

- superadmin and normal-user login and logout;
- the responsive project/work tree used for permission assignment;
- visual and functional inheritance of works when a project is selected;
- an isolated grant to `O Livro dos Espíritos`, without exposing `O Livro dos Médiuns` or the complete project in chat;
- checkbox selection in chat;
- a real question and the display of its answer with evidence;
- removal of the visible question and answer after logout;
- no restoration of the prior conversation after a new login;
- widths of 1440 × 1000, 768 × 1024, and 390 × 844 without unintended horizontal scrolling;
- no HTTP or console errors after correction.

The smoke test identified and corrected two interface defects:

1. the logout button was hidden at widths up to 820 px;
2. the branding fallback requested a nonexistent `/logo/logo.svg` and generated HTTP 404.

The original screenshots were stored in a local validation-artifact directory outside the repository. The temporary user, permissions, sessions, and smoke-specific audit events were deleted after the test, and user/session counts returned to their initial values.

## Local infrastructure acceptance

The local automated verifier passed **18 of 18 checks**:

```powershell
php bin\verify-deployment.php https://localhost/eva.oceanno.com.br --local
```

Confirmed results:

- Apache listening on ports 80/443 and MySQL on 3306;
- `/api/health` ready and the database available;
- CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Resource-Policy`, and `Cache-Control` present;
- random `X-Request-Id` and no `X-Powered-By`;
- `.env`, `api_key.md`, logs, SQL dumps, and `.git` blocked from HTTP access;
- a traversal attempt toward `.env` did not expose the file;
- no queued, running, or failed jobs;
- duplicate OpenSSL configuration corrected for PHP CLI and `httpd -t` successful.

### Backup and restoration

The test created a real database dump, restored it into a temporary database, compared 13 tables and their row counts, archived `storage/documents`, and compared extracted file hashes. The temporary database, directory, and artifacts were deleted during cleanup.

This proves that the technical procedure worked in that environment. Production still needs a recurring backup schedule, retention policy, and a copy outside the application server.

### Basic concurrency

- 100 concurrent health-check requests: 100% HTTP 200, p95 112 ms;
- 50 concurrent authenticated `/api/me` requests: 100% HTTP 200, p95 41 ms.

These figures are a local smoke check, not a hosting-plan capacity forecast.

## Domain infrastructure before upload

Even before application publication, the domain layer confirmed:

- HTTP-to-HTTPS redirect;
- TLS 1.3 and a certificate valid for `eva.oceanno.com.br`;
- HTTP 405 for `TRACE`.

After upload, these properties and the application headers must be validated together. The code emits HSTS only for HTTPS requests outside local hosts.

## Safe log diagnostics

Diagnostic logging was updated to classify failures without recording sensitive content. Covered categories include:

- truncated AI output;
- provider HTTP failure, preserving only a safe numeric status;
- transport failure and timeout;
- invalid response or serialization failure;
- AI configuration failure;
- database failure;
- generic application failure.

Security tests confirmed removal of passwords, secrets, tokens, API keys, Bearer headers, key-shaped patterns, prompts, inputs, content, and request/response bodies. Clients continue to receive generic messages.

## Final regression record

At the original pre-CIE acceptance, 15 suites ran without paid provider calls and passed **883 assertions**. JavaScript syntax validation, the complete commented inventory of 46 `.env` variables, the real backup/restore test, and the local deployment verifier also passed.

For the August 2, 2026 CIE update, `tests/ContextIntelligenceEngineTest.php` passed 13 assertions, `tests/ContextIntelligenceIntegrationTest.php` passed 10, and `tests/QueryTest.php` passed 49 without external calls. Before the next deployment, the complete regression and one controlled live semantic query must still run. The current default is `QUERY_CANDIDATE_LIMIT=20`; another value from `1` to `200` requires explicit validation.

## Mandatory post-upload procedure

1. Publish the project with all `.htaccess` files intact and without exposing `.env`, credentials, logs, dumps, or `.git`.
2. Configure the production `.env`, writable permissions for `storage/documents` and `storage/logs`, and the queue worker or cron schedule.
3. Configure recurring database and document backups with retention and an off-server copy.
4. From a machine that can reach the public domain, run:

   ```powershell
   php bin\verify-deployment.php https://eva.oceanno.com.br
   ```

5. Require zero verifier failures, then perform a final superadmin login and normal-user login on the published domain.
6. Confirm that a conceptual or relational query returns `context_intelligence`, preserves leading core and complementary convergence, resolves only primary sources, and requires complete analytical incorporation of final context.

If any verifier check fails, keep the release in acceptance until corrected. The entire paid AI matrix need not be repeated only when code, database, and configuration are exactly those accepted; otherwise revalidate the affected behavior. In all cases, complete the final online smoke test and one controlled profile-aware query.

See [Security and deployment](07_SECURITY_AND_DEPLOYMENT.md) for the current deployment checklist and [Go-live readiness validation](14_GO_LIVE_VALIDATION.md) for the preceding functional record.
