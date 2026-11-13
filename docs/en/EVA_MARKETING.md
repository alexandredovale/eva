# EVA — Product Vision, Market Applications, and Impact

**Documentation version:** 8.0.2  
**Equivalent Portuguese edition:** [`../EVA_MARKETING.md`](../EVA_MARKETING.md)

## 1. Executive summary

EVA is a documentary AI platform built for environments where producing a convincing answer is not enough: the authorized sources supporting that answer must be visible.

The system turns structured documents into a queryable memory of primary evidence, controls which projects and works each user may access, selects context according to the nature of the question, and validates citations before delivering the answer.

> **EVA turns institutional documentation into verifiable answers while keeping the source as the authority and decisions under human governance.**

The current public product has two levels:

1. **Horizontal Core:** ingestion, documentary memory, query, permissions, administration, operational audit, API, queue, and white-label identity.
2. **Demonstrated modular extension:** an independent runtime and the public `com.eva.explorer` module, which reuses the Core in an educational workflow without placing pedagogical rules in the nucleus.

The main asset is not merely a chat over files. It is the combination of reusable documentary infrastructure with contracts that preserve provenance, scope, validation, and extensibility.

This document distinguishes:

- what is present in the public distribution;
- impacts that follow directly from implemented behavior;
- economic, educational, or energy results that still require field measurement.

## 2. The problem EVA addresses

Organizations accumulate knowledge in policies, manuals, standards, books, contracts, procedures, reports, and training material. This knowledge often remains fragmented across files and teams.

Generative tools can improve access, but they introduce risks when they:

- mix external knowledge with internal rules;
- answer without identifying the origin of a claim;
- cite sources that were not part of the retrieved context;
- remove passages from the structure in which they were published;
- expose documents outside the authorized scope;
- treat an AI output as a final decision;
- resend large documentary volumes for every question;
- require rebuilding the entire solution for every new use case.

EVA operates between the collection and the generative model:

```text
authorized documentation
        ↓
structure and primary evidence
        ↓
access control
        ↓
retrieval suited to the question
        ↓
query-local context selection
        ↓
answer with validated citations
        ↓
human review and decision
```

The objective is to make institutional knowledge more accessible without taking control over source, scope, and responsibility away from the institution.

## 3. Accurate view of the public product

EVA provides a PHP web application, a MySQL or MariaDB database, private document storage, a processing queue, configurable AI adapters, an authenticated API, and a modular runtime with private SQLite storage per package.

### 3.1 Implemented capabilities

- Markdown, JSON, and XML ingestion;
- normalization into a shared documentary tree;
- persistence of source, hierarchy, order, path, and reference;
- literal `primary:node_content` primary evidence;
- embeddings of complete documentary units;
- direct, structural, broad, conceptual, and relational retrieval;
- query-local κq boundary and Context Intelligence Engine;
- answers with locally validated citations;
- transient `simetry` and `assimetry` relations;
- simultaneous query over multiple authorized works;
- projects, users, permissions, and response profiles;
- one independent question per request, without inherited prior context;
- sanitized operational audit and descriptive metrics;
- queue, explicit failure retry, and confirmed processing;
- private, authenticated documentary figures;
- white-label configuration;
- provider neutrality through configuration;
- runtime for independent modules;
- public reference module `com.eva.explorer`.

### 3.2 Current limits

The current public product does not include:

- automatic capture of websites, email, drives, or corporate systems;
- OCR or multimodal image interpretation;
- real-time synchronization with external sources;
- permanent Core chat memory;
- autonomous legal, clinical, financial, or operational decisions;
- implicit regulatory or security certification;
- an automated module marketplace;
- a guarantee of superiority over other RAG architectures;
- direct proof of net energy reduction;
- modules that exist only in local directories and are not tracked in public `main`.

### 3.3 Current cognitive base

Since version 6.0.0, the operational flow has not used hierarchical summaries, derived evidence, or persisted synthesis lineages. The cognitive base consists of literal primary evidence and its embeddings.

## 4. How the product works

### 4.1 From source to evidence

EVA validates the file, selects the parser, and creates a normalized tree. Each valid documentary content item produces primary evidence linked to its source node.

The document, node title, structural path, order, reference, literal content, and hash are preserved. AI-generated content is not persisted as if it were an original source.

### 4.2 From evidence to retrieval

Processing creates embeddings of complete documentary units together with structural context. A unit above the provider's safe budget must be subdivided in the documentation itself; the service does not silently cut it. Embeddings with the same model and hash may be reused.

EVA locally identifies whether a question is direct, structural, broad, conceptual, or relational:

- direct, structural, and broad routes inspect identifiers, text, and the tree without embedding the question;
- conceptual and relational routes use vector retrieval over eligible primary evidence;
- κq and the CIE organize the query's similarity distribution;
- the core forms the basis of the answer and convergence may complement its context;
- current semantic routes have no configurable Top-k.

The CIE organizes candidates deterministically for the same mathematical input. It does not assign truth, quality, confidence, or importance to sources.

### 4.3 From context to answer

Without recovered evidence, EVA reports the limitation and does not call the answer provider. When context exists, the output must:

- cite only recovered evidence;
- use every citation where it contributes analytically;
- keep visible citations consistent with declared IDs;
- use valid literal excerpts in cognitive relations;
- keep limitations separate from the main answer.

An incompatible output may be discarded and regenerated within a limit of three total attempts. The system does not add missing citations merely to appear compliant.

### 4.4 One question at a time

Each request restarts the query-local election: input, transient embedding, retrieval, κq, CIE, answer, and interactions are not inherited from the previous question.

This restart does not rebuild documentary memory. The work's primary evidence and embeddings remain stored and are reused. Independence between questions therefore prevents accumulated history growth without requiring full reprocessing of the collection.

### 4.5 Traceability and audit

The result shows used evidence with document, node, path, reference, and content. This is documentary traceability.

The Core audit is operational and sanitized: it does not store prompts, questions, answers, keys, tokens, or documentary content. The chat transcript is not persisted as a conversation by the base product.

> **Correct formulation: documentary traceability in the result and sanitized operational audit.**

## 5. Security, governance, and operation

EVA implements authentication, sessions, access recovery, a superadmin role, and permissions by project or work. Response profiles may guide audience, tone, vocabulary, and focus, but never replace evidence rules.

Direct impacts:

- users query only the scopes granted to them;
- works shared among selected projects are deduplicated;
- modules revalidate actor and scope before reusing the Core;
- presentation rules do not become documentary authority;
- provider, endpoint, model, and credential settings remain outside the public interface.

These controls do not, on their own, amount to regulatory certification, an absolute security guarantee, or certified multitenant isolation.

The administrative panel manages documents, projects, users, permissions, queue, modules, metrics, and audit. Real AI use requires server-side enablement and explicit confirmation.

## 6. Modular extension verified in public main

Verification of the tree tracked in public `main` identifies the modular runtime and one reference module: `com.eva.explorer`. Untracked local directories are not presented here as available products.

### 6.1 EXPLORER

`com.eva.explorer` demonstrates EVA as an institutional collection-based learning platform.

| Role | Operation |
|---|---|
| **Teacher** | Creates, pauses, and reactivates Learning Topics; reviews stages and analyses. |
| **Student** | Completes Quizz, Nodes, and Exam and receives qualitative feedback. |
| **Academic office** | Reviews topics, participation, completed stages, progress, and teacher activity. |
| **Superadmin** | Assigns users to module roles. |

The three experiences are:

- **Quizz:** a short open question about a central concept;
- **Nodes:** a student-written relation between topics in the work;
- **Exam:** a question with four choices and one correct answer.

After the response, the result is classified as `correct` or `deepen` and accompanied by qualitative analysis. There is no grade, percentage, ranking, or mastery level.

Directly observable impacts:

- turns a work into three forms of practice;
- separates the displayed activity from the documentary support used in analysis;
- provides feedback tied to the same base document;
- preserves distinct roles;
- keeps activity and progress in private module storage;
- revalidates documentary authorization for every generation and analysis.

Learning gains, reduced teacher time, retention, and satisfaction must be measured in pilots; the repository does not prove them.

## 7. The Core as a horizontal application

```text
administrator imports and processes documents
        ↓
organizes works into projects
        ↓
grants user access
        ↓
user selects a project or works
        ↓
asks an independent question
        ↓
receives an answer, citations, and used evidence
```

| Situation | EVA behavior | Direct impact |
|---|---|---|
| Confirm an internal rule | Queries only the authorized policy and presents the cited passage. | Brings guidance closer to its institutional source. |
| Locate a procedure by section | The structural route traverses the tree without embedding the question. | Uses the original organization and avoids an external call on this route. |
| Combine concepts from multiple works | Consolidates authorized context and deduplicates shared sources. | Supports multi-work analysis without documentary duplication. |
| Ask an unsupported question | Reports insufficient evidence and does not call the answer provider. | Avoids generation without recovered support. |
| Reuse a manual across teams | The work can belong to different projects and permission sets. | Reuses the same documentary memory. |

## 8. Possible market applications

The scenarios below are compatible with the Core when the work consists of querying supplied, structured, processed, and authorized documentation. They do not mean that sector integrations or certifications are already available.

| Market | Typical sources | Compatible application | Current limit |
|---|---|---|---|
| Education | books, handouts, regulations | query and practice with EXPLORER | pedagogical impact requires measurement |
| Corporate knowledge | policies, manuals, processes | onboarding and internal support | external synchronization requires integration |
| Compliance | standards and controls | cited documentary guidance | does not issue opinions or certification |
| Engineering and energy | manuals and procedures | instruction location and comparison | does not operate telemetry or equipment |
| Legal | contracts and regulations | clause research and verification | does not replace legal counsel |
| Government | acts and procedures | supported administrative query | does not decide or monitor official journals in real time |
| Research and publishing | books, articles, collections | structural and semantic exploration | not a full reference manager |
| Health, finance, and insurance | protocols and internal rules | consultative documentary support | high-impact decisions remain outside the product |

## 9. Version 8.0.2 documentary benchmark

The public benchmark compared five independent questions about one work under two approaches:

1. **EVA:** new question embedding, retrieval, κq, CIE, elected evidence, generation, and validation.
2. **Full context:** the same model received the complete Markdown again before every question, with the prefix arranged to favor provider caching.

| Aggregate indicator | EVA | Full context | Observed result |
|---|---:|---:|---|
| Answer-model input | 25,805 | 1,246,430 | EVA: 97.93% lower |
| Output | 2,645 | 1,472 | EVA: 79.69% higher |
| Total in answer model | 28,450 | 1,247,902 | full context: 43.86 times higher |
| Operational total, including embeddings | 28,554 | 1,247,902 | EVA: 97.71% lower |
| Aggregate cost | US$0.004142710 | US$0.041383956 | EVA: 89.99% lower |
| Total latency | 26.467 s | 22.996 s | full context: 13.11% lower |

EVA generated more output tokens because its answers were more developed and included traceability. The saving occurred in input context, not through shorter answers.

The experiment respected the one-question-at-a-time contract. Each query restarted the query-local election while reusing persistent documentary memory. The result therefore did not depend on inheriting previous-question context.

### 9.1 Responsible energy interpretation

The reduction in tokens and cache misses demonstrates a lower logical load sent to the model in the measured scenario and makes it plausible to expect less inference computation. This is a coherent technical inference even when the system processes one question at a time.

However, the benchmark did not measure power, energy, water, carbon, PUE, hardware, data-center location, or the energy spent on initial embedding construction. The correct formulation is therefore:

> **EVA demonstrated containment of tokens and operational cost in the experiment; its contribution to energy efficiency is logically plausible, but net energy reduction still requires instrumental measurement.**

Full context was faster overall, while EVA's answer-provider calls were shorter in aggregate. EVA exchanges a small amount of local latency for a substantial reduction in generative context and for evidence and validation contracts.

See the [complete benchmark](19_EVA_VS_FULL_CONTEXT_BENCHMARK.md) for methodology, questions, answers, costs, limitations, and pricing references.

## 10. Verifiable impact versus results to prove

| Dimension | Impact verifiable now |
|---|---|
| Grounding | Accepted answers cite recovered evidence and use it analytically. |
| Refusal | Without evidence, the answer provider is not called. |
| Provenance | Evidence retains document, node, path, reference, and literal content. |
| Scope | Users and modules query authorized projects and works. |
| Reuse | Identical embeddings by model and hash may be reused. |
| Containment | Non-semantic routes do not require a question embedding. |
| Validation | Out-of-context citations and relations without literal excerpts are rejected. |
| Extensibility | EXPLORER operates with its own domain, data, and interface. |

The following still require additional evidence:

- higher accuracy or recall than other architectures;
- percentage reductions across other corpora, models, and loads;
- reduced regulatory risk;
- learning or productivity gains;
- net energy reduction;
- return on investment;
- performance and availability at untested scale.

## 11. Where EVA fits best

The product is most appropriate when:

- a relevant, repeatedly used collection exists;
- documents can be prepared as Markdown, JSON, or XML;
- source structure helps interpret the content;
- the origin of the answer must be checked;
- different groups need different scopes;
- usage remains consultative and supervised;
- someone is responsible for source quality and authorization.

Additional development is required for continuous ingestion from external systems, OCR, visual analysis, real-time operational data, domain-specific transactional workflows, complete legal retention, automated high-impact decisions, or special scale and certification requirements.

## 12. Communicable differentiators

- literal primary evidence as the persistent base;
- preservation of hierarchy, order, and reference;
- embeddings of complete documentary units;
- retrieval adapted to question type;
- query-local statistical selection without fixed semantic Top-k;
- generation blocked when no evidence exists;
- local citation and analytical-use validation;
- transient relations verified through literal excerpts;
- multi-work query with deduplication;
- permissions by project and work;
- one question at a time without accumulated history;
- sanitized operational audit;
- provider neutrality through configuration;
- white-label experience;
- independent modular runtime and public EXPLORER.

Necessary distinctions:

- the CIE is deterministic for the same distribution; this does not make embeddings and generation deterministic;
- provider neutrality does not guarantee identical quality or cost across models;
- white label does not automatically constitute a multitenant SaaS;
- access control is not a compliance certification;
- validated citations reduce failures but do not guarantee every interpretation is correct.

## 13. Product and offering thesis

```text
                    EVA Core
          documentary memory and query
                       │
                 module runtime
                       │
                   EXPLORER
                    learning
```

The architecture supports Core reuse, keeps domain data and rules outside the nucleus, enables dedicated and white-label deployments, and allows new modules to be developed with lower coupling.

Technically possible offering models:

- dedicated institutional installation;
- white-label product;
- documentary query application;
- educational package with EXPLORER;
- development of a new vertical module;
- authenticated API integration;
- collection deployment, organization, and governance.

A commercial proposal should separate licensing, infrastructure, AI consumption, initial processing, updates, support, backup, document preparation, integrations, and institutional responsibilities.

## 14. Measurement with clients

A pilot should compare the prior process and the EVA process over the same corpus and audience.

| Objective | Recommended indicators |
|---|---|
| Information access | time to source; resolved queries; manual searches; escalations |
| Documentary quality | citation validity; coverage; correct refusal; corrections |
| Computational efficiency | calls; embeddings; tokens; cache; latency; cost per query |
| Energy | power and energy per query; hardware; PUE; location; construction and operation |
| Collection operation | ingestion; reuse; failures; retries; updates |
| EXPLORER | topics; participation; completion; recurring `deepen`; teacher time |
| Adoption | active users; return; abandonment; satisfaction; perceived usefulness |

Every quantitative claim should state the corpus, model, configuration, baseline, period, and method.

## 15. Allowed and prohibited claims

### Factual formulations

- “EVA turns Markdown, JSON, and XML into structured primary evidence.”
- “Accepted answers cite evidence recovered from the authorized collection.”
- “Without recovered evidence, EVA does not call the answer provider.”
- “The public product includes the EXPLORER reference module.”
- “The 8.0.2 benchmark observed 97.71% fewer operational tokens and 89.99% lower cost in the documented scenario.”
- “EVA implements computational-containment mechanisms whose net energy effect still requires measurement.”

### Formulations requiring additional proof

- “Eliminates hallucinations.”
- “Guarantees correct answers.”
- “Is superior to every RAG.”
- “Reduces energy by a specific percentage.”
- “Guarantees compliance.”
- “Is secure for any sensitive data.”
- “Replaces specialists.”
- “Keeps a complete history of Core answers.”

| Avoid | Use |
|---|---|
| “Hallucination-free AI” | “Out-of-context citations are rejected and queries without evidence end without generation.” |
| “Guaranteed correct answer” | “Answer limited to recovered evidence and validated by the local contract.” |
| “Complete chat audit” | “Documentary traceability in the result and sanitized operational audit.” |
| “Proven energy savings” | “Measured token and cost reduction, with net energy effect still to be measured.” |

## 16. Risks and due diligence

The repository demonstrates product and architecture, but it does not prove paying customers, recurring revenue, acquisition cost, commercial retention, margin, SLA, certifications, or financial and educational field impact.

Before deployment, it is necessary to:

1. validate in an isolated environment;
2. test the client's actual corpus and questions;
3. measure quality, latency, tokens, cost, and energy where relevant;
4. validate documentary conversion and updates;
5. test backup, recovery, scale, and availability;
6. define human governance and domain responsibility;
7. validate legal, privacy, and security requirements.

## 17. Adoption plan

1. **Controlled corpus:** select structured documents, questions, and responsible people.
2. **Operational pilot:** compare quality, time, failures, use, and cost against the baseline.
3. **Specialized application:** activate EXPLORER for educational use or develop a dedicated module.
4. **Governed expansion:** add projects, works, profiles, users, and modules after validating corpus and operation.
5. **Business case:** turn measured results into a commercial proposal with a declared method.

## 18. Recommended messages

### Institutional

> **EVA turns authorized documentation into verifiable answers with visible sources, governed access, and modular extension.**

### Product

> **Documentary infrastructure that preserves primary evidence, selects context according to the question, and validates citations before delivering the answer.**

### Responsible efficiency

> **EVA reduces generative context by retrieving relevant evidence; the 8.0.2 benchmark quantifies tokens and cost, while energy impact remains a measurable hypothesis.**

### Why not merely a chatbot with files?

Because EVA preserves structure and primary evidence, governs which works may be queried, selects context according to the question, validates citations, and supports independent extensions over the same collection.

## 19. Conclusion

EVA is a functional platform for ingestion, processing, query, validation, access, and administration. The public distribution also demonstrates extensibility through the modular runtime and EXPLORER.

The responsible view is:

- an executable documentary platform exists;
- a public reference module exists;
- immediately demonstrable horizontal use cases exist;
- the 8.0.2 benchmark measures a substantial reduction in tokens and cost in a bounded scenario;
- the energy effect is logically coherent but was not directly measured;
- economic and educational impact, scale, and traction must not be assumed.

> **EVA creates a verifiable bridge between institutional knowledge and AI applications, preserving the source as the foundation and human governance as the boundary.**

## 20. Internal references

- [Documentation index](README.md)
- [Overview](01_OVERVIEW.md)
- [Architecture](02_ARCHITECTURE.md)
- [Ingestion and cognitive build](04_INGESTION_AND_BUILD.md)
- [Query and single-turn chat](05_QUERY_AND_CHAT.md)
- [API and operations](06_API_AND_OPERATIONS.md)
- [Scientific scope and energy sustainability](08_SCIENTIFIC_AND_ENERGY.md)
- [Context Intelligence Engine](09_CONTEXT_INTELLIGENCE_ENGINE.md)
- [Connector modules](17_MODULE_CONNECTORS.md)
- [Database relationships](18_DATABASE_RELATIONSHIPS.md)
- [EVA versus full-context benchmark](19_EVA_VS_FULL_CONTEXT_BENCHMARK.md)
- [EXPLORER README](../../modules/com.eva.explorer/README.md)
- [Changelog](../../CHANGELOG.md)
