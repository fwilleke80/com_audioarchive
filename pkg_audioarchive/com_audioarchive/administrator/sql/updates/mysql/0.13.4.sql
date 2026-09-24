CREATE TABLE IF NOT EXISTS `#__audioarchive_recordings` (
 `user_id` int unsigned NOT NULL,
 `payload` mediumtext NOT NULL,
 PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
