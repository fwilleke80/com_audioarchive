CREATE TABLE IF NOT EXISTS `#__audioarchive_ratings` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `clip_id` int unsigned NOT NULL,
    `voter_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `vote` tinyint NOT NULL,
    `created` datetime NOT NULL,
    `modified` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_audioarchive_rating_clip_voter` (`clip_id`, `voter_hash`),
    KEY `idx_audioarchive_rating_clip_vote` (`clip_id`, `vote`),
    KEY `idx_audioarchive_rating_modified` (`modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
