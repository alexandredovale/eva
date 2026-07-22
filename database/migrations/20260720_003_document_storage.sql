ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS storage_path VARCHAR(500) NULL AFTER source_hash;

