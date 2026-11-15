CREATE TABLE IF NOT EXISTS `#__audioarchive_download_tickets` (
    `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `clip_id` int unsigned NOT NULL,
    `user_id` int unsigned NOT NULL DEFAULT 0,
    `context` varchar(8) NOT NULL DEFAULT 'site',
    `expires_at` bigint unsigned NOT NULL,
    `counted` tinyint unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`token_hash`),
    KEY `idx_download_ticket_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
