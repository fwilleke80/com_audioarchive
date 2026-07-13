<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Component\Router\Rules\RulesInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * @brief Keep archive tag filters as readable query parameters.
 *
 * New routes use a comma-separated list of tag aliases, for example
 * `?tags=ambient,field-recording`. The parser retains compatibility with the
 * short-lived `/tags/alias` path format used by development version 4.2.
 */
class TagFilterRules implements RulesInterface
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/**
	 * @brief Construct the tag-filter routing rule.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 */
	public function __construct(DatabaseInterface $database)
	{
		$this->database = $database;
	}

	/**
	 * @brief No preprocessing is required for archive tag filters.
	 *
	 * @param array<string, mixed> $query Route query.
	 *
	 * @return void
	 */
	public function preprocess(&$query)
	{
	}

	/**
	 * @brief Convert tag identifiers or alias arrays to one readable query value.
	 *
	 * @param array<string, mixed> $query Route query.
	 * @param string[] $segments Generated route segments.
	 *
	 * @return void
	 */
	public function build(&$query, &$segments)
	{
		if (($query['view'] ?? '') !== 'archive' || !isset($query['tags']))
		{
			return;
		}

		$aliases = $this->normaliseAliases($query['tags']);

		if ($aliases === [])
		{
			$tagIds = $this->normaliseIntegerList($query['tags']);
			$aliasesById = $this->loadAliasesById($tagIds);

			foreach ($tagIds as $tagId)
			{
				if (isset($aliasesById[$tagId]))
				{
					$aliases[] = $aliasesById[$tagId];
				}
			}
		}

		$aliases = array_values(array_unique(array_filter($aliases)));
		sort($aliases, SORT_NATURAL | SORT_FLAG_CASE);

		if ($aliases === [])
		{
			unset($query['tags']);
			return;
		}

		$query['tags'] = implode(',', $aliases);
	}

	/**
	 * @brief Parse the obsolete `/tags/alias` path form for compatibility.
	 *
	 * @param string[] $segments Remaining route segments.
	 * @param array<string, mixed> $vars Parsed route variables.
	 *
	 * @return void
	 */
	public function parse(&$segments, &$vars)
	{
		if ($segments === [] || (string) $segments[0] !== 'tags')
		{
			return;
		}

		$aliases = $this->normaliseAliases(array_slice($segments, 1));

		if ($aliases === [])
		{
			return;
		}

		$segments = [];
		$vars['view'] = 'archive';
		$vars['tags'] = implode(',', $aliases);
	}

	/**
	 * @brief Load tag aliases keyed by identifier.
	 *
	 * @param int[] $tagIds Tag identifiers.
	 *
	 * @return array<int, string>
	 */
	private function loadAliasesById(array $tagIds): array
	{
		if ($tagIds === [])
		{
			return [];
		}

		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('id'),
				$this->database->quoteName('alias'),
			])
			->from($this->database->quoteName('#__tags'))
			->whereIn($this->database->quoteName('id'), $tagIds, ParameterType::INTEGER);
		$rows = (array) $this->database->setQuery($query)->loadObjectList();
		$result = [];

		foreach ($rows as $row)
		{
			$alias = trim((string) $row->alias);

			if ($alias !== '')
			{
				$result[(int) $row->id] = $alias;
			}
		}

		return $result;
	}

	/**
	 * @brief Normalise aliases supplied as arrays or comma-separated strings.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string[]
	 */
	private function normaliseAliases(mixed $value): array
	{
		$values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);
		$aliases = [];

		foreach ((array) $values as $entry)
		{
			$alias = trim((string) $entry);

			if ($alias !== '' && !ctype_digit($alias))
			{
				$aliases[] = $alias;
			}
		}

		return array_values(array_unique($aliases));
	}

	/**
	 * @brief Normalise an array or scalar into unique positive tag identifiers.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int[]
	 */
	private function normaliseIntegerList(mixed $value): array
	{
		$values = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value);

		return array_values(array_unique(array_filter(
			array_map('intval', (array) $values),
			static fn(int $id): bool => $id > 0
		)));
	}
}
