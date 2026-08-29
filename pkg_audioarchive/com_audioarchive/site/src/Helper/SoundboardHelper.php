<?php

namespace Punga\Component\Audioarchive\Site\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Resolve Sound Board settings that must be consistent across frontend views.
 */
final class SoundboardHelper
{
	/**
	 * @brief Return the effective Sound Board pad count.
	 *
	 * The Sound Board menu item may override the component-wide pad count. Archive,
	 * clip-detail, and playlist pages must therefore not use their own active menu
	 * item when deciding whether the browser-local Sound Board is full.
	 *
	 * @param Registry|null $fallbackParams Optional already-resolved parameters used as fallback.
	 *
	 * @return int Effective pad count in the supported range 4..36.
	 */
	public static function getPadCount(?Registry $fallbackParams = null): int
	{
		$params = clone ComponentHelper::getParams('com_audioarchive');

		if ($fallbackParams !== null && $params->get('soundboard_pad_count', null) === null)
		{
			$params->set('soundboard_pad_count', $fallbackParams->get('soundboard_pad_count', 12));
		}

		$padCount = (int) $params->get('soundboard_pad_count', 12);
		$application = Factory::getApplication();
		$menu = $application->getMenu();
		$languageTag = Factory::getLanguage()->getTag();
		$viewLevels = array_map('intval', (array) $application->getIdentity()->getAuthorisedViewLevels());
		$items = $menu->getItems('component', 'com_audioarchive') ?: [];
		$candidates = [];

		foreach ($items as $item)
		{
			if (($item->query['view'] ?? '') !== 'soundboard')
			{
				continue;
			}

			if (isset($item->access) && !in_array((int) $item->access, $viewLevels, true))
			{
				continue;
			}

			$itemLanguage = (string) ($item->language ?? '*');
			$languagePriority = $itemLanguage === $languageTag ? 0 : ($itemLanguage === '*' ? 1 : 2);
			$candidates[] = [$languagePriority, (int) ($item->id ?? 0), $item];
		}

		if ($candidates !== [])
		{
			usort(
				$candidates,
				static fn(array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]
			);
			$soundboardItem = $candidates[0][2];
			$override = $soundboardItem->getParams()->get('soundboard_pad_count', '');

			if ($override !== '' && $override !== null)
			{
				$padCount = (int) $override;
			}
		}

		return max(4, min(36, $padCount));
	}
}
