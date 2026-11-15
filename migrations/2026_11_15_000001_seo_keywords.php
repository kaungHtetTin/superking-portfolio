<?php

// Optional site-wide metadata; preserve existing company settings.
return [
    'up' => ["ALTER TABLE settings ADD meta_keywords VARCHAR(500) NOT NULL DEFAULT ''"],
    'down' => ['ALTER TABLE settings DROP COLUMN meta_keywords'],
];
