<?php

namespace Willeke\Plugin\Content\Audioarchive\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Module\Audioarchive\Site\Helper\AudioarchiveHelper;

\defined('_JEXEC') or die;

/**
 * @brief Replace Audio Archive placeholders with playable clip embeds.
 */
final class Audioarchive extends CMSPlugin implements SubscriberInterface
{
	/** @var bool Load the plugin language files automatically. */
	protected $autoloadLanguage = true;

	/** @var int Counter used to create unique player identifiers. */
	private static int $embedCounter = 0;

	/**
	 * @brief Return events handled by the plugin.
	 *
	 * @return array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepare' => 'onContentPrepare',
		];
	}

	/**
	 * @brief Replace Audio Archive placeholders in prepared frontend content.
	 *
	 * @param ContentPrepareEvent $event Joomla content preparation event.
	 *
	 * @return void
	 */
	public function onContentPrepare(ContentPrepareEvent $event): void
	{
		$application = $this->getApplication();

		if (!$application->isClient('site'))
		{
			return;
		}

		$item = $event->getItem();

		if (!\is_object($item) || !isset($item->text) || !\is_string($item->text))
		{
			return;
		}

		if (stripos($item->text, '{audioarchive') === false)
		{
			return;
		}

		$this->loadModuleLanguage();
		$item->text = (string) preg_replace_callback(
			'/\{audioarchive\s+([^{}]+)\}/i',
			fn(array $match): string => $this->renderPlaceholder((string) $match[0], (string) $match[1]),
			$item->text
		);
	}

	/**
	 * @brief Parse and render one Audio Archive placeholder.
	 *
	 * @param string $placeholder Original placeholder text.
	 * @param string $arguments Placeholder arguments without braces.
	 *
	 * @return string Rendered HTML or the original malformed placeholder.
	 */
	private function renderPlaceholder(string $placeholder, string $arguments): string
	{
		$options = $this->parseArguments($arguments);

		if ($options === null)
		{
			return $placeholder;
		}

		if (in_array($options['mode'], ['count', 'playtime'], true))
		{
			return $this->renderAggregate($options);
		}

		if (!class_exists(AudioarchiveHelper::class))
		{
			return $this->renderUnavailableMessage(Text::_('PLG_CONTENT_AUDIOARCHIVE_DEPENDENCY_MISSING'));
		}

		$module = (object) [
			'id' => 1000000000 + (++self::$embedCounter),
		];
		$params = $this->createRenderParams($options);
		$items = [];

		if (in_array($options['mode'], ['random', 'longest', 'shortest'], true))
		{
			$items = AudioarchiveHelper::getItems($params, $module);
		}
		else
		{
			foreach ($this->resolveClipIds((string) $options['clip']) as $clipId)
			{
				$params->set('specific_clip', $clipId);
				$items = AudioarchiveHelper::getItems($params, $module);

				if ($items !== [])
				{
					break;
				}
			}
		}

		if ($items === [])
		{
			return $this->renderUnavailableMessage(Text::_('PLG_CONTENT_AUDIOARCHIVE_NOT_FOUND'));
		}

		return $this->renderModuleLayout($items, $params, $module);
	}

	/**
	 * @brief Parse the supported placeholder grammar.
	 *
	 * Supported forms:
	 * {audioarchive random}
	 * {audioarchive random layout=compact}
	 * {audioarchive random layout=featured dataview=spectrum}
	 * {audioarchive longest}
	 * {audioarchive longest count=3 layout=compact}
	 * {audioarchive shortest}
	 * {audioarchive clip=some-alias}
	 * {audioarchive clip=123 layout=featured}
	 * {audioarchive count}
	 * {audioarchive count category=music,soundfx}
	 * {audioarchive playtime}
	 * {audioarchive playtime category=music,soundfx}
	 *
	 * @param string $arguments Placeholder arguments.
	 *
	 * @return array{mode:string, clip:string, layout:string, dataView:string, categories:string[], count:int}|null Parsed options or null.
	 */
	private function parseArguments(string $arguments): ?array
	{
		$remaining = trim($arguments);
		$attributes = [];

		$remaining = (string) preg_replace_callback(
			'/\b([a-z][a-z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/i',
			static function(array $match) use (&$attributes): string
			{
				$key = strtolower((string) $match[1]);
				$value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
				$attributes[$key] = trim((string) $value);

				return ' ';
			},
			$remaining
		);

		$mode = '';
		$modeMatches = [];
		preg_match_all('/\b(random|longest|shortest|count|playtime)\b/i', $remaining, $modeMatches);

		if (count($modeMatches[1] ?? []) > 1)
		{
			return null;
		}

		if (($modeMatches[1] ?? []) !== [])
		{
			$mode = strtolower((string) $modeMatches[1][0]);
			$remaining = (string) preg_replace('/\b' . preg_quote($mode, '/') . '\b/i', ' ', $remaining, 1);
		}

		if (trim($remaining) !== '')
		{
			return null;
		}

		$aggregateMode = in_array($mode, ['count', 'playtime'], true);
		$orderedMode = in_array($mode, ['longest', 'shortest'], true);
		$allowed = $aggregateMode
			? ['category']
			: ($orderedMode ? ['layout', 'count', 'dataview'] : ['clip', 'layout', 'dataview']);

		foreach (array_keys($attributes) as $key)
		{
			if (!in_array($key, $allowed, true))
			{
				return null;
			}
		}

		$clip = trim((string) ($attributes['clip'] ?? ''));
		$count = max(1, min(50, (int) ($attributes['count'] ?? 1)));
		$categories = $this->parseCategoryReferences((string) ($attributes['category'] ?? ''));

		if ($aggregateMode)
		{
			return [
				'mode' => $mode,
				'clip' => '',
				'layout' => '',
				'dataView' => '',
				'categories' => $categories,
				'count' => 1,
			];
		}

		if ($clip !== '')
		{
			if ($mode !== '')
			{
				return null;
			}

			$mode = 'specific';
		}

		if ($mode === '')
		{
			return null;
		}

		$configuredPresentation = strtolower(trim((string) $this->params->get('presentation', 'inherit')));

		if ($configuredPresentation === 'inherit')
		{
			$configuredPresentation = strtolower(trim((string) ComponentHelper::getParams('com_audioarchive')->get(
				'default_embed_presentation',
				'default'
			)));
		}

		if (!in_array($configuredPresentation, ['minimal', 'compact', 'default', 'featured'], true))
		{
			$configuredPresentation = 'default';
		}

		$layout = strtolower(trim((string) ($attributes['layout'] ?? $configuredPresentation)));

		if (!in_array($layout, ['minimal', 'compact', 'default', 'featured'], true))
		{
			return null;
		}

		$configuredDataView = strtolower(trim((string) $this->params->get('preferred_data_view', 'inherit')));

		if ($configuredDataView === 'inherit')
		{
			$configuredDataView = strtolower(trim((string) ComponentHelper::getParams('com_audioarchive')->get(
				'player_preferred_data_view',
				'waveform'
			)));
		}

		$configuredDataView = in_array($configuredDataView, ['waveform', 'spectrogram'], true)
			? $configuredDataView
			: 'waveform';
		$dataView = strtolower(trim((string) ($attributes['dataview'] ?? $configuredDataView)));

		if (in_array($dataView, ['spectrum', 'spetrum'], true))
		{
			$dataView = 'spectrogram';
		}

		if (!in_array($dataView, ['waveform', 'spectrogram'], true))
		{
			return null;
		}

		return [
			'mode' => $mode,
			'clip' => $clip,
			'layout' => $layout,
			'dataView' => $dataView,
			'categories' => [],
			'count' => $orderedMode ? $count : 1,
		];
	}

	/**
	 * @brief Parse a comma-separated category restriction.
	 *
	 * Category references may be numeric identifiers, aliases, or exact titles.
	 * Empty entries are ignored and duplicate entries are removed.
	 *
	 * @param string $value Raw category attribute value.
	 *
	 * @return string[] Normalised category references.
	 */
	private function parseCategoryReferences(string $value): array
	{
		$references = preg_split('/\s*,\s*/u', trim($value)) ?: [];
		$references = array_values(array_filter(
			array_map(static fn(string $reference): string => trim($reference), $references),
			static fn(string $reference): bool => $reference !== ''
		));

		return array_values(array_unique($references));
	}

	/**
	 * @brief Render an archive count or total playtime placeholder.
	 *
	 * @param array{mode:string, clip:string, layout:string, dataView:string, categories:string[], count:int} $options Parsed placeholder options.
	 *
	 * @return string Aggregate value formatted for inline content.
	 */
	private function renderAggregate(array $options): string
	{
		$totals = $this->getPublicAggregate($options['categories']);

		if ($options['mode'] === 'count')
		{
			return (string) $totals['count'];
		}

		return $this->formatPlaytime($totals['duration_ms']);
	}

	/**
	 * @brief Return aggregate data for clips visible to the current visitor.
	 *
	 * The query mirrors the public module eligibility rules: clips and their
	 * categories must be published, accessible, within publication dates, in
	 * the current language, and backed by an available original file.
	 *
	 * @param string[] $categoryReferences Optional category IDs, aliases, or titles.
	 *
	 * @return array{count:int, duration_ms:int} Public clip count and summed duration.
	 */
	private function getPublicAggregate(array $categoryReferences): array
	{
		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$application = $this->getApplication();
		$levels = array_values(array_unique(array_map(
			'intval',
			$application->getIdentity()->getAuthorisedViewLevels()
		)));
		$levels = $levels !== [] ? $levels : [1];
		$categoryIds = $this->resolveCategoryIds($database, $categoryReferences);

		if ($categoryReferences !== [] && $categoryIds === [])
		{
			return ['count' => 0, 'duration_ms' => 0];
		}

		$published = 1;
		$available = 1;
		$original = 'original';
		$extension = 'com_audioarchive';
		$now = Factory::getDate()->toSql();
		$language = $application->getLanguage()->getTag();
		$query = $database->getQuery(true)
			->select([
				'COUNT(DISTINCT ' . $database->quoteName('a.id') . ') AS ' . $database->quoteName('clip_count'),
				'COALESCE(SUM(' . $database->quoteName('a.duration_ms') . '), 0) AS ' . $database->quoteName('total_duration_ms'),
			])
			->from($database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$database->quoteName('#__categories', 'c')
				. ' ON ' . $database->quoteName('c.id') . ' = ' . $database->quoteName('a.catid')
			)
			->where($database->quoteName('a.state') . ' = :aggregateClipPublished')
			->where($database->quoteName('c.published') . ' = :aggregateCategoryPublished')
			->where($database->quoteName('c.extension') . ' = :aggregateCategoryExtension')
			->whereIn($database->quoteName('a.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('c.access'), $levels, ParameterType::INTEGER)
			->whereIn($database->quoteName('a.language'), ['*', $language], ParameterType::STRING)
			->extendWhere(
				'AND',
				[
					$database->quoteName('a.publish_up') . ' IS NULL',
					$database->quoteName('a.publish_up') . ' <= :aggregatePublishNow',
				],
				'OR'
			)
			->extendWhere(
				'AND',
				[
					$database->quoteName('a.publish_down') . ' IS NULL',
					$database->quoteName('a.publish_down') . ' >= :aggregateUnpublishNow',
				],
				'OR'
			)
			->bind(':aggregateClipPublished', $published, ParameterType::INTEGER)
			->bind(':aggregateCategoryPublished', $published, ParameterType::INTEGER)
			->bind(':aggregateCategoryExtension', $extension, ParameterType::STRING)
			->bind(':aggregatePublishNow', $now, ParameterType::STRING)
			->bind(':aggregateUnpublishNow', $now, ParameterType::STRING);

		$originalFile = $database->getQuery(true)
			->select('1')
			->from($database->quoteName('#__audioarchive_files', 'aggregateFile'))
			->where($database->quoteName('aggregateFile.clip_id') . ' = ' . $database->quoteName('a.id'))
			->where($database->quoteName('aggregateFile.file_role') . ' = :aggregateFileRole')
			->where($database->quoteName('aggregateFile.is_available') . ' = :aggregateFileAvailable');
		$query->where('EXISTS (' . $originalFile . ')')
			->bind(':aggregateFileRole', $original, ParameterType::STRING)
			->bind(':aggregateFileAvailable', $available, ParameterType::INTEGER);

		$ancestor = $database->getQuery(true)
			->select('1')
			->from($database->quoteName('#__categories', 'aggregateAncestor'))
			->where($database->quoteName('aggregateAncestor.extension') . ' = :aggregateAncestorExtension')
			->where($database->quoteName('aggregateAncestor.lft') . ' < ' . $database->quoteName('c.lft'))
			->where($database->quoteName('aggregateAncestor.rgt') . ' > ' . $database->quoteName('c.rgt'))
			->where(
				'(' . $database->quoteName('aggregateAncestor.published') . ' <> :aggregateAncestorPublished'
				. ' OR ' . $database->quoteName('aggregateAncestor.access') . ' NOT IN (' . implode(',', $levels) . '))'
			);
		$query->where('NOT EXISTS (' . $ancestor . ')')
			->bind(':aggregateAncestorExtension', $extension, ParameterType::STRING)
			->bind(':aggregateAncestorPublished', $published, ParameterType::INTEGER);

		if ($categoryIds !== [])
		{
			$query->whereIn($database->quoteName('a.catid'), $categoryIds, ParameterType::INTEGER);
		}

		$result = $database->setQuery($query)->loadObject();

		return [
			'count' => max(0, (int) ($result->clip_count ?? 0)),
			'duration_ms' => max(0, (int) ($result->total_duration_ms ?? 0)),
		];
	}

	/**
	 * @brief Resolve category references to Audio Archive category identifiers.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param string[] $references Numeric identifiers, aliases, or exact titles.
	 *
	 * @return int[] Matching category identifiers.
	 */
	private function resolveCategoryIds(DatabaseInterface $database, array $references): array
	{
		if ($references === [])
		{
			return [];
		}

		$numericIds = [];
		$names = [];

		foreach ($references as $reference)
		{
			if (ctype_digit($reference) && (int) $reference > 0)
			{
				$numericIds[] = (int) $reference;
			}
			else
			{
				$names[] = strtolower($reference);
			}
		}

		$extension = 'com_audioarchive';
		$query = $database->getQuery(true)
			->select($database->quoteName('id'))
			->from($database->quoteName('#__categories'))
			->where($database->quoteName('extension') . ' = :aggregateResolveExtension')
			->bind(':aggregateResolveExtension', $extension, ParameterType::STRING);

		$conditions = [];

		if ($numericIds !== [])
		{
			$conditions[] = $database->quoteName('id') . ' IN (' . implode(',', array_unique($numericIds)) . ')';
		}

		if ($names !== [])
		{
			$names = array_values(array_unique($names));
			$aliasPlaceholders = [];
			$titlePlaceholders = [];
			$aliasBindings = [];
			$titleBindings = [];

			foreach ($names as $index => $name)
			{
				$aliasPlaceholders[] = ':aggregateCategoryAlias' . $index;
				$titlePlaceholders[] = ':aggregateCategoryTitle' . $index;
				$aliasBindings[$index] = $name;
				$titleBindings[$index] = $name;
			}

			$conditions[] = 'LOWER(' . $database->quoteName('alias') . ') IN ('
				. implode(',', $aliasPlaceholders) . ')';
			$conditions[] = 'LOWER(' . $database->quoteName('title') . ') IN ('
				. implode(',', $titlePlaceholders) . ')';

			foreach ($names as $index => $name)
			{
				$query->bind($aliasPlaceholders[$index], $aliasBindings[$index], ParameterType::STRING)
					->bind($titlePlaceholders[$index], $titleBindings[$index], ParameterType::STRING);
			}
		}

		if ($conditions === [])
		{
			return [];
		}

		$query->where('(' . implode(' OR ', $conditions) . ')');

		return array_values(array_unique(array_filter(
			array_map('intval', (array) $database->setQuery($query)->loadColumn()),
			static fn(int $categoryId): bool => $categoryId > 0
		)));
	}

	/**
	 * @brief Format a summed duration without wrapping after 24 hours.
	 *
	 * @param int $milliseconds Duration in milliseconds.
	 *
	 * @return string Duration as MM:SS or H:MM:SS.
	 */
	private function formatPlaytime(int $milliseconds): string
	{
		$totalSeconds = max(0, (int) floor($milliseconds / 1000));
		$hours = intdiv($totalSeconds, 3600);
		$minutes = intdiv($totalSeconds % 3600, 60);
		$seconds = $totalSeconds % 60;

		if ($hours > 0)
		{
			return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
		}

		return sprintf('%02d:%02d', $minutes, $seconds);
	}

	/**
	 * @brief Create module-compatible rendering parameters.
	 *
	 * @param array{mode:string, clip:string, layout:string, dataView:string, categories:string[], count:int} $options Parsed placeholder options.
	 *
	 * @return Registry Module-compatible parameters.
	 */
	private function createRenderParams(array $options): Registry
	{
		return new Registry([
			'selection_mode' => in_array($options['mode'], ['random', 'longest', 'shortest'], true) ? $options['mode'] : 'specific',
			'count' => (int) ($options['count'] ?? 1),
			'specific_clip' => 0,
			'presentation' => 'default',
			'player_presentation' => $options['layout'],
			'preferred_data_view' => $options['dataView'],
			'show_title' => (int) $this->params->get('show_title', 1),
			'link_title' => (int) $this->params->get('link_title', 1),
			'show_player' => 1,
			'show_duration' => (int) $this->params->get('show_duration', 1),
			'show_date' => (int) $this->params->get('show_date', 0),
			'display_date' => (string) $this->params->get('display_date', 'uploaded'),
			'show_category' => (int) $this->params->get('show_category', 0),
			'show_tags' => (int) $this->params->get('show_tags', 1),
			'show_description' => (int) $this->params->get('show_description', 0),
			'description_length' => max(20, (int) $this->params->get('description_length', 160)),
			'show_detail_link' => (int) $this->params->get('show_detail_link', 1),
			'show_download' => (int) $this->params->get('show_download', 0),
			'show_counts' => (int) $this->params->get('show_counts', 0),
			'moduleclass_sfx' => ' plg-content-audioarchive',
		]);
	}

	/**
	 * @brief Resolve a numeric identifier or alias to candidate clip identifiers.
	 *
	 * The final publication, access, category and file checks are performed by
	 * the shared module helper before an item is rendered.
	 *
	 * @param string $reference Clip ID, ID-alias route segment or alias.
	 *
	 * @return int[] Candidate clip identifiers.
	 */
	private function resolveClipIds(string $reference): array
	{
		$reference = trim($reference);

		if ($reference === '')
		{
			return [];
		}

		if (preg_match('/^(\d+)(?:-.+)?$/u', $reference, $match) === 1)
		{
			$clipId = (int) $match[1];

			return $clipId > 0 ? [$clipId] : [];
		}

		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$alias = $reference;
		$aliasLower = strtolower($reference);
		$query = $database->getQuery(true)
			->select($database->quoteName('id'))
			->from($database->quoteName('#__audioarchive_clips'))
			->where(
				'(' . $database->quoteName('alias') . ' = :alias'
				. ' OR LOWER(' . $database->quoteName('alias') . ') = :aliasLower)'
			)
			->order($database->quoteName('id') . ' ASC')
			->bind(':alias', $alias, ParameterType::STRING)
			->bind(':aliasLower', $aliasLower, ParameterType::STRING);

		return array_values(array_filter(
			array_map('intval', (array) $database->setQuery($query)->loadColumn()),
			static fn(int $clipId): bool => $clipId > 0
		));
	}

	/**
	 * @brief Render one selected item through the Audio Archive module layout.
	 *
	 * @param object[] $items Prepared clip items.
	 * @param Registry $params Module-compatible rendering parameters.
	 * @param object $module Virtual module record used for unique player IDs.
	 *
	 * @return string Rendered embed HTML.
	 */
	private function renderModuleLayout(array $items, Registry $params, object $module): string
	{
		$application = $this->getApplication();
		$document = $application->getDocument();
		$assets = $document->getWebAssetManager();

		if (!$assets->assetExists('style', 'com_audioarchive.site'))
		{
			$assets->registerStyle('com_audioarchive.site', 'com_audioarchive/site.css');
		}

		if (!$assets->assetExists('style', 'com_audioarchive.player-style'))
		{
			$assets->registerStyle('com_audioarchive.player-style', 'com_audioarchive/player.css');
		}

		if (!$assets->assetExists('script', 'com_audioarchive.player'))
		{
			$assets->registerScript('com_audioarchive.player', 'com_audioarchive/player.js', [], ['type' => 'module'], ['core']);
		}

		$assets
			->useStyle('com_audioarchive.site')
			->useStyle('com_audioarchive.player-style')
			->useScript('com_audioarchive.player')
			->registerAndUseStyle('mod_audioarchive.site', 'mod_audioarchive/module.css');

		$playCountUrl = '';
		$playCountToken = '';

		if ((int) ComponentHelper::getParams('com_audioarchive')->get('enable_play_counts', 1) === 1)
		{
			$playCountUrl = Route::_(RouteHelper::getPlayCountRoute((int) ($items[0]->itemid ?? 0)));
			$playCountToken = Session::getFormToken();
		}

		$layout = 'default';
		ob_start();

		try
		{
			require ModuleHelper::getLayoutPath('mod_audioarchive', $layout);
		}
		finally
		{
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * @brief Render the configured output for an unavailable clip.
	 *
	 * @param string $message Translated diagnostic message.
	 *
	 * @return string Empty output or a safe message.
	 */
	private function renderUnavailableMessage(string $message): string
	{
		if ((string) $this->params->get('missing_behavior', 'message') === 'remove')
		{
			return '';
		}

		return '<span class="plg-content-audioarchive-message">'
			. htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
			. '</span>';
	}

	/**
	 * @brief Load the site module language used by the shared layouts.
	 *
	 * @return void
	 */
	private function loadModuleLanguage(): void
	{
		$this->getApplication()->getLanguage()->load(
			'mod_audioarchive',
			JPATH_SITE . '/modules/mod_audioarchive',
			null,
			true
		);
	}
}
