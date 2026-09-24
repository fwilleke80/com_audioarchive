CREATE TABLE IF NOT EXISTS `#__audioarchive_clips` (
 `visibility_mode` varchar(16) NOT NULL DEFAULT 'normal',
 KEY `idx_owner_visibility` (`created_by`, `visibility_mode`),
 KEY `idx_visibility` (`visibility_mode`),
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `asset_id` int unsigned NOT NULL DEFAULT 0,
    `uuid` char(36) NOT NULL,
    `catid` int unsigned NOT NULL DEFAULT 0,
    `title` varchar(255) NOT NULL DEFAULT '',
    `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
    `description` mediumtext NOT NULL,
    `original_filename` varchar(255) NOT NULL DEFAULT '',
    `state` tinyint NOT NULL DEFAULT 0,
    `access` int unsigned NOT NULL DEFAULT 1,
    `language` char(7) NOT NULL DEFAULT '*',
    `ordering` int NOT NULL DEFAULT 0,
    `duration_ms` bigint unsigned NOT NULL DEFAULT 0,
    `recorded_at` datetime DEFAULT NULL,
    `recorded_date_source` varchar(16) NOT NULL DEFAULT 'manual',
    `uploaded_at` datetime NOT NULL,
    `publish_up` datetime DEFAULT NULL,
    `publish_down` datetime DEFAULT NULL,
    `created` datetime NOT NULL,
    `created_by` int unsigned NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int unsigned NOT NULL DEFAULT 0,
    `checked_out` int unsigned DEFAULT NULL,
    `checked_out_time` datetime DEFAULT NULL,
    `play_count` bigint unsigned NOT NULL DEFAULT 0,
    `download_count` bigint unsigned NOT NULL DEFAULT 0,
    `metadata_status` varchar(24) NOT NULL DEFAULT 'missing',
    `preview_status` varchar(24) NOT NULL DEFAULT 'not_required',
    `normalization_mode` varchar(16) NOT NULL DEFAULT 'inherit',
    `waveform_status` varchar(24) NOT NULL DEFAULT 'missing',
    `spectrogram_status` varchar(24) NOT NULL DEFAULT 'missing',
    `frequency_profile_status` varchar(24) NOT NULL DEFAULT 'missing',
    `technical_metadata` mediumtext NOT NULL,
    `params` mediumtext NOT NULL,
    `version` int unsigned NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_audioarchive_uuid` (`uuid`),
    UNIQUE KEY `idx_audioarchive_alias` (`alias`),
    KEY `idx_audioarchive_state` (`state`),
    KEY `idx_audioarchive_access` (`access`),
    KEY `idx_audioarchive_catid` (`catid`),
    KEY `idx_audioarchive_duration` (`duration_ms`),
    KEY `idx_audioarchive_recorded` (`recorded_at`),
    KEY `idx_audioarchive_uploaded` (`uploaded_at`),
    KEY `idx_audioarchive_publish_up` (`publish_up`),
    KEY `idx_audioarchive_publish_down` (`publish_down`),
    KEY `idx_audioarchive_title` (`title`),
    KEY `idx_audioarchive_created_by` (`created_by`),
    KEY `idx_audioarchive_state_access_uploaded` (`state`, `access`, `uploaded_at`),
    KEY `idx_audioarchive_state_cat_uploaded` (`state`, `catid`, `uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__audioarchive_files` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `clip_id` int unsigned NOT NULL,
    `file_role` varchar(16) NOT NULL,
    `storage_key` varchar(512) NOT NULL,
    `file_extension` varchar(16) NOT NULL DEFAULT '',
    `mime_type` varchar(127) NOT NULL DEFAULT '',
    `container_format` varchar(64) NOT NULL DEFAULT '',
    `audio_codec` varchar(64) NOT NULL DEFAULT '',
    `file_size` bigint unsigned NOT NULL DEFAULT 0,
    `duration_ms` bigint unsigned NOT NULL DEFAULT 0,
    `checksum_sha256` char(64) NOT NULL DEFAULT '',
    `created` datetime NOT NULL,
    `created_by` int unsigned NOT NULL DEFAULT 0,
    `is_available` tinyint unsigned NOT NULL DEFAULT 1,
    `processing_error` text NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_audioarchive_file_clip` (`clip_id`),
    KEY `idx_audioarchive_file_role` (`file_role`),
    KEY `idx_audioarchive_file_checksum` (`checksum_sha256`),
    UNIQUE KEY `idx_audioarchive_file_clip_role` (`clip_id`, `file_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__audioarchive_waveforms` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `clip_id` int unsigned NOT NULL,
    `storage_key` varchar(512) NOT NULL,
    `data_format` varchar(32) NOT NULL DEFAULT 'json-peaks-v1',
    `point_count` int unsigned NOT NULL DEFAULT 0,
    `channel_mode` varchar(32) NOT NULL DEFAULT 'mono',
    `generated_at` datetime DEFAULT NULL,
    `generator` varchar(64) NOT NULL DEFAULT '',
    `generator_version` varchar(64) NOT NULL DEFAULT '',
    `is_available` tinyint unsigned NOT NULL DEFAULT 0,
    `processing_error` text NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_audioarchive_waveform_clip` (`clip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__audioarchive_analyses` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `clip_id` int unsigned NOT NULL,
    `analysis_type` varchar(32) NOT NULL,
    `storage_key` varchar(512) NOT NULL DEFAULT '',
    `data_format` varchar(64) NOT NULL DEFAULT '',
    `status` varchar(16) NOT NULL DEFAULT 'missing',
    `parameters` mediumtext NOT NULL,
    `generated_at` datetime DEFAULT NULL,
    `generator` varchar(64) NOT NULL DEFAULT '',
    `generator_version` varchar(64) NOT NULL DEFAULT '',
    `file_size` bigint unsigned NOT NULL DEFAULT 0,
    `is_available` tinyint unsigned NOT NULL DEFAULT 0,
    `processing_error` text NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_audioarchive_analysis_clip_type` (`clip_id`, `analysis_type`),
    KEY `idx_audioarchive_analysis_type_status` (`analysis_type`, `status`),
    KEY `idx_audioarchive_analysis_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__audioarchive_jobs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `clip_id` int unsigned DEFAULT NULL,
    `job_type` varchar(64) NOT NULL,
    `state` varchar(16) NOT NULL DEFAULT 'pending',
    `priority` int NOT NULL DEFAULT 0,
    `attempts` int unsigned NOT NULL DEFAULT 0,
    `maximum_attempts` int unsigned NOT NULL DEFAULT 3,
    `payload` mediumtext NOT NULL,
    `last_error` text NOT NULL,
    `created` datetime NOT NULL,
    `started` datetime DEFAULT NULL,
    `finished` datetime DEFAULT NULL,
    `locked_by` varchar(128) NOT NULL DEFAULT '',
    `locked_until` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_audioarchive_job_clip` (`clip_id`),
    KEY `idx_audioarchive_job_state_priority` (`state`, `priority`, `created`),
    KEY `idx_audioarchive_job_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


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

CREATE TABLE IF NOT EXISTS `#__audioarchive_recordings` (
 `user_id` int unsigned NOT NULL,
 `payload` mediumtext NOT NULL,
 PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
