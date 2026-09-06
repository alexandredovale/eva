# Context Intelligence Engine — query-local boundaries and global consolidation

**Status:** implemented; comparative gain has not yet been demonstrated
**Updated:** August 8, 2026
**Português:** [Context Intelligence Engine — fronteiras query-local e consolidação global](../04_CONTEXT_INTELLIGENCE_ENGINE.md)

## Proposition

The Context Intelligence Engine (CIE) turns semantic-context selection into deterministic observation of the distributions produced by Retriever. It does not replace cosine similarity, assign absolute relevance, or decide which document is true, better, or more important.

The current flow is:

```text
query q
  → complete primary population per work
  → κq
  → first source CIE
  → upper core or convergence fallback
  → primary cosine
  → κe + primary CIE per region and work
  → deduplicated union of local primary nuclei Gq
  → global CIE
  → global nucleus Eq (or convergence if the nucleus is empty)
  → LLM
```

Exact literal matches outside the vector primary population remain protected anchors and accompany `Eq`.

## Why semantic Top-k no longer exists

A Top-k known before the query would artificially define the population used to calculate mean, standard deviation, and CV. EVA calculates cosine for every validated and embedded `primary:node_content` record in each work and lets κq emerge from the ordered curve.

If κq finds no identifiable break—because the population is small, dispersion is absent, the curve is continuous, or the break is ambiguous—the complete population proceeds to CIE. No substitute cutoff is invented.

## Preserved CIE mathematics

For `N` similarities `sᵢ`:

```text
μ = (Σ sᵢ) / N
σ = √[(Σ(sᵢ − μ)²) / N]
CV = σ / μ
```

When `μ = 0`, the auditable value of `CV` is `null`. Regions are always:

```text
discard:     s < μ
convergence: μ ≤ s < μ + σ
core:        s ≥ μ + σ
```

If `core` is empty, `convergence` is promoted. If `σ = 0`, every value equal to `μ` belongs to core. The implementation uses a scale-aware `1e-12` numerical tolerance only to prevent floating-point boundary errors; the formulas remain unchanged.

## Three applications, three responsibilities

### 1. Initial source CIE

κq legitimizes the primary-evidence population per work. CIE classifies discard, convergence, and core. Only the `s ≥ μ + σ` core proceeds at this stage; convergence is forwarded only as fallback when that core is empty.

### 2. κe and primary CIE

Because the initial population is already primary, no summary resolution occurs in this pass. Each source inherits the forwarded initial region: `core` prevails and `convergence` appears only as fallback. The same transient query embedding is reused to calculate cosine against primary embeddings, with no additional external call.

κe is calculated separately for primary sources inherited from `core` and from `convergence` in each work. Each legitimized population receives its own CIE. The local nucleus is the primary `core`, or its `convergence` when core is empty.

### 3. Global CIE

Local primary nuclei are deduplicated and united:

```text
Gq = ⋃d (Ld,core ∪ Ld,convergence)
```

Because every primary cosine uses the same query and embedding model, CIE can classify `Gq` globally. The population delivered to the LLM is:

```text
Eq = CoreG, when CoreG ≠ ∅
Eq = ConvG, when CoreG = ∅
K(q) = |Eq|
```

`K(q)` is neither configured nor known before the query. Each final source retains its inherited `core` or `convergence` role even when its global CIE region is `core`.

## Cantelli as a bound, not a quota

For non-zero variance, the one-sided Cantelli inequality states:

```text
P(X − μ ≥ σ) ≤ 1/2
```

Therefore, the `s ≥ μ + σ` nucleus contains at most half of the analyzed population. This does not instruct the system to select half and does not define a percentage; it is only a mathematical upper bound implied by the preserved CIE boundary. For homogeneous distributions (`σ = 0`), the non-zero-variance premise does not apply and every candidate remains in core.

## Neutrality and determinability

Given the same ordered populations and similarities, output is invariant. The flow does not:

- create weights, grades, or configured semantic thresholds;
- use history, feedback, or AI reranking;
- add embedding calls or split response generation into multiple batches;
- persist the query vector, scores, statistics, regions, or context;
- alter evidence, derivations, or embeddings;
- use similarity as epistemic confidence.

`QUERY_NON_SEMANTIC_MAX_EVIDENCE` applies only to direct, structural, and broad routes. It never participates in κq, κe, or any CIE stage.

## LLM contract

The provider receives `Eq`, protected literal anchors, and inherited initial roles. It has no authority to retrieve external sources. Presence in available context authorizes use but does not require a decorative citation: only sources analytically incorporated into prose and visibly cited remain in `used_evidence_ids`.

## Auditability

`context_intelligence` identifies each analysis by `stage`:

- `hierarchical`: compatibility name retained for the first source CIE after κq;
- `primary`: CIE after κe, with `source_region` equal to `core` or `convergence`;
- `global`: final CIE over `Gq`.

Each item transiently reports population, `μ`, `σ`, `CV`, bounds, elected region, core, convergence, discard, and κ diagnostics where applicable.

## Scientific limit

Determinism does not prove relevance. The flow still depends on corpus quality, document structure, primary-evidence granularity, embeddings, and κ's ability to identify useful breaks. CIE uses relative boundaries and has no absolute “no sufficiently related candidate” threshold. The global nucleus does not guarantee balanced coverage of every discipline.

The falsifiable hypothesis is that, under the same corpus, models, and protocol, the current flow reduces noise, tokens, and paraphrase instability without unacceptable recall loss. This requires representative comparison with fixed Top-k, reranking, and long-context baselines; current functional tests do not demonstrate statistical superiority.

## Operational references

- [Deterministic evidence contract](05_DETERMINISTIC_EVIDENCE_CONTRACT.md)
- [Current API flow](03_EVA_API_FLOW.md)
- [Operational CIE documentation](../../docs/en/09_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Documentary query and answer composition](../../docs/en/05_QUERY_AND_CHAT.md)
