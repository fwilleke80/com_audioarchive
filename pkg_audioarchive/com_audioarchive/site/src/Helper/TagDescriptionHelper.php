<?php

namespace Willeke\Component\Audioarchive\Site\Helper;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * @brief Attach safe plain-text Joomla tag descriptions to tag records.
 */
abstract class TagDescriptionHelper
{
	/**
	 * @brief Enrich one list of tag records with description_text properties.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param object[] $tags Joomla tag records.
	 *
	 * @return object[] Enriched tag records.
	 */
	public static function enrich(DatabaseInterface $database, array $tags): array
	{
		$groups = self::enrichGroups($database, [$tags]);

		return $groups[0] ?? [];
	}

	/**
	 * @brief Enrich several tag lists using at most one description query.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param array<int|string, object[]> $groups Tag lists keyed by clip identifier.
	 *
	 * @return array<int|string, object[]> Enriched tag lists.
	 */
	public static function enrichGroups(DatabaseInterface $database, array $groups): array
	{
		$tagIds = [];
		$descriptions = [];

		foreach ($groups as $tags)
		{
			foreach ($tags as $tag)
			{
				$tagId = (int) ($tag->id ?? 0);

				if ($tagId <= 0)
				{
					continue;
				}

				$tagIds[] = $tagId;

				if (property_exists($tag, 'description'))
				{
					$descriptions[$tagId] = (string) $tag->description;
				}
			}
		}

		$missingIds = array_values(array_diff(array_unique($tagIds), array_keys($descriptions)));

		if ($missingIds !== [])
		{
			$query = $database->getQuery(true)
				->select([
					$database->quoteName('id'),
					$database->quoteName('description'),
				])
				->from($database->quoteName('#__tags'))
				->whereIn($database->quoteName('id'), $missingIds, ParameterType::INTEGER);

			foreach ((array) $database->setQuery($query)->loadObjectList() as $tag)
			{
				$descriptions[(int) $tag->id] = (string) $tag->description;
			}
		}

		foreach ($groups as &$tags)
		{
			foreach ($tags as $tag)
			{
				$tagId = (int) ($tag->id ?? 0);
				$description = $tagId > 0 ? (string) ($descriptions[$tagId] ?? '') : '';
				$tag->description_text = self::toPlainText($description);
			}
		}
		unset($tags);

		return $groups;
	}

	/**
	 * @brief Convert an editor-authored tag description into compact plain text.
	 *
	 * @param string $description Raw Joomla tag description.
	 *
	 * @return string Normalised plain text suitable for a tooltip.
	 */
	public static function toPlainText(string $description): string
	{
		$text = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim((string) preg_replace('/\s+/u', ' ', $text));
	}
}
