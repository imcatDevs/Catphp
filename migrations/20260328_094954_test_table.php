<?php
declare(strict_types=1);

// 마이그레이션: test_table
// 생성일: 2026-03-28 09:49:54

return [
    'up'   => 'CREATE TABLE IF NOT EXISTS test_table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'down' => 'DROP TABLE IF EXISTS test_table',
];
