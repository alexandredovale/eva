# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
