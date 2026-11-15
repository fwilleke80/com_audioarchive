<?php

namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Short-lived, permission-bound download tickets with atomic accounting. */
final class DownloadTicketService
{
	/** @var int Lifetime of one download URL in seconds. */
	public const LIFETIME = 600;

	/** @brief Construct the ticket store. */
	public function __construct(private DatabaseInterface $database)
	{
	}

	/** @brief The global mode is authoritative, regardless of menu overrides. */
	public static function enabled(): bool
	{
		return ComponentHelper::getParams('com_audioarchive')->get('download_mode', 'direct') === 'ticket';
	}

	/** @brief Sign a page-issued action proof, binding user, clip, source and expiry. */
	public static function proof(int $clipId, int $userId, string $context, ?int $expires = null): string
	{
		$expires ??= time() + 43200;
		$data = 'audioarchive.download:' . $clipId . ':' . $userId . ':' . $context . ':' . $expires;
		return $expires . '.' . hash_hmac('sha256', $data, (string) Factory::getApplication()->get('secret'));
	}

	/** @brief Reject modified, expired or differently scoped page proofs. */
	public static function validProof(string $proof, int $clipId, int $userId, string $context): bool
	{
		if (!preg_match('/^(\d{10})\.[a-f0-9]{64}$/D', $proof, $matches))
		{
			return false;
		}
		$expires = (int) $matches[1];
		return $expires >= time() && $expires <= time() + 43200
			&& hash_equals(self::proof($clipId, $userId, $context, $expires), $proof);
	}

	/** @brief Create a random ticket after the caller has checked current permissions. */
	public function issue(int $clipId, int $userId, string $context): string
	{
		$db = $this->database;
		$db->setQuery('DELETE FROM ' . $db->quoteName('#__audioarchive_download_tickets') . ' WHERE expires_at < ' . time())->execute();
		$token = bin2hex(random_bytes(32));
		$row = (object) [
			'token_hash' => hash('sha256', $token), 'clip_id' => $clipId, 'user_id' => $userId,
			'context' => $context, 'expires_at' => time() + self::LIFETIME, 'counted' => 0,
		];
		$db->insertObject('#__audioarchive_download_tickets', $row);
		return $token;
	}

	/** @brief Validate a ticket without consuming it, retaining HEAD/range/retry support. */
	public function valid(string $token, int $clipId, int $userId, string $context): bool
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $token))
		{
			return false;
		}
		$db = $this->database;
		return (bool) $db->setQuery('SELECT 1 FROM ' . $db->quoteName('#__audioarchive_download_tickets')
			. ' WHERE ' . $this->condition($token, $clipId, $userId, $context))->loadResult();
	}

	/**
	 * @brief Atomically mark a ticket and update the clip counter once, including concurrent retries.
	 * @return bool Whether this request should emit the download analytics event.
	 */
	public function countOnce(string $token, int $clipId, int $userId, string $context): bool
	{
		$db = $this->database;
		$db->transactionStart();
		try
		{
			$db->setQuery('UPDATE ' . $db->quoteName('#__audioarchive_download_tickets') . ' SET counted = 1 WHERE '
				. $this->condition($token, $clipId, $userId, $context) . ' AND counted = 0')->execute();
			$counted = $db->getAffectedRows() === 1;
			if ($counted)
			{
				$db->setQuery('UPDATE ' . $db->quoteName('#__audioarchive_clips')
					. ' SET download_count = download_count + 1 WHERE id = ' . $clipId)->execute();
				if ($db->getAffectedRows() !== 1)
				{
					throw new \RuntimeException('Download clip no longer exists.');
				}
			}
			$db->transactionCommit();
			return $counted;
		}
		catch (\Throwable $exception)
		{
			$db->transactionRollback();
			throw $exception;
		}
	}

	/** @brief Build the complete ticket binding and expiry predicate. */
	private function condition(string $token, int $clipId, int $userId, string $context): string
	{
		$db = $this->database;
		return 'token_hash = ' . $db->quote(hash('sha256', $token)) . ' AND clip_id = ' . $clipId
			. ' AND user_id = ' . $userId . ' AND ' . $db->quoteName('context') . ' = ' . $db->quote($context)
			. ' AND expires_at >= ' . time();
	}

	/** @brief Include the shared module once even when multiple download controls are rendered. */
	public static function needsScript(): bool
	{
		static $included = false;
		if ($included)
		{
			return false;
		}
		$included = true;
		return true;
	}
}
