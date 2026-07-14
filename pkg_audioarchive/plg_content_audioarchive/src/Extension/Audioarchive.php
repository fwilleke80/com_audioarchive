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

		if (!class_exists(AudioarchiveHelper::class))
		{
			return $this->renderUnavailableMessage(Text::_('PLG_CONTENT_AUDIOARCHIVE_DEPENDENCY_MISSING'));
		}

		$module = (object) [
			'id' => 1000000000 + (++self::$embedCounter),
		];
		$params = $this->createRenderParams($options);
		$items = [];

		if ($options['mode'] === 'random')
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
	 * {audioarchive clip=some-alias}
	 * {audioarchive clip=123 layout=featured}
	 *
	 * @param string $arguments Placeholder arguments.
	 *
	 * @return array{mode:string, clip:string, layout:string}|null Parsed options or null.
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

		if (preg_match('/\brandom\b/i', $remaining) === 1)
		{
			$mode = 'random';
			$remaining = (string) preg_replace('/\brandom\b/i', ' ', $remaining, 1);
		}

		if (trim($remaining) !== '')
		{
			return null;
		}

		$allowed = ['clip', 'layout'];

		foreach (array_keys($attributes) as $key)
		{
			if (!in_array($key, $allowed, true))
			{
				return null;
			}
		}

		$clip = trim((string) ($attributes['clip'] ?? ''));

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

		if (!in_array($configuredPresentation, ['default', 'compact', 'featured'], true))
		{
			$configuredPresentation = 'default';
		}

		$layout = strtolower(trim((string) ($attributes['layout'] ?? $configuredPresentation)));

		if (!in_array($layout, ['default', 'compact', 'featured'], true))
		{
			return null;
		}

		return [
			'mode' => $mode,
			'clip' => $clip,
			'layout' => $layout,
		];
	}

	/**
	 * @brief Create module-compatible rendering parameters.
	 *
	 * @param array{mode:string, clip:string, layout:string} $options Parsed placeholder options.
	 *
	 * @return Registry Module-compatible parameters.
	 */
	private function createRenderParams(array $options): Registry
	{
		return new Registry([
			'selection_mode' => $options['mode'] === 'random' ? 'random' : 'specific',
			'count' => 1,
			'specific_clip' => 0,
			'presentation' => $options['layout'],
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

		if (!$assets->assetExists('script', 'com_audioarchive.player'))
		{
			$assets->registerScript('com_audioarchive.player', 'com_audioarchive/player.js', [], ['type' => 'module'], ['core']);
		}

		$assets
			->useStyle('com_audioarchive.site')
			->useScript('com_audioarchive.player')
			->registerAndUseStyle('mod_audioarchive.site', 'mod_audioarchive/module.css');

		$playCountUrl = '';
		$playCountToken = '';

		if ((int) ComponentHelper::getParams('com_audioarchive')->get('enable_play_counts', 1) === 1)
		{
			$playCountUrl = Route::_(RouteHelper::getPlayCountRoute((int) ($items[0]->itemid ?? 0)));
			$playCountToken = Session::getFormToken();
		}

		$layout = (string) $params->get('presentation', 'default');
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
