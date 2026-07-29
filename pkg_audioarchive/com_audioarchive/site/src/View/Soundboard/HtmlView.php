<?php

namespace Punga\Component\Audioarchive\Site\View\Soundboard;

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
 * @brief Public browser-local sound board.
 */
class HtmlView extends BaseHtmlView
{
	/** @var Registry */
	public Registry $params;

	/** @var string */
	public string $pageHeading = '';

	/** @var string */
	public string $streamTemplate = '';

	/** @var string */
	public string $routesUrl = '';

	/** @var string */
	public string $canonicalUrl = '';

	/** @var int */
	public int $padCount = 12;

	/** @var string */
	public string $returnTitle = '';

	/** @var string */
	public string $playCountUrl = '';

	/** @var string */
	public string $playCountToken = '';

	/** @var bool */
	public bool $polyphonic = true;


	/**
	 * @brief Display the sound board page.
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

		if ((int) $this->params->get('enable_soundboard', 1) !== 1)
		{
			throw new \Exception(Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_DISABLED'), 404);
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

		$this->padCount = max(4, min(36, (int) $this->params->get('soundboard_pad_count', 12)));
		$this->polyphonic = (int) $this->params->get('soundboard_polyphony', 1) === 1;
		$itemId = (int) ($item?->id ?? $application->getInput()->getInt('Itemid', 0));
		$this->pageHeading = (string) $this->params->get(
			'page_heading',
			$item?->title ?? Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_TITLE')
		);
		$this->returnTitle = trim((string) ($item?->title ?? ''));

		if ($this->returnTitle === '')
		{
			$this->returnTitle = $this->pageHeading;
		}
		$this->streamTemplate = Route::_(RouteHelper::getPlaybackRoute(987654321, $itemId));

		if (
			(int) $this->params->get('enable_play_counts', 1) === 1
			&& (int) $this->params->get('soundboard_record_plays', 1) === 1
		)
		{
			$this->playCountUrl = Route::_(RouteHelper::getPlayCountRoute($itemId));
			$this->playCountToken = Session::getFormToken();
		}

		$this->routesUrl = Route::_(RouteHelper::getSoundboardRoutesRoute($itemId));
		$this->canonicalUrl = Route::_(
			RouteHelper::getSoundboardRoute($itemId),
			false,
			Route::TLS_IGNORE,
			true
		);

		$document = $this->getDocument();
		$pageTitle = trim((string) $this->params->get('page_title', ''));
		$this->setDocumentTitle($pageTitle !== '' ? $pageTitle : $this->pageHeading);
		$document->addHeadLink($this->canonicalUrl, 'canonical');
		$document->setMetaData('og:type', 'website', 'property');
		$document->setMetaData('og:title', $this->pageHeading, 'property');
		$document->setMetaData('og:url', $this->canonicalUrl, 'property');
		$document->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useScript('com_audioarchive.social');

		parent::display($tpl);
	}
}
