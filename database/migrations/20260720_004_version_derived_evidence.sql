ALTER TABLE evidences
    ADD COLUMN IF NOT EXISTS generation_model VARCHAR(120) NULL AFTER source_hash,
    ADD COLUMN IF NOT EXISTS generation_input_hash CHAR(64) NULL AFTER generation_model;

ALTER TABLE evidences
    ADD UNIQUE KEY IF NOT EXISTS uq_evidence_generation
        (node_id, evidence_type, generation_model, generation_input_hash);