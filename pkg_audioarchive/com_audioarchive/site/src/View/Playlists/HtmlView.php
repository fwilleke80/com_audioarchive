<?php

namespace Punga\Component\Audioarchive\Site\View\Playlists;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;

\defined('_JEXEC') or die;

/**
 * @brief Public browser-local playlist manager and player.
 */
class HtmlView extends BaseHtmlView
{
	/** @var Registry */
	public Registry $params;

	/** @var string */
	public string $pageHeading = '';

	/** @var string */
	public string $returnTitle = '';

	/** @var string */
	public string $itemsUrl = '';

	/** @var string */
	public string $interactionUrl = '';

	/** @var string */
	public string $interactionToken = '';

	/** @var string */
	public string $canonicalUrl = '';

	/** @var string */
	public string $playCountUrl = '';

	/** @var string */
	public string $playCountToken = '';

	/** @var int */
	public int $soundboardPadCount = 12;

	/** @var bool */
	public bool $soundboardEnabled = true;

	/**
	 * @brief Display the playlist page.
	 *
	 * @param string|null $tpl Template name.
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		$application = Factory::getApplication();
		$this->params = clone ComponentHelper::getParams('com_audioarchive');
		$item = $application->getMenu()->getActive();

		if ((int) $this->params->get('enable_playlists', 1) !== 1)
		{
			throw new \Exception(Text::_('COM_AUDIOARCHIVE_PLAYLISTS_DISABLED'), 404);
		}

		if ($item !== null)
		{
			foreach ($item->getParams()->toArray() as $key => $value)
			{
				if ($value !== '' && $value !== null)
				{
					$this->params->set($key, $value);
				}
			}
		}

		$itemId = (int) ($item?->id ?? $application->getInput()->getInt('Itemid', 0));
		$this->pageHeading = (string) $this->params->get(
			'page_heading',
			$item?->title ?? Text::_('COM_AUDIOARCHIVE_PLAYLISTS_TITLE')
		);
		$this->returnTitle = trim((string) ($item?->title ?? ''));

		if ($this->returnTitle === '')
		{
			$this->returnTitle = $this->pageHeading;
		}

		$this->itemsUrl = Route::_(RouteHelper::getPlaylistItemsRoute($itemId));
		$this->interactionUrl = Route::_(RouteHelper::getInteractionRecordRoute($itemId));
		$this->interactionToken = Session::getFormToken();
		$this->canonicalUrl = Route::_(
			RouteHelper::getPlaylistsRoute($itemId),
			false,
			Route::TLS_IGNORE,
			true
		);
		$this->soundboardEnabled = (int) $this->params->get('enable_soundboard', 1) === 1;
		$this->soundboardPadCount = max(4, min(36, (int) $this->params->get('soundboard_pad_count', 12)));

		if ((int) $this->params->get('enable_play_counts', 1) === 1)
		{
			$this->playCountUrl = Route::_(RouteHelper::getPlayCountRoute($itemId));
			$this->playCountToken = Session::getFormToken();
		}

		$document = $this->getDocument();
		$pageTitle = trim((string) $this->params->get('page_title', ''));
		$this->setDocumentTitle($pageTitle !== '' ? $pageTitle : $this->pageHeading);
		$document->addHeadLink($this->canonicalUrl, 'canonical');
		$document->setMetaData('og:type', 'website', 'property');
		$document->setMetaData('og:title', $this->pageHeading, 'property');
		$document->setMetaData('og:url', $this->canonicalUrl, 'property');
		$document->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useStyle('com_audioarchive.player-style')
			->useScript('com_audioarchive.player')
			->useScript('com_audioarchive.social')
			->useScript('com_audioarchive.playlist');

		parent::display($tpl);
	}
}
