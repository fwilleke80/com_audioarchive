<?php

namespace Willeke\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Willeke\Component\Audioarchive\Site\Service\DownloadAccessService;
use Willeke\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Deliver protected originals for playback and downloads.
 */
class StreamController extends BaseController
{
	/**
	 * @brief Stream the public original inline with range support.
	 *
	 * @return void
	 */
	public function play(): void
	{
		$this->deliver(false);
	}

	/**
	 * @brief Download the public original without exposing its managed path.
	 *
	 * @return void
	 */
	public function download(): void
	{
		$this->deliver(true);
	}

	/**
	 * @brief Deliver one protected derived analysis for an authorised clip.
	 *
	 * @return void
	 */
	public function analysis(): void
	{
		$application = Factory::getApplication();
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

		if (!in_array($method, ['GET', 'HEAD'], true))
		{
			header('Allow: GET, HEAD');
			$this->sendError(405, Text::_('COM_AUDIOARCHIVE_STREAM_METHOD_NOT_ALLOWED'));
		}

		$id = $application->getInput()->getInt('id', 0);
		$type = $application->getInput()->getCmd('type', 'waveform');
		$params = ComponentHelper::getParams('com_audioarchive');
		$service = new PublicMediaService(
			Factory::getContainer()->get(DatabaseInterface::class),
			$params,
			$application->getIdentity()
		);
		$analysis = $service->getPublicAnalysis($id, $type);

		if ($analysis === null)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ANALYSIS_NOT_FOUND'));
		}

		try
		{
			$path = $service->resolveAnalysisPath($analysis);
		}
		catch (\Throwable)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ANALYSIS_NOT_FOUND'));
		}

		$this->sendAnalysisFile($path, $analysis, $method === 'HEAD');
	}

	/**
	 * @brief Record the first actual playback start reported by the page.
	 *
	 * @return void
	 */
	public function countPlay(): void
	{
		$application = Factory::getApplication();
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

		if ($method !== 'POST')
		{
			$this->sendJson(405, ['success' => false]);
		}

		if (!Session::checkToken('post'))
		{
			$this->sendJson(403, ['success' => false]);
		}

		$params = ComponentHelper::getParams('com_audioarchive');

		if ((int) $params->get('enable_play_counts', 1) !== 1)
		{
			$this->sendJson(200, ['success' => true, 'counted' => false]);
		}

		$id = $application->getInput()->getInt('id', 0);
		$service = new PublicMediaService(
			Factory::getContainer()->get(DatabaseInterface::class),
			$params,
			$application->getIdentity()
		);

		$clip = $service->getPublicClip($id, false);

		if ($clip === null)
		{
			$this->sendJson(404, ['success' => false]);
		}

		$counted = $this->shouldCount('play', $id, 30);

		if ($counted)
		{
			$service->incrementPlayCount($id);
			$this->recordSimpleStatsEvent('audio.play', $clip);
		}

		$this->sendJson(200, ['success' => true, 'counted' => $counted]);
	}

	/**
	 * @brief Resolve, authorise, and send one original media file.
	 *
	 * @param bool $download Whether to force attachment disposition.
	 *
	 * @return void
	 */
	private function deliver(bool $download): void
	{
		$application = Factory::getApplication();
		$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

		if (!in_array($method, ['GET', 'HEAD'], true))
		{
			header('Allow: GET, HEAD');
			$this->sendError(405, Text::_('COM_AUDIOARCHIVE_STREAM_METHOD_NOT_ALLOWED'));
		}

		$id = $application->getInput()->getInt('id', 0);
		$params = ComponentHelper::getParams('com_audioarchive');

		if ($download && !DownloadAccessService::canDownload($params, $application->getIdentity()))
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_STREAM_NOT_FOUND'));
		}

		$service = new PublicMediaService(
			Factory::getContainer()->get(DatabaseInterface::class),
			$params,
			$application->getIdentity()
		);
		$clip = $service->getPublicClip($id, false);

		if ($clip === null)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_STREAM_NOT_FOUND'));
		}

		try
		{
			$path = $service->resolveOriginalPath($clip);
		}
		catch (\Throwable)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_STREAM_NOT_FOUND'));
		}

		if (
			$download
			&& $method === 'GET'
			&& (int) $params->get('enable_download_counts', 1) === 1
			&& $this->shouldCount('download', $id, 10)
		)
		{
			try
			{
				$service->incrementDownloadCount($id);
			}
			catch (\Throwable)
			{
				// A counter failure must never prevent an authorised download.
			}

			$this->recordSimpleStatsEvent('audio.download', $clip);
		}

		$this->sendFile($path, $clip, $download, $method === 'HEAD');
	}

	/**
	 * @brief Dispatch one optional Simple Stats custom event.
	 *
	 * The event uses only Joomla's generic dispatcher contract, so Audio
	 * Archive does not depend on Simple Stats being installed or enabled.
	 * Listener failures are isolated because statistics must never interrupt
	 * playback or an authorised download.
	 *
	 * @param string $eventType Stable Simple Stats event type.
	 * @param object $clip Public clip associated with the event.
	 *
	 * @return void
	 */
	private function recordSimpleStatsEvent(string $eventType, object $clip): void
	{
		$eventName = 'onSimpleStatsRecord';

		try
		{
			Factory::getApplication()->getDispatcher()->dispatch(
				$eventName,
				new GenericEvent(
					$eventName,
					[
						'subject' => $this,
						'event_type' => $eventType,
						'component' => 'com_audioarchive',
						'view_name' => 'clip',
						'item_type' => 'audioarchive.clip',
						'item_id' => (string) ($clip->id ?? ''),
						'item_title' => (string) ($clip->title ?? ''),
					]
				)
			);
		}
		catch (\Throwable)
		{
			// Optional statistics listeners must not affect media delivery.
		}
	}

	/**
	 * @brief Send one compact analysis data file.
	 *
	 * @param string $path Absolute validated path.
	 * @param object $analysis Analysis record.
	 * @param bool $headOnly Whether the request is HEAD.
	 *
	 * @return void
	 */
	private function sendAnalysisFile(string $path, object $analysis, bool $headOnly): void
	{
		$size = max(0, (int) filesize($path));

		if ($size <= 0)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ANALYSIS_NOT_FOUND'));
		}

		$handle = null;

		if (!$headOnly)
		{
			$handle = @fopen($path, 'rb');

			if ($handle === false)
			{
				$this->sendError(404, Text::_('COM_AUDIOARCHIVE_ANALYSIS_NOT_FOUND'));
			}
		}

		$format = strtolower(trim((string) ($analysis->data_format ?? '')));
		$mime = match (true)
		{
			str_starts_with($format, 'json-') => 'application/json; charset=utf-8',
			str_starts_with($format, 'png-') => 'image/png',
			str_starts_with($format, 'webp-') => 'image/webp',
			str_starts_with($format, 'svg-') => 'image/svg+xml; charset=utf-8',
			default => 'application/octet-stream',
		};
		$etag = '"' . sha1($path . ':' . $size . ':' . (int) filemtime($path)) . '"';
		$this->clearOutputBuffers();
		@session_write_close();
		http_response_code(200);
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . $size);
		header('ETag: ' . $etag);
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT');
		header('Cache-Control: private, max-age=86400, must-revalidate');
		header('X-Content-Type-Options: nosniff');

		if ($headOnly)
		{
			Factory::getApplication()->close();
		}

		while (!feof($handle))
		{
			$chunk = fread($handle, 65536);

			if ($chunk === false || $chunk === '')
			{
				break;
			}

			echo $chunk;
		}

		fclose($handle);
		Factory::getApplication()->close();
	}

	/**
	 * @brief Send one file response using bounded memory.
	 *
	 * @param string $path Absolute validated path.
	 * @param object $clip Public clip data.
	 * @param bool $download Whether to force attachment disposition.
	 * @param bool $headOnly Whether the request is HEAD.
	 *
	 * @return void
	 */
	private function sendFile(string $path, object $clip, bool $download, bool $headOnly): void
	{
		$size = (int) filesize($path);

		if ($size <= 0)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_STREAM_NOT_FOUND'));
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
		$mime = $this->normaliseMimeType((string) ($clip->mime_type ?? ''));
		$filenameValue = str_replace('\\', '/', (string) ($clip->original_filename ?? ('clip-' . (int) $clip->id)));
		$filename = basename($filenameValue);
		$fallbackFilename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: ('clip-' . (int) $clip->id);
		$disposition = $download ? 'attachment' : 'inline';
		$etagValue = trim((string) ($clip->checksum_sha256 ?? ''));
		$etag = $etagValue !== '' ? '"' . $etagValue . '"' : '"' . sha1($path . ':' . $size . ':' . filemtime($path)) . '"';

		$handle = null;

		if (!$headOnly)
		{
			$handle = @fopen($path, 'rb');

			if ($handle === false)
			{
				$this->sendError(404, Text::_('COM_AUDIOARCHIVE_STREAM_NOT_FOUND'));
			}

			if ($start > 0 && fseek($handle, $start) !== 0)
			{
				fclose($handle);
				$this->sendError(416, Text::_('COM_AUDIOARCHIVE_STREAM_RANGE_NOT_SATISFIABLE'));
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
		header('Cache-Control: private, max-age=0, must-revalidate');
		header('X-Content-Type-Options: nosniff');
		header(
			'Content-Disposition: ' . $disposition
			. '; filename="' . addcslashes($fallbackFilename, '"\\') . '"'
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
	 * @brief Parse one RFC 7233 single byte range.
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

			$start = max(0, $size - $suffixLength);
			$end = $size - 1;

			return [true, $start, $end];
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
	 * @brief Return a header-safe media MIME type.
	 *
	 * @param string $mime Candidate MIME type.
	 *
	 * @return string Valid MIME type or the binary fallback.
	 */
	private function normaliseMimeType(string $mime): string
	{
		$mime = strtolower(trim($mime));

		return preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*$~', $mime)
			? $mime
			: 'application/octet-stream';
	}

	/**
	 * @brief Apply a short session throttle to aggregate counter requests.
	 *
	 * @param string $type Counter type.
	 * @param int $id Clip identifier.
	 * @param int $minimumInterval Minimum seconds between counts.
	 *
	 * @return bool True when this request should increment the counter.
	 */
	private function shouldCount(string $type, int $id, int $minimumInterval): bool
	{
		if ($id <= 0 || !in_array($type, ['play', 'download'], true))
		{
			return false;
		}

		$session = Factory::getApplication()->getSession();
		$key = 'com_audioarchive.counter.' . $type . '.' . $id;
		$now = time();
		$last = (int) $session->get($key, 0);

		if ($last > 0 && $now - $last < max(1, $minimumInterval))
		{
			return false;
		}

		$session->set($key, $now);

		return true;
	}

	/**
	 * @brief Send a small JSON response for the playback-count endpoint.
	 *
	 * @param int $status HTTP status code.
	 * @param array<string, mixed> $payload Response payload.
	 *
	 * @return never
	 */
	private function sendJson(int $status, array $payload): never
	{
		$this->clearOutputBuffers();
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		Factory::getApplication()->close();
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
	 * @brief Remove existing buffers before emitting binary data.
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
