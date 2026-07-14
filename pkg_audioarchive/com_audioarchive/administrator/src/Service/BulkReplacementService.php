<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * @brief Match inbox replacement files to existing Audio Archive originals.
 */
class BulkReplacementService
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var array<string, object[]>|null */
	private ?array $matchIndex = null;

	/**
	 * @brief Construct the replacement matcher.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 */
	public function __construct(DatabaseInterface $database)
	{
		$this->database = $database;
	}

	/**
	 * @brief Find clips whose current original has the same normalised basename.
	 *
	 * File extensions are ignored. Whitespace, underscores, ASCII hyphens, and
	 * Unicode dash punctuation are treated as equivalent separators.
	 *
	 * @param string $filename Inbox filename or relative path.
	 *
	 * @return object[] Matching clip and original-file rows.
	 */
	public function findMatches(string $filename): array
	{
		$key = $this->normaliseBasename($filename);

		if ($key === '')
		{
			return [];
		}

		$this->matchIndex ??= $this->buildMatchIndex();

		return $this->matchIndex[$key] ?? [];
	}

	/**
	 * @brief Create the stable comparison key for an original filename.
	 *
	 * @param string $filename Filename or relative path.
	 *
	 * @return string Normalised extension-free basename.
	 */
	public function normaliseBasename(string $filename): string
	{
		$filename = str_replace('\\', '/', str_replace("\0", '', trim($filename)));
		$filename = basename($filename);
		$basename = (string) pathinfo($filename, PATHINFO_FILENAME);

		if (class_exists('\Normalizer'))
		{
			$normalised = \Normalizer::normalize($basename, \Normalizer::FORM_C);

			if (is_string($normalised))
			{
				$basename = $normalised;
			}
		}

		$basename = mb_strtolower(trim($basename), 'UTF-8');
		$basename = preg_replace('/[\s_\p{Pd}]+/u', ' ', $basename) ?? $basename;
		$basename = preg_replace('/\s+/u', ' ', $basename) ?? $basename;

		return trim($basename);
	}

	/**
	 * @brief Load all original filenames and index them by normalised basename.
	 *
	 * @return array<string, object[]> Match index.
	 */
	private function buildMatchIndex(): array
	{
		$query = $this->database->getQuery(true)
			->select([
				$this->database->quoteName('a.id', 'clip_id'),
				$this->database->quoteName('a.uuid'),
				$this->database->quoteName('a.title'),
				$this->database->quoteName('a.original_filename'),
				$this->database->quoteName('a.state'),
				$this->database->quoteName('a.catid'),
				$this->database->quoteName('c.title', 'category_title'),
				$this->database->quoteName('f.id', 'file_id'),
				$this->database->quoteName('f.file_extension'),
				$this->database->quoteName('f.mime_type'),
				$this->database->quoteName('f.container_format'),
				$this->database->quoteName('f.audio_codec'),
				$this->database->quoteName('f.file_size'),
				$this->database->quoteName('f.duration_ms'),
				$this->database->quoteName('f.checksum_sha256'),
			])
			->from($this->database->quoteName('#__audioarchive_clips', 'a'))
			->innerJoin(
				$this->database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $this->database->quoteName('f.clip_id') . ' = ' . $this->database->quoteName('a.id')
				. ' AND ' . $this->database->quoteName('f.file_role') . ' = ' . $this->database->quote('original')
			)
			->leftJoin(
				$this->database->quoteName('#__categories', 'c')
				. ' ON ' . $this->database->quoteName('c.id') . ' = ' . $this->database->quoteName('a.catid')
			)
			->order($this->database->quoteName('a.id') . ' ASC');
		$rows = $this->database->setQuery($query)->loadObjectList() ?: [];
		$index = [];

		foreach ($rows as $row)
		{
			$key = $this->normaliseBasename((string) $row->original_filename);

			if ($key !== '')
			{
				$index[$key][] = $row;
			}
		}

		return $index;
	}
}
