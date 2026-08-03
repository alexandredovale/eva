# Context Intelligence Engine (CIE)

## Purpose

The Context Intelligence Engine is the mathematical layer between vector retrieval and EVA's cognitive layers. The Retriever still locates and orders candidates by cosine similarity. CIE observes the Top-k distribution and determines the final semantic context without another model, an AI reranker, subjective weights, or learned relevance rules.

CIE applies only to conceptual and relational routes because only those routes produce vector-similarity distributions. Direct, structural, and broad routes continue to use identifiers, literal content, and document hierarchy.

## Flow

```text
conceptual or relational input
        → transient input embedding
        → Retriever
        → vector Top-k (20 by default)
        → Context Intelligence Engine
        → derived-to-primary lineage resolution
        → global final-context limit
        → cognitive layers
        → LLM
```

## Calculation and regions

For `N` candidates with similarities `sᵢ`:

```text
μ = (Σ sᵢ) / N
σ = √[(Σ(sᵢ − μ)²) / N]
CV = σ / μ
```

- **Discard:** `s < μ`.
- **Convergence range:** `μ ≤ s < μ + σ`; complementary analysis context.
- **Convergence core:** `s ≥ μ + σ`; primary answer context.

When the core is non-empty, it leads the selected semantic context and the convergence range follows as mandatory complementary context. When no core exists, the convergence range becomes the primary context. Candidates below the mean remain discarded. If `μ = 0`, CV is undefined and the auditable output uses `null`. A homogeneous distribution has `σ = 0`, so all candidates equal to the mean belong to the core.

Boundary comparisons use a scale-aware `1e-12` numerical tolerance so that binary floating-point representation cannot misclassify a value mathematically equal to a threshold. The formulas and reported values are unchanged.

## Determinism and neutrality

The same ordered candidates and similarities always produce the same result. CIE preserves Retriever order within each region. It does not judge truth, correctness, quality, or importance; create artificial scores or weights; make external calls; alter evidence; or persist its analysis. Similarities are not sent to the answer provider as documentary authority.

Selected derived candidates are resolved through `evidence_derivations` until primary sources are reached. Resolution distributes the bounded primary context across selected candidates and orders lineage sources by their query similarity, instead of exhausting the limit with the first broad lineage. Only literal primary evidence enters the answer context, annotated as `core` or `convergence`; scores are not sent as documentary authority. This final election is binding: every primary source must be accepted and incorporated where its analytical contribution is explained. Missing citations and citation-only inventories are rejected.

For multiple works, CIE analyzes each document distribution independently. `DocumentQueryService` then interleaves selected primary sources, deduplicates them, and applies the global `QUERY_MAX_EVIDENCE` cap.

## Configuration

```env
QUERY_CANDIDATE_LIMIT=20
QUERY_MAX_EVIDENCE=8
```

- `QUERY_CANDIDATE_LIMIT`: semantic Top-k analyzed per document; default `20`, effective range `1..200`.
- `QUERY_MAX_EVIDENCE`: primary-evidence cap delivered to the answer provider; default `8`, effective range `1..50`.

The first limit defines the statistical population. The second bounds the final context after CIE selection and lineage resolution.

## Auditable output and tests

Query responses include `context_intelligence`, with one transient analysis for each semantic retrieval. It reports candidate count, mean, population standard deviation, CV, convergence bounds, selected region, and the core/convergence/discard candidate groups with original similarities. The list is empty for exclusively direct, structural, or broad queries.

Run the database-independent mathematical test with:

```powershell
php tests\ContextIntelligenceEngineTest.php
php tests\ContextIntelligenceIntegrationTest.php
```

The second test creates a simple five-vector document inside a database transaction and verifies the primary context and API payload. `tests/QueryTest.php` also covers the general semantic-retrieval and lineage-resolution integration. All use simulated providers and make no paid calls.
