<?php

namespace Willeke\Component\Audioarchive\Site\View\Soundboard;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;

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
	public string $canonicalUrl = '';

	/** @var int */
	public int $padCount = 12;

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
		$itemId = (int) ($item?->id ?? $application->getInput()->getInt('Itemid', 0));
		$this->pageHeading = (string) $this->params->get(
			'page_heading',
			$item?->title ?? Text::_('COM_AUDIOARCHIVE_SOUNDBOARD_TITLE')
		);
		$this->streamTemplate = Route::_(RouteHelper::getPlaybackRoute(987654321, $itemId));
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
