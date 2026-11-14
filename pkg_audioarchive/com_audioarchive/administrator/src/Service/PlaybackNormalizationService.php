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

	/** @var array<int, float> Gains cached only for the current PHP request. */
	private static array $gains = [];

	/** @brief Resolve a page of already-authorized clip gains in one database query.
	 * @param int[] $clipIds Clip identifiers to prime.
	 * @return void
	 */
	public static function prime(array $clipIds): void
	{
		$ids = array_values(array_filter(array_unique(array_map('intval', $clipIds)),
			static fn(int $id): bool => $id > 0 && !array_key_exists($id, self::$gains)));
		if ($ids === [])
		{
			return;
		}
		foreach ($ids as $id)
		{
			self::$gains[$id] = 1.0;
		}
		try
		{
			$params = ComponentHelper::getParams('com_audioarchive');
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$rows = $db->setQuery('SELECT c.id, c.normalization_mode, c.waveform_status, a.storage_key FROM '
				. $db->quoteName('#__audioarchive_clips') . ' c LEFT JOIN '
				. $db->quoteName('#__audioarchive_analyses')
				. " a ON a.clip_id=c.id AND a.analysis_type='waveform' AND a.status='available' AND a.is_available=1 WHERE c.id IN ("
				. implode(',', $ids) . ')')->loadObjectList();
			$storage = new ManagedStorageService($params);
			foreach ($rows as $row)
			{
				if ($row->waveform_status !== 'available' || empty($row->storage_key)
					|| ($row->normalization_mode !== 'enabled' && ($row->normalization_mode === 'disabled' || !$params->get('normalize_playback', 0))))
				{
					continue;
				}
				try
				{
					$path = $storage->resolveManagedPath('waveform', $row->storage_key);
					if (!is_file($path) || is_link($path) || filesize($path) > 1048576)
					{
						continue;
					}
					$data = json_decode((string) file_get_contents($path), true);
					self::$gains[(int) $row->id] = self::gain((bool) $params->get('normalize_playback', 0),
						$row->normalization_mode, $data['global_peak_dbfs'] ?? null, $params->get('normalization_target_dbfs', -1));
				}
				catch (\Throwable)
				{
					// @brief One invalid artifact must not suppress other clips' gains.
				}
			}
		}
		catch (\Throwable)
		{
			// @brief Preserve the existing unity fallback when metadata is unavailable.
		}
	}

	/** @brief Return the request-cached gain, priming standalone players on demand. */
	public static function forClip(int $clipId): float
	{
		self::prime([$clipId]);
		return self::$gains[$clipId] ?? 1.0;
	}
}
