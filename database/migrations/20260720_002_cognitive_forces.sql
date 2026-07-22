-- O banco estava sem evidências e Cnodes quando esta migração foi criada.
-- Relações antigas não devem ser convertidas automaticamente para simetry ou assimetry.

ALTER TABLE evidences
    DROP COLUMN IF EXISTS confidence;

ALTER TABLE cnodes
    ADD COLUMN IF NOT EXISTS interaction_type ENUM('simetry', 'assimetry') NOT NULL AFTER document_id;

ALTER TABLE cnodes
    ADD INDEX IF NOT EXISTS idx_cnodes_document_interaction (document_id, interaction_type);

ALTER TABLE cnodes
    DROP INDEX IF EXISTS idx_cnodes_document_relation,
    DROP COLUMN IF EXISTS relation_type,
    DROP COLUMN IF EXISTS direction,
    DROP COLUMN IF EXISTS confidence,
    DROP COLUMN IF EXISTS similarity_score;

ALTER TABLE cnode_evidences
    MODIFY COLUMN role ENUM('participant', 'origin', 'destination', 'reference') NOT NULL;
