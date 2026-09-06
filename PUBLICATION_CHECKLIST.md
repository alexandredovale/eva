# Publication checklist

Use this checklist for every public release. Always start from a fresh clone of the canonical GitHub repository in an exclusive local analysis directory, inspect the remote state, and copy only an explicit source allowlist.

## Release v6.0.0

- [x] Remove summaries and derivation lineage from ingestion, cognitive build, queueing, retrieval, metrics, and the consolidated schema.
- [x] Add and validate the manual 4.x-to-6.0.0 database migration and apply it to the authorized local database after a complete backup.
- [x] Preserve κq, κe, and source/primary/global CIE selection, including the commented core-plus-convergence alternative.
- [x] Update public version metadata, bilingual documentation, diagrams, and offline regression tests.
- [x] Commit and push `main`, create annotated tag `v6.0.0`, and publish the GitHub release after explicit authorization.

## Historical release v4.0.2

- [x] Start from a fresh clone of canonical `main` without creating a release branch.
- [x] Copy only the two authorized source changes: source-first retrieval in `DocumentContextRetriever` and paragraph-separated analytical perspectives in `QueryAnswerProvider`.
- [x] Preserve the former summary-first and core-plus-convergence modes as adjacent comments for manual rollback tests.
- [x] Update Portuguese and English documentation for the primary-evidence κq population, upper-core first-stage forwarding, convergence fallback, and answer paragraph behavior.
- [x] Update public version metadata, citation metadata, changelog, and asset cache markers to 4.0.2.
- [x] Run PHP and JavaScript syntax checks and the affected regression suites with real AI calls disabled; 27 available public offline suites passed. `GoLiveReadinessTest` requires explicit paid live execution, `InfrastructureBackupRestoreTest` requires the unavailable `ZipArchive` extension, and `AiAdaptersTest` retains a pre-existing assertion inconsistent with the prompt examples already present in 4.0.1.
- [x] Review the complete diff and confirm that no credential, operational data, upload, log, dump, backup, private connector, or Runtime state is present.
- [x] Commit and push canonical `main`, create annotated tag `v4.0.2`, and publish the GitHub release.

## Release v4.0.1

- [x] Create a fresh clone from `alexandredovale/eva` under an exclusive local analysis directory and verify `origin/main` before copying files.
- [x] Copy only source, public documentation, empty schema, versioned migrations, public tests, and approved figure-contract examples.
- [x] Confirm that `.env`, operational databases, module SQLite files, Runtime state, uploaded documents, logs, dumps, backups, archives, private corpora, and manual portrait fixtures are absent.
- [x] Review the complete diff from `v4.0.0` and confirm that the 4.0.1 change is limited to white-label documentation URLs and release metadata.
- [x] Run PHP and JavaScript syntax validation plus the affected frontend regression test in the release clone with `AI_LIVE_ENABLED=false`.
- [x] Confirm that `database/` contains only `schema.sql`, versioned migrations, and tracked placeholders.
- [x] Confirm that `modules/.runtime/` contains only HTTP protections and empty tracked placeholders.
- [x] Confirm that `modules/com.eva.explorer/` contains the complete public reference cartridge and no Runtime data.
- [x] Confirm that the private ENADE package, its package-specific tests and documentation, placeholders, and Runtime data are absent from the public release.
- [x] Confirm Portuguese-English documentation parity for every current technical specification changed by 4.0.1.
- [x] Review attribution, `CHANGELOG.md`, `CITATION.cff`, health-endpoint version, README versions, and asset cache version.
- [x] Commit and push canonical `main`, verify `origin/HEAD`, create annotated tag `v4.0.1`, and publish the GitHub Release.

Release tags preserve published history and are not competing development branches. Create or remove a tag only as an explicit release-management decision.

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
