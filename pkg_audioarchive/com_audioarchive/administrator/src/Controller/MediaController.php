<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Path;
use Willeke\Component\Audioarchive\Administrator\Service\ManagedStorageService;

\defined('_JEXEC') or die;

/**
 * @brief Deliver protected audio previews inside the administrator client.
 */
class MediaController extends BaseController
{
	/**
	 * @brief Stream one stored original to an authorised administrator.
	 *
	 * @return void
	 */
	public function play(): void
	{
		$application = Factory::getApplication();
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

		if (!in_array($method, ['GET', 'HEAD'], true))
		{
			header('Allow: GET, HEAD');
			$this->sendError(405, Text::_('COM_AUDIOARCHIVE_ADMIN_STREAM_METHOD_NOT_ALLOWED'));
		}

		if (!Session::checkToken('get'))
		{
			$this->sendError(403, Text::_('JINVALID_TOKEN'));
		}

		$id = $application->getInput()->getInt('id', 0);
		$clip = $this->getClipFile($id);

		if ($clip === null || !$this->canPreview($clip))
		{
			$this->sendError(403, Text::_('JERROR_ALERTNOAUTHOR'));
		}

		if ((int) $clip->is_available !== 1)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ADMIN_STREAM_NOT_FOUND'));
		}

		try
		{
			$path = $this->resolveOriginalPath((string) $clip->storage_key);
		}
		catch (\Throwable)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ADMIN_STREAM_NOT_FOUND'));
		}

		$this->sendFile($path, $clip, $method === 'HEAD');
	}

	/**
	 * @brief Load the clip and its original file record.
	 *
	 * @param int $id Clip identifier.
	 *
	 * @return object|null Joined clip/file data.
	 */
	private function getClipFile(int $id): ?object
	{
		if ($id <= 0)
		{
			return null;
		}

		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$role = 'original';
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('a.id'),
				$database->quoteName('a.created_by'),
				$database->quoteName('a.original_filename'),
				$database->quoteName('f.storage_key'),
				$database->quoteName('f.mime_type'),
				$database->quoteName('f.checksum_sha256'),
				$database->quoteName('f.is_available'),
			])
			->from($database->quoteName('#__audioarchive_clips', 'a'))
			->join(
				'INNER',
				$database->quoteName('#__audioarchive_files', 'f')
				. ' ON ' . $database->quoteName('f.clip_id') . ' = ' . $database->quoteName('a.id')
			)
			->where($database->quoteName('a.id') . ' = :clipId')
			->where($database->quoteName('f.file_role') . ' = :fileRole')
			->bind(':clipId', $id, ParameterType::INTEGER)
			->bind(':fileRole', $role, ParameterType::STRING);
		$result = $database->setQuery($query, 0, 1)->loadObject();

		return is_object($result) ? $result : null;
	}

	/**
	 * @brief Check whether the current administrator may edit this clip.
	 *
	 * @param object $clip Clip data.
	 *
	 * @return bool
	 */
	private function canPreview(object $clip): bool
	{
		$user = Factory::getApplication()->getIdentity();
		$asset = 'com_audioarchive.clip.' . (int) $clip->id;

		if (
			$user->authorise('core.admin', 'com_audioarchive')
			|| $user->authorise('core.edit', $asset)
			|| $user->authorise('core.edit', 'com_audioarchive')
		)
		{
			return true;
		}

		return (int) $clip->created_by === (int) $user->id
			&& (
				$user->authorise('core.edit.own', $asset)
				|| $user->authorise('core.edit.own', 'com_audioarchive')
			);
	}

	/**
	 * @brief Resolve one managed original without allowing path or symlink escapes.
	 *
	 * @param string $storageKey Managed storage key.
	 *
	 * @return string Validated absolute path.
	 */
	private function resolveOriginalPath(string $storageKey): string
	{
		$storage = new ManagedStorageService(ComponentHelper::getParams('com_audioarchive'));
		$root = realpath($storage->getRoot('original'));
		$candidate = $storage->resolveManagedPath('original', $storageKey);
		$realPath = realpath($candidate);

		if (
			$root === false
			|| $realPath === false
			|| !is_dir($root)
			|| !is_file($realPath)
			|| is_link($candidate)
			|| !is_readable($realPath)
		)
		{
			throw new \RuntimeException('Stored original is unavailable.');
		}

		$root = rtrim(Path::clean($root), DIRECTORY_SEPARATOR);
		$realPath = Path::clean($realPath);

		if (!str_starts_with($realPath . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR))
		{
			throw new \RuntimeException('Stored original escaped its configured root.');
		}

		return $realPath;
	}

	/**
	 * @brief Send one audio response with single-range seeking support.
	 *
	 * @param string $path Validated absolute path.
	 * @param object $clip Clip/file data.
	 * @param bool $headOnly Whether the request is HEAD.
	 *
	 * @return void
	 */
	private function sendFile(string $path, object $clip, bool $headOnly): void
	{
		$size = (int) filesize($path);

		if ($size <= 0)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ADMIN_STREAM_NOT_FOUND'));
		}

		$start = 0;
		$end = $size - 1;
		$status = 200;
		$range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));

		if ($range !== '')
		{
			[$valid, $start, $end] = $this->parseRange($range, $size);

			if (!$valid)
			{
				$this->clearOutputBuffers();
				http_response_code(416);
				header('Content-Range: bytes */' . $size);
				header('Accept-Ranges: bytes');
				header('Content-Length: 0');
				Factory::getApplication()->close();
			}

			$status = 206;
		}

		$length = $end - $start + 1;
		$mime = $this->normaliseMimeType((string) $clip->mime_type);
		$filename = basename(str_replace('\\', '/', (string) $clip->original_filename));
		$fallbackFilename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: ('clip-' . (int) $clip->id);
		$etagValue = trim((string) $clip->checksum_sha256);
		$etag = $etagValue !== '' ? '"' . $etagValue . '"' : '"' . sha1($path . ':' . $size . ':' . filemtime($path)) . '"';
		$handle = null;

		if (!$headOnly)
		{
			$handle = @fopen($path, 'rb');

			if ($handle === false)
			{
				$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ADMIN_STREAM_NOT_FOUND'));
			}

			if ($start > 0 && fseek($handle, $start) !== 0)
			{
				fclose($handle);
				$this->sendError(416, Text::_('COM_AUDIOARCHIVE_ADMIN_STREAM_RANGE_NOT_SATISFIABLE'));
			}
		}

		$this->clearOutputBuffers();
		@set_time_limit(0);
		@session_write_close();
		http_response_code($status);
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . $length);
		header('Accept-Ranges: bytes');
		header('ETag: ' . $etag);
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT');
		header('Cache-Control: private, no-store');
		header('X-Content-Type-Options: nosniff');
		header(
			'Content-Disposition: inline; filename="' . addcslashes($fallbackFilename, '"\\') . '"'
			. "; filename*=UTF-8''" . rawurlencode($filename)
		);

		if ($status === 206)
		{
			header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
		}

		if ($headOnly)
		{
			Factory::getApplication()->close();
		}

		$remaining = $length;
		$chunkSize = 1024 * 1024;

		while ($remaining > 0 && !feof($handle))
		{
			$chunk = fread($handle, min($chunkSize, $remaining));

			if ($chunk === false || $chunk === '')
			{
				break;
			}

			echo $chunk;
			$remaining -= strlen($chunk);
			flush();
		}

		fclose($handle);
		Factory::getApplication()->close();
	}

	/**
	 * @brief Parse one single HTTP byte range.
	 *
	 * @param string $range Range header.
	 * @param int $size File size.
	 *
	 * @return array{0:bool,1:int,2:int}
	 */
	private function parseRange(string $range, int $size): array
	{
		if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches))
		{
			return [false, 0, 0];
		}

		$startText = $matches[1];
		$endText = $matches[2];

		if ($startText === '' && $endText === '')
		{
			return [false, 0, 0];
		}

		if ($startText === '')
		{
			$suffixLength = (int) $endText;

			if ($suffixLength <= 0)
			{
				return [false, 0, 0];
			}

			return [true, max(0, $size - $suffixLength), $size - 1];
		}

		$start = (int) $startText;
		$end = $endText === '' ? $size - 1 : (int) $endText;

		if ($start < 0 || $start >= $size || $end < $start)
		{
			return [false, 0, 0];
		}

		return [true, $start, min($end, $size - 1)];
	}

	/**
	 * @brief Return a header-safe MIME type.
	 *
	 * @param string $mime Candidate MIME type.
	 *
	 * @return string
	 */
	private function normaliseMimeType(string $mime): string
	{
		$mime = strtolower(trim($mime));

		return preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*$~', $mime)
			? $mime
			: 'application/octet-stream';
	}

	/**
	 * @brief Send a small plain-text error response.
	 *
	 * @param int $status HTTP status.
	 * @param string $message Public error message.
	 *
	 * @return never
	 */
	private function sendError(int $status, string $message): never
	{
		$this->clearOutputBuffers();
		http_response_code($status);
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo $message;
		Factory::getApplication()->close();
	}

	/**
	 * @brief Remove existing output buffers before binary output.
	 *
	 * @return void
	 */
	private function clearOutputBuffers(): void
	{
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}
	}
}
