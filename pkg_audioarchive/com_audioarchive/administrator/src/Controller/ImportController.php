<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Willeke\Component\Audioarchive\Administrator\Model\ClipModel;
use Willeke\Component\Audioarchive\Administrator\Model\ImportModel;

\defined('_JEXEC') or die;

/**
 * @brief Controller for incremental server-directory imports.
 */
class ImportController extends BaseController
{
	/**
	 * @brief Scan the configured import inbox.
	 *
	 * @return void
	 */
	public function scan(): void
	{
		try
		{
			$this->assertRequestAuthorised();
			$recursive = Factory::getApplication()->getInput()->post->getBool('recursive', false);
			/** @var ImportModel $model */
			$model = $this->getModel('Import');
			$files = $model->scanInbox($recursive);

			$this->sendJson(
				['files' => $files, 'count' => count($files)],
				Text::sprintf('COM_AUDIOARCHIVE_IMPORT_SCAN_SUCCESS', count($files)),
				false,
				200
			);
		}
		catch (\Throwable $exception)
		{
			$this->sendException($exception);
		}
	}

	/**
	 * @brief Inspect one discovered inbox file.
	 *
	 * @return void
	 */
	public function inspect(): void
	{
		try
		{
			$this->assertRequestAuthorised();
			$app = Factory::getApplication();
			$relativePath = $app->getInput()->post->getString('path');
			$policyChoice = $app->getInput()->post->getCmd('duplicate_policy', 'component');
			/** @var ImportModel $model */
			$model = $this->getModel('Import');
			$effectivePolicy = $model->resolveDuplicatePolicy($policyChoice);
			$result = $model->prepareInboxFile($relativePath, 'ignore');
			$prepared = $result['prepared'];
			$uploadService = $model->createAudioUploadService();
			$recordingDate = $uploadService->determineRecordingDate($prepared);
			$metadata = (array) ($prepared['metadata'] ?? []);
			$duplicate = is_object($prepared['duplicate'] ?? null)
				? $this->normaliseDuplicate($prepared['duplicate'])
				: null;
			$eligible = !($duplicate !== null && $effectivePolicy === 'reject');
			$warnings = array_values(array_filter((array) ($metadata['warnings'] ?? [])));

			if ($duplicate !== null && $effectivePolicy === 'warn')
			{
				$warnings[] = Text::sprintf(
					'COM_AUDIOARCHIVE_WARNING_DUPLICATE_ALLOWED',
					(string) ($duplicate['title'] ?: $duplicate['filename'])
				);
			}

			$this->sendJson(
				[
					'path' => $relativePath,
					'eligible' => $eligible,
					'proposed_title' => $uploadService->generateTitle($prepared),
					'recorded_at' => (string) $recordingDate['date'],
					'recorded_date_source' => (string) $recordingDate['source'],
					'duration_ms' => (int) ($metadata['duration_ms'] ?? 0),
					'duration' => $this->formatDuration((int) ($metadata['duration_ms'] ?? 0)),
					'container' => (string) ($metadata['container_format'] ?? ''),
					'codec' => (string) ($metadata['audio_codec'] ?? ''),
					'mime_type' => (string) ($metadata['mime_type'] ?? ''),
					'checksum' => (string) ($prepared['checksum_sha256'] ?? ''),
					'warnings' => $warnings,
					'duplicate' => $duplicate,
					'duplicate_policy' => $effectivePolicy,
				],
				$eligible
					? Text::_('COM_AUDIOARCHIVE_IMPORT_INSPECT_SUCCESS')
					: Text::_('COM_AUDIOARCHIVE_IMPORT_DUPLICATE_REJECTED'),
				false,
				200
			);
		}
		catch (\Throwable $exception)
		{
			$this->sendException($exception);
		}
	}

	/**
	 * @brief Import one selected inbox file.
	 *
	 * @return void
	 */
	public function importFile(): void
	{
		$app = Factory::getApplication();

		try
		{
			$this->assertRequestAuthorised();
			$user = $app->getIdentity();
			/** @var ImportModel $importModel */
			$importModel = $this->getModel('Import');
			$form = $importModel->getForm([], false);

			if ($form === false)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_IMPORT_FORM'), 500);
			}

			$posted = $app->getInput()->post->get('jform', [], 'array');
			$data = $importModel->validate($form, $posted);

			if ($data === false)
			{
				$errors = array_map(
					static fn ($error): string => $error instanceof \Throwable ? $error->getMessage() : (string) $error,
					$importModel->getErrors()
				);
				throw new \RuntimeException(
					$errors !== [] ? implode(' ', $errors) : Text::_('COM_AUDIOARCHIVE_ERROR_IMPORT_METADATA'),
					400
				);
			}

			$categoryId = $importModel->getValidCategoryId((int) ($data['catid'] ?? 0));

			if ($categoryId <= 0)
			{
				throw new \RuntimeException(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'), 400);
			}

			$categoryAsset = 'com_audioarchive.category.' . $categoryId;

			if (!$user->authorise('core.create', 'com_audioarchive') && !$user->authorise('core.create', $categoryAsset))
			{
				throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}

			if (!$user->authorise('core.edit.state', 'com_audioarchive') && !$user->authorise('core.edit.state', $categoryAsset))
			{
				$data['state'] = 0;
			}

			$relativePath = $app->getInput()->post->getString('path');
			$effectivePolicy = $importModel->resolveDuplicatePolicy((string) ($data['duplicate_policy'] ?? 'component'));
			$preparedResult = $importModel->prepareInboxFile($relativePath, $effectivePolicy);
			$prepared = $preparedResult['prepared'];
			$data['id'] = 0;
			$data['catid'] = $categoryId;
			$data['title'] = '';
			$data['alias'] = '';
			$data['description'] = '';
			$data['language'] = '*';
			$data['recorded_date_source'] = trim((string) ($data['recorded_at'] ?? '')) !== '' ? 'manual' : 'import';
			$data['tags'] = array_values(array_filter(
				(array) ($data['tags'] ?? []),
				static fn ($value): bool => (string) $value !== ''
			));
			unset($data['recursive'], $data['duplicate_policy'], $data['delete_source']);

			/** @var ClipModel $clipModel */
			$clipModel = $this->getModel('Clip', 'Administrator', ['ignore_request' => true]);

			if (!$clipModel->savePreparedFile($data, $prepared))
			{
				throw new \RuntimeException(
					(string) ($clipModel->getError() ?: Text::_('COM_AUDIOARCHIVE_ERROR_IMPORT_FAILED')),
					400
				);
			}

			$clipId = (int) $clipModel->getState('clip.id');
			$item = $clipModel->getItem($clipId);
			$file = $clipModel->getOriginalFile($clipId);

			if ($clipId <= 0 || !$item || $file === null)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_SAVED_CLIP_NOT_FOUND'), 500);
			}

			$sourceDeleted = false;
			$deleteSource = (int) ($posted['delete_source'] ?? 0) === 1;

			if ($deleteSource)
			{
				$sourceDeleted = $importModel->deleteInboxFile($relativePath);

				if (!$sourceDeleted)
				{
					$app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_IMPORT_WARNING_SOURCE_NOT_DELETED'), 'warning');
				}
			}

			$technicalMetadata = json_decode((string) $item->technical_metadata, true);
			$technicalMetadata = is_array($technicalMetadata) ? $technicalMetadata : [];
			$lastPrepared = $clipModel->getLastPreparedUpload();
			$duplicate = is_array($lastPrepared) && is_object($lastPrepared['duplicate'] ?? null)
				? $this->normaliseDuplicate($lastPrepared['duplicate'])
				: null;

			$this->sendJson(
				[
					'clip_id' => $clipId,
					'path' => $relativePath,
					'title' => (string) $item->title,
					'filename' => (string) $item->original_filename,
					'duration_ms' => (int) $file->duration_ms,
					'duration' => $this->formatDuration((int) $file->duration_ms),
					'container' => (string) $file->container_format,
					'codec' => (string) $file->audio_codec,
					'sample_rate' => (int) ($technicalMetadata['sample_rate'] ?? 0),
					'channels' => (int) ($technicalMetadata['channels'] ?? 0),
					'category' => $importModel->getCategoryTitle($categoryId),
					'state' => (int) $item->state,
					'edit_url' => Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . $clipId, false),
					'messages' => $this->normaliseMessages($app->getMessageQueue(true)),
					'duplicate' => $duplicate,
					'source_deleted' => $sourceDeleted,
				],
				Text::_('COM_AUDIOARCHIVE_IMPORT_SUCCESS'),
				false,
				200
			);
		}
		catch (\Throwable $exception)
		{
			$this->sendException($exception);
		}
	}

	/**
	 * @brief Verify token and component import permission.
	 *
	 * @return void
	 */
	private function assertRequestAuthorised(): void
	{
		if (!Session::checkToken('post'))
		{
			throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
		}

		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('audioarchive.import', 'com_audioarchive'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/**
	 * @brief Convert a duplicate row to safe response data.
	 *
	 * @param object $duplicate Duplicate summary.
	 *
	 * @return array<string, mixed>
	 */
	private function normaliseDuplicate(object $duplicate): array
	{
		$clipId = (int) ($duplicate->clip_id ?? 0);

		return [
			'clip_id' => $clipId,
			'title' => (string) ($duplicate->title ?? ''),
			'filename' => (string) ($duplicate->original_filename ?? ''),
			'category' => (string) ($duplicate->category_title ?? ''),
			'uploaded_at' => (string) ($duplicate->uploaded_at ?? ''),
			'state' => (int) ($duplicate->state ?? 0),
			'edit_url' => $clipId > 0
				? Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . $clipId, false)
				: '',
		];
	}

	/**
	 * @brief Convert Joomla messages to a predictable JSON structure.
	 *
	 * @param array<int, array<string, mixed>> $messages Joomla message queue.
	 *
	 * @return array<int, array{message:string,type:string}>
	 */
	private function normaliseMessages(array $messages): array
	{
		$result = [];

		foreach ($messages as $message)
		{
			$text = trim((string) ($message['message'] ?? ''));

			if ($text !== '')
			{
				$result[] = [
					'message' => $text,
					'type' => (string) ($message['type'] ?? 'message'),
				];
			}
		}

		return $result;
	}

	/**
	 * @brief Format milliseconds as a compact audio duration.
	 *
	 * @param int $durationMs Duration in milliseconds.
	 *
	 * @return string Formatted duration.
	 */
	private function formatDuration(int $durationMs): string
	{
		$seconds = max(0, intdiv($durationMs, 1000));
		$hours = intdiv($seconds, 3600);
		$minutes = intdiv($seconds % 3600, 60);
		$remaining = $seconds % 60;

		return $hours > 0
			? sprintf('%d:%02d:%02d', $hours, $minutes, $remaining)
			: sprintf('%d:%02d', $minutes, $remaining);
	}

	/**
	 * @brief Emit a Joomla JSON response and stop request processing.
	 *
	 * @param mixed $data Response data.
	 * @param string $message Human-readable message.
	 * @param bool $error Whether the request failed.
	 * @param int $statusCode HTTP status code.
	 *
	 * @return never
	 */
	private function sendJson(mixed $data, string $message, bool $error, int $statusCode): never
	{
		$app = Factory::getApplication();
		http_response_code($statusCode);
		$app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		echo new JsonResponse($data, $message, $error);
		$app->close();
	}

	/**
	 * @brief Emit a caught exception as JSON.
	 *
	 * @param \Throwable $exception Failure.
	 *
	 * @return never
	 */
	private function sendException(\Throwable $exception): never
	{
		$statusCode = (int) $exception->getCode();

		if ($statusCode < 400 || $statusCode > 599)
		{
			$statusCode = 500;
		}

		$this->sendJson(
			['messages' => $this->normaliseMessages(Factory::getApplication()->getMessageQueue(true))],
			$exception->getMessage(),
			true,
			$statusCode
		);
	}
}
