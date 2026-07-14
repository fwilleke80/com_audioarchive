<?php

namespace Willeke\Component\Audioarchive\Site\View\Clip;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Database\DatabaseInterface;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\ClipModel;
use Willeke\Component\Audioarchive\Site\Service\ArchiveMenuItemResolver;
use Willeke\Component\Audioarchive\Site\Service\DownloadAccessService;

\defined('_JEXEC') or die;

/**
 * @brief Public detail view for one audio clip.
 */
class HtmlView extends BaseHtmlView
{
	/** @var object */
	public object $item;

	/** @var Registry */
	public Registry $params;

	/** @var string */
	public string $streamUrl = '';

	/** @var string */
	public string $downloadUrl = '';

	/** @var string */
	public string $archiveUrl = '';

	/** @var string */
	public string $playCountUrl = '';

	/** @var string */
	public string $playCountToken = '';

	/** @var bool */
	public bool $canDownload = false;

	/** @var int */
	public int $archiveItemId = 0;

	/**
	 * @brief Display one public clip.
	 *
	 * @param string|null $tpl Template name.
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		/** @var ClipModel $model */
		$model = $this->getModel();
		$model->setUseExceptions(true);
		$item = $model->getItem();

		if ($item === null)
		{
			throw new \Exception(Text::_('COM_AUDIOARCHIVE_CLIP_NOT_FOUND'), 404);
		}

		$this->item = $item;
		$this->params = $model->getResolvedParams();
		$application = Factory::getApplication();
		$this->canDownload = DownloadAccessService::canDownload(
			$this->params,
			$application->getIdentity()
		);
		$currentItemId = $application->getInput()->getInt('Itemid', 0);
		$tagIds = array_map(
			static fn(object $tag): int => (int) $tag->id,
			(array) $item->tags
		);
		$resolver = new ArchiveMenuItemResolver(Factory::getContainer()->get(DatabaseInterface::class));
		$routeItemId = $resolver->resolve(
			(string) ($item->language ?? '*'),
			(int) $item->catid,
			$tagIds,
			$currentItemId,
			(array) $application->getIdentity()->getAuthorisedViewLevels()
		);
		$this->archiveItemId = $routeItemId;

		$this->streamUrl = Route::_(RouteHelper::getPlaybackRoute((int) $item->id, $routeItemId));
		$this->downloadUrl = $this->canDownload
			? Route::_(RouteHelper::getDownloadRoute((int) $item->id, $routeItemId))
			: '';
		$this->archiveUrl = $routeItemId > 0
			? Route::_('index.php?Itemid=' . $routeItemId)
			: Route::_('index.php?option=com_audioarchive&view=archive');

		if ((int) $this->params->get('enable_play_counts', 1) === 1)
		{
			$this->playCountUrl = Route::_(RouteHelper::getPlayCountRoute($routeItemId));
			$this->playCountToken = Session::getFormToken();
		}

		$canonicalInternal = RouteHelper::getClipRoute((int) $item->id, $routeItemId);
		$canonical = Route::_($canonicalInternal, false, Route::TLS_IGNORE, true);
		$this->redirectLegacyOrStaleRoute($canonicalInternal, $routeItemId, $currentItemId);
		$this->preparePathway($routeItemId);
		$this->prepareDocument($canonical, $routeItemId);

		$this->getDocument()->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useStyle('com_audioarchive.player-style')
			->useScript('com_audioarchive.player');

		parent::display($tpl);
	}


	/**
	 * @brief Build an Archive URL filtered exclusively by one tag.
	 *
	 * @param object $tag Joomla tag record.
	 *
	 * @return string Routed Archive URL.
	 */
	public function getTagUrl(object $tag): string
	{
		$alias = trim((string) ($tag->alias ?? ''));
		$tagValue = $alias !== '' ? $alias : (string) (int) ($tag->id ?? 0);
		$query = $tagValue !== '' && $tagValue !== '0'
			? ['tags' => $tagValue]
			: [];

		return Route::_(RouteHelper::getArchiveRoute($this->archiveItemId, $query));
	}

	/**
	 * @brief Redirect old aliases, raw query URLs, and no-menu component routes.
	 *
	 * @param string $canonicalInternal Canonical internal Joomla route.
	 * @param int $routeItemId Resolved Archive menu item.
	 * @param int $currentItemId Current request menu item.
	 *
	 * @return void
	 */
	private function redirectLegacyOrStaleRoute(
		string $canonicalInternal,
		int $routeItemId,
		int $currentItemId
	): void
	{
		$application = Factory::getApplication();
		$currentUri = Uri::getInstance();
		$rawQuery = $currentUri->getQuery(true);
		$currentPath = '/' . trim($currentUri->getPath(), '/');
		$router = $application->getRouter();
		$needsRedirect = $router->isTainted()
			|| isset($rawQuery['view'])
			|| isset($rawQuery['id'])
			|| str_contains($currentPath, '/component/audioarchive/')
			|| ($routeItemId > 0 && $currentItemId !== $routeItemId);

		if (!$needsRedirect)
		{
			return;
		}

		$highlight = $rawQuery['highlight'] ?? null;
		$redirectInternal = $canonicalInternal;

		if (is_string($highlight) && $highlight !== '')
		{
			$redirectInternal .= '&highlight=' . rawurlencode($highlight);
		}

		$redirectUrl = Route::_($redirectInternal, false, Route::TLS_IGNORE, true);

		if (rtrim($redirectUrl, '/') !== rtrim((string) $currentUri, '/'))
		{
			$application->redirect($redirectUrl, 301);
		}
	}

	/**
	 * @brief Add category and clip entries to Joomla breadcrumbs.
	 *
	 * @param int $routeItemId Archive menu item used by this detail route.
	 *
	 * @return void
	 */
	private function preparePathway(int $routeItemId): void
	{
		$application = Factory::getApplication();
		$pathway = $application->getPathway();
		$active = $application->getMenu()->getActive();

		if ($active === null || (string) $active->component !== 'com_audioarchive')
		{
			$pathway->addItem(Text::_('COM_AUDIOARCHIVE_ARCHIVE_TITLE'), $this->archiveUrl);
		}

		$categoryLink = $routeItemId > 0
			? Route::_('index.php?option=com_audioarchive&view=archive&category=' . (int) $this->item->catid . '&Itemid=' . $routeItemId)
			: Route::_('index.php?option=com_audioarchive&view=archive&category=' . (int) $this->item->catid);
		$pathway->addItem((string) $this->item->category_title, $categoryLink);
		$pathway->addItem((string) $this->item->title);
	}

	/**
	 * @brief Apply browser title, description, keywords, robots, and social metadata.
	 *
	 * @param int $routeItemId Resolved Archive menu item.
	 *
	 * @return void
	 */
	private function prepareDocument(string $canonical, int $routeItemId): void
	{
		$application = Factory::getApplication();
		$document = $this->getDocument();
		$this->setDocumentTitle((string) $this->item->title);

		$description = $this->createMetaDescription((string) $this->item->description);

		if ($description === '')
		{
			$description = Text::sprintf(
				'COM_AUDIOARCHIVE_CLIP_META_DESCRIPTION_FALLBACK',
				(string) $this->item->title,
				(string) $this->item->category_title
			);
		}

		$document->setDescription($description);
		$tagTitles = array_map(
			static fn(object $tag): string => trim((string) $tag->title),
			(array) $this->item->tags
		);
		$keywords = array_values(array_unique(array_filter(array_merge(
			[(string) $this->item->category_title],
			$tagTitles
		))));

		if ($keywords !== [])
		{
			$document->setMetaData('keywords', implode(', ', $keywords));
		}

		$menu = $routeItemId > 0 ? $application->getMenu()->getItem($routeItemId) : $application->getMenu()->getActive();
		$menuParams = $menu?->getParams();
		$robots = trim((string) ($menuParams?->get('robots', '') ?? ''));

		if ($robots !== '')
		{
			$document->setMetaData('robots', $robots);
		}

		if (trim((string) ($this->item->author_name ?? '')) !== '')
		{
			$document->setMetaData('author', (string) $this->item->author_name);
		}

		$document->addHeadLink($canonical, 'canonical');
		$document->setMetaData('og:type', 'music.song', 'property');
		$document->setMetaData('og:title', (string) $this->item->title, 'property');
		$document->setMetaData('og:description', $description, 'property');
		$document->setMetaData('og:url', $canonical, 'property');
		$document->setMetaData('twitter:card', 'summary');
	}

	/**
	 * @brief Convert editor HTML into a compact metadata description.
	 *
	 * @param string $html Clip description.
	 *
	 * @return string Plain description of at most about 160 characters.
	 */
	private function createMetaDescription(string $html): string
	{
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim((string) preg_replace('/\s+/u', ' ', $text));

		if ($text === '')
		{
			return '';
		}

		if (mb_strlen($text) <= 160)
		{
			return $text;
		}

		return rtrim(mb_substr($text, 0, 157)) . '…';
	}
}
