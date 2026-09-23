CREATE TABLE IF NOT EXISTS `#__audioarchive_collections` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `uuid` varchar(80) NOT NULL,
 `user_id` int unsigned NOT NULL,
 `kind` varchar(16) NOT NULL,
 `title` varchar(120) NOT NULL,
 `pad_count` int unsigned NOT NULL DEFAULT 0,
 `share_token` char(48) DEFAULT NULL,
 `created` datetime NOT NULL,
 `modified` datetime NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `idx_uuid` (`uuid`),
 UNIQUE KEY `idx_share` (`share_token`), KEY `idx_owner_kind` (`user_id`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__audioarchive_collection_items` (
 `collection_id` int unsigned NOT NULL,
 `position` int unsigned NOT NULL,
 `clip_id` int unsigned NOT NULL,
 PRIMARY KEY (`collection_id`,`position`), KEY `idx_clip` (`clip_id`),
 FOREIGN KEY (`collection_id`) REFERENCES `#__audioarchive_collections` (`id`) ON DELETE CASCADE,
 FOREIGN KEY (`clip_id`) REFERENCES `#__audioarchive_clips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__audioarchive_collection_state` (
 `user_id` int unsigned NOT NULL,
 `revision` int unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__audioarchive_clips` ADD COLUMN `normalization_mode` varchar(16) NOT NULL DEFAULT 'inherit';
