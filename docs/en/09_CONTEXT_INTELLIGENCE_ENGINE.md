# Context Intelligence Engine (CIE)

## Purpose

The Context Intelligence Engine is the mathematical layer between vector retrieval and EVA's cognitive layers. Retriever scores the complete eligible primary population, globally orders it, and applies the query-local κq boundary. CIE then receives the statistically legitimized population while preserving its mean, standard-deviation, and CV rules.

CIE applies only to conceptual and relational routes because only those routes produce vector-similarity distributions. Direct, structural, and broad routes continue to use identifiers, literal content, and document hierarchy.

## Flow

```text
conceptual or relational input
        → transient input embedding
        → Retriever
        → complete eligible primary population
        → global cosine ordering and query-local κq
        → first CIE over sources
        → upper core or convergence fallback
        → primary cosine → κe → primary CIE by region and work
        → union of local nuclei → global CIE
        → global nucleus (or convergence fallback) + literal anchors
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

At any CIE stage, a non-empty core is the elected population; convergence is promoted only when that core is empty. At the first source stage, only the core is forwarded, and convergence proceeds only as fallback when that core is empty. Candidates below the mean remain discarded. If `μ = 0`, CV is undefined and the auditable output uses `null`. A homogeneous distribution has `σ = 0`, so all candidates equal to the mean belong to the core.

Boundary comparisons use a scale-aware `1e-12` numerical tolerance so that binary floating-point representation cannot misclassify a value mathematically equal to a threshold. The formulas and reported values are unchanged.

## Determinism and neutrality

The same ordered candidates and similarities always produce the same result. CIE preserves Retriever order within each region. It does not judge truth, correctness, quality, or importance; create artificial scores or weights; make external calls; alter evidence; or persist its analysis. The answer provider receives only the global CIE nucleus—or global convergence when that nucleus is empty—plus protected literal anchors. Each source retains its inherited `core` or `convergence` role; that role does not imply that global convergence was also sent. Similarities are never documentary authority.

The first population already consists of primary evidence. Its upper core, or its convergence fallback, passes through κe and primary CIE. Local nuclei form one deduplicated population for a final global CIE, while each retained source preserves its inherited documentary role.

For multiple works, each document stabilizes κq, the first source CIE, κe, and primary CIE independently. Only the local primary nuclei are pooled for global CIE consolidation.

## Query-local κq boundary

κq is calculated over every validated and compatibly embedded `primary:node_content` record in the work. Rank and similarity are normalized to `[0,1]`; the geometric candidate comes from maximum perpendicular distance to the endpoint chord, and an adjacent gap must confirm exceptional separation using only that query's gap mean and standard deviation. There are no configured semantic thresholds, weights, history, or training.

Degenerate states are explicit: empty or insufficient population, no dispersion, no structural break, and ambiguous break. Whenever κq is not identified, the complete population proceeds to CIE; no artificial cutoff is introduced.

## Configuration

Semantic evidence count has no configured limit. `QUERY_NON_SEMANTIC_MAX_EVIDENCE` is restricted to routes that do not execute CIE. The final basis remains the effectively cited subset of the global nucleus.

## Auditable output and tests

Query responses identify hierarchical, primary, and global analyses through `stage`; primary analyses also expose `source_region`. Every analysis reports mean, population standard deviation, CV, convergence bounds, selected region, and candidate groups, while κ diagnostics remain attached where applicable.

Run the database-independent mathematical test with:

```powershell
php tests\ContextIntelligenceEngineTest.php
php tests\QueryLocalKappaDetectorTest.php
php tests\ContextIntelligenceIntegrationTest.php
```

The second test creates a simple five-vector document inside a database transaction and verifies the primary context and API payload. `tests/QueryTest.php` also covers the general semantic-retrieval and lineage-resolution integration. All use simulated providers and make no paid calls.
