# Security and deployment

## Public boundary

Only `public/` should be web-accessible. Source code, environment files, database files, documentation, tests, storage, and logs must never be served directly.

The root Apache configuration blocks real files and directories outside the public surface, disables indexes, restricts unused mutation methods, and forwards only virtual application routes. Validate equivalent rules when using Nginx or another server.

The application emits a restrictive Content Security Policy, framing, MIME-sniffing, referrer, permissions, cache, and resource policies. It removes `X-Powered-By`, assigns a random `X-Request-Id`, and emits HSTS for non-local HTTPS requests. Apache's global configuration—not `.htaccess`—must disable `TRACE` with `TraceEnable Off` and reduce server signatures.

## Secrets

- Never commit `.env`, tokens, passwords, provider responses, or production endpoints containing credentials.
- Use `.env.example` only for names and safe placeholders.
- Rotate any credential suspected of exposure; removing a file from the latest commit does not remove it from Git history.
- Enable GitHub secret scanning and push protection after publication.

## Private runtime data

Operational dumps, uploaded documents, logs, user records, access grants, and password hashes are not source code and are excluded from the public repository. The public package contains only the empty schema and versioned migrations.

Back up the database and `storage/documents/` together. A production procedure needs a schedule, retention policy, restoration test, and a copy outside the application server. Never use an operational dump as installation seed data.

## Safe diagnostics

Client error messages remain generic. Server diagnostics use categories such as truncated AI output, provider HTTP failure, transport failure, invalid provider response, database failure, and application failure. Passwords, tokens, credential-shaped values, Bearer headers, prompts, query inputs, documentary content, and raw request/provider bodies must remain omitted or redacted.

## Production checklist

1. Use HTTPS and a production hostname.
2. Set `APP_ENV=production` and `APP_DEBUG=false`.
3. Generate unique database and administrative credentials.
4. Serve only `public/` and deny directory listing.
5. Apply global Apache hardening such as `TraceEnable Off` and reduced server tokens.
6. Restrict filesystem permissions for `.env`, storage, and logs.
7. Back up and restore the private database in an isolated verification environment.
8. Run offline regression tests and the deployment verifier.
9. Enable live providers only after CIE candidate limits, final-context limits, models, endpoints, and billing controls are reviewed.
10. Verify sanitized error responses, audit records, CSP, security headers, and access scopes online.
11. Run one controlled semantic query and verify its `context_intelligence` regions before production traffic.

## Post-upload verification

After publishing, run the deployment verifier from a machine that can reach the final domain:

```powershell
php bin\verify-deployment.php https://your-production-host.example
```

Require zero failures. Then verify one superadmin login, one normal-user login, and one controlled conceptual or relational query. The semantic response must expose `context_intelligence`, resolve final context to primary sources, preserve core and convergence roles, and incorporate every elected evidence record analytically.

If any verifier check fails, keep the release in acceptance until it is corrected. Re-run tests for any code, schema, configuration, provider, or hosting behavior that changed since the accepted build.

## Historical acceptance records

- [Go-live readiness validation](14_GO_LIVE_VALIDATION.md) records the initial NO-GO, the corrections, and the subsequent controlled approval.
- [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md) records the local HTTPS smoke test, infrastructure verification, backup/restore exercise, and mandatory online follow-up.

These dated records demonstrate what was tested in their stated environments. They do not certify a later deployment automatically.

## Vulnerability reporting

Follow the private process in the repository root [`SECURITY.md`](../../SECURITY.md). Never publish an active exploit or real secret in an issue.
