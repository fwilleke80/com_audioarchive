ALTER TABLE `#__audioarchive_clips` ADD COLUMN `visibility_mode` varchar(16) NOT NULL DEFAULT 'normal', ADD KEY `idx_owner_visibility` (`created_by`, `visibility_mode`), ADD KEY `idx_visibility` (`visibility_mode`);

CREATE TABLE IF NOT EXISTS `#__audioarchive_group_quotas` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `group_id` int unsigned NOT NULL,
 `storage_quota_bytes` bigint DEFAULT NULL,
 `clip_quota` int DEFAULT NULL,
 `created` datetime NOT NULL,
 `modified` datetime DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `idx_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__audioarchive_user_profiles` (
 `user_id` int unsigned NOT NULL,
 `collection_storage_preference` varchar(16) NOT NULL DEFAULT '',
 `default_visibility` varchar(16) NOT NULL DEFAULT '',
 `default_category_id` int unsigned NOT NULL DEFAULT 0,
 `default_access_id` int unsigned NOT NULL DEFAULT 0,
 `browser_import_prompt` tinyint NOT NULL DEFAULT 1,
 `default_soundboard_id` int unsigned NOT NULL DEFAULT 0,
 `storage_quota_override_bytes` bigint DEFAULT NULL,
 `clip_quota_override` int DEFAULT NULL,
 `created` datetime NOT NULL,
 `modified` datetime DEFAULT NULL,
 PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
