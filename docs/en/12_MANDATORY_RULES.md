# Mandatory rules

These invariants define the implemented Evidence Algorithm. Product profiles, providers, interfaces, and future extensions must remain subordinate to them.

1. Accept only Markdown, JSON, and XML as documentary sources.
2. Preserve source content, order, hierarchy, and origin reference.
3. Never confuse original text with AI-generated content.
4. Persist evidence with explicit `evidence_class` and `evidence_type` values.
5. Build higher-level summaries only from identified lower-level evidence.
6. Generate embeddings only from organized units, never from arbitrary size-based cuts.
7. Treat EVA — Evidence Algorithm — as the principal system name and architecture, keeping evidence, derivations, and embeddings as its persistent core.
8. Treat Cnode only as an internal transient conceptual derivation of EVA, never as a system, superior hierarchical layer, documentary node, or persistent entity; do not persist candidate pairs, interaction analyses, or relational metrics.
9. Evaluate interactions when at least two evidence records are recovered, and emit only relationships between evidence records actually cited, within the requested limit.
10. Use only `simetry` and `assimetry` to describe interactions.
11. Do not classify relationships through judgmental taxonomies.
12. Do not turn vector similarity into a conclusion.
13. Use `simetry` only for an explicit reciprocal interaction.
14. Use `assimetry` only with an explicit origin and destination.
15. Do not interpret asymmetry as hierarchy, superiority, causation, or importance.
16. Require two recovered and cited evidence records for every interaction.
17. Require one literal excerpt from each participant.
18. Do not assign confidence, intensity, priority, relevance, importance, or weight.
19. Do not answer a claim as documentary fact without sufficient primary evidence.
20. State when the document does not support a conclusion.
21. Expose the evidence used and separate `simetry`, `assimetry`, and limitations.
22. Preserve history when summaries, models, or vectors change.
23. Keep providers replaceable and configurable by capability.
24. Never store in logs or expose keys, passwords, or secrets.
25. Keep uploaded files outside the public directory.
26. Avoid duplicating concepts, tables, and responsibilities.
27. Require both enabled configuration and an explicit CLI option for real provider calls.
28. Create a query embedding only for conceptual or relational inputs.
29. Do not persist query embeddings or calculated similarities.
30. Do not call the answer provider when no primary evidence is recovered.
31. Keep `simetry` and `assimetry` as essential operators of relational cognitive understanding.
32. Do not require the words `simetry` and `assimetry` to occur literally in source documents.
33. Discard unverifiable interactions without deleting an otherwise valid documentary answer.
34. Report a relational limitation when no `simetry` or `assimetry` interaction can be validated.
35. Allow a user to combine supported and unsupported aspects freely in one input.
36. Answer each supported aspect with cited evidence.
37. Name each aspect for which the recovered context contains insufficient evidence.
38. Never erase a valid partial relationship only because another input aspect lacks evidence.
39. Treat retrieved results as candidates until the application completes deterministic composition of the available context.
40. Deliver only the authorized final context to the provider, using primary sources and explicit `core` or `convergence` roles on semantic routes.
41. Require every evidence record retained in the result to be cited in the analytical passage where it contributes.
42. Discard recovered evidence omitted from the text, and reject isolated markers or citation inventories that do not demonstrate analytical incorporation.
43. Never add a citation omitted by the provider merely to make the response appear compliant.
44. Treat `QUERY_MAX_EVIDENCE` as the global primary-evidence limit delivered to the provider for each query, applied after CIE on semantic routes.
45. Do not confuse recovered context with used evidence. The application authorizes the available set and retains only effectively cited sources in the result.
46. Treat `QUERY_MAX_INTERACTIONS` as a transient relational-output limit, never as an evidence count, persisted-pair count, or instruction to precompute combinations.
47. A zero `QUERY_MAX_INTERACTIONS` disables interactions without disabling the evidence-based documentary answer.
48. Detect relational intent locally through normalized deterministic rules without an AI call before retrieval.
49. Never accept, repair, or complete a response whose `finish_reason` is `length`.
50. Permit at most one complete compact regeneration after truncation without exceeding `AI_QUERY_MAX_OUTPUT_TOKENS`.
51. Treat `AI_QUERY_MAX_OUTPUT_TOKENS` as a per-attempt ceiling and `QUERY_MAX_INTERACTIONS` as an interaction ceiling, never as fill targets.
52. Validate every pending unit against the provider input limit before sending any embedding batch.
53. Never truncate, cut, or arbitrarily fragment evidence to create its embedding.
54. Represent an oversized primary unit through the embedding of a valid derived summary only when lineage to the complete primary evidence is persisted.
55. Apply CIE only to vector distributions from conceptual and relational routes.
56. Limit the statistical set with `QUERY_CANDIDATE_LIMIT`, default `20`, effective range `1..200` candidates per document.
57. Calculate population mean and population standard deviation over the Top-k, and calculate `CV = σ / μ`, using `null` when `μ = 0`.
58. Classify `s < μ` as discard, `μ ≤ s < μ + σ` as convergence, and `s ≥ μ + σ` as core.
59. Use core as the primary reference and convergence as available complementary context; when core is empty, promote convergence to the primary role.
60. Preserve Retriever order within regions; do not create a subjective score, weight, heuristic, or AI reranking stage.
61. Resolve derived candidates selected by CIE to primary sources before applying the global limit and calling the answer provider.
62. Do not persist candidates, similarities, statistics, regions, or the CIE selection as documentary memory.
63. Stop vectorization and report the evidence identifier when an oversized primary unit has no compatible derived summary; require a real structural subdivision.
64. Require `used_evidence_ids` to contain only evidence records that are effectively cited in the answer.
65. Preserve `core` as argumentative precedence and use `convergence` only when it contributes literal support, context, limitation, or counterpoint.
66. Do not invent relationships to accommodate recovered evidence; discard uncited candidates without invalidating the answer.
67. Keep strict semantic calibration of `simetry` and `assimetry` separate from the documentary validity of the answer.
68. Silently discard an answer rejected by local validation and permit at most three total attempts with the same available context; from the second attempt onward, send only a safe corrective code. Recovered but uncited evidence must be discarded from the final basis, never used alone to trigger another attempt or block the whole answer.
69. Show an error to the user only after the third consecutive validation failure, using a generic message without an evidence identifier or internal technical rule.
70. Keep modules independent from projects, users, and documents; observed associations belong to the module, not the Core persistence model.
71. Allow zero, one, or many active modules and deliver each event only to declared subscribers.
72. Let the Core know only generic contracts and capabilities, never a module-specific name, menu, rule, HTML, CSS, or function.
73. Use `module_events` as the only additional main-database table for the neutral mailbox without altering pre-existing tables.
74. Keep each module's schema, history, and cursor in its own private SQLite database excluded from version control.
75. Persist the sanitized event transactionally and resolve its processing immediately after the completed interaction.
76. Isolate a module failure from the documentary answer and from other subscribed modules.
77. Reject events containing sensitive fields and never let modules write into Core documentary memory.
78. Require typed confirmation for permanent deletion and remove both the package and its corresponding private data directory.
79. Do not assign scores, weights, confidence, or any subjective value to pedagogical observations produced by modules.

Related explanations are available in [Architecture](02_ARCHITECTURE.md), [Query and conversational continuity](05_QUERY_AND_CHAT.md), [Cnode as an EVA conceptual derivation](10_CNODE.md), [Context Intelligence Engine](09_CONTEXT_INTELLIGENCE_ENGINE.md), and [Connector modules](17_MODULE_CONNECTORS.md).
