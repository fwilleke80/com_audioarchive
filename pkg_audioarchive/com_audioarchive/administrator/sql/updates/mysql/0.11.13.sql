ALTER TABLE `#__audioarchive_clips`
    ADD COLUMN `frequency_profile_status` varchar(24) NOT NULL DEFAULT 'missing' AFTER `spectrogram_status`;
