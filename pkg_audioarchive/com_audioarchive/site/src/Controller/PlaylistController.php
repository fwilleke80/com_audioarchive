<?php

namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;
use Punga\Component\Audioarchive\Site\Model\ArchiveModel;
use Punga\Component\Audioarchive\Site\Service\ArchiveMenuItemResolver;
use Punga\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Resolve browser-local playlist entries to current public clip data.
 */
class PlaylistController extends BaseController
{
	/**
	 * @brief Return every accessible clip in the current Archive result set.
	 *
	 * The Archive model applies the same filters, ordering, menu restrictions,
	 * publication rules, and access checks as the visible result table.
	 *
	 * @return void
	 */
	public function archiveItems(): void
	{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET')
		{
			$this->sendJson(405, ['success' => false, 'items' => []]);
		}

		try
		{
			/** @var ArchiveModel $model */
			$model = $this->getModel('Archive');
			$model->setUseExceptions(true);

			if ((int) $model->getResolvedParams()->get('enable_playlists', 1) !== 1)
			{
				$this->sendJson(404, ['success' => false, 'items' => []]);
			}

			$items = array_values(array_map(
				static fn(object $item): array => [
					'uuid' => strtolower(trim((string) ($item->uuid ?? ''))),
					'id' => max(0, (int) ($item->id ?? 0)),
					'title' => trim((string) ($item->title ?? '')),
				],
				$model->getPlaylistItems()
			));

			$this->sendJson(200, [
				'success' => true,
				'items' => $items,
			]);
		}
		catch (\Throwable)
		{
			$this->sendJson(500, ['success' => false, 'items' => []]);
		}
	}

	/**
	 * @brief Return metadata and routes for accessible playlist clips.
	 *
	 * @return void
	 */
	public function items(): void
	{
		$application = Factory::getApplication();
		$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

		if (!in_array($requestMethod, ['GET', 'POST'], true))
		{
			$this->sendJson(405, ['success' => false, 'items' => []]);
		}

		$params = ComponentHelper::getParams('com_audioarchive');

		if ((int) $params->get('enable_playlists', 1) !== 1)
		{
			$this->sendJson(404, ['success' => false, 'items' => []]);
		}

		$uuids = $this->normaliseUuids($application->getInput()->getString('uuids', ''));

		if ($uuids === [])
		{
			$this->sendJson(200, ['success' => true, 'items' => []]);
		}

		try
		{
			$database = Factory::getContainer()->get(DatabaseInterface::class);
			$identity = $application->getIdentity();
			$mediaService = new PublicMediaService($database, $params, $identity);
			$menuResolver = new ArchiveMenuItemResolver($database);
			$preferredItemId = $application->getInput()->getInt('Itemid', 0);
			$authorisedViewLevels = (array) $identity->getAuthorisedViewLevels();
			$items = [];

			foreach ($this->resolveIdsByUuid($database, $uuids) as $uuid => $id)
			{
				$clip = $mediaService->getPublicClip($id, true);

				if ($clip === null)
				{
					continue;
				}

				$tagIds = array_map(
					static fn(object $tag): int => (int) ($tag->id ?? 0),
					(array) ($clip->tags ?? [])
				);
				$archiveItemId = $menuResolver->resolve(
					(string) ($clip->language ?? '*'),
					(int) ($clip->catid ?? 0),
					$tagIds,
					$preferredItemId,
					$authorisedViewLevels
				);
				$detailUrl = Route::_(
					RouteHelper::getClipRoute($id, $archiveItemId),
					false,
					Route::TLS_IGNORE,
					true
				);
				$items[$uuid] = [
					'uuid' => (string) $clip->uuid,
					'id' => $id,
					'title' => (string) $clip->title,
					'duration_ms' => max(0, (int) $clip->duration_ms),
					'mime_type' => trim((string) $clip->mime_type) ?: 'application/octet-stream',
					'stream_url' => Route::_(RouteHelper::getPlaybackRoute($id, $archiveItemId)),
					'detail_url' => $detailUrl,
					'share_url' => $detailUrl,
					'tags' => array_values(array_map(
						static fn(object $tag): array => [
							'id' => (int) ($tag->id ?? 0),
							'title' => (string) ($tag->title ?? ''),
						],
						(array) ($clip->tags ?? [])
					)),
				];
			}

			$this->sendJson(200, ['success' => true, 'items' => $items]);
		}
		catch (\Throwable)
		{
			$this->sendJson(500, ['success' => false, 'items' => []]);
		}
	}

	/**
	 * @brief Parse, deduplicate, validate, and limit requested UUIDs.
	 *
	 * @param string $value Comma- or whitespace-separated UUIDs.
	 *
	 * @return string[] Valid UUIDs in request order.
	 */
	private function normaliseUuids(string $value): array
	{
		$parts = preg_split('/[\s,]+/', trim($value)) ?: [];
		$uuids = [];

		foreach ($parts as $part)
		{
			$uuid = strtolower(trim($part));

			if (
				preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $uuid) !== 1
				|| isset($uuids[$uuid])
			)
			{
				continue;
			}

			$uuids[$uuid] = $uuid;

			if (count($uuids) >= 500)
			{
				break;
			}
		}

		return array_values($uuids);
	}

	/**
	 * @brief Resolve requested UUIDs to clip identifiers.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param string[] $uuids Requested UUIDs.
	 *
	 * @return array<string, int> UUID-to-ID map in request order.
	 */
	private function resolveIdsByUuid(DatabaseInterface $database, array $uuids): array
	{
		$query = $database->getQuery(true)
			->select([
				$database->quoteName('uuid'),
				$database->quoteName('id'),
			])
			->from($database->quoteName('#__audioarchive_clips'))
			->whereIn($database->quoteName('uuid'), $uuids, ParameterType::STRING);
		$rows = $database->setQuery($query)->loadObjectList('uuid');
		$result = [];

		foreach ($uuids as $uuid)
		{
			$id = (int) ($rows[$uuid]->id ?? 0);

			if ($id > 0)
			{
				$result[$uuid] = $id;
			}
		}

		return $result;
	}

	/**
	 * @brief Emit a compact JSON response and end the application.
	 *
	 * @param int $status HTTP status code.
	 * @param array<string, mixed> $payload Response payload.
	 *
	 * @return never
	 */
	private function sendJson(int $status, array $payload): never
	{
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		Factory::getApplication()->close();
	}
}
