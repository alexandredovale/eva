SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS module_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    contract_version SMALLINT UNSIGNED NOT NULL,
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_module_events_event_id (event_id),
    KEY idx_module_events_type_id (event_type, id),
    KEY idx_module_events_created (created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
