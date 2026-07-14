<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Resolve the best public Archive menu item for a clip route.
 */
class ArchiveMenuItemResolver
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var array<string, int> */
	private array $resultCache = [];

	/**
	 * @brief Construct the menu-item resolver.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 */
	public function __construct(DatabaseInterface $database)
	{
		$this->database = $database;
	}

	/**
	 * @brief Find the most appropriate Archive menu item for one clip.
	 *
	 * A supplied preferred menu item wins when it is a compatible published
	 * Archive item. Otherwise the most specific compatible item is selected:
	 * exact language, category restriction, greatest number of required tags,
	 * then lowest menu identifier.
	 *
	 * @param string $language Clip language.
	 * @param int $categoryId Clip category identifier.
	 * @param int[] $tagIds Clip tag identifiers.
	 * @param int $preferredItemId Preferred current Archive menu item.
	 * @param int[] $authorisedViewLevels Menu access levels available to the caller.
	 *
	 * @return int Menu item identifier, or zero when none is suitable.
	 */
	public function resolve(
		string $language,
		int $categoryId,
		array $tagIds,
		int $preferredItemId = 0,
		array $authorisedViewLevels = [1]
	): int
	{
		$language = trim($language) !== '' ? trim($language) : '*';
		$tagIds = $this->normaliseIntegerList($tagIds);
		$authorisedViewLevels = $this->normaliseIntegerList($authorisedViewLevels);
		$authorisedViewLevels = $authorisedViewLevels !== [] ? $authorisedViewLevels : [1];
		$cacheKey = implode(':', [
			$language,
			(string) $categoryId,
			implode(',', $tagIds),
			(string) $preferredItemId,
			implode(',', $authorisedViewLevels),
		]);

		if (array_key_exists($cacheKey, $this->resultCache))
		{
			return $this->resultCache[$cacheKey];
		}

		$candidates = $this->loadCandidates($language, $authorisedViewLevels);
		$compatible = [];

		foreach ($candidates as $candidate)
		{
			$linkQuery = [];
			parse_str((string) parse_url(str_replace('&amp;', '&', (string) $candidate->link), PHP_URL_QUERY), $linkQuery);

			if (($linkQuery['option'] ?? '') !== 'com_audioarchive' || ($linkQuery['view'] ?? '') !== 'archive')
			{
				continue;
			}

			$params = new Registry((string) $candidate->params);
			$restrictedCategory = (int) $params->get('archive_category_restriction', 0);

			if ($restrictedCategory > 0 && $restrictedCategory !== $categoryId)
			{
				continue;
			}

			$requiredTags = $this->normaliseIntegerList($params->get('archive_tag_restriction', []));

			if ($requiredTags !== [] && array_diff($requiredTags, $tagIds) !== [])
			{
				continue;
			}

			$candidate->_audioarchive_language_score = (string) $candidate->language === $language ? 0 : 1;
			$candidate->_audioarchive_category_score = $restrictedCategory > 0 ? 0 : 1;
			$candidate->_audioarchive_tag_score = -count($requiredTags);
			$compatible[] = $candidate;
		}

		usort($compatible, static function(object $left, object $right) use ($preferredItemId): int
		{
			$leftPreferred = (int) $left->id === $preferredItemId ? 0 : 1;
			$rightPreferred = (int) $right->id === $preferredItemId ? 0 : 1;

			return $leftPreferred <=> $rightPreferred
				?: (int) $left->_audioarchive_language_score <=> (int) $right->_audioarchive_language_score
				?: (int) $left->_audioarchive_category_score <=> (int) $right->_audioarchive_category_score
				?: (int) $left->_audioarchive_tag_score <=> (int) $right->_audioarchive_tag_score
				?: (int) $left->id <=> (int) $right->id;
		});

		$this->resultCache[$cacheKey] = $compatible !== [] ? (int) $compatible[0]->id : 0;

		return $this->resultCache[$cacheKey];
	}

	/**
	 * @brief Load published site menu items belonging to Audio Archive.
	 *
	 * @param string $language Requested content language.
	 * @param int[] $authorisedViewLevels Allowed menu access levels.
	 *
	 * @return object[] Candidate menu items.
	 */
	private function loadCandidates(string $language, array $authorisedViewLevels): array
	{
		$database = $this->database;
		$componentType = 'component';
		$componentElement = 'com_audioarchive';
		$componentQuery = $database->getQuery(true)
			->select($database->quoteName('extension_id'))
			->from($database->quoteName('#__extensions'))
			->where($database->quoteName('type') . ' = :componentType')
			->where($database->quoteName('element') . ' = :componentElement')
			->bind(':componentType', $componentType, ParameterType::STRING)
			->bind(':componentElement', $componentElement, ParameterType::STRING);
		$componentId = (int) $database->setQuery($componentQuery)->loadResult();

		if ($componentId <= 0)
		{
			return [];
		}

		$languages = $language !== '*' ? [$language, '*'] : ['*'];
		$clientId = 0;
		$published = 1;
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('id'),
				$database->quoteName('link'),
				$database->quoteName('language'),
				$database->quoteName('params'),
			])
			->from($database->quoteName('#__menu'))
			->where($database->quoteName('client_id') . ' = :menuClientId')
			->where($database->quoteName('published') . ' = :menuPublished')
			->where($database->quoteName('component_id') . ' = :menuComponentId')
			->whereIn($database->quoteName('access'), $authorisedViewLevels, ParameterType::INTEGER)
			->whereIn($database->quoteName('language'), $languages, ParameterType::STRING)
			->bind(':menuClientId', $clientId, ParameterType::INTEGER)
			->bind(':menuPublished', $published, ParameterType::INTEGER)
			->bind(':menuComponentId', $componentId, ParameterType::INTEGER)
			->order($database->quoteName('id') . ' ASC');

		return (array) $database->setQuery($query)->loadObjectList();
	}

	/**
	 * @brief Normalise array, JSON, or comma-separated identifiers.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int[] Sorted positive identifiers.
	 */
	private function normaliseIntegerList($value): array
	{
		if (is_string($value))
		{
			$trimmed = trim($value);

			if ($trimmed === '')
			{
				return [];
			}

			$decoded = json_decode($trimmed, true);
			$value = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $trimmed);
		}

		if (!is_array($value))
		{
			$value = [$value];
		}

		$ids = array_values(array_unique(array_filter(
			array_map('intval', $value),
			static fn(int $id): bool => $id > 0
		)));
		sort($ids);

		return $ids;
	}
}
