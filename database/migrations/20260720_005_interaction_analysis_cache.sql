ALTER TABLE cnode_evidences
    ADD COLUMN IF NOT EXISTS excerpt LONGTEXT NULL AFTER excerpt_reference;

CREATE TABLE IF NOT EXISTS interaction_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    left_evidence_id BIGINT UNSIGNED NOT NULL,
    right_evidence_id BIGINT UNSIGNED NOT NULL,
    model VARCHAR(120) NOT NULL,
    input_hash CHAR(64) NOT NULL,
    interaction_exists TINYINT(1) NOT NULL,
    interaction_type ENUM('simetry', 'assimetry') NULL,
    summary LONGTEXT NULL,
    origin_evidence_id BIGINT UNSIGNED NULL,
    destination_evidence_id BIGINT UNSIGNED NULL,
    left_excerpt LONGTEXT NULL,
    right_excerpt LONGTEXT NULL,
    cnode_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interaction_analysis_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_interaction_analysis_left FOREIGN KEY (left_evidence_id) REFERENCES evidences (id) ON DELETE CASCADE,
    CONSTRAINT fk_interaction_analysis_right FOREIGN KEY (right_evidence_id) REFERENCES evidences (id) ON DELETE CASCADE,
    CONSTRAINT fk_interaction_analysis_origin FOREIGN KEY (origin_evidence_id) REFERENCES evidences (id) ON DELETE SET NULL,
    CONSTRAINT fk_interaction_analysis_destination FOREIGN KEY (destination_evidence_id) REFERENCES evidences (id) ON DELETE SET NULL,
    CONSTRAINT fk_interaction_analysis_cnode FOREIGN KEY (cnode_id) REFERENCES cnodes (id) ON DELETE SET NULL,
    UNIQUE KEY uq_interaction_analysis_version
        (left_evidence_id, right_evidence_id, model, input_hash),
    KEY idx_interaction_analysis_document (document_id, interaction_exists)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;