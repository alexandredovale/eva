# EVA 8.0.2 Benchmark: documentary retrieval versus full context

## Executive summary

This benchmark compares EVA's documentary flow with a direct call that sends the complete Markdown file of one work to the same model. The test used five new questions about **The Spirits' Book (O Livro dos Espíritos)**, always with the `deepseek-flash` model, temperature zero, reasoning mode disabled, and an output ceiling of 1,800 tokens.

Across the five questions, EVA accounted for 28,450 tokens in the answer model and 104 additional tokens in the embedding model. The direct call accounted for 1,247,902 tokens. Considering the operational total, EVA reduced token volume by **97.71%** and sent **43.86 times fewer tokens** to the answer model.

The observed cost was approximately **USD 0.00414271** for EVA and **USD 0.041383956** for full context, a reduction of **89.99%**. The direct baseline was deliberately arranged to favor its own cache: the complete document remained before the question as an identical prefix. After the first call, 249,088 tokens per question were therefore retrieved from the provider cache. Even in this favorable scenario, the aggregate cost of full context was approximately **9.99 times higher**.

Full context had lower total latency across the set: 22.996 seconds, compared with 26.467 seconds for EVA. The difference results from the additional embedding, retrieval, κq, CIE, and local validation stages. In isolation, however, EVA answer generation consumed 19.410 seconds, 15.59% less than the 22.996 seconds spent by the model on direct calls.

This result does not establish universal superiority or score doctrinal quality. For this work, configuration, and question set, it demonstrates the practical difference between retrieving evidence before inference and resending the complete document for every query.

## Objective

The test answers four operational questions:

1. How many tokens are accounted for when the model receives only the evidence elected by EVA?
2. How many tokens are accounted for when the same model receives the complete `.md` file?
3. How do cache, cost, and latency behave across five successive queries about the same work?
4. What observable differences appear in the answers, particularly in concision, breadth, and documentary traceability?

## Environment and corpus

| Item | Value |
|---|---|
| Execution date | November 13, 2026 |
| Completion time | 13:08:27 UTC / 10:08:27 America/Sao_Paulo |
| Base commit | `e2f5ea3f3bb7e30336524d3b714f39326931208a` |
| PHP | `8.2.12` |
| Document | `EVA-D000221` — O Livro dos Espíritos |
| File | `storage/documents/EVA-D000221.md` |
| Size | 890,163 bytes |
| Characters | 860,908 |
| Lines | 3,728 |
| SHA-256 | `67de2fc192c8d5b31cf74932e1ca533d4297a9ae957b8b0b436e38c5045bb1a9` |
| Answer model | `deepseek-flash` |
| Embedding model | `text-embedding-3-small` |
| Temperature | `0` |
| Provider reasoning | disabled |
| Output limit | 1,800 tokens |

The source file is not distributed in the public repository. Its identifier, dimensions, and hash make it possible to verify locally whether another run uses the same corpus without revealing or duplicating its content.

## Methodology

### EVA scenario

Each question was processed as an independent query by the existing operational flow:

```text
question → transient embedding → retrieval from primary evidence
         → κq → primary/global CIE → elected documentary context
         → JSON generation → local validation → answer
```

The following were recorded separately:

- question embedding tokens;
- input, cache-hit, cache-miss, and output tokens from the answer model;
- total time and time spent by the answer provider;
- evidence actually cited;
- validated transient interactions and declared limitations.

### Full-context scenario

The direct call used the same `deepseek-flash` and received:

```text
minimal instruction → complete work Markdown → question
```

The instruction required an answer in Portuguese, exclusive use of the document, no external knowledge, and an explicit statement when the document did not support a conclusion.

The document was placed before the question to form an identical prefix across calls. This order maximizes the direct baseline's caching opportunity. The first query was cold; for each of the next four, the provider reported a cache hit of 249,088 tokens.

### Order and isolation

The five questions were defined before execution. For each question, EVA ran first and the direct call followed. No answer was used as input to another question. None of the ten generations required a retry, was truncated, or failed.

### Effect of the one-question-per-request contract

The benchmark fully respected EVA's operational contract of one independent question per request. Each of the five queries generated a new question embedding and restarted retrieval, κq, and CIE without inheriting the input, recovered population, transient embedding, answer, or interaction from the preceding query.

This restart does not reconstruct documentary memory. Primary evidence and its embeddings, produced earlier during cognitive construction, remained stored and were reused in every query. Starting a question from zero therefore means repeating only the query-local election; it does not mean reprocessing the complete Markdown, regenerating the work's embeddings, or resending the complete work to the answer model.

The contract also prevents the cumulative growth of a conversational window. Instead of carrying prior questions, answers, and contexts, each call contains only fixed instructions, the current question, and the evidence elected for it. Across the measured set, the five new question embeddings totaled 104 tokens, while the context sent to the answer model totaled 25,805 input tokens.

The direct baseline followed the same isolation of one question per call but received the complete document again as a prefix. Its cache was deliberately favored for the next four calls, yet the provider still accounted for 1,246,430 input tokens across the set. The savings observed in this benchmark therefore already include the cost of restarting every query and do not depend on conversational memory between questions.

## Questions

| ID | Category | EVA local classification | Question |
|---|---|---|---|
| Q1 | Conceptual definition | `conceptual` | What is God, and which attributes define the divine nature? |
| Q2 | Moral relationship | `relational`, `conceptual` | How does free will relate to moral responsibility for human actions? |
| Q3 | Doctrinal relationship | `relational`, `conceptual` | What is the relationship among reincarnation, the Spirit's progress, and divine justice? |
| Q4 | Thematic explanation | `conceptual` | How does The Spirits' Book explain the origin and purpose of the law of labor? |
| Q5 | Process and identity | `conceptual` | What happens to the soul after death, and how does it preserve its individuality? |

## Token results

### EVA

| ID | Input | Cache hit | Cache miss | Output | DeepSeek total | Embedding |
|---|---:|---:|---:|---:|---:|---:|
| Q1 | 4,106 | 1,792 | 2,314 | 790 | 4,896 | 17 |
| Q2 | 6,060 | 1,792 | 4,268 | 413 | 6,473 | 22 |
| Q3 | 2,851 | 1,792 | 1,059 | 312 | 3,163 | 23 |
| Q4 | 2,610 | 1,792 | 818 | 461 | 3,071 | 22 |
| Q5 | 10,178 | 1,792 | 8,386 | 669 | 10,847 | 20 |
| **Total** | **25,805** | **8,960** | **16,845** | **2,645** | **28,450** | **104** |

### Full context

| ID | Input | Cache hit | Cache miss | Output | Total |
|---|---:|---:|---:|---:|---:|
| Q1 | 249,282 | 0 | 249,282 | 270 | 249,552 |
| Q2 | 249,287 | 249,088 | 199 | 180 | 249,467 |
| Q3 | 249,289 | 249,088 | 201 | 388 | 249,677 |
| Q4 | 249,286 | 249,088 | 198 | 443 | 249,729 |
| Q5 | 249,286 | 249,088 | 198 | 191 | 249,477 |
| **Total** | **1,246,430** | **996,352** | **250,078** | **1,472** | **1,247,902** |

### Aggregate comparison

| Indicator | EVA | Full context | Observed difference |
|---|---:|---:|---:|
| Answer-model input | 25,805 | 1,246,430 | EVA: 97.93% lower |
| Cache hit | 8,960 | 996,352 | Direct favored by the stable complete-document prefix |
| Cache miss | 16,845 | 250,078 | Direct: 14.85 times higher |
| Output | 2,645 | 1,472 | EVA: 79.69% higher |
| Answer-model total | 28,450 | 1,247,902 | Direct: 43.86 times higher |
| Operational total, including embeddings | 28,554 | 1,247,902 | EVA: 97.71% lower |
| Input cache-hit rate | 34.72% | 79.94% | Direct baseline warmed the complete document |

The 104 embedding tokens belong to another model and tokenizer. They are presented as additional operational consumption but do not represent exact linguistic equivalence with `deepseek-flash` tokens.

## Costs

The costs below use the off-peak prices in effect on the test date for `deepseek-flash`: USD 0.003 per million cache-hit tokens, USD 0.15 per million cache-miss tokens, and USD 0.60 per million output tokens. The `text-embedding-3-small` embedding was calculated at USD 0.02 per million tokens. The figures do not include local infrastructure, database, storage, or network costs.

| ID | EVA | Full context | Direct / EVA |
|---|---:|---:|---:|
| Q1 | USD 0.000826816 | USD 0.037554300 | 45.42 times |
| Q2 | USD 0.000893816 | USD 0.000885114 | 0.99 time |
| Q3 | USD 0.000351886 | USD 0.001010214 | 2.87 times |
| Q4 | USD 0.000405116 | USD 0.001042764 | 2.57 times |
| Q5 | USD 0.001665076 | USD 0.000891564 | 0.54 time |
| **Total** | **USD 0.004142710** | **USD 0.041383956** | **9.99 times** |

The first direct call accounted for 90.75% of the baseline's total cost because the document was not yet cached. Across the four warm queries, aggregate direct cost was USD 0.003829656, compared with USD 0.003315894 for EVA. With the complete document still available in cache, costs therefore became similar, even though the baseline continued to account for approximately 249 thousand input tokens per question.

The result distinguishes two measurements:

- **accounted tokens:** remain high in full context; cache-hit tokens are retrieved without the same recomputation required by cache misses;
- **billed cost:** drops substantially while the complete prefix remains available in the provider cache.

DeepSeek caching is automatic, prefix-based, and best-effort; its persistence is not guaranteed. For cold queries, queries distributed over time, or document changes, direct cost tends to approach the full cost observed in Q1 again.

Pricing and accounting references:

- [DeepSeek — Models & Pricing](https://api-docs.deepseek.com/quick_start/pricing/)
- [DeepSeek — Context Caching](https://api-docs.deepseek.com/guides/kv_cache/)
- [DeepSeek — Token & Token Usage](https://api-docs.deepseek.com/quick_start/token_usage/)
- [OpenAI — text-embedding-3-small](https://developers.openai.com/api/docs/models/text-embedding-3-small)

## Latency

| ID | EVA total | Full context | Lower time |
|---|---:|---:|---|
| Q1 | 6.554 s | 6.839 s | EVA |
| Q2 | 4.775 s | 4.418 s | Direct |
| Q3 | 3.827 s | 4.692 s | EVA |
| Q4 | 5.122 s | 3.931 s | Direct |
| Q5 | 6.189 s | 3.117 s | Direct |
| **Total** | **26.467 s** | **22.996 s** | **Direct** |
| **Mean** | **5.293 s** | **4.599 s** | **Direct** |

Full context was 13.11% faster overall. EVA added approximately 7.057 seconds of embeddings and local processing to the set. Considering only calls to the answer provider, EVA consumed 19.410 seconds, 15.59% less than the direct calls.

Latency and tokens did not vary linearly. Q5 had EVA's largest context, yet the direct baseline had its lowest time. This behavior may reflect caching, provider load, network conditions, and operational variation. Five sequential observations are not enough to infer latency percentiles.

## Evidence and traceability

| ID | Evidence used by EVA | Characters in used evidence | Validated interactions |
|---|---|---:|---:|
| Q1 | `EVA-E008287`, `EVA-E008290`, `EVA-E008285`, `EVA-E008286`, `EVA-E008284`, `EVA-E008957` | 5,293 | 0 |
| Q2 | `EVA-E009162`, `EVA-E009139`, `EVA-E009134`, `EVA-E009136`, `EVA-E009135`, `EVA-E009133`, `EVA-E009137`, `EVA-E009140` | 11,252 | 0 |
| Q3 | `EVA-E008447` | 2,244 | 0 |
| Q4 | `EVA-E008966`, `EVA-E008969` | 1,085 | 0 |
| Q5 | `EVA-E008428`, `EVA-E008426`, `EVA-E008258`, `EVA-E008441`, `EVA-E008427`, `EVA-E008431`, `EVA-E008438` | 16,131 | 0 |

EVA used 24 evidence items across the five answers, totaling 36,005 characters of content actually cited. The absence of `simetry` or `assimetry` is not a failure: for four questions, the system explicitly recorded that the evidence supported the answer but did not contain enough literal elements to validate these operators. Q3 was supported by a single evidence item and did not require this limitation.

The direct baseline cited question numbers from the work in every answer, but the model itself produced those numbers. They did not pass through the ID contract, local context-membership verification, or EVA's analytical-incorporation validation.

## Qualitative observations

Both approaches answered the core of all five questions. No subjective quality score was applied, because doing so would require independent evaluators, previously calibrated criteria, and specialized doctrinal review.

- **Q1:** both identified God as the supreme intelligence and presented the divine attributes. EVA developed the limits of human understanding, the evolution of the idea of God, and criticism of pantheism; the direct answer was shorter and indicated question numbers.
- **Q2:** both related free will and responsibility. EVA incorporated degrees of freedom, knowledge, resistance, and incapacity; the baseline condensed the conclusion and pointed to specific questions.
- **Q3:** both related reincarnation, progress, and justice. EVA supported the entire answer with one primary evidence item; the baseline added references to questions 167 and 171.
- **Q4:** EVA focused on corporeal origin, intellectual improvement, and the duty of usefulness. The baseline broadened the answer with limits on labor, social duty, and participation in Creation.
- **Q5:** both affirmed the soul's survival and individuality. EVA detailed the perispirit, gradual separation, disturbance, and recovery of consciousness; the baseline answered more briefly with references to questions 149 and 150.

EVA generated 2,645 output tokens, compared with 1,472 for the baseline. EVA's savings therefore occurred in input context, not through shorter answers. EVA answers were longer and traceable; direct answers were generally more concise.

## Complete results

The calls originally returned Portuguese answers. The texts below are faithful editorial translations for this English mirror; they are not new model generations and do not alter the recorded metrics.

<details>
<summary>Q1 — What is God, and which attributes define the divine nature?</summary>

### EVA

God is presented as a being whose attributes include being eternal, infinite, immutable, immaterial, unique, omnipotent, supremely just, and good, although human language, restricted to human ideas and sensations, has no means of expressing everything above human intelligence [EVA-E008287]. Reason indicates that God must possess these perfections to the supreme degree, because if one were missing or not infinite, God would no longer be superior to everything and, consequently, would not be God [EVA-E008287].

Each attribute is justified by its role in universal order: God is eternal because, having had a beginning, God would have come from nothing or been created by an earlier being; immutable because, if subject to change, the laws governing the Universe would have no stability; immaterial because God's nature differs from everything called matter and would otherwise be subject to material transformations; unique because, if there were many gods, there would be no unity of view or power in ordering the Universe; omnipotent because God is unique and, without sovereign power, something would be more powerful or equally powerful; and supremely just and good, a wisdom revealed in the smallest as well as the greatest things [EVA-E008287].

God's inner nature, however, cannot be understood by humanity, which lacks the necessary sense [EVA-E008284]. It is nevertheless possible to form an idea of some divine perfections, understood more fully as human beings rise above matter and glimpse them through thought [EVA-E008286]. Humanity will understand them when the spirit is no longer obscured by matter and, through perfection, has drawn closer to God. During humanity's infancy, people often confuse God with the creature and attribute imperfections to God; as the moral sense develops, however, they form a more accurate idea of the Divinity [EVA-E008285].

The conception of one God results from the development of ideas: unable to conceive an immaterial being without a defined form acting upon matter, humanity assigned God attributes of corporeal nature, and everything beyond ordinary intelligence was regarded as a divinity. At all times, however, learned people understood that multiple powers could not govern the world without a higher direction, thereby rising to the conception of one God [EVA-E008957]. Pantheistic doctrine, in turn, makes God a material being who, although endowed with supreme intelligence, would be subject to the vicissitudes of matter and would lack the essential attribute of immutability. It confuses the Creator with the creature, like claiming that a machine is an integral part of the mechanic who conceived it [EVA-E008290].

### Full context

God is the supreme intelligence, the primary cause of all things (question 1). God's attributes are: eternal, immutable, immaterial, unique, omnipotent, supremely just, and good (question 13). The document explains that this idea is complete from the human point of view, but that some things are above human intelligence and cannot be expressed by language. God is eternal because, if God had had a beginning, God would have come from nothing or been created by an earlier being; immutable because, if subject to change, the laws of the Universe would have no stability; immaterial because God's nature differs from everything called matter; unique because, if there were many gods, there would be no unity of power in ordering the Universe; omnipotent because God is unique; and supremely just and good, a wisdom revealed in the divine laws. The document also states that humanity cannot understand God's inner nature (question 10) and that only higher Spirits see and understand God (question 244).

</details>

<details>
<summary>Q2 — How does free will relate to moral responsibility for human actions?</summary>

### EVA

Free will is a condition of moral responsibility: without it, a human being would be a machine [EVA-E009133] and would have neither guilt for evil nor merit for good, so blame or praise falls upon intention, that is, upon the will [EVA-E009162]. Responsibility grows with freedom: a more enlightened person is more responsible for what they do than a savage is for their acts [EVA-E009139], and freedom, almost nonexistent in the earliest phases of life, develops with the faculties [EVA-E009134]. Instinctive predispositions brought at birth do not eliminate this freedom, because no impulse is irresistible when one has the will to resist [EVA-E009135]. The influence of matter hinders the Spirit's manifestations without granting the faculty itself, and those who willingly concern themselves only with matter incur fault [EVA-E009136]. An impairment of the faculties, in turn, removes the freedom of one who is no longer master of their own thought [EVA-E009137], while the demands of the world do not eliminate the responsibility to make efforts to overcome obstacles [EVA-E009140]. Moral responsibility is therefore grounded in free will, which can yield or resist, and is measured by the degree of freedom actually exercised.

### Full context

Based on the document, free will is the basis of moral responsibility. The text states that, without free will, a human being would be a machine (q. 843) and would have neither guilt for evil nor merit for good (q. 872). Responsibility is proportional to the means available to understand good and evil: an educated person who commits injustice is more blameworthy than an ignorant savage (q. 637). Free will develops with self-awareness (q. 122) and allows a person to choose whether to yield to or resist spiritual influences (q. 851, 872). Each person therefore answers for their actions according to their intention and knowledge of moral law.

</details>

<details>
<summary>Q3 — What is the relationship among reincarnation, the Spirit's progress, and divine justice?</summary>

### EVA

Reincarnation is grounded in God's justice and revelation, because a good father always leaves a door open for his children to repent [EVA-E008447]. All Spirits tend toward perfection, and God provides the means to attain it through the trials of corporeal life. Divine justice allows them, in new existences, to accomplish what they could not do or complete in a first trial [EVA-E008447]. The doctrine of many successive existences is presented as the only one corresponding to the idea of divine justice toward people in a morally inferior condition, explaining the future and offering the means to redeem errors through new trials [EVA-E008447]. A person conscious of their inferiority finds consoling hope in this doctrine, because inferiority does not eternally disinherit them from the supreme good, which they can attain through new efforts [EVA-E008447]. Reincarnation therefore connects the Spirit's progress toward perfection with divine justice, which grants new trials as a means of redemption and of completing what remained unfinished.

### Full context

Based on the document, the relationship among reincarnation, the Spirit's progress, and divine justice is presented as follows:

Reincarnation is the means by which the Spirit can progress and purify itself through many corporeal existences. The document states that “we all have many existences” and that reincarnation has as its purpose the “expiation and progressive improvement of Humanity” (question 167). The Spirit, created simple and ignorant, acquires knowledge and perfects itself through the trials of corporeal life, and reincarnation allows it to accomplish in new existences what it could not complete in a first trial (question 171).

This doctrine is presented as the only one corresponding to the idea of God's Justice. The document argues that “God would act neither equitably nor in accordance with his goodness if he forever condemned those who may have encountered obstacles to their improvement arising from the very environment in which they were placed and foreign to the will that animated them” (question 171). Reincarnation is therefore the expression of divine justice, because it gives all Spirits the means to redeem their errors through new trials and attain perfection, which is the final destiny of all. God's justice is manifested by granting everyone the possibility of progress, without privileges or eternal condemnation, making each person's future depend on their own efforts and merits throughout successive existences.

</details>

<details>
<summary>Q4 — How does The Spirits' Book explain the origin and purpose of the law of labor?</summary>

### EVA

The Spirits' Book presents the law of labor as a consequence of humanity's corporeal nature, simultaneously serving as expiation and as a means of improving intelligence: labor is imposed upon humanity “because it is a consequence of corporeal nature. It is expiation and, at the same time, a means of improving intelligence,” so that without it, humanity “would always remain in the infancy of intelligence” [EVA-E008966]. The material dependence of existence is connected to this purpose, because food, safety, and well-being “depend on labor and activity,” although the same passage notes that God granted intelligence to those extremely weak in body, which is also labor [EVA-E008966].

The law is not restricted to material effort. Those who possess enough wealth to secure their existence may be exempt “from material labor, perhaps; but not from the obligation to make themselves useful,” according to the means available to them, nor from improving their own intelligence or that of others, “which is also labor” [EVA-E008969]. Exemption from the need to feed oneself by the sweat of one's brow does not eliminate the obligation, which grows with the opportunities to do good made possible by the advancement received [EVA-E008969].

The origin of the law of labor therefore lies in humanity's corporeal condition, and its purpose includes expiation, the improvement of intelligence, and usefulness toward others, including the nonmaterial form of making oneself useful and developing one's own intelligence and that of others [EVA-E008966][EVA-E008969].

### Full context

Based on the document, the law of labor is explained as follows:

**Origin and nature:**

- Labor is a **Law of Nature**, because it is a necessity (question 674).
- It is a **consequence of humanity's corporeal nature** and, at the same time, **expiation and a means of improving intelligence** (question 676).
- It is not restricted to material occupations: “the Spirit works, just as the body does. Every useful occupation is labor” (question 675).

**Purpose:**

- Without labor, “humanity would always remain in the infancy of intelligence” (question 676).
- Human labor has a **twofold purpose**: “the preservation of the body and the development of the faculty of thought, which is also a necessity and raises humanity above itself” (question 677).
- Labor is also a **means for humanity to contribute to the work of Creation**: “by contributing to the general work, humanity itself advances” (question 132).
- In more perfected worlds, “the less material the needs are, the less material labor is,” but idleness would be “a torment rather than a benefit” (question 678).

**Limits and related duties:**

- The limit of labor is the limit of strength; God leaves humanity entirely free in this respect (question 683).
- Anyone who abuses authority by imposing excessive labor on subordinates “transgresses the Law of God” (question 684).
- The strong must work for the weak; society must support those who cannot work (question 685).
- Even those with enough wealth are not exempt from the obligation to be useful and to improve their own intelligence and that of others (question 679).

</details>

<details>
<summary>Q5 — What happens to the soul after death, and how does it preserve its individuality?</summary>

### EVA

After death, the soul preserves its individuality and never loses it [EVA-E008426]. This individuality is demonstrated by the persistence of its own fluid, drawn from the atmosphere of the planet, which retains the appearance of the last incarnation: the perispirit [EVA-E008426]. The soul takes nothing from this world except the memory and desire to go to a better world, a memory filled with sweetness or bitterness according to the use it made of life [EVA-E008426].

Spiritist doctrine adopts the meaning of soul as the immaterial and individual being residing within us and surviving the body [EVA-E008258]. The view that the soul returns to the universal whole and loses its individuality is rejected: the totality of Spirits forms a complete world, but each always preserves its individuality, like a member of an assembly [EVA-E008427]. Those who suppose that souls merge into an amalgam are mistaken, because souls manifest awareness of self and their own will, and the infinite diversity they exhibit results from their being distinct individuals [EVA-E008428]. Individuality becomes even clearer when beings prove their identity through indisputable indications, verifiable individual details concerning their earthly lives, and when they become visible in apparitions [EVA-E008428].

The separation of soul and body is not instantaneous: the soul detaches gradually; the bonds are loosened rather than broken [EVA-E008431]. Death is the destruction of the body alone, not of the perispirit, which separates when organic life ceases, and complete detachment is slower in cases of violent death [EVA-E008438]. At death, everything is initially confused, and the soul needs some time to gain knowledge of itself, like someone awakening from a deep sleep. Lucidity and memory of the past return as the influence of matter fades and the mist obscuring thought dissipates [EVA-E008441].

The soul therefore survives the body as an immaterial and individual being, preserving its individuality through the perispirit and self-awareness, even though it undergoes a variable period of disturbance before recognizing itself in its new situation [EVA-E008426][EVA-E008441].

### Full context

Based on the document, after death the soul becomes a Spirit again, returning to the world of Spirits from which it had temporarily withdrawn (question 149). It preserves its individuality because it never loses it (question 150). How does it demonstrate that individuality once it no longer has a material body? “It continues to have a fluid of its own, drawn from the atmosphere of its planet, which retains the appearance of its last incarnation: its perispirit” (question 150a). The soul takes nothing from this world “except the memory and desire to go to a better world, a memory filled with sweetness or bitterness according to the use it made of life” (question 150b).

</details>

## Limitations

- The benchmark covers one work, five questions, and one sequential execution per question.
- The same model was used in both scenarios, isolating the architecture, but the results cannot automatically be generalized to other models.
- The complete document fit within the one-million-token context window of `deepseek-flash`; larger works or sets of works may exceed it.
- The direct cache was warmed within the same time window. Repetitions after expiration, prefix changes, or corpus changes may produce a different cost.
- The measurement uses provider-reported tokens as the source of truth. Embedding and generation tokens use different tokenizers.
- Cost uses the price table and off-peak period in effect at execution time; future prices may change the amounts.
- Latency includes network and external load and was not repeated enough to produce statistical percentiles.
- There was no blind expert evaluation or doctrinal-accuracy calculation. The qualitative analysis describes observable properties of the answers.
- The direct baseline did not implement programmatic validation of the cited question numbers.
- The complete answers in this English mirror are editorial translations of the original Portuguese outputs, not new model calls.

## Conclusion

In this test, EVA replaced the repeated submission of a full context accounting for 1,246,430 input tokens with 25,805 answer-context tokens and 104 embedding tokens. The gain remained substantial even against a direct baseline with optimized caching: 97.71% fewer operational tokens and 89.99% lower aggregate cost.

These figures already reflect five isolated questions, each with a new embedding and new retrieval. The efficiency resulted from reusing previously constructed documentary memory and from the absence of accumulated conversational history, not from inheriting the context of a preceding question.

Full context was 13.11% faster across the set and produced shorter answers. EVA answered in greater depth and added verifiable IDs, deterministic selection, local validation, and an explicit declaration when cognitive interactions could not be supported literally.

The practical result is a measurable tradeoff: EVA adds local stages and a small amount of latency to reduce generative context drastically and maintain documentary control. Provider caching can make repeated full-context queries temporarily inexpensive and avoid prefix recomputation, but it does not reduce the logical input volume accounted for, depends on an identical prefix, and does not replace evidence and validation contracts.
