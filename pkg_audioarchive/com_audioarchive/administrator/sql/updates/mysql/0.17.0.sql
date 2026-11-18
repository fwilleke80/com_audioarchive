CREATE TABLE IF NOT EXISTS `#__audioarchive_recording_shares` (
 `user_id` int unsigned NOT NULL,
 `recording_id` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `token` char(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY (`user_id`, `recording_id`), UNIQUE KEY `idx_recording_share_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
