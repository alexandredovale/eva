CREATE TABLE IF NOT EXISTS processing_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('summaries', 'embeddings', 'cnodes') NOT NULL,
    version_key VARCHAR(255) NOT NULL,
    job_key CHAR(64) NOT NULL,
    status ENUM('queued', 'running', 'completed', 'failed') NOT NULL DEFAULT 'queued',
    run_count INT UNSIGNED NOT NULL DEFAULT 0,
    failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_failures SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    locked_by VARCHAR(120) NULL,
    last_error VARCHAR(500) NULL,
    result JSON NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_processing_jobs_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    UNIQUE KEY uq_processing_jobs_public_id (public_id),
    UNIQUE KEY uq_processing_jobs_key (job_key),
    KEY idx_processing_jobs_claim (status, available_at, id),
    KEY idx_processing_jobs_document (document_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id VARCHAR(64) NULL,
    actor_fingerprint CHAR(64) NULL,
    network_fingerprint CHAR(64) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_events_created (created_at, id),
    KEY idx_audit_events_entity (entity_type, entity_id),
    KEY idx_audit_events_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
