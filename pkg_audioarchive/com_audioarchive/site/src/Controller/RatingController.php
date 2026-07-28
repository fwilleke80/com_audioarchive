<?php

namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Site\Service\PublicMediaService;
use Punga\Component\Audioarchive\Site\Service\RatingService;

\defined('_JEXEC') or die;

/**
 * @brief Handle public anonymous rating requests.
 */
class RatingController extends BaseController
{
	/**
	 * @brief Store one thumbs-up, thumbs-down, or cleared vote.
	 *
	 * @return void
	 */
	public function vote(): void
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

		$params = ComponentHelper::getParams('com_audioarchive');
		$database = Factory::getContainer()->get(DatabaseInterface::class);
		$clipId = $application->getInput()->post->getInt('id', 0);
		$clientId = strtolower($application->getInput()->post->getString('client_id', ''));
		$vote = $application->getInput()->post->getInt('vote', 0);
		$mediaService = new PublicMediaService($database, $params, $application->getIdentity());

		if ($mediaService->getPublicClip($clipId, false) === null)
		{
			$this->sendJson(404, ['success' => false]);
		}

		try
		{
			$ratingService = new RatingService($database, $params, $application->getIdentity());
			$result = $ratingService->storeVote($clipId, $clientId, $vote);
			$this->sendJson(200, ['success' => true] + $result);
		}
		catch (\InvalidArgumentException)
		{
			$this->sendJson(400, ['success' => false]);
		}
		catch (\RuntimeException)
		{
			$this->sendJson(403, ['success' => false]);
		}
		catch (\Throwable)
		{
			$this->sendJson(500, ['success' => false]);
		}
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
