<?php

namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;
use Punga\Component\Audioarchive\Site\Service\ArchiveMenuItemResolver;
use Punga\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Resolve canonical public detail routes for Sound Board clips.
 */
class SoundboardController extends BaseController
{
	/**
	 * @brief Return routed detail URLs for accessible public clips.
	 *
	 * @return void
	 */
	public function routes(): void
	{
		$application = Factory::getApplication();

		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET')
		{
			$this->sendJson(405, ['success' => false, 'routes' => []]);
		}

		$ids = $this->normaliseIds($application->getInput()->getString('ids', ''));

		if ($ids === [])
		{
			$this->sendJson(200, ['success' => true, 'routes' => []]);
		}

		$params = ComponentHelper::getParams('com_audioarchive');

		if ((int) $params->get('enable_soundboard', 1) !== 1)
		{
			$this->sendJson(404, ['success' => false, 'routes' => []]);
		}

		try
		{
			$database = Factory::getContainer()->get(DatabaseInterface::class);
			$identity = $application->getIdentity();
			$mediaService = new PublicMediaService($database, $params, $identity);
			$menuResolver = new ArchiveMenuItemResolver($database);
			$preferredItemId = $application->getInput()->getInt('Itemid', 0);
			$authorisedViewLevels = (array) $identity->getAuthorisedViewLevels();
			$routes = [];

			foreach ($ids as $id)
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
				$routes[(string) $id] = Route::_(
					RouteHelper::getClipRoute($id, $archiveItemId),
					false,
					Route::TLS_IGNORE,
					true
				);
			}

			$this->sendJson(200, ['success' => true, 'routes' => $routes]);
		}
		catch (\Throwable)
		{
			$this->sendJson(500, ['success' => false, 'routes' => []]);
		}
	}

	/**
	 * @brief Parse, deduplicate, and limit requested clip identifiers.
	 *
	 * @param string $value Comma- or whitespace-separated identifiers.
	 *
	 * @return int[] Positive clip identifiers, limited to the maximum board size.
	 */
	private function normaliseIds(string $value): array
	{
		$parts = preg_split('/[\s,]+/', trim($value)) ?: [];
		$ids = array_values(array_unique(array_filter(
			array_map('intval', $parts),
			static fn(int $id): bool => $id > 0
		)));

		return array_slice($ids, 0, 36);
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
