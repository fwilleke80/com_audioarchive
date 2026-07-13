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
		$this->getDocument()->setTitle($this->pageHeading);
		$this->getDocument()->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useScript('com_audioarchive.player')
			->useScript('com_audioarchive.archive');

		parent::display($tpl);
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
		$values = [
			'q' => (string) $this->state->get('filter.search', ''),
			'category' => (int) $this->state->get('filter.category', 0),
			'tags' => (array) $this->state->get('filter.tags', []),
			'duration_min' => (string) $this->state->get('filter.duration_min', ''),
			'duration_max' => (string) $this->state->get('filter.duration_max', ''),
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
