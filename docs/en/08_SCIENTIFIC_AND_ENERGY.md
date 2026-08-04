# Scientific scope and energy sustainability

## Scope

EVA may help reduce pressure from AI energy demand by avoiding unnecessary documentary computation and by supporting evidence-based technical decisions. It does not generate energy, control an electrical grid, or replace operational systems such as SCADA, EMS, and protection equipment.

The proposed effect is efficiency: small reductions per query may accumulate into lower processing, cooling, and capacity demand when the system serves a large workload.

## Scientific position

EVA proposes an evidence-centered alternative to architectures that begin with arbitrary chunks or persist precomputed relations. Its hypotheses include:

- structural units can improve documentary provenance;
- separating primary and derived evidence can improve auditability;
- resolving derived retrieval back to primary sources can constrain generated answers;
- distribution-based context stabilization can reduce semantic-retrieval noise without an AI reranker;
- transient evidence interactions can avoid persistent graph expansion;
- local routing and evidence gating can reduce unnecessary external computation.

These are architectural hypotheses. Functional tests demonstrate implemented behavior, not statistical superiority over other retrieval systems.

## Current empirical baseline

The project records a small operational baseline covering literal, structural, conceptual, relational, and negative-control queries. Offline tests also verify deterministic CIE classification, leading-core plus complementary-convergence composition, zero-mean behavior, homogeneous distributions, auditable serialization, and rejection of citation-only evidence coverage. A historical directed real-provider case incorporated ten of ten primary sources under the former full-incorporation contract. On August 4, 2026, a real-provider query completed with four cited sources while six recovered but uncited candidates were discarded, without truncation or whole-answer failure. These checks establish implemented behavior, not retrieval-quality superiority. The operational sample is intentionally described as a baseline, not a conclusive comparative study.

Future comparisons should use the same corpus, questions, providers, hardware, and quality requirements across EVA, fixed-block vector RAG, long-context retrieval, GraphRAG, and agentic RAG. They should report precision/recall, citation validity, correct refusal, latency percentiles, tokens, cost, memory, and stability.

## Energy mechanisms

EVA can potentially reduce avoidable computation by:

- skipping answer generation when no primary evidence is recovered;
- skipping transient query embeddings for direct, structural, and broad routes;
- reusing summaries and embeddings by model and content hash;
- producing the answer and transient interactions in one bounded call;
- limiting evidence context, chat history, output, and retries;
- discarding recovered but uncited evidence instead of requiring another generation;
- filtering a vector Top-k locally through CIE before sending primary context to the answer provider;
- avoiding precomputed all-pairs relationships and persistent interaction graphs.

At scale, fewer external calls, tokens, retries, and GPU-hours may reduce server and cooling demand. Provider neutrality also permits migration to more efficient models and infrastructure without replacing the documentary core.

## Potential aggregate effect

A simplified accounting boundary is:

```text
total energy = reusable cognitive build
             + local query processing
             + required transient embeddings
             + justified generative answers
             + bounded additional attempts
```

EVA acts on these terms by reusing the build, avoiding embeddings on non-semantic routes, blocking generation without evidence, and bounding context, output, and retries. At high query volume, that discipline may mean fewer generative calls and GPU-hours, lower server and cooling demand, less simultaneous compute pressure at peaks, and better use of installed capacity.

The net effect depends on workload composition. A stable corpus queried repeatedly amortizes its build more effectively than a source processed once and rarely queried.

## Use in the energy sector

In addition to containing its own compute workload, EVA can organize verifiable documentary memory for utilities, generators, system operators, industrial organizations, and regulators. Applicable sources include:

- contingency and recovery procedures;
- technical asset manuals and histories;
- failure and maintenance reports;
- standards, contracts, and capacity studies;
- shutdown, restart, and incident-response plans.

During a crisis, traceable retrieval may reduce the time needed to locate procedures, compare documents, and identify missing documentary support. This role remains advisory. Any connection to live operational data requires authorized connectors, its own validation, human governance, and strict separation from grid-control systems such as SCADA, EMS, and protective systems.

## Scientific limit

Net energy savings have not yet been experimentally demonstrated. Initial summaries, embeddings, storage, and inference also consume energy. The result depends on workload composition, corpus reuse, model, hardware, output length, data-center efficiency, and electricity supply.

The official claim is therefore limited: **EVA implements verifiable computational-containment mechanisms with the potential to reduce energy demand at scale; the magnitude and net benefit remain to be measured.**

The experiment must compare EVA with fixed-block vector RAG, long-context retrieval, GraphRAG, and agentic RAG while holding the corpus, questions, model or capability class, hardware, quality threshold, and execution conditions constant. Workloads must separately represent direct, structural, broad, conceptual, relational, and negative-control queries. Build energy must be amortized across multiple usage volumes instead of comparing inference alone.

At minimum, report:

- joules per query and kWh per thousand queries;
- amortized build energy;
- external calls and embeddings per query;
- Top-k size, discard ratio, core/convergence composition, and context tokens before and after CIE;
- input and output tokens;
- GPU time and p50, p95, and p99 latency;
- summary and embedding reuse rates;
- the fraction of queries stopped without generation;
- precision, recall, citation validity, and correct-refusal rate;
- energy adjusted by infrastructure PUE when available.

An energy advantage is supported only if EVA consumes less energy at equivalent or better documentary quality. Results must report dispersion, experimental configuration, and the proportion of each query class.

## References

- [IEA — Energy demand from AI](https://www.iea.org/reports/energy-and-ai/energy-demand-from-ai)
- [IEA — Key Questions on Energy and AI](https://www.iea.org/reports/key-questions-on-energy-and-ai/executive-summary)
- [Poddar et al., Towards Sustainable NLP, NAACL 2025](https://aclanthology.org/2025.naacl-long.632/)
- [Chung et al., The ML.ENERGY Benchmark, NeurIPS 2025](https://papers.nips.cc/paper_files/paper/2025/hash/9dc510e3d7b0b3b2a58ffed7a3ad6b0f-Abstract-Datasets_and_Benchmarks_Track.html)
- [Portuguese scientific paper](../../philosophy/01_EVA_SCIENTIFIC_PAPER.md)
- [Operational benchmark baseline](../../philosophy/02_EVA_BENCHMARK_BASELINE.md)
