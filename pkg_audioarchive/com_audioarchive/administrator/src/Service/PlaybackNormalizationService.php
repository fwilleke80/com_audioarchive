<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Resolve sample-peak gain from current waveform artifacts without analysing media at playback. */
final class PlaybackNormalizationService
{
	/** @brief Apply tri-state policy, finite-value validation and a silence/near-zero safety bound. */
	public static function gain(bool $global, string $override, mixed $peak, mixed $target = -1): float
	{
		$enabled = $override === 'enabled' || ($override !== 'disabled' && $global);
		if (!$enabled || !is_numeric($peak) || !is_numeric($target))
		{
			return 1.0;
		}
		$peak = (float) $peak;
		$target = (float) $target;
		if (!is_finite($peak) || !is_finite($target) || $target > 0 || $target < -60 || $peak < -120 || $peak > 60 || $target - $peak > 60)
		{
			return 1.0;
		}
		return pow(10.0, ($target - $peak) / 20.0);
	}

	/** @brief Read gain for an already-authorized clip; missing/old/stale analysis always means unity. */
	public static function forClip(int $clipId): float
	{
		if ($clipId <= 0)
		{
			return 1.0;
		}
		try
		{
			$params = ComponentHelper::getParams('com_audioarchive');
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$row = $db->setQuery('SELECT c.normalization_mode, c.waveform_status, a.storage_key FROM ' . $db->quoteName('#__audioarchive_clips') . ' c LEFT JOIN ' . $db->quoteName('#__audioarchive_analyses') . " a ON a.clip_id=c.id AND a.analysis_type='waveform' AND a.status='available' AND a.is_available=1 WHERE c.id=" . $clipId)->loadObject();
			if (!$row || $row->waveform_status !== 'available' || empty($row->storage_key) || ($row->normalization_mode !== 'enabled' && ($row->normalization_mode === 'disabled' || !$params->get('normalize_playback', 0))))
			{
				return 1.0;
			}
			$path = (new ManagedStorageService($params))->resolveManagedPath('waveform', $row->storage_key);
			if (!is_file($path) || is_link($path) || filesize($path) > 1048576)
			{
				return 1.0;
			}
			$data = json_decode((string) file_get_contents($path), true);
			return self::gain((bool) $params->get('normalize_playback', 0), $row->normalization_mode, $data['global_peak_dbfs'] ?? null, $params->get('normalization_target_dbfs', -1));
		}
		catch (\Throwable)
		{
			return 1.0;
		}
	}
}
