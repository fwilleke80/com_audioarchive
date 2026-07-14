<?php

namespace Willeke\Component\Audioarchive\Site\View\Tagdirectory;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\TagdirectoryModel;

\defined('_JEXEC') or die;

/**
 * @brief Public Audio Archive tag-directory view.
 */
class HtmlView extends BaseHtmlView
{
	/** @var object[] */
	public array $items = [];

	/** @var Registry */
	public Registry $params;

	/** @var string */
	public string $pageHeading = '';

	/** @var int */
	public int $archiveItemId = 0;

	/**
	 * @brief Display the public tag directory.
	 *
	 * @param string|null $tpl Template name.
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		/** @var TagdirectoryModel $model */
		$model = $this->getModel();
		$model->setUseExceptions(true);
		$this->params = $model->getResolvedParams();
		$directory = $model->getDirectory();
		$this->items = (array) $directory->items;
		$this->archiveItemId = (int) $directory->archive_item_id;

		$app = Factory::getApplication();
		$menuItem = $app->getMenu()->getActive();
		$this->pageHeading = (string) $this->params->get(
			'page_heading',
			$menuItem?->title ?? Text::_('COM_AUDIOARCHIVE_TAG_DIRECTORY_TITLE')
		);
		$itemId = $app->getInput()->getInt('Itemid', 0);
		$this->prepareDocument($itemId, $menuItem);
		$this->getDocument()->getWebAssetManager()->useStyle('com_audioarchive.site');

		parent::display($tpl);
	}

	/**
	 * @brief Apply page metadata, breadcrumb fallback, and canonical URL.
	 *
	 * @param int $itemId Active Tag Directory menu item identifier.
	 * @param object|null $menuItem Active menu item.
	 *
	 * @return void
	 */
	private function prepareDocument(int $itemId, ?object $menuItem): void
	{
		$document = $this->getDocument();
		$pageTitle = trim((string) $this->params->get('page_title', ''));
		$title = $pageTitle !== '' ? $pageTitle : $this->pageHeading;
		$this->setDocumentTitle($title);

		$description = trim((string) $this->params->get('menu-meta_description', ''));

		if ($description !== '')
		{
			$document->setDescription($description);
		}

		$keywords = trim((string) $this->params->get('menu-meta_keywords', ''));

		if ($keywords !== '')
		{
			$document->setMetaData('keywords', $keywords);
		}

		$robots = trim((string) $this->params->get('robots', ''));

		if ($robots !== '')
		{
			$document->setMetaData('robots', $robots);
		}

		if ($menuItem === null || (string) ($menuItem->component ?? '') !== 'com_audioarchive')
		{
			Factory::getApplication()->getPathway()->addItem($this->pageHeading);
		}

		$canonical = Route::_(
			RouteHelper::getTagDirectoryRoute($itemId),
			false,
			Route::TLS_IGNORE,
			true
		);
		$document->addHeadLink($canonical, 'canonical');
		$document->setMetaData('og:type', 'website', 'property');
		$document->setMetaData('og:title', $title, 'property');
		$document->setMetaData('og:url', $canonical, 'property');

		if ($description !== '')
		{
			$document->setMetaData('og:description', $description, 'property');
		}
	}
}
