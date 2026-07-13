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

		$canonical = $itemId > 0
			? Route::_('index.php?Itemid=' . $itemId, false, Route::TLS_IGNORE, true)
			: Route::_('index.php?option=com_audioarchive&view=archive', false, Route::TLS_IGNORE, true);
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

		$query['option'] = 'com_audioarchive';
		$query['view'] = 'archive';
		$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
		if ($itemId > 0)
		{
			$query['Itemid'] = $itemId;
		}

		return Route::_('index.php?' . http_build_query($query));
	}

	/**
	 * @brief Return the reset URL for the active menu item.
	 *
	 * @return string
	 */
	public function getResetUrl(): string
	{
		$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
		return Route::_('index.php?option=com_audioarchive&view=archive' . ($itemId > 0 ? '&Itemid=' . $itemId : ''));
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
		$durationMinimum = (string) $this->state->get('filter.duration_min', '');
		$durationMaximum = (string) $this->state->get('filter.duration_max', '');
		$durationMinimumMs = $this->state->get('filter.duration_min_ms');
		$durationMaximumMs = $this->state->get('filter.duration_max_ms');

		if ($durationMinimumMs !== null && (int) $durationMinimumMs <= 0)
		{
			$durationMinimum = '';
		}

		if (
			$durationMaximumMs !== null
			&& $this->maximumDurationSeconds > 0
			&& (int) floor((int) $durationMaximumMs / 1000) >= $this->maximumDurationSeconds
		)
		{
			$durationMaximum = '';
		}

		$values = [
			'q' => (string) $this->state->get('filter.search', ''),
			'category' => (int) $this->state->get('filter.category', 0),
			'tags' => (array) $this->state->get('filter.tags', []),
			'duration_min' => $durationMinimum,
			'duration_max' => $durationMaximum,
			'recorded_from' => (string) $this->state->get('filter.recorded_from', ''),
			'recorded_to' => (string) $this->state->get('filter.recorded_to', ''),
			'uploaded_from' => (string) $this->state->get('filter.uploaded_from', ''),
			'uploaded_to' => (string) $this->state->get('filter.uploaded_to', ''),
			'sort' => (string) $this->state->get('list.ordering', 'uploaded'),
			'direction' => strtolower((string) $this->state->get('list.direction', 'DESC')),
			'limit' => (int) $this->state->get('list.limit', 20),
		];

		return array_filter(
			$values,
			static fn(mixed $value): bool => $value !== '' && $value !== [] && $value !== 0
		);
	}
}
