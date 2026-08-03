# Cnode and cognitive interactions

## Definition

A Cnode, or Cognitive Node, is the contextual understanding of an explicit semantic interaction between evidence recovered for one query. It is not an isolated or persistent entity: it exists only while the query organizes documentary interactions.

The Evidence Algorithm persists evidence and its lineage. A Cnode is produced transiently from that core and validated against primary sources.

The [Context Intelligence Engine](09_CONTEXT_INTELLIGENCE_ENGINE.md) operates before this relational understanding. CIE reduces the vector Top-k to a leading statistical core and a complementary convergence range—promoting convergence when no core exists—without producing `simetry`, `assimetry`, or any semantic interpretation. CIE and Cnode are separate transient layers: the first selects context from a distribution; the second describes explicit interactions between sources already selected.

`simetry` and `assimetry` are terms in EVA's internal vocabulary. Source documents do not need to contain these words, and their textual absence does not prevent EVA from answering the substantive question from valid evidence.

## The only interaction types

```text
simetry
assimetry
```

The former taxonomy—`supports`, `complements`, `expands`, `contradicts`, `questions`, `defines`, `depends_on`, `causes`, `precedes`, `exemplifies`, `specializes`, `generalizes`, and `analogous_to`—is not part of the model.

## Role in the system

The `simetry`/`assimetry` distinction preserves the form of a documentary interaction understood during a query. `simetry` records explicit reciprocity; `assimetry` preserves an explicit direction from origin to destination. When neither form can be demonstrated by the evidence, EVA keeps the valid documentary answer and reports the relational limitation.

This distinction allows the system to:

- distinguish reciprocity from direction without altering source content;
- present reciprocal and directed interactions separately in query output;
- trace every interaction to two cited evidence records and their literal excerpts;
- prevent thematic similarity from becoming a proven relationship;
- state when evidence supports an answer but not an interaction classification.

This layer is explanatory and transient. It assigns no score, confidence, weight, intensity, importance, or truth; creates no ranking; changes no embedding; and produces no persistent memory or database relationship.

## Simetry

`simetry` represents an explicitly demonstrated reciprocal interaction. Both evidence records use the `participant` role. Reciprocity does not assert that their content is equal.

```text
participant ↔ participant
```

## Assimetry

`assimetry` represents an interaction whose direction is semantically explicit.

```text
origin → destination
```

Direction does not imply superiority, importance, inferred causation, support, opposition, truth, or intensity.

## Transient contract

A valid interaction contains:

- type `simetry` or `assimetry`;
- a neutral semantic description;
- two participating primary evidence records;
- roles consistent with the interaction type;
- one literal excerpt from each evidence record;
- the source reference for each excerpt.

It contains no permanent identifier, confidence, similarity, weight, intensity, priority, database state, or embedding.

## Validation

An interaction enters the result only when it can be reconstructed from cited evidence. Thematic similarity alone is insufficient. An invalid interaction candidate is discarded and leaves no residual cognitive record. If the documentary answer and its citations remain valid, EVA returns them together with the relational limitation. Invalid documentary citations still invalidate the answer.

### Current limit and future refinement

The application deterministically validates participant identity, roles, declared orientation, and excerpt literalness. Strict proof that those excerpts express reciprocity or direction still depends on provider interpretation. A strong thematic convergence can therefore occasionally be classified as `simetry` without unequivocal documentary reciprocity.

A future calibration should require a separate demonstration of both directions for `simetry` and a stricter origin/destination demonstration for `assimetry`. That refinement must not block a valid documentary answer, alter elected evidence, or authorize persistent relationships.

## Quantity

The number of interactions in a response describes only that query context. EVA has no persistent global Cnode count, and no count may be converted into rank or importance.

See also [Query and conversational continuity](05_QUERY_AND_CHAT.md) and [Mandatory rules](12_MANDATORY_RULES.md).
