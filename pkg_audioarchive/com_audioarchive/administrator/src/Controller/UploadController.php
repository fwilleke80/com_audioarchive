<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Willeke\Component\Audioarchive\Administrator\Model\ClipModel;
use Willeke\Component\Audioarchive\Administrator\Model\UploadModel;

\defined('_JEXEC') or die;

/**
 * @brief Controller for browser bulk uploads.
 */
class UploadController extends BaseController
{
    /**
     * @brief Upload and import one file from the browser queue.
     *
     * @return void
     */
    public function upload(): void
    {
        $app = Factory::getApplication();

        try
        {
            if (!Session::checkToken('post'))
            {
                throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            $user = $app->getIdentity();

            if (!$user->authorise('audioarchive.managefiles', 'com_audioarchive'))
            {
                throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            /** @var UploadModel $uploadModel */
            $uploadModel = $this->getModel('Upload');
            $form = $uploadModel->getForm([], false);

            if ($form === false)
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_ERROR_BULK_UPLOAD_FORM'), 500);
            }

            $posted = $app->getInput()->post->get('jform', [], 'array');
            $data = $uploadModel->validate($form, $posted);

            if ($data === false)
            {
                $errors = array_map(
                    static fn ($error): string => $error instanceof \Throwable ? $error->getMessage() : (string) $error,
                    $uploadModel->getErrors()
                );
                throw new \RuntimeException(
                    $errors !== [] ? implode(' ', $errors) : Text::_('COM_AUDIOARCHIVE_ERROR_BULK_UPLOAD_METADATA'),
                    400
                );
            }

            $categoryId = $uploadModel->getValidCategoryId((int) ($data['catid'] ?? 0));

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

            $data['id'] = 0;
            $data['catid'] = $categoryId;
            $data['title'] = '';
            $data['alias'] = '';
            $data['description'] = '';
            $data['language'] = '*';
            $data['recorded_date_source'] = trim((string) ($data['recorded_at'] ?? '')) !== '' ? 'manual' : 'import';
            $data['tags'] = array_values(array_filter((array) ($data['tags'] ?? []), static fn ($value): bool => (string) $value !== ''));

            /** @var ClipModel $clipModel */
            $clipModel = $this->getModel('Clip', 'Administrator', ['ignore_request' => true]);

            if (!$clipModel->save($data))
            {
                throw new \RuntimeException(
                    (string) ($clipModel->getError() ?: Text::_('COM_AUDIOARCHIVE_ERROR_BULK_UPLOAD_FAILED')),
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

            $technicalMetadata = json_decode((string) $item->technical_metadata, true);
            $technicalMetadata = is_array($technicalMetadata) ? $technicalMetadata : [];
            $messages = $this->normaliseMessages($app->getMessageQueue(true));
            $preparedUpload = $clipModel->getLastPreparedUpload();
            $duplicate = is_array($preparedUpload) && is_object($preparedUpload['duplicate'] ?? null)
                ? $this->normaliseDuplicate($preparedUpload['duplicate'])
                : null;
            $response = [
                'clip_id' => $clipId,
                'title' => (string) $item->title,
                'filename' => (string) $item->original_filename,
                'duration_ms' => (int) $file->duration_ms,
                'duration' => $this->formatDuration((int) $file->duration_ms),
                'container' => (string) $file->container_format,
                'codec' => (string) $file->audio_codec,
                'sample_rate' => (int) ($technicalMetadata['sample_rate'] ?? 0),
                'channels' => (int) ($technicalMetadata['channels'] ?? 0),
                'category' => $uploadModel->getCategoryTitle($categoryId),
                'state' => (int) $item->state,
                'edit_url' => Route::_('index.php?option=com_audioarchive&task=clip.edit&id=' . $clipId, false),
                'messages' => $messages,
                'duplicate' => $duplicate,
            ];

            $this->sendJson($response, Text::_('COM_AUDIOARCHIVE_BULK_UPLOAD_SUCCESS'), false, 200);
        }
        catch (\Throwable $exception)
        {
            $statusCode = (int) $exception->getCode();

            if ($statusCode < 400 || $statusCode > 599)
            {
                $statusCode = 500;
            }

            $this->sendJson(
                ['messages' => $this->normaliseMessages($app->getMessageQueue(true))],
                $exception->getMessage(),
                true,
                $statusCode
            );
        }
    }


    /**
     * @brief Convert a duplicate database row to safe response data.
     *
     * @param object $duplicate Duplicate clip summary.
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

            if ($text === '')
            {
                continue;
            }

            $result[] = [
                'message' => $text,
                'type' => (string) ($message['type'] ?? 'message'),
            ];
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
}
