ALTER TABLE `#__audioarchive_clips`
    MODIFY `checked_out` int unsigned DEFAULT NULL,
    MODIFY `checked_out_time` datetime DEFAULT NULL;
