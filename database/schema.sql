SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    format ENUM('markdown', 'json', 'xml') NOT NULL,
    source_hash CHAR(64) NOT NULL,
    storage_path VARCHAR(500) NULL,
    status ENUM('received', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'received',
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_documents_public_id (public_id),
    KEY idx_documents_hash (source_hash),
    KEY idx_documents_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_nodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    node_type VARCHAR(40) NOT NULL,
    title VARCHAR(500) NOT NULL,
    structural_path VARCHAR(1000) NOT NULL,
    depth SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    content LONGTEXT NULL,
    source_reference VARCHAR(500) NULL,
    source_hash CHAR(64) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_nodes_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_nodes_parent FOREIGN KEY (parent_id) REFERENCES document_nodes (id) ON DELETE CASCADE,
    UNIQUE KEY uq_nodes_path (document_id, structural_path),
    KEY idx_nodes_parent_order (parent_id, sort_order),
    KEY idx_nodes_type (document_id, node_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    node_id BIGINT UNSIGNED NULL,
    evidence_class ENUM('primary', 'derived') NOT NULL,
    evidence_type VARCHAR(60) NOT NULL,
    content LONGTEXT NOT NULL,
    summary LONGTEXT NULL,
    source_hash CHAR(64) NULL,
    generation_model VARCHAR(120) NULL,
    generation_input_hash CHAR(64) NULL,
    status ENUM('pending', 'generated', 'validated', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_evidences_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_evidences_node FOREIGN KEY (node_id) REFERENCES document_nodes (id) ON DELETE SET NULL,
    UNIQUE KEY uq_evidences_public_id (public_id),
    UNIQUE KEY uq_evidence_generation (node_id, evidence_type, generation_model, generation_input_hash),
    KEY idx_evidences_document_class (document_id, evidence_class),
    KEY idx_evidences_node (node_id),
    KEY idx_evidences_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidence_derivations (
    evidence_id BIGINT UNSIGNED NOT NULL,
    source_evidence_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evidence_id, source_evidence_id),
    CONSTRAINT fk_derivations_evidence FOREIGN KEY (evidence_id) REFERENCES evidences (id) ON DELETE CASCADE,
    CONSTRAINT fk_derivations_source FOREIGN KEY (source_evidence_id) REFERENCES evidences (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidence_embeddings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evidence_id BIGINT UNSIGNED NOT NULL,
    model VARCHAR(120) NOT NULL,
    dimensions SMALLINT UNSIGNED NOT NULL,
    vector_data LONGTEXT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_embeddings_evidence FOREIGN KEY (evidence_id) REFERENCES evidences (id) ON DELETE CASCADE,
    UNIQUE KEY uq_embeddings_version (evidence_id, model, content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processing_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(32) NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('summaries', 'embeddings') NOT NULL,
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

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    recovery_code_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_sessions_token (token_hash),
    KEY idx_user_sessions_expiration (expires_at),
    KEY idx_user_sessions_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_projects_name (name),
    KEY idx_projects_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_documents (
    project_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, document_id),
    CONSTRAINT fk_project_documents_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_project_documents_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    KEY idx_project_documents_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_projects (
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, project_id),
    CONSTRAINT fk_user_projects_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_projects_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    KEY idx_user_projects_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_documents (
    user_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, document_id),
    CONSTRAINT fk_user_documents_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_documents_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    KEY idx_user_documents_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
