<?php
namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Site\Service\CollectionService;

\defined('_JEXEC') or die;

/** @brief Typed JSON collection endpoints with no-store responses and CSRF-protected mutations. */
class CollectionController extends BaseController
{
	/** @brief Bootstrap effective storage and owned collections. */
	public function state(): void
	{
		$this->run('state');
	}
	/** @brief Save the bounded playlist collection set atomically. */
	public function playlists(): void
	{
		$this->run('playlists', true);
	}
	/** @brief Save one board. */
	public function board(): void
	{
		$this->run('board', true);
	}
	/** @brief Delete an owned board. */
	public function deleteBoard(): void
	{
		$this->run('deleteBoard', true);
	}
	/** @brief Set the owner's default board. */
	public function defaultBoard(): void
	{
		$this->run('defaultBoard', true);
	}
	/** @brief Import a browser playlist as a separate account collection. */
	public function importPlaylist(): void
	{
		$this->run('importPlaylist', true);
	}
	/** @brief Import a browser board without overwriting the default board. */
	public function importBoard(): void
	{
		$this->run('importBoard', true);
	}
	/** @brief Create a revocable share link. */
	public function share(): void
	{
		$this->run('share', true);
	}
	/** @brief Revoke all uses of the current collection share link. */
	public function revoke(): void
	{
		$this->run('revoke', true);
	}
	/** @brief Resolve a shared collection without granting access to its clips. */
	public function shared(): void
	{
		$this->run('shared');
	}

	/** @brief Validate transport and dispatch only explicitly defined operations. */
	private function run(string $action, bool $mutation = false): void
	{
		$app = Factory::getApplication();
		$app->setHeader('Cache-Control', 'private, no-store', true);
		$app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		$code = 200;
		try
		{
			$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
			if ($method !== ($mutation ? 'POST' : 'GET'))
			{
				throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
			}
			if ($mutation && (!Session::checkToken('post') || (int) $app->getIdentity()->id <= 0))
			{
				throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
			$params = ComponentHelper::getParams('com_audioarchive');
			$service = new CollectionService(Factory::getContainer()->get(DatabaseInterface::class), $params, $app->getIdentity());
			$raw = $mutation ? $app->getInput()->post->get('payload', '{}', 'raw') : '{}';
			if (strlen($raw) > 4 * 1024 * 1024)
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_LIMIT'), 413);
			}
			$data = json_decode($raw, true);
			if (!is_array($data) || ($action === 'playlists' && !is_array($data['playlists'] ?? null)))
			{
				throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COLLECTION_INVALID'), 400);
			}
			$revision = (int) ($data['revision'] ?? -1);
			$id = is_string($data['id'] ?? null) ? $data['id'] : '';
			$result = match ($action)
			{
				'state' => $service->state() + ['token' => Session::getFormToken(), 'sharing' => (bool) $params->get('collections_sharing', 1), 'labels' => $this->labels()],
				'playlists' => $service->playlists(is_array($data['playlists'] ?? null) ? $data['playlists'] : [], $revision),
				'importPlaylist', 'importBoard' => $service->importCollection($action === 'importPlaylist' ? 'playlist' : 'soundboard', is_array($data['collection'] ?? null) ? $data['collection'] : [], $revision),
				'board' => $service->board(is_array($data['board'] ?? null) ? $data['board'] : [], $revision),
				'deleteBoard' => $service->deleteBoard($id, $revision),
				'defaultBoard' => $service->defaultBoard($id, $revision),
				'share', 'revoke' => $service->share($id, $action === 'revoke', $revision),
				'shared' => $service->shared($app->getInput()->getString('share', '')),
			};
			$payload = ['success' => true, 'data' => $result];
		}
		catch (\Throwable $error)
		{
			$code = in_array($error->getCode(), [400, 403, 404, 405, 409, 413], true) ? $error->getCode() : 500;
			$payload = ['success' => false, 'message' => $code === 500 ? Text::_('COM_AUDIOARCHIVE_COLLECTION_ERROR') : $error->getMessage()];
		}
		http_response_code($code);
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: private, no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$app->close();
	}

	/** @brief Translate the shared browser/server controls once for both collection UIs. */
	private function labels(): array
	{
		$result = [];
		foreach (['BROWSER_PLAYLISTS', 'BROWSER_BOARD', 'IMPORT_RETAINED', 'SERVER', 'BROWSER', 'DISABLED', 'SAVING', 'ERROR', 'CONFLICT', 'NEW', 'RENAME', 'DELETE', 'DEFAULT', 'NAME', 'CONFIRM', 'IMPORT', 'LATER', 'IMPORT_DONE', 'REVOKE', 'REVOKED', 'SHARED', 'UNAVAILABLE', 'DEFAULT_NAME', 'IMPORT_FOUND', 'SHARE_HELP', 'RESTORE'] as $key)
		{
			$result[strtolower($key)] = Text::_('COM_AUDIOARCHIVE_COLLECTION_' . $key);
		}
		return $result;
	}
}
