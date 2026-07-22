ALTER TABLE document_nodes
    ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER source_hash;

