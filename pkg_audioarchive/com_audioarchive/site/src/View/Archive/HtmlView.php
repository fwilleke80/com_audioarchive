<?php

namespace Willeke\Component\Audioarchive\Site\View\Archive;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\ArchiveModel;

\defined('_JEXEC') or die;

/**
 * @brief Public browse/archive view.
 */
class HtmlView extends BaseHtmlView
{
	/** @var object[] */
	public array $items = [];

	/** @var \Joomla\CMS\Pagination\Pagination */
	public $pagination;

	/** @var Registry */
	public Registry $state;

	/** @var Registry */
	public Registry $params;

	/** @var object[] */
	public array $categoryOptions = [];

	/** @var object[] */
	public array $tagOptions = [];

	/** @var string[] */
	public array $filterErrors = [];

	/** @var int[] */
	public array $pageSizeOptions = [];

	/** @var int */
	public int $maximumDurationSeconds = 0;

	/** @var string */
	public string $pageHeading = '';

	/** @var string */
	public string $playCountUrl = '';

	/** @var string */
	public string $playCountToken = '';
	/** @var object|null */
	public ?object $item = null;

	/** @var array<string, bool> */
	public array $archiveColumns = [];

	/** @var ArchiveModel */
	private ArchiveModel $archiveModel;


	/**
	 * @brief Display the public archive.
	 *
	 * @param string|null $tpl Template name.
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		/** @var ArchiveModel $model */
		$model = $this->getModel();
		$this->archiveModel = $model;
		$model->setUseExceptions(true);
		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->params = $model->getResolvedParams();
		$this->categoryOptions = $model->getCategoryOptions();
		$this->tagOptions = $model->getTagOptions();
		$this->filterErrors = $model->getFilterErrors();
		$this->pageSizeOptions = $model->getPageSizeOptions();
		$maximumDurationMs = $model->getMaximumDurationMs();
		$this->maximumDurationSeconds = $maximumDurationMs > 0
			? max(1, (int) floor($maximumDurationMs / 1000))
			: 0;

		$app = Factory::getApplication();
		$itemId = $app->getInput()->getInt('Itemid', 0);

		foreach ($this->items as $clip)
		{
			$clip->detail_url = Route::_(RouteHelper::getClipRoute((int) $clip->id, $itemId));
			$clip->stream_url = Route::_(RouteHelper::getPlaybackRoute((int) $clip->id, $itemId));
		}

		if ((int) $this->params->get('enable_play_counts', 1) === 1)
		{
			$this->playCountUrl = Route::_(RouteHelper::getPlayCountRoute($itemId));
			$this->playCountToken = Session::getFormToken();
		}

		$item = $app->getMenu()->getActive();
		$this->pageHeading = (string) $this->params->get('page_heading', $item?->title ?? Text::_('COM_AUDIOARCHIVE_ARCHIVE_TITLE'));
		$this->prepareDocument($itemId, $item);
		$this->getDocument()->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useScript('com_audioarchive.player')
			->useScript('com_audioarchive.archive');

		parent::display($tpl);
	}


	/**
	 * @brief Apply archive page title, menu metadata, breadcrumbs, and canonical URL.
	 *
	 * @param int $itemId Active Archive menu item identifier.
	 * @param object|null $menuItem Active menu item.
	 *
	 * @return void
	 */
	private function prepareDocument(int $itemId, ?object $menuItem): void
	{
		$document = $this->getDocument();
		$pageTitle = trim((string) $this->params->get('page_title', ''));
		$this->setDocumentTitle($pageTitle !== '' ? $pageTitle : $this->pageHeading);

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
			RouteHelper::getArchiveRoute($itemId, $this->getQueryValues()),
			false,
			Route::TLS_IGNORE,
			true
		);
		$document->addHeadLink($canonical, 'canonical');
		$document->setMetaData('og:type', 'website', 'property');
		$document->setMetaData('og:title', $pageTitle !== '' ? $pageTitle : $this->pageHeading, 'property');
		$document->setMetaData('og:url', $canonical, 'property');

		if ($description !== '')
		{
			$document->setMetaData('og:description', $description, 'property');
		}
	}

	/**
	 * @brief Build a URL that preserves the current archive filter state.
	 *
	 * @param array<string, mixed> $changes Query values to add or replace.
	 * @param string[] $remove Query keys to remove.
	 *
	 * @return string
	 */
	public function buildUrl(array $changes = [], array $remove = []): string
	{
		$query = $this->getQueryValues();
		foreach ($remove as $key)
		{
			unset($query[$key]);
		}
		foreach ($changes as $key => $value)
		{
			if ($value === null || $value === '' || $value === [])
			{
				unset($query[$key]);
			}
			else
			{
				$query[$key] = $value;
			}
		}

		$query = $this->removeDefaultListValues($query);
		$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);

		return Route::_(RouteHelper::getArchiveRoute($itemId, $query));
	}

	/**
	 * @brief Build an Archive URL filtered exclusively by one tag.
	 *
	 * @param int $tagId Joomla tag identifier.
	 *
	 * @return string
	 */
	public function getTagUrl(int $tagId): string
	{
		$alias = '';

		foreach ($this->tagOptions as $tag)
		{
			if ((int) $tag->id === $tagId)
			{
				$alias = trim((string) $tag->alias);
				break;
			}
		}

		$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
		$query = $alias !== '' ? ['tags' => $alias] : [];

		return Route::_(RouteHelper::getArchiveRoute($itemId, $query));
	}

	/**
	 * @brief Return the reset URL for the active menu item.
	 *
	 * @return string
	 */
	public function getResetUrl(): string
	{
		$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
		return Route::_(RouteHelper::getArchiveRoute($itemId));
	}

	/**
	 * @brief Return true when at least one visitor-controlled filter is active.
	 *
	 * @return bool
	 */
	public function hasActiveFilters(): bool
	{
		foreach ($this->getQueryValues() as $key => $value)
		{
			if (!in_array($key, ['sort', 'direction', 'limit', 'limitstart'], true) && $value !== '' && $value !== [] && $value !== 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @brief Return visitor-controlled query values.
	 *
	 * @return array<string, mixed>
	 */
	public function getQueryValues(): array
	{
		return $this->archiveModel->getCanonicalQueryValues();
	}

	/**
	 * @brief Remove list values that equal this menu item's defaults.
	 *
	 * @param array<string, mixed> $query Candidate public query values.
	 *
	 * @return array<string, mixed>
	 */
	private function removeDefaultListValues(array $query): array
	{
		$defaultOrdering = (string) $this->params->get('archive_default_ordering', $this->params->get('default_ordering', 'uploaded_at'));
		$defaultOrdering = match ($defaultOrdering)
		{
			'uploaded_at' => 'uploaded',
			'recorded_at' => 'recorded',
			default => $defaultOrdering,
		};
		$defaultDirection = strtolower((string) $this->params->get('archive_default_direction', $this->params->get('default_direction', 'desc')));
		$maximumLimit = max(1, min(1000, (int) $this->params->get('archive_maximum_page_size', 200)));
		$defaultLimit = max(1, min($maximumLimit, (int) $this->params->get('archive_default_limit', $this->params->get('default_limit', 20))));

		if (($query['sort'] ?? $defaultOrdering) === $defaultOrdering)
		{
			unset($query['sort']);
		}

		if (strtolower((string) ($query['direction'] ?? $defaultDirection)) === $defaultDirection)
		{
			unset($query['direction']);
		}

		if ((int) ($query['limit'] ?? $defaultLimit) === $defaultLimit)
		{
			unset($query['limit']);
		}

		if ((int) ($query['limitstart'] ?? 0) <= 0)
		{
			unset($query['limitstart']);
		}

		return $query;
	}
}
