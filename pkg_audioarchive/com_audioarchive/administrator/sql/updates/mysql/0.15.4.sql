CREATE TABLE IF NOT EXISTS `#__audioarchive_covers` (
  `entity_type` varchar(8) NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `mime_type` varchar(32) NOT NULL,
  `width` int unsigned NOT NULL,
  `height` int unsigned NOT NULL,
  `file_size` int unsigned NOT NULL,
  `image_data` mediumtext NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
