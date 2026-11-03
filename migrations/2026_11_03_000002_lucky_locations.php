<?php
// Location data is kept separately from the prize result. One first reveal per code.
return ['up' => [
    "CREATE TABLE lucky_locations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code_id BIGINT UNSIGNED NOT NULL UNIQUE, latitude DECIMAL(10,7) NOT NULL, longitude DECIMAL(11,7) NOT NULL, accuracy_m DECIMAL(12,2) NOT NULL, consent_version VARCHAR(30) NOT NULL, recorded_at DATETIME NOT NULL, INDEX lucky_location_date (recorded_at,id), CONSTRAINT lucky_location_code FOREIGN KEY (code_id) REFERENCES lucky_codes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
], 'down' => []];
