<?php
namespace Punga\Component\Audioarchive\Site\Service;
\defined('_JEXEC') or die;

/** @brief Summarize owner-facing processing state without hiding individual analysis details. */
final class ProcessingStatusService
{
	/** @brief Prefer failures and active work over optional or completed analyses. */
	public static function summary(object $clip): string
	{
		$states = [];
		foreach (['metadata_status', 'preview_status', 'waveform_status', 'spectrogram_status', 'frequency_profile_status'] as $field)
		{
			$states[] = (string) ($clip->$field ?? 'missing');
		}
		foreach (['failed', 'running', 'pending', 'stale', 'missing'] as $state)
		{
			if (in_array($state, $states, true))
			{
				return $state;
			}
		}
		return 'available';
	}
}
