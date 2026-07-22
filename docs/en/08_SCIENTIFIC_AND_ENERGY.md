# Scientific scope and energy sustainability

## Scientific position

EVA proposes an evidence-centered alternative to architectures that begin with arbitrary chunks or persist precomputed relations. Its hypotheses include:

- structural units can improve documentary provenance;
- separating primary and derived evidence can improve auditability;
- resolving derived retrieval back to primary sources can constrain generated answers;
- transient evidence interactions can avoid persistent graph expansion;
- local routing and evidence gating can reduce unnecessary external computation.

These are architectural hypotheses. Functional tests demonstrate implemented behavior, not statistical superiority over other retrieval systems.

## Current empirical baseline

The project records a small operational baseline covering literal, structural, conceptual, relational, and negative-control queries. It measures external calls, tokens, latency, evidence use, and validation failures. The sample is intentionally described as a baseline, not a conclusive comparative study.

Future comparisons should use the same corpus, questions, providers, hardware, and quality requirements across EVA, fixed-block vector RAG, long-context retrieval, GraphRAG, and agentic RAG. They should report precision/recall, citation validity, correct refusal, latency percentiles, tokens, cost, memory, and stability.

## Energy mechanisms

EVA can potentially reduce avoidable computation by:

- skipping answer generation when no primary evidence is recovered;
- skipping transient query embeddings for direct, structural, and broad routes;
- reusing summaries and embeddings by model and content hash;
- producing the answer and transient interactions in one bounded call;
- limiting evidence context, chat history, output, and retries;
- avoiding precomputed all-pairs relationships and persistent interaction graphs.

At scale, fewer external calls, tokens, retries, and GPU-hours may reduce server and cooling demand. Provider neutrality also permits migration to more efficient models and infrastructure without replacing the documentary core.

## Scientific limit

Net energy savings have not yet been experimentally demonstrated. Initial summaries, embeddings, storage, and inference also consume energy. The result depends on workload composition, corpus reuse, model, hardware, output length, data-center efficiency, and electricity supply.

The official claim is therefore limited: **EVA implements verifiable computational-containment mechanisms with the potential to reduce energy demand at scale; the magnitude and net benefit remain to be measured.**

The validation protocol should report joules per query, kWh per thousand queries, amortized build energy, calls and embeddings per query, input/output tokens, GPU time, cache reuse, no-generation rate, latency, answer quality, and infrastructure PUE when available.

## References

- [IEA — Energy demand from AI](https://www.iea.org/reports/energy-and-ai/energy-demand-from-ai)
- [IEA — Key Questions on Energy and AI](https://www.iea.org/reports/key-questions-on-energy-and-ai/executive-summary)
- [Poddar et al., Towards Sustainable NLP, NAACL 2025](https://aclanthology.org/2025.naacl-long.632/)
- [Chung et al., The ML.ENERGY Benchmark, NeurIPS 2025](https://papers.nips.cc/paper_files/paper/2025/hash/9dc510e3d7b0b3b2a58ffed7a3ad6b0f-Abstract-Datasets_and_Benchmarks_Track.html)
- [Portuguese scientific paper](../../philosophy/01_EVA_SCIENTIFIC_PAPER.md)
- [Operational benchmark baseline](../../philosophy/02_EVA_BENCHMARK_BASELINE.md)

