# Security Policy

## Supported versions

Security fixes are applied to the latest release on the `main` branch.

## Reporting a vulnerability

Do not disclose suspected vulnerabilities in public issues, discussions, or pull requests.

Use GitHub's private vulnerability reporting feature from the repository **Security** tab. Include:

- affected component and version;
- reproducible steps or a minimal proof of concept;
- expected and observed impact;
- suggested mitigation, when available.

Maintainers will acknowledge a complete report as soon as practical, investigate it privately, and coordinate disclosure after a fix is available. Never include production credentials, private documents, personal data, or active access tokens in a report.

## Operational responsibilities

- Keep `.env`, `api_key.md`, database dumps, uploaded documents, and logs outside version control.
- Keep `AI_LIVE_ENABLED=false` unless real provider calls are explicitly intended.
- Rotate any credential that may have been exposed, even if it was later removed from Git history.
- Serve only the `public/` directory and follow the deployment guidance in `docs/en/07_SECURITY_AND_DEPLOYMENT.md`.
