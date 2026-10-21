<?php
return ['up'=>[
    "ALTER TABLE inquiries ADD internal_notes TEXT NULL, ADD updated_at DATETIME NULL, ADD handled_by BIGINT UNSIGNED NULL, ADD INDEX inbox_status_created (status,created_at)",
    "CREATE TABLE admin_activity (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(190) NOT NULL, action VARCHAR(50) NOT NULL, content_type VARCHAR(50) NOT NULL, record_id BIGINT UNSIGNED NULL, record_label VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX activity_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
], 'down'=>[]];
