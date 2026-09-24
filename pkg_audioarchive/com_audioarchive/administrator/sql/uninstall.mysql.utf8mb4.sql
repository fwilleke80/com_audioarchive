DELETE FROM `#__contentitem_tag_map`
WHERE `type_alias` = 'com_audioarchive.clip';

DELETE FROM `#__ucm_content`
WHERE `core_type_alias` = 'com_audioarchive.clip';

DELETE FROM `#__ucm_base`
WHERE `ucm_type_id` IN (
    SELECT `type_id`
    FROM `#__content_types`
    WHERE `type_alias` = 'com_audioarchive.clip'
);

DELETE FROM `#__history`
WHERE `item_id` LIKE 'com_audioarchive.clip.%';

DELETE FROM `#__content_types`
WHERE `type_alias` = 'com_audioarchive.clip';

DROP TABLE IF EXISTS `#__audioarchive_collection_items`;
DROP TABLE IF EXISTS `#__audioarchive_collections`;
DROP TABLE IF EXISTS `#__audioarchive_collection_state`;

DROP TABLE IF EXISTS `#__audioarchive_ratings`;
DROP TABLE IF EXISTS `#__audioarchive_jobs`;
DROP TABLE IF EXISTS `#__audioarchive_analyses`;
DROP TABLE IF EXISTS `#__audioarchive_waveforms`;
DROP TABLE IF EXISTS `#__audioarchive_files`;
DROP TABLE IF EXISTS `#__audioarchive_clips`;

DROP TABLE IF EXISTS `#__audioarchive_group_quotas`;
DROP TABLE IF EXISTS `#__audioarchive_user_profiles`;


DROP TABLE IF EXISTS `#__audioarchive_recordings`;
