<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Scan and safely resolve files in the configured import inbox.
 */
class DirectoryImportService
{
	/** @var Registry */
	private Registry $params;

	/** @var ManagedStorageService */
	private ManagedStorageService $storage;

	/**
	 * @brief Construct the directory-import service.
	 *
	 * @param Registry $params Component parameters.
	 */
	public function __construct(Registry $params)
	{
		$this->params = $params;
		$this->storage = new ManagedStorageService($params);
	}

	/**
	 * @brief Scan the configured inbox for supported audio files.
	 *
	 * @param bool $recursive Whether child directories are scanned.
	 *
	 * @return array<int, array{path:string,filename:string,size:int,modified:int}> Discovered files.
	 */
	public function scan(bool $recursive): array
	{
		$root = $this->storage->ensureDirectory('import');
		$allowedExtensions = $this->getAllowedExtensions();
		$allowSymlinks = (bool) $this->params->get('allow_symlinks', 0);
		$directories = [$root];
		$visited = [];
		$files = [];

		while ($directories !== [])
		{
			$directory = array_pop($directories);
			$realDirectory = realpath($directory);

			if ($realDirectory === false || !$this->isContained($root, $realDirectory))
			{
				continue;
			}

			$realDirectory = Path::clean($realDirectory);

			if (isset($visited[$realDirectory]))
			{
				continue;
			}

			$visited[$realDirectory] = true;
			try
			{
				$iterator = new \FilesystemIterator(
					$directory,
					\FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS
				);
			}
			catch (\UnexpectedValueException $exception)
			{
				continue;
			}

			foreach ($iterator as $entry)
			{
				$name = $entry->getFilename();

				if ($name === '' || str_starts_with($name, '.'))
				{
					continue;
				}

				$logicalPath = $entry->getPathname();

				if ($entry->isLink() && !$allowSymlinks)
				{
					continue;
				}

				$resolvedPath = realpath($logicalPath);

				if ($resolvedPath === false || !$this->isContained($root, $resolvedPath))
				{
					continue;
				}

				if (is_dir($resolvedPath))
				{
					if ($recursive)
					{
						$directories[] = $logicalPath;
					}

					continue;
				}

				if (!is_file($resolvedPath) || !is_readable($resolvedPath))
				{
					continue;
				}

				$extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

				if (!in_array($extension, $allowedExtensions, true))
				{
					continue;
				}

				$relative = $this->relativePath($root, $logicalPath);
				$files[] = [
					'path' => $relative,
					'filename' => $name,
					'size' => max(0, (int) filesize($resolvedPath)),
					'modified' => max(0, (int) filemtime($resolvedPath)),
				];

				if (count($files) >= 10000)
				{
					break 2;
				}
			}
		}

		usort(
			$files,
			static fn (array $left, array $right): int => strnatcasecmp($left['path'], $right['path'])
		);

		return $files;
	}

	/**
	 * @brief Resolve one client-provided relative inbox path.
	 *
	 * @param string $relativePath Relative path from the import root.
	 *
	 * @return array{relative_path:string,logical_path:string,real_path:string,filename:string,size:int} Safe source data.
	 */
	public function resolveSource(string $relativePath): array
	{
		$relativePath = str_replace('\\', '/', trim($relativePath));

		if (
			$relativePath === ''
			|| str_contains($relativePath, "\0")
			|| str_starts_with($relativePath, '/')
			|| preg_match('#(^|/)\.\.(/|$)#', $relativePath)
		)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_ERROR_PATH'));
		}

		foreach (explode('/', $relativePath) as $segment)
		{
			if ($segment === '' || $segment === '.' || str_starts_with($segment, '.'))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_ERROR_PATH'));
			}
		}

		$root = $this->storage->ensureDirectory('import');
		$logicalPath = Path::clean($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

		if (!$this->isContained($root, $logicalPath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_ERROR_PATH_ESCAPE'));
		}

		$allowSymlinks = (bool) $this->params->get('allow_symlinks', 0);

		if (!$allowSymlinks && $this->containsSymlink($root, $logicalPath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_ERROR_SYMLINK'));
		}

		$realPath = realpath($logicalPath);

		if ($realPath === false || !$this->isContained($root, $realPath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_ERROR_PATH_ESCAPE'));
		}

		if (!is_file($realPath) || !is_readable($realPath))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_IMPORT_ERROR_NOT_READABLE'));
		}

		$extension = strtolower((string) pathinfo($logicalPath, PATHINFO_EXTENSION));

		if (!in_array($extension, $this->getAllowedExtensions(), true))
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ERROR_UPLOAD_EXTENSION', $extension !== '' ? $extension : '?'));
		}

		return [
			'relative_path' => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
			'logical_path' => $logicalPath,
			'real_path' => Path::clean($realPath),
			'filename' => basename($logicalPath),
			'size' => max(0, (int) filesize($realPath)),
		];
	}

	/**
	 * @brief Delete a successfully imported inbox entry.
	 *
	 * Symlinks are unlinked as links; their targets are never deleted.
	 *
	 * @param string $relativePath Relative inbox path.
	 *
	 * @return bool True when the source is absent or removed.
	 */
	public function deleteSource(string $relativePath): bool
	{
		$source = $this->resolveSource($relativePath);
		$path = $source['logical_path'];

		if (!file_exists($path) && !is_link($path))
		{
			return true;
		}

		if (!is_file($path) && !is_link($path))
		{
			return false;
		}

		$deleted = @unlink($path);

		if ($deleted)
		{
			$this->removeEmptyParents(dirname($path), $this->storage->getRoot('import'));
		}

		return $deleted;
	}

	/**
	 * @brief Return configured accepted extensions.
	 *
	 * @return string[] Lowercase extensions.
	 */
	private function getAllowedExtensions(): array
	{
		$value = strtolower((string) $this->params->get(
			'permitted_extensions',
			'm4a,mp4,aac,mp3,ogg,oga,opus,wav,flac,webm'
		));

		return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
	}

	/**
	 * @brief Convert an absolute logical path to an inbox-relative path.
	 *
	 * @param string $root Import root.
	 * @param string $path Logical source path.
	 *
	 * @return string Relative path.
	 */
	private function relativePath(string $root, string $path): string
	{
		$root = rtrim(Path::clean($root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$path = Path::clean($path);

		return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
	}

	/**
	 * @brief Check whether a path is contained by a root.
	 *
	 * @param string $root Root path.
	 * @param string $path Candidate path.
	 *
	 * @return bool True when contained.
	 */
	private function isContained(string $root, string $path): bool
	{
		$root = rtrim(Path::clean($root), DIRECTORY_SEPARATOR);
		$path = Path::clean($path);

		return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
	}

	/**
	 * @brief Check each existing path component for a symbolic link.
	 *
	 * @param string $root Root path.
	 * @param string $path Candidate path.
	 *
	 * @return bool True when any component is a symlink.
	 */
	private function containsSymlink(string $root, string $path): bool
	{
		$root = rtrim(Path::clean($root), DIRECTORY_SEPARATOR);
		$path = Path::clean($path);
		$relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
		$current = $root;

		foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment)
		{
			if ($segment === '')
			{
				continue;
			}

			$current .= DIRECTORY_SEPARATOR . $segment;

			if (is_link($current))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @brief Remove empty source directories up to the import root.
	 *
	 * @param string $directory Starting directory.
	 * @param string $root Import root.
	 *
	 * @return void
	 */
	private function removeEmptyParents(string $directory, string $root): void
	{
		$root = rtrim(Path::clean($root), DIRECTORY_SEPARATOR);
		$directory = Path::clean($directory);

		while ($directory !== $root && $this->isContained($root, $directory))
		{
			$entries = @scandir($directory);

			if ($entries === false || array_diff($entries, ['.', '..']) !== [])
			{
				break;
			}

			if (!@rmdir($directory))
			{
				break;
			}

			$directory = dirname($directory);
		}
	}
}
