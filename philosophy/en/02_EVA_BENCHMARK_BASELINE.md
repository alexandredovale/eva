# EVA — Documentary Query Benchmark Baseline

**Version:** 1.2
**Execution date:** July 20, 2026
**Architectural review:** August 2, 2026
**Document:** `EVA-D000060` — *The Mediums' Book*
**Execution type:** single sequential run, no concurrency
**Português:** [Benchmark original](../02_EVA_BENCHMARK_BASELINE.md)

## 1. Objective

Record an operational and scientific baseline for future comparisons between EVA versions and alternative documentary-retrieval architectures.

This execution does not demonstrate statistical superiority. It measures five predefined cases and reports valid results, refusals, and contract failures without retrospective selection. Provider and model identities were omitted under the white-label principle. Token counts came directly from configured APIs.

## 2. Collection state

| Parameter | Value |
|---|---:|
| Document nodes | 472 |
| Primary evidence | 371 |
| Derived evidence | 472 |
| Persistent embeddings | 843 |
| Evidence limit in this historical execution | 8 |
| Interaction limit per query | 4 |

## 3. Methodology

Queries ran sequentially in the local environment. Duration used a high-resolution monotonic clock. For each external call, only type, duration, request size, and numerical usage counters were recorded; credentials, endpoints, models, and payloads were not.

The five questions were defined before execution to exercise literal retrieval, structural navigation, conceptual retrieval, relational query, and a negative no-evidence control.

## 4. Results by query

| # | Category | Total latency | External calls | Total tokens | Used evidence | Result |
|---:|---|---:|---:|---:|---:|---|
| 1 | literal | 2.052 s | 1 answer | 2,709 | 1 | valid |
| 2 | structural | 2.547 s | 1 answer | 10,667 | 0 | blocked |
| 3 | conceptual | 4.617 s | 1 embedding + 1 answer | 10,596 | 3 | valid |
| 4 | relational | 6.271 s | 1 embedding + 1 answer | 8,438 | — | blocked |
| 5 | negative control | 10.4 ms | 0 | 0 | 0 | correct refusal |

### 4.1 Literal retrieval

**Question**

> How should the statement “each person carries within the seed of the qualities needed to become a medium” be understood?

**Validated answer**

> The statement means that every person potentially possesses the qualities required to develop mediumship, but these qualities exist in varying degrees and their development depends on causes that cannot be controlled at will.
>
> Evidence: [EVA-E000894]

| Metric | Value |
|---|---:|
| Prompt tokens | 2,625 |
| Answer tokens | 84 |
| External latency | 2.045 s |
| Local latency | 6.7 ms |
| Characters in used evidence | 8,188 |
| Transient embedding | not required |

### 4.2 Structural retrieval

**Question**

> Within Chapter XXVIII—Charlatanism and Fraud—which criteria distinguish fraud from authentic phenomena?

The provider returned text but declared no used evidence. EVA rejected it with: `The answer did not identify any primary evidence used.`

Local diagnosis showed that the correct chapter was recovered, but structural selection also included a Chapter XIX path because of lexical overlap. An irrelevant 12,341-character evidence record entered context.

| Metric | Value |
|---|---:|
| Prompt tokens | 10,561 |
| Rejected-answer tokens | 106 |
| External latency | 2.538 s |
| Local latency | 9.2 ms |
| Request size | 39,805 bytes |
| Result | blocked for absent structured citation |

### 4.3 Conceptual retrieval

**Question**

> Why does a medium's moral quality influence communications without mechanically determining the mediumistic faculty?

**Validated answer**

> Moral quality influences communications because it attracts or repels Spirits by affinity, but it does not mechanically determine the faculty, which is organic and morally independent. An imperfect medium may exceptionally transmit good communications when good Spirits use that person for lack of another, but only temporarily. The faculty is a gift that may be used well or badly; moral influence concerns its use and the nature of communicating Spirits, not the faculty's existence.
>
> Evidence: [EVA-E001126] [EVA-E001130] [EVA-E001127]

| Metric | Value |
|---|---:|
| Embedding tokens | 28 |
| Embedding latency | 1.135 s |
| Answer-prompt tokens | 10,382 |
| Answer tokens | 186 |
| Answer latency | 3.111 s |
| Local latency | 371 ms |
| Used evidence | 3 |
| Characters in used evidence | 14,786 |

### 4.4 Relational query

**Question**

> What is the relationship between the medium's moral influence and the environment's influence on communications, and in what sense is it simetry or assimetry according to the evidence?

The provider produced a relational answer but supplied excerpts that were not literal copies of the cited evidence. EVA rejected the output with: `The interaction does not contain literal excerpts from the indicated evidence.`

| Metric | Value |
|---|---:|
| Embedding tokens | 41 |
| Embedding latency | 712 ms |
| Answer-prompt tokens | 7,818 |
| Structured-answer tokens | 579 |
| Answer latency | 5.187 s |
| Local latency | 372 ms |
| Total request size | 29,251 bytes |
| Result | blocked for unverifiable interaction |

### 4.5 Negative control

**Question**

> Is there evidence for the claim “mediumship can be measured by electroencephalogram”?

**Deterministic answer**

> There is insufficient documentary evidence to answer this input.

| Metric | Value |
|---|---:|
| Total latency | 10.4 ms |
| External calls | 0 |
| Tokens | 0 |
| Evidence | 0 |
| Result | correct refusal |

## 5. Aggregate metrics

| Metric | Value |
|---|---:|
| Total time | 15.498 s |
| Mean latency | 3.100 s |
| Median latency | 2.547 s |
| Accumulated external time | 14.728 s |
| External share of latency | 95.04% |
| Accumulated local time | 769 ms |
| Input tokens | 31,455 |
| Output tokens | 955 |
| Total tokens | 32,410 |
| External calls | 6 |
| Total transmitted volume | 118,649 bytes |
| Largest observed memory increase | approximately 54 MiB |
| Functionally valid results | 3 of 5 |
| Validation-blocked outputs | 2 of 5 |

The two rejected outputs consumed 19,105 tokens, or 58.95% of the execution total.

## 6. Observed benefits

- Literal query avoided transient embedding.
- Negative control ended locally in 10.4 ms with no external call or cost.
- Conceptual retrieval scanned 843 embeddings in about 371 ms of local processing.
- Validation prevented an answer with no declared evidence and a non-literal interaction from reaching the user.
- Local latency was 4.96% of total time; external calls dominated.

## 7. Revealed limitations

- Complete semantic units can create large prompts.
- Structural selection may mix paths through lexical coincidence.
- The relational contract depends on correctly reproduced literal excerpts.
- An invalid interaction also blocked a potentially useful documentary answer in that historical version.
- One five-case execution cannot support statistical inference or superiority claims.

## 8. Next comparative protocol

The next experiment should run at least 30 repetitions per category and compare EVA with fixed-block RAG using the same document, questions, providers, and context budget. It should measure valid-answer rate; correct and incorrect refusal; evidence precision/recall; citation and simetry/assimetry validity; input/output tokens; p50, p95, and p99 latency; monetary cost; peak memory; and run-to-run stability.

## 9. Partial relational-coverage verification

After the baseline, partial coverage was tested for composite relational inputs. `simetry` and `assimetry` remained cognitive operators; the change distinguished aspects with and without evidence.

**Verification question**

> What is the relationship among the medium's moral influence, the environment's influence on communications, and measurement of mediumship by electroencephalogram?

- X: moral influence—evidence found;
- Y: environmental influence—evidence found;
- Z: electroencephalogram measurement—no sufficient evidence.

**Validated answer**

> The medium's moral influence and the environment's influence affect the nature and quality of spirit communications. Measurement of mediumship by electroencephalogram is not mentioned in the evidence.
>
> Evidence: [EVA-E001126] [EVA-E001127] [EVA-E001128] [EVA-E001129] [EVA-E001130] [EVA-E001133]

**Specific limitation**

> Insufficient evidence was found in recovered context for: measurement of mediumship by electroencephalogram.

The result preserved one validated `simetry` interaction between X and Y. One additional candidate without valid literal excerpts was discarded without invalidating the answer or valid interaction.

| Metric | Value |
|---|---:|
| Documentary result | valid |
| Cited evidence | 6 |
| Validated `simetry` | 1 |
| Validated `assimetry` | 0 |
| Unsupported aspects identified | 1 |
| Total latency | 5.803 s |
| External latency | 5.399 s |
| Local latency | 404 ms |
| Embedding tokens | 39 |
| Answer-prompt tokens | 9,830 |
| Structured-answer tokens | 502 |
| Total tokens | 10,371 |
| Total request size | 37,172 bytes |

This single execution functionally confirms the expected pattern—requested X/Y/Z relationship, supported X/Y answer, explicit Z limitation, preserved interaction understanding—but not a statistical success rate.

## 10. Historical lexical and structural retrieval verification

This remains a historical structural-retrieval record and anticipates the current visible-citation contract. CIE does not change direct, lexical, structural, or broad location. The application forms authorized context; the provider may use only contributing sources; and the final basis retains only analytically cited evidence.

The previously blocked Chapter XXVIII question was repeated after historical AI candidate triage. Retriever supplied eight candidates, including an intrusive Chapter XIX path. AI cited four relevant Chapter XXVIII sources and none of the four intruders.

**Validated answer**

> Chapter XXVIII offers criteria for distinguishing fraud from authentic phenomena: material disinterest is the strongest guarantee because “there is no disinterested charlatanism” [EVA-E001204]; fraud always seeks material interest, and “where nothing can be gained, there is no interest in deceiving” [EVA-E001214]; physical phenomena are easier to imitate, but conjuring cannot imitate “those beautiful and sublime dictations” of intelligent communications [EVA-E001215]; and mediumship should not serve personal ambition because “good Spirits withdraw from anyone who would make it a stepping stone” [EVA-E001206].

| Metric | Baseline | After historical AI triage |
|---|---:|---:|
| Supplied candidates | 8 | 8 |
| Cited evidence | 0 | 4 |
| Cited intrusive candidates | 0 | 0 |
| Documentary result | blocked | valid |
| Embedding calls | 0 | 0 |
| Answer calls | 1 | 1 |
| Total latency | 2.547 s | 3.876 s |
| Local latency | 9.2 ms | 7.2 ms |
| Prompt tokens | 10,561 | 10,994 |
| Answer tokens | 106 | 242 |
| Total tokens | 10,667 | 11,236 |
| Request size | 39,805 bytes | 41,734 bytes |

In the architecture then evaluated, retrieval produced candidates and AI selected supporting text. In the current architecture, the application determines available context and the LLM selects only the cited subset; uncited candidates are discarded, external sources forbidden, and artificial citation lists rejected.

## 11. Multidisciplinary protocol to execute

The baseline used one work and does not measure EVA's multidisciplinary capacity. A future study should build projects with documents from at least three disciplines and include: explicitly supported cross-field relationships; relationships supported through different vocabularies; partial coverage with one unsupported field; and false intersections where semantic proximity does not support the requested relationship.

Evaluate source/discipline provenance, multi-document precision/recall, interaction fragments and participants, claims beyond cited sources, non-evasive declarations of unsupported fields, document diversity in the final global nucleus, stability under input variation, and documentary-memory invariance before and after queries.

Invariance must compare counts and hashes of documents, nodes, evidence, derivations, and embeddings. Distinguish process reliability, semantic correctness evaluated by annotators, and multidisciplinary completeness. Emergent synthesis is valid only when components are traceable and formulation stays within citations; it remains transient, not new evidence.

## 12. Context Intelligence Engine baseline to execute

The July 20 executions predate CIE and do not demonstrate its effects. Repeat conceptual and relational queries under the same corpus, models, prompts, and operational conditions, comparing:

1. direct vector Top-k, explicitly identified as a legacy baseline;
2. current complete-population flow with κq, hierarchical CIE, κe, primary CIE, and global CIE;
3. a reference reranker under a comparable budget.

Record total hierarchical population, κq, every κe, `μ`, `σ`, `CV`, stage-level discard/convergence/core counts, sources after resolution, `|Gq|`, final `K(q)`, precision/recall, context tokens, local latency, and paraphrase stability. Legacy Top-k is an experimental variable, never current-flow configuration.

Offline mathematical tests prove deterministic formula and boundary execution; they do not replace empirical retrieval-quality evaluation.
