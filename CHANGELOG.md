# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [2.0.0] - 2026-08-05

### Added

- Generic module action, access-control, dashboard-refresh, and actor-bound scoped-query contracts without introducing module-specific knowledge into the Core.
- Complete Portuguese and English database-relationship documentation covering all 14 tables, 15 foreign keys, logical links, access paths, cascades, and real data lifecycles.
- A complete current API-flow diagram covering ingestion, cognitive build, retrieval, CIE, answer generation, transient CNodes, validation, audit, module events, and delivery.
- Public regression coverage for interactive module connectors and clean-clone backup/restore behavior.

### Changed

- The recovered primary set is now an available context from which the answer may retain only the analytically used and visibly cited subset; uncited candidates are discarded from the final evidence basis.
- CNode `simetry` and `assimetry` interactions are documented and validated as transient products of the same generation that formulates the answer, never as persistent graph entities.
- Answer validation uses bounded corrective attempts with safe failure guidance while preserving the elected documentary context and rejecting invented citations or citation-only inventories.
- Consolidated the neutral `module_events` mailbox into `database/schema.sql` so fresh installations require one schema import; migration `010` remains the idempotent upgrade path for existing databases.
- Consolidated the GitHub repository on one canonical `main` branch and aligned Portuguese, English, scientific, operational, and philosophical documentation with the implemented flow.

### Removed

- Legacy pre-CIE `philosophy/EVA_API_FLOW.png`; `EVA_API_FLOW_CIE.svg` is the single canonical flow diagram.

### Fixed

- Recovered evidence that does not contribute to the answer no longer forces artificial citation or invalidates an otherwise grounded response.
- The infrastructure backup/restore test now creates and removes a public synthetic fixture when a clean clone has no operational documents.

### Security

- Interactive module queries remain bound to the authenticated actor and are re-authorized through Core scope resolution.
- The release snapshot excludes credentials, runtime state, operational databases, uploaded documents, logs, private corpora, and module SQLite data.

## [1.2.0] - 2026-08-04

### Added

- EVA Module Contract v1 and a provider-neutral connector runtime under `modules/`.
- Independent module discovery, activation, deactivation, definitive removal, event fan-out, retention, backup, and per-module SQLite storage.
- Generic module-dashboard discovery in the white-label interface, including declarative filtering, accordion behavior, refresh, CSP-nonced module CSS, and manifest-defined ordering.
- Neutral `module_events` mailbox as the only new Core table; no existing table was altered for modules.
- Reference connector `com.eva.education`, with descriptive pedagogical observations, immediate transactional processing, localized labels, linguistic concept extraction from question and answer, and an independent learning-trajectory dashboard.
- Animated yellow waiting indicator for documentary queries, with reduced-motion support.

### Changed

- Locally rejected answers now receive deterministic corrective feedback on the next bounded attempt: a safe failure code and, only when applicable, an already elected evidence ID.
- Module interfaces, names, styles, persistence, and domain rules remain inside their packages; the Core exposes only generic contracts and hosts.
- The Education connector now uses three non-valuative dimensions: conceptual articulation, evidence use, and contextual connection.
- Authentication-dialog focus now moves before the access panel is hidden, using `inert` to preserve accessibility.

### Fixed

- Exact textual matches now anchor conceptual and relational answers without suppressing semantic Top-k/CIE enrichment from the selected works.
- Removed legacy separate credential-file loading and aligned all public guidance with `.env` as the only local configuration source.
- Removed three unused credential variables and their obsolete documentation references from the local configuration inventory.
- Corrective retries no longer repeat an identical rejected generation without validation guidance.
- Education trajectory labels, dates, evidence layout, concepts, direct-reference text, and accordion filtering now remain readable and localized to the question language.
- Removed the redundant Education observation “Question Refinement” from new and existing module histories through schema migration 2.

### Security

- Module packages and runtime data are denied direct HTTP access by `.htaccess`; SQLite, state, manifests, and internal PHP remain private.
- Definitive module removal requires explicit typed confirmation and deletes both the package and its isolated runtime history.
- Module events reject sensitive keys recursively, and modules receive AI capabilities without provider credentials.

## [1.1.1] - 2026-08-03

### Added

- Complete English coverage for the Portuguese technical specifications, including Cnode, database, mandatory rules, roadmap, and dated validation records.
- Portuguese and English configuration catalogs at the end of `.env.example`.
- Silent server-side regeneration for locally rejected answers, limited to three total attempts with the same elected context.

### Changed

- Reduced the default semantic candidate population from Top-30 to Top-20 through `.env.example`, configuration fallback, Retriever default, tests, and current documentation.

### Fixed

- Internal evidence-validation messages no longer reach users on an isolated generation failure; only three consecutive failures return a generic error.

## [1.1.0] - 2026-08-02

### Added

- Context Intelligence Engine between semantic retrieval and cognitive processing.
- Deterministic mean, population-standard-deviation, coefficient-of-variation, convergence, core, and fallback analysis for vector Top-k candidates.
- Configurable `QUERY_CANDIDATE_LIMIT` and transient `context_intelligence` query output.
- Database-independent CIE regression tests and updated Portuguese/English scientific documentation.
- Auditable `core` and `convergence` roles on the final primary-evidence context.
- Closed analytical-coverage validation for every deterministically elected evidence.
- A real-call reference record covering Top-30 statistics, lineage resolution, complete evidence use, interactions, latency, and truncation status.

### Changed

- Core evidence now leads the answer while convergence evidence provides mandatory complementary analysis; convergence becomes primary only when no core exists.
- The answer provider must accept the complete deterministic election in order and may no longer re-elect or reduce the evidence set.
- Every elected evidence must be cited where its analytical contribution is explained.

### Removed

- Automatic `Evidências: [...]` citation appendices for evidence omitted from the generated analysis.

### Fixed

- Prevented formally accepted evidence IDs from satisfying the query contract without substantive analytical incorporation.

## [1.0.2] - 2026-07-31

### Added

- Public contribution and security policies.
- Sanitized environment template.
- English project documentation and GitHub community files.
- Superadmin browser control for explicitly confirmed queue draining without shell access.
- Superadmin-managed project response profiles with explicit scope activation and shared-document deduplication.

## [1.0.0] - 2026-07-22

### Added

- Structured Markdown, JSON, and XML ingestion.
- Literal primary evidence and traceable hierarchical derived evidence.
- Versioned summaries and contextual embeddings.
- Adaptive direct, structural, broad, conceptual, and relational retrieval.
- Transient `simetry` and `assimetry` interactions validated against literal evidence.
- Evidence-gated answer generation and bounded conversational continuity.
- White-label web interface, authenticated API, queue, audit, and metrics.
- Operational, architectural, scientific, and energy-sustainability documentation.
