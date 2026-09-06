-- EVA 6.0.0 — remove definitivamente resumos derivados do fluxo operacional.
-- Faça um backup completo do banco antes de executar este arquivo manualmente.

-- Interrompa workers antes da execução. Jobs de síntese deixam de ser válidos na 6.0.0.
DELETE FROM processing_jobs
WHERE stage = 'summaries';

-- Remove vínculos e embeddings antes das evidências derivadas correspondentes.
DELETE FROM evidence_derivations;

DELETE ee
FROM evidence_embeddings ee
JOIN evidences e ON e.id = ee.evidence_id
WHERE e.evidence_class = 'derived'
   OR e.evidence_type = 'node_summary';

DELETE FROM evidences
WHERE evidence_class = 'derived'
   OR evidence_type = 'node_summary';

-- A 6.0.0 mantém somente embeddings de evidências primárias.
DROP TABLE IF EXISTS evidence_derivations;

ALTER TABLE processing_jobs
    MODIFY COLUMN stage ENUM('embeddings') NOT NULL;

ALTER TABLE evidences
    DROP INDEX uq_evidence_generation,
    DROP COLUMN summary,
    DROP COLUMN generation_model,
    DROP COLUMN generation_input_hash,
    MODIFY COLUMN evidence_class ENUM('primary') NOT NULL;
