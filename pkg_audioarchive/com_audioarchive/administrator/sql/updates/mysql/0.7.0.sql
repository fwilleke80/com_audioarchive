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

INSERT IGNORE INTO `#__audioarchive_analyses`
    (`clip_id`, `analysis_type`, `storage_key`, `data_format`, `status`, `parameters`, `generated_at`, `generator`, `generator_version`, `file_size`, `is_available`, `processing_error`)
SELECT
    `w`.`clip_id`,
    'waveform',
    `w`.`storage_key`,
    `w`.`data_format`,
    CASE
        WHEN `c`.`waveform_status` IN ('available', 'pending', 'failed', 'stale', 'missing')
            THEN `c`.`waveform_status`
        WHEN `w`.`is_available` = 1
            THEN 'available'
        ELSE 'failed'
    END,
    JSON_OBJECT('point_count', `w`.`point_count`, 'channel_mode', `w`.`channel_mode`),
    `w`.`generated_at`,
    `w`.`generator`,
    `w`.`generator_version`,
    0,
    CASE
        WHEN `c`.`waveform_status` = 'available' AND `w`.`is_available` = 1
            THEN 1
        ELSE 0
    END,
    `w`.`processing_error`
FROM `#__audioarchive_waveforms` AS `w`
LEFT JOIN `#__audioarchive_clips` AS `c` ON `c`.`id` = `w`.`clip_id`;

ALTER TABLE `#__audioarchive_jobs`
    MODIFY `job_type` varchar(64) NOT NULL;
