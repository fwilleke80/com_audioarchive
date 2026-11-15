<?php

namespace Punga\Component\Audioarchive\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Public model for one Punga Audio Archive clip.
 */
class ClipModel extends BaseDatabaseModel
{
	/** @var Registry|null */
	private ?Registry $resolvedParams = null;

	/**
	 * @brief Load the requested public clip.
	 *
	 * @param int|null $id Optional clip identifier.
	 *
	 * @return object|null Public clip or null.
	 */
	public function getItem(?int $id = null): ?object
	{
		$id ??= Factory::getApplication()->getInput()->getInt('id', 0);
		if ($id <= 0 && $this->isSelectionMenu())
		{
			$id = $this->getMenuClipId();
		}
		$service = new PublicMediaService($this->getDatabase(), $this->getResolvedParams(), $this->getCurrentUser());

		return $service->getPublicClip($id, true);
	}

	/** @var int|null Selected clip cached for this request. */
	private ?int $menuClipId = null;

	/**
	 * @brief Identify the clip-selection menu landing page without an explicit clip ID.
	 * @return bool Whether this request opens the selection menu itself.
	 */
	public function isSelectionMenu(): bool
	{
		$app = Factory::getApplication();
		$menu = $app->getMenu()->getActive();
		return $app->getInput()->getInt('id', 0) <= 0
			&& $menu !== null && ($menu->query['option'] ?? '') === 'com_audioarchive'
			&& ($menu->query['view'] ?? '') === 'clipselection';
	}

	/**
	 * @brief Select one accessible clip using the same rules as the module.
	 * @return int Selected clip ID, or zero when no eligible clip exists.
	 */
	private function getMenuClipId(): int
	{
		if ($this->menuClipId !== null)
		{
			return $this->menuClipId;
		}
		$menu = Factory::getApplication()->getMenu()->getActive();
		$params = clone $menu->getParams();
		$mode = (string) $params->get('clip_selection_mode', 'specific');
		$params->set('selection_mode', in_array($mode, ['specific', 'latest', 'random', 'daily'], true) ? $mode : 'specific');
		$params->set('count', 1);
		$alias = trim((string) $params->get('clip_alias', ''));
		if ($mode === 'specific' && $alias !== '')
		{
			$db = $this->getDatabase();
			$query = $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__audioarchive_clips'))
				->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
			$params->set('specific_clip', (int) $db->setQuery($query, 0, 1)->loadResult());
		}
		$items = \Punga\Component\Audioarchive\Site\Service\ClipSelectionService::getItems($params, (object) ['id' => (int) $menu->id]);
		$this->menuClipId = (int) ($items[0]->id ?? 0);
		return $this->menuClipId;
	}

	/**
	 * @brief Load one available derived analysis for the current public clip.
	 *
	 * @param string $analysisType Stable analysis type.
	 * @param int|null $id Optional clip identifier.
	 *
	 * @return object|null Analysis record.
	 */
	public function getAnalysis(string $analysisType, ?int $id = null): ?object
	{
		$id ??= Factory::getApplication()->getInput()->getInt('id', 0);
		$service = new PublicMediaService($this->getDatabase(), $this->getResolvedParams(), $this->getCurrentUser());

		return $service->getPublicAnalysis($id, $analysisType);
	}


	/**
	 * @brief Load clips related to the current public clip.
	 *
	 * @param object $clip Current public clip.
	 *
	 * @return array<int, object> Related clips.
	 */
	public function getRelatedClips(object $clip): array
	{
		$params = $this->getResolvedParams();

		if ((int) $params->get('related_show', 1) !== 1)
		{
			return [];
		}

		$service = new PublicMediaService($this->getDatabase(), $params, $this->getCurrentUser());

		return $service->getRelatedClips(
			$clip,
			(int) $params->get('related_count', 4),
			(int) $params->get('related_min_shared_tags', 1),
			(string) $params->get('related_ranking', 'rare_tags')
		);
	}

	/**
	 * @brief Return global settings with active menu-item overrides.
	 *
	 * @return Registry
	 */
	public function getResolvedParams(): Registry
	{
		if ($this->resolvedParams !== null)
		{
			return $this->resolvedParams;
		}

		$params = clone ComponentHelper::getParams('com_audioarchive');

		if (trim((string) $params->get('detail_presentation', '')) === '')
		{
			$params->set(
				'detail_presentation',
				(int) $params->get('detail_show_waveform', 1) === 1 ? 'featured' : 'default'
			);
		}

		$item = Factory::getApplication()->getMenu()->getActive();

		if ($item)
		{
			$menuParams = $item->getParams()->toArray();
			$legacyWaveformOverride = $menuParams['detail_show_waveform'] ?? null;

			foreach ($menuParams as $key => $value)
			{
				if ($value !== '' && $value !== null)
				{
					$params->set($key, $value);
				}
			}

			if (trim((string) ($menuParams['detail_presentation'] ?? '')) === ''
				&& $legacyWaveformOverride !== ''
				&& $legacyWaveformOverride !== null)
			{
				$params->set(
					'detail_presentation',
					(int) $legacyWaveformOverride === 1 ? 'featured' : 'default'
				);
			}
		}

		$detailPresentation = (string) $params->get('detail_presentation', 'featured');
		$params->set(
			'detail_presentation',
			in_array($detailPresentation, ['minimal', 'compact', 'default', 'featured'], true) ? $detailPresentation : 'featured'
		);

		$this->resolvedParams = $params;

		return $this->resolvedParams;
	}
}
