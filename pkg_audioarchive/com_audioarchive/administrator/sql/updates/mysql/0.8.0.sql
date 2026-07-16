ALTER TABLE `#__audioarchive_clips`
    ADD COLUMN `spectrogram_status` varchar(24) NOT NULL DEFAULT 'missing' AFTER `waveform_status`;
