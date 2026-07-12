ALTER TABLE `#__audioarchive_files`
    ADD UNIQUE KEY `idx_audioarchive_file_clip_role` (`clip_id`, `file_role`);
