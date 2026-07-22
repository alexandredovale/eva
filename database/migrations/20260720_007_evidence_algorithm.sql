-- O Evidence Algorithm passa a manter somente evidências, derivações e embeddings.
-- Interações simetry/assimetry são resultados transitórios validados durante a consulta.

DELETE FROM processing_jobs WHERE stage = 'cnodes';

ALTER TABLE processing_jobs
    MODIFY COLUMN stage ENUM('summaries', 'embeddings') NOT NULL;

DROP TABLE IF EXISTS interaction_analyses;
DROP TABLE IF EXISTS cnode_embeddings;
DROP TABLE IF EXISTS cnode_evidences;
DROP TABLE IF EXISTS cnodes;

ALTER TABLE evidences
    MODIFY COLUMN evidence_class ENUM('primary', 'derived') NOT NULL;
