<?php

namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Site\Service\PublicMediaService;

\defined('_JEXEC') or die;

/**
 * @brief Dispatch allow-listed browser interaction events to Punga Analytics.
 */
class InteractionController extends BaseController
{
	/** @var string[] */
	private const ALLOWED_EVENTS = [
		'audioarchive.playlist.created',
		'audioarchive.playlist.deleted',
		'audioarchive.playlist.clip_added',
		'audioarchive.playlist.clips_added',
		'audioarchive.playlist.clip_removed',
		'audioarchive.playlist.play',
		'audioarchive.playlist.shared',
		'audioarchive.playlist.saved_shared',
		'audioarchive.soundboard.play',
		'audioarchive.soundboard.shared',
	];

	/** @var string[] */
	private const CLIP_EVENTS = [
		'audioarchive.playlist.clip_added',
		'audioarchive.playlist.clip_removed',
		'audioarchive.playlist.play',
		'audioarchive.soundboard.play',
	];

	/**
	 * @brief Record one optional custom analytics event.
	 *
	 * @return void
	 */
	public function record(): void
	{
		$application = Factory::getApplication();

		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST')
		{
			$this->sendJson(405, ['success' => false]);
		}

		if (!Session::checkToken('post'))
		{
			$this->sendJson(403, ['success' => false]);
		}

		$eventType = strtolower(trim($application->getInput()->getString('event_type', '')));

		if (!in_array($eventType, self::ALLOWED_EVENTS, true))
		{
			$this->sendJson(400, ['success' => false]);
		}

		$params = clone ComponentHelper::getParams('com_audioarchive');
		$menuItem = $application->getMenu()->getActive();

		if ($menuItem !== null)
		{
			foreach ($menuItem->getParams()->toArray() as $key => $value)
			{
				if ($value !== '' && $value !== null)
				{
					$params->set($key, $value);
				}
			}
		}

		if (
			(str_starts_with($eventType, 'audioarchive.playlist.') && (int) $params->get('enable_playlists', 1) !== 1)
			|| (str_starts_with($eventType, 'audioarchive.soundboard.') && (int) $params->get('enable_soundboard', 1) !== 1)
			|| ($eventType === 'audioarchive.soundboard.play' && (int) $params->get('soundboard_record_plays', 1) !== 1)
		)
		{
			$this->sendJson(404, ['success' => false]);
		}

		$clip = null;
		$clipId = $application->getInput()->getInt('clip_id', 0);

		if (in_array($eventType, self::CLIP_EVENTS, true))
		{
			$mediaService = new PublicMediaService(
				Factory::getContainer()->get(DatabaseInterface::class),
				$params,
				$application->getIdentity()
			);
			$clip = $mediaService->getPublicClip($clipId, false);

			if ($clip === null)
			{
				$this->sendJson(404, ['success' => false]);
			}
		}

		$isPlaylistEvent = str_starts_with($eventType, 'audioarchive.playlist.');
		$isSoundboardEvent = str_starts_with($eventType, 'audioarchive.soundboard.');
		$itemType = $clip !== null
			? 'audioarchive.clip'
			: ($isPlaylistEvent ? 'audioarchive.playlist' : 'audioarchive.soundboard');
		$itemId = $clip !== null ? (string) ($clip->id ?? '') : '';
		$itemTitle = $clip !== null ? (string) ($clip->title ?? '') : '';
		$eventName = 'onPungaAnalyticsRecord';

		try
		{
			$application->getDispatcher()->dispatch(
				$eventName,
				new GenericEvent(
					$eventName,
					[
						'subject' => $this,
						'event_type' => $eventType,
						'component' => 'com_audioarchive',
						'view_name' => $isPlaylistEvent ? 'playlists' : ($isSoundboardEvent ? 'soundboard' : ''),
						'item_type' => $itemType,
						'item_id' => $itemId,
						'item_title' => $itemTitle,
					]
				)
			);
		}
		catch (\Throwable)
		{
			// Optional statistics listeners must never affect frontend actions.
		}

		$this->sendJson(200, ['success' => true]);
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
