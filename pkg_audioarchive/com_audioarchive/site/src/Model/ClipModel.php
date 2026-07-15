<?php

namespace Willeke\Component\Audioarchive\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Public model for one Audio Archive clip.
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
		$service = new PublicMediaService($this->getDatabase(), $this->getResolvedParams(), $this->getCurrentUser());

		return $service->getPublicClip($id, true);
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
