<?php

namespace Willeke\Plugin\Finder\Audioarchive\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Service\ArchiveMenuItemResolver;

\defined('_JEXEC') or die;

/**
 * @brief Smart Search adapter for Audio Archive clips.
 */
final class Audioarchive extends Adapter implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/** @var string Finder plugin identifier. */
	protected $context = 'Audioarchive';

	/** @var string Indexed component. */
	protected $extension = 'com_audioarchive';

	/** @var string Public result layout. */
	protected $layout = 'clip';

	/** @var string Finder content type. */
	protected $type_title = 'Audio Clip';

	/** @var string Component item table. */
	protected $table = '#__audioarchive_clips';

	/** @var bool Load plugin language files automatically. */
	protected $autoloadLanguage = true;

	/** @var array<int, array<int, object>> Tags keyed by clip identifier. */
	private array $tagsByClip = [];

	/** @var bool Whether the tag-assignment cache has been loaded. */
	private bool $tagsLoaded = false;

	/** @var ArchiveMenuItemResolver|null Archive menu resolver. */
	private ?ArchiveMenuItemResolver $archiveMenuItemResolver = null;

	/**
	 * @brief Return Finder events handled by the plugin.
	 *
	 * @return array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return array_merge(parent::getSubscribedEvents(), [
			'onFinderCategoryChangeState' => 'onFinderCategoryChangeState',
			'onFinderChangeState' => 'onFinderChangeState',
			'onFinderAfterDelete' => 'onFinderAfterDelete',
			'onFinderBeforeSave' => 'onFinderBeforeSave',
			'onFinderAfterSave' => 'onFinderAfterSave',
		]);
	}

	/**
	 * @brief Prepare cached tag information before an indexing run.
	 *
	 * @return bool True when setup succeeds.
	 */
	protected function setup()
	{
		if (!$this->tagsLoaded)
		{
			$this->loadTags();
		}

		return true;
	}

	/**
	 * @brief Update indexed items after an Audio Archive category state change.
	 *
	 * @param FinderEvent\AfterCategoryChangeStateEvent $event Finder event.
	 *
	 * @return void
	 */
	public function onFinderCategoryChangeState(FinderEvent\AfterCategoryChangeStateEvent $event): void
	{
		if ($event->getExtension() !== $this->extension)
		{
			return;
		}

		foreach ($event->getPks() as $categoryId)
		{
			$this->reindexCategorySubtree((int) $categoryId);
		}
	}

	/**
	 * @brief Remove a deleted clip from the Smart Search index.
	 *
	 * @param FinderEvent\AfterDeleteEvent $event Finder event.
	 *
	 * @return void
	 */
	public function onFinderAfterDelete(FinderEvent\AfterDeleteEvent $event): void
	{
		$context = $event->getContext();
		$item = $event->getItem();

		if ($context === 'com_audioarchive.clip')
		{
			$id = (int) ($item->id ?? 0);
		}
		elseif ($context === 'com_finder.index')
		{
			$id = (int) ($item->link_id ?? 0);
		}
		else
		{
			return;
		}

		if ($id > 0)
		{
			$this->remove($id);
		}
	}

	/**
	 * @brief Reindex a clip or update category access after a save.
	 *
	 * @param FinderEvent\AfterSaveEvent $event Finder event.
	 *
	 * @return void
	 */
	public function onFinderAfterSave(FinderEvent\AfterSaveEvent $event): void
	{
		$context = $event->getContext();
		$row = $event->getItem();
		$isNew = $event->getIsNew();

		if ($context === 'com_audioarchive.clip')
		{
			if (!$isNew && $this->old_access != $row->access)
			{
				$this->itemAccessChange($row);
			}

			$this->tagsByClip = [];
			$this->tagsLoaded = false;
			$this->reindex((int) $row->id);
		}

		if (
			$context === 'com_categories.category'
			&& ($row->extension ?? '') === $this->extension
		)
		{
			if (!$isNew && $this->old_cataccess != $row->access)
			{
				$this->categoryAccessChange($row);
			}

			$this->reindexCategorySubtree((int) $row->id);
		}
	}

	/**
	 * @brief Remember access values before a clip or category is saved.
	 *
	 * @param FinderEvent\BeforeSaveEvent $event Finder event.
	 *
	 * @return void
	 */
	public function onFinderBeforeSave(FinderEvent\BeforeSaveEvent $event): void
	{
		$context = $event->getContext();
		$row = $event->getItem();
		$isNew = $event->getIsNew();

		if ($context === 'com_audioarchive.clip' && !$isNew)
		{
			$this->checkItemAccess($row);
		}

		if (
			$context === 'com_categories.category'
			&& ($row->extension ?? '') === $this->extension
			&& !$isNew
		)
		{
			$this->checkCategoryAccess($row);
		}
	}

	/**
	 * @brief Synchronise clip publication states and plugin disablement.
	 *
	 * @param FinderEvent\AfterChangeStateEvent $event Finder event.
	 *
	 * @return void
	 */
	public function onFinderChangeState(FinderEvent\AfterChangeStateEvent $event): void
	{
		$context = $event->getContext();
		$pks = $event->getPks();
		$value = $event->getValue();

		if ($context === 'com_audioarchive.clip')
		{
			$this->itemStateChange($pks, $value);
		}

		if ($context === 'com_plugins.plugin' && $value === 0)
		{
			$this->pluginDisable($pks);
		}
	}

	/**
	 * @brief Index one Audio Archive clip.
	 *
	 * @param Result $item Finder result object.
	 *
	 * @return void
	 */
	protected function index(Result $item)
	{
		$item->setLanguage();

		if (!ComponentHelper::isEnabled($this->extension))
		{
			return;
		}

		$item->context = 'com_audioarchive.clip';
		$itemParameters = new Registry((string) $item->params);
		$item->params = clone ComponentHelper::getParams($this->extension, true);
		$item->params->merge($itemParameters);
		$item->metadata = new Registry((string) ($item->metadata ?? '{}'));

		$item->summary = Helper::prepareContent((string) $item->summary, $item->params, $item);
		$item->body = Helper::prepareContent((string) $item->body, $item->params, $item);

		$tags = $this->getTagsForClip((int) $item->id);
		$tagTitles = array_map(static fn (object $tag): string => (string) $tag->title, $tags);
		$searchExtras = array_filter([
			(string) $item->original_filename,
			(string) $item->category,
			implode(' ', $tagTitles),
			(string) $item->recorded_at,
			(string) $item->uploaded_at,
			(string) $item->author,
		]);

		if ($searchExtras)
		{
			$item->body = trim($item->body . "\n" . implode("\n", $searchExtras));
		}

		$item->url = $this->getUrl((int) $item->id, $this->extension, $this->layout);
		$item->route = RouteHelper::getClipRoute(
			(int) $item->id,
			$this->getArchiveMenuItemId(
				(string) $item->language,
				(int) $item->catid,
				$tags
			)
		);
		$item->metaauthor = (string) $item->author;

		foreach (['original_filename', 'category', 'recorded_at', 'uploaded_at', 'author'] as $field)
		{
			$item->addInstruction(Indexer::META_CONTEXT, $field);
		}

		$item->state = (int) $item->has_original === 1
			? $this->translateState((int) $item->state, (int) $item->cat_state)
			: 0;
		$item->access = max((int) $item->access, (int) $item->cat_access);

		$item->addTaxonomy('Type', 'Audio Clip');

		if (!empty($item->author))
		{
			$item->addTaxonomy('Author', (string) $item->author, $item->state);
		}

		$categories = $this->getApplication()
			->bootComponent($this->extension)
			->getCategory(['published' => false, 'access' => false]);
		$category = $categories->get((int) $item->catid);

		if (!$category)
		{
			return;
		}

		$item->addNestedTaxonomy(
			'Category',
			$category,
			$this->translateState((int) $category->published),
			(int) $category->access,
			(string) $category->language
		);

		foreach ($tags as $tag)
		{
			$item->addTaxonomy(
				'Tag',
				(string) $tag->title,
				$this->translateState((int) $tag->published),
				(int) $tag->access,
				(string) $tag->language
			);
		}

		$item->addTaxonomy('Language', (string) $item->language);
		Helper::getContentExtras($item);
		$this->indexer->index($item);
	}

	/**
	 * @brief Build the Finder list query for Audio Archive clips.
	 *
	 * @param mixed $query Existing query or null.
	 *
	 * @return QueryInterface
	 */
	protected function getListQuery($query = null)
	{
		$db = $this->getDatabase();
		$query = $query instanceof QueryInterface ? $query : $db->createQuery()
			->select([
				'a.id',
				'a.title',
				'a.alias',
				'a.description AS summary',
				'a.description AS body',
				'a.state',
				'a.catid',
				'a.uploaded_at AS start_date',
				'a.created',
				'a.created_by',
				'a.modified',
				'a.modified_by',
				'a.params',
				'a.language',
				'a.access',
				'a.version',
				'a.ordering',
				'a.publish_up AS publish_start_date',
				'a.publish_down AS publish_end_date',
				'a.original_filename',
				'a.duration_ms',
				'a.recorded_at',
				'a.uploaded_at',
				'a.play_count',
				'a.download_count',
				'c.title AS category',
				'c.published AS cat_state',
				'c.access AS cat_access',
				'c.language AS cat_language',
				'u.name AS author',
				"'' AS metadata",
				"'' AS metakey",
				"'' AS metadesc",
				"'' AS created_by_alias",
				'CASE WHEN f.id IS NULL THEN 0 ELSE 1 END AS has_original',
			])
			->from($db->quoteName('#__audioarchive_clips', 'a'))
			->join(
				'LEFT',
				$db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
			)
			->join(
				'LEFT',
				$db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by')
			)
			->join(
				'LEFT',
				$db->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $db->quoteName('f.clip_id') . ' = ' . $db->quoteName('a.id')
				. ' AND ' . $db->quoteName('f.file_role') . ' = ' . $db->quote('original')
				. ' AND ' . $db->quoteName('f.is_available') . ' = 1'
			);

		$itemId = $query->castAs('CHAR', 'a.id');
		$query->select(
			'CASE WHEN ' . $query->charLength('a.alias', '!=', '0')
			. ' THEN ' . $query->concatenate([$itemId, 'a.alias'], ':')
			. ' ELSE ' . $itemId . ' END AS slug'
		);

		$categoryId = $query->castAs('CHAR', 'c.id');
		$query->select(
			'CASE WHEN ' . $query->charLength('c.alias', '!=', '0')
			. ' THEN ' . $query->concatenate([$categoryId, 'c.alias'], ':')
			. ' ELSE ' . $categoryId . ' END AS catslug'
		);

		return $query;
	}

	/**
	 * @brief Reindex Clips in a category and all of its descendants.
	 *
	 * Category titles, hierarchy, state, and access participate in Finder data,
	 * so a category edit must refresh descendant Clip entries as well.
	 *
	 * @param int $categoryId Changed category identifier.
	 *
	 * @return void
	 */
	private function reindexCategorySubtree(int $categoryId): void
	{
		if ($categoryId <= 0)
		{
			return;
		}

		$db = $this->getDatabase();
		$extension = $this->extension;
		$query = $db->getQuery(true)
			->select($db->quoteName('a.id'))
			->from($db->quoteName('#__audioarchive_clips', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__categories', 'itemCategory')
				. ' ON ' . $db->quoteName('itemCategory.id') . ' = ' . $db->quoteName('a.catid')
			)
			->join(
				'INNER',
				$db->quoteName('#__categories', 'changedCategory')
				. ' ON ' . $db->quoteName('itemCategory.lft') . ' >= ' . $db->quoteName('changedCategory.lft')
				. ' AND ' . $db->quoteName('itemCategory.rgt') . ' <= ' . $db->quoteName('changedCategory.rgt')
			)
			->where($db->quoteName('changedCategory.id') . ' = :categoryId')
			->where($db->quoteName('itemCategory.extension') . ' = :itemExtension')
			->where($db->quoteName('changedCategory.extension') . ' = :changedExtension')
			->bind(':categoryId', $categoryId, ParameterType::INTEGER)
			->bind(':itemExtension', $extension, ParameterType::STRING)
			->bind(':changedExtension', $extension, ParameterType::STRING);

		foreach ((array) $db->setQuery($query)->loadColumn() as $clipId)
		{
			$this->reindex((int) $clipId);
		}
	}

	/**
	 * @brief Translate Joomla states to published-only Finder visibility.
	 *
	 * Archived Audio Archive Clips are not public Archive results and therefore
	 * remain hidden from Smart Search as well.
	 *
	 * @param int $item Item state.
	 * @param int|null $category Optional category state.
	 *
	 * @return int One for a published item in a published category, otherwise zero.
	 */
	protected function translateState($item, $category = null)
	{
		return (int) $item === 1
			&& ($category === null || (int) $category === 1)
			? 1
			: 0;
	}

	/**
	 * @brief Cache all published tag assignments for Audio Archive clips.
	 *
	 * @return void
	 */
	private function loadTags(): void
	{
		$db = $this->getDatabase();
		$typeAlias = 'com_audioarchive.clip';
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('m.content_item_id', 'clip_id'),
				$db->quoteName('t.id'),
				$db->quoteName('t.title'),
				$db->quoteName('t.published'),
				$db->quoteName('t.access'),
				$db->quoteName('t.language'),
			])
			->from($db->quoteName('#__contentitem_tag_map', 'm'))
			->join('INNER', $db->quoteName('#__tags', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('m.tag_id'))
			->where($db->quoteName('m.type_alias') . ' = :typeAlias')
			->where($db->quoteName('t.id') . ' > 1')
			->where($db->quoteName('t.published') . ' = 1')
			->bind(':typeAlias', $typeAlias, ParameterType::STRING)
			->order($db->quoteName('t.title') . ' ASC');

		$this->tagsByClip = [];
		$this->tagsLoaded = true;

		foreach ((array) $db->setQuery($query)->loadObjectList() as $tag)
		{
			$this->tagsByClip[(int) $tag->clip_id][] = $tag;
		}
	}

	/**
	 * @brief Return tags for one clip, loading the cache when necessary.
	 *
	 * @param int $clipId Clip identifier.
	 *
	 * @return array<int, object>
	 */
	private function getTagsForClip(int $clipId): array
	{
		if (!$this->tagsLoaded)
		{
			$this->loadTags();
		}

		return $this->tagsByClip[$clipId] ?? [];
	}

	/**
	 * @brief Find a published Archive menu item for result routing.
	 *
	 * @param string $language Clip language.
	 * @param int $categoryId Clip category identifier.
	 * @param array<int, object> $tags Published Clip tags.
	 *
	 * @return int Menu item identifier, or zero when none exists.
	 */
	private function getArchiveMenuItemId(string $language, int $categoryId, array $tags): int
	{
		$this->archiveMenuItemResolver ??= new ArchiveMenuItemResolver($this->getDatabase());
		$tagIds = array_map(
			static fn(object $tag): int => (int) $tag->id,
			$tags
		);

		return $this->archiveMenuItemResolver->resolve(
			$language,
			$categoryId,
			$tagIds,
			0,
			[1]
		);
	}
}
