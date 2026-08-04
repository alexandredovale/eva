# Go-live readiness validation

## Decision record

**Initial result on July 22, 2026: NO-GO.**

**EVA query revalidation on July 22, 2026: APPROVED after corrections.**

**Pre-deployment acceptance on July 22, 2026: APPROVED FOR CONTROLLED UPLOAD.**

The initial assessment found two defects in EVA's query flow: incomplete detection of relational questions in Portuguese and intermittent truncation of relational responses at the output limit. Both were corrected, and the live relational matrix then passed without failures. The team subsequently completed the local visual smoke test, infrastructure acceptance, real backup/restore test, and safe diagnostic improvements. Acceptance of the online environment still depends on post-upload verification described in [Pre-deployment acceptance](15_PRE_DEPLOYMENT_ACCEPTANCE.md).

This record covers the application, database, permissions, authentication, real calls to the configured provider, browser behavior, and local infrastructure. Local concurrency figures do not predict hosting-plan capacity after publication.

> Update of August 2, 2026: the live matrix and historical counts below predate the Context Intelligence Engine. At that time, CIE had only its database-independent mathematical test, and conceptual and relational queries still required revalidation with `QUERY_CANDIDATE_LIMIT=30`. This passage records the condition on that date and does not replace later operational adjustments.

> Directed validation on August 2, 2026: one real conceptual query over *The Spirits' Book*, using Top-30, three final core evidence records, and seven convergence records, completed without truncation in 24.32 seconds. All ten records were incorporated into analytical prose, and none appeared only in a citation inventory. This approves the deterministic contract and closed validation for the reference case, but does not replace the pending comparative matrix over a representative corpus.

> Operational adjustment on August 3, 2026: the current default was reduced to `QUERY_CANDIDATE_LIMIT=20` after a directed query over *The Gospel According to Spiritism* produced a more concentrated context and a better-focused documentary answer. This case informs the new default but remains an operational observation; the representative comparative matrix is still pending.

> Citation-contract revalidation on August 4, 2026: the former requirement to incorporate every recovered source was replaced by the visible-citation contract. In the real-provider query “O que é ectoplasma?” over an authorized seven-work project, ten evidence records were recovered. Before the correction, the third generation ended normally (`finish_reason=stop`) with 659 of 1800 tokens, cited nine evidence records, and omitted one; the entire answer was nevertheless rejected. After the correction, the query succeeded, cited four evidence records, and discarded the six uncited candidates. The validated generation ended with `finish_reason=stop` and 354 tokens. All 24 non-provider suites also passed. This case confirms that the failure was not caused by the output ceiling and validates the new discard behavior, but it does not replace the representative comparative matrix.

## Environment and scope

The validation used the active `Pentateuco Espírita` project and two ready works:

- `O Livro dos Médiuns`;
- `O Livro dos Espíritos`.

An existing superadmin and a temporary normal user created only for the test were used. The temporary user and all assignments were removed during cleanup, including on failure. Original user and permission counts were restored.

Effective EVA query configuration during the test:

```env
QUERY_MAX_EVIDENCE=10
QUERY_MAX_INTERACTIONS=20
AI_QUERY_MAX_OUTPUT_TOKENS=1800
```

Credentials were checked only for presence. No key or secret was written to reports.

## Executed functional matrix

Each positive combination received three synthetic questions: broad, conceptual, and relational. Calls alternated between the superadmin and the registered user.

| Selected scope | User permission | Superadmin | User | Access result |
|---|---|---:|---:|---|
| Complete project | Complete project | 3 questions | 3 questions | Correctly allowed |
| Work belonging to the project | Complete project | 3 questions | 3 questions | Correctly allowed |
| One individual work | That work only | 3 questions | 3 questions | Correctly allowed |
| Two individual works | Both works, not the project | 3 questions | 3 questions | Correctly allowed |

Nine negative attempts were also run, with three questions in each condition:

- a project without project permission;
- another work not granted to the user;
- the complete project when the user held only individual grants to its works.

All nine attempts were refused with HTTP 403. No evidence leaked between projects or works.

## Confirmed results

### Permissions and granularity

- The superadmin accessed every project and work without explicit assignment.
- A project grant allowed queries over the complete project and each child work.
- An individual-work grant exposed neither another work nor the complete project.
- Two individual grants allowed a combined query over those works without becoming a project grant.
- Every authorized answer used only documents in the requested scope.

### Answers, evidence, and citations

- All 24 calls in the main matrix returned HTTP 200.
- Every authorized response contained an answer and evidence.
- No response exceeded `QUERY_MAX_EVIDENCE=10`.
- No response exceeded `QUERY_MAX_INTERACTIONS=20`.
- Citations and evidence identifiers remained bound to recovered context.
- Broad and conceptual questions worked in all four scenarios for both user profiles.

### Session and revocation

- Logout invalidated the session in use.
- The previous session was not accepted after a new login.
- A disabled user could not authenticate.
- Revocation and reactivation were reflected in authentication.
- Temporary test state was removed at completion.

### Automated suite without paid calls

At the original final regression, 15 suites ran without paid calls and passed 883 assertions. Coverage included access control, AI adapters, cognitive build, deletion, document ingestion, the evidence schema, parsers, the product layer, queries, the real-document fixture, upload security, white-label architecture, complete `.env` inventory, safe log diagnostics, and backup/restore infrastructure.

### Context Intelligence Engine update — August 2, 2026

The project added `tests/ContextIntelligenceEngineTest.php` and `tests/ContextIntelligenceIntegrationTest.php`. The first is database-independent and covers population formulas, core, convergence fallback, zero mean, homogeneous and empty distributions, boundary tolerance, and auditable output. The second creates a controlled five-vector document inside a transaction, verifies final context, and rolls the data back. `tests/QueryTest.php` also verifies CIE integration with semantic retrieval and primary-source resolution.

The empty schema was imported locally because it contains only idempotent creation statements. The mathematical test passed 13 assertions, controlled integration passed 10, and `tests/QueryTest.php` passed 49. No external or paid call was made. Representative live quality and stability revalidation remains pending before publication.

## Initial blockers and corrections

### 1. Incomplete relational-question detection

The eight relational questions in the main matrix used the natural Portuguese construction `se relacionam`. All returned HTTP 200 documentary answers but were classified only as `conceptual`; consequently, they did not activate `simetry`, `assimetry`, or a relational limitation.

The detector recognized formulations such as `Qual é a relação entre ...`, but its word-boundary expression after `relaciona` did not cover the `relacionam` ending. Observed result: **8 failures in 8 attempts using `se relacionam`**.

Related implementation: `app/Application/Query/InputTypeDetector.php`.

**Correction:** the detector now normalizes diacritics and recognizes complete morphological families plus neutral formal operators. Classification remains local and deterministic, with no extra AI call. Regression cases cover `se relacionam`, `relacionados`, `interact`, and `↔`, plus a negative case ensuring that two concepts alone do not imply relational intent.

### 2. Intermittent provider JSON truncation

A follow-up used formulations already recognized by the detector:

- eight authorized relational queries;
- four HTTP 200 responses with evidence, validated interactions, and a limitation;
- four HTTP 503 responses.

The same scope and query were then repeated five times: three valid responses and two incomplete-JSON failures. Safe envelope inspection confirmed `finish_reason=length`. With `AI_QUERY_MAX_OUTPUT_TOKENS=900`, output ended before the JSON contract closed.

The diagnosed version did not handle `finish_reason=length` before decoding and did not perform a bounded retry. Observed result in the controlled repetition: **2 failures in 5 identical calls**.

Related implementation: `.env`, `config/ai.php`, and `app/Infrastructure/Ai/QueryAnswerProvider.php`.

**Correction:** the ceiling was calibrated to `1800`; the prompt gained an explicit compactness instruction; and `interaction_limit` can no longer be read as a fill target. The provider checks `finish_reason=length` before decoding, discards the partial output, and permits at most one complete compact regeneration at the same ceiling. A second truncation ends with an explicit error, without JSON repair or unlimited retries.

### 3. Insufficient operational diagnostics

The API correctly returned a generic client message, but server logs originally stored only the exception class. Operators could confuse truncation with provider downtime.

**Correction:** every request now receives an `X-Request-Id`, and failures are classified into safe operational categories. Exception messages, credentials, prompts, inputs, documents, and raw provider responses are omitted or replaced by `[REDACTED]`. The dedicated security suite passed 13 assertions.

## Revalidation after correction

The relational matrix was repeated with the natural formulations that had failed, alternating between superadmin and normal user:

- 8 of 8 authorized queries passed;
- 3 of 3 forbidden attempts returned HTTP 403;
- 321 assertions passed;
- zero truncations;
- zero HTTP 503 responses;
- zero cross-work or cross-project leakage;
- limits of 10 evidence records and 20 interactions respected;
- temporary user and permissions removed afterward.

The raw execution file was named `go-live-relational-after-fix.json`; it is an operational artifact and is not part of the public repository.

## Environment observations

During CLI execution, PHP initially reported that `openssl` was already loaded. A duplicate declaration in the local XAMPP `php.ini` was removed; the module remained active, the warning disappeared, and `httpd -t` passed.

The first raw report was created before correcting a bookkeeping error in the tester's visual `passed` field. Incorrect array union marked successful lines as false. HTTP statuses, content, evidence, assertions, and the failure list were unchanged; the tester was corrected before validation closed.

## GO criteria status

1. Expand relational detection for common Portuguese inflections and add linguistic regression tests — **completed**.
2. Handle `finish_reason=length` explicitly — **completed**.
3. Calibrate relational output and implement one safe bounded retry — **completed**.
4. Record a safe cognitive-failure category without credentials, full prompts, or sensitive content — **completed**.
5. Rerun the live relational matrix with complete authorized success, complete forbidden denial, and no scope leakage — **completed for the post-fix matrix**.
6. Run browser smoke tests for checkbox trees, multi-selection, logout state, and responsive layouts — **completed locally over HTTPS**.
7. Run pre-deployment infrastructure acceptance — **completed; online verification remains mandatory after upload**.

The pre-deployment criteria were met. The system was approved for a controlled upload; definitive online acceptance requires zero failures from:

```powershell
php bin\verify-deployment.php https://eva.oceanno.com.br
```

## Reproduction

The reproducible test is `tests/GoLiveReadinessTest.php`. Real calls require both explicit confirmations:

```powershell
$env:AI_LIVE_ENABLED='true'
php tests\GoLiveReadinessTest.php --live --report=go-live-readiness.json
```

Without both `--live` and `AI_LIVE_ENABLED=true`, the file makes no paid provider calls. Use only an isolated test account and corpus, and never commit the generated report if it contains operational metadata.
