<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Store anonymous thumbs-up and thumbs-down ratings.
 */
class RatingService
{
	/**
	 * @brief Construct the rating service.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 * @param User $user Current visitor.
	 */
	public function __construct(
		private DatabaseInterface $database,
		private Registry $params,
		private User $user
	)
	{
	}

	/**
	 * @brief Determine whether the current visitor may submit ratings.
	 *
	 * @return bool True when voting is enabled for the visitor.
	 */
	public function canVote(): bool
	{
		if ((int) $this->params->get('enable_ratings', 1) !== 1)
		{
			return false;
		}

		return match ((string) $this->params->get('rating_permission', 'all'))
		{
			'all' => true,
			'registered' => !$this->user->guest,
			default => false,
		};
	}

	/**
	 * @brief Insert, change, or remove one visitor's vote for a clip.
	 *
	 * @param int $clipId Clip identifier.
	 * @param string $clientId Browser-generated anonymous identifier.
	 * @param int $vote Vote value: -1, 0, or 1.
	 *
	 * @return array{up:int, down:int, score:int, vote:int}
	 */
	public function storeVote(int $clipId, string $clientId, int $vote): array
	{
		if (!$this->canVote())
		{
			throw new \RuntimeException('Voting is not permitted.');
		}

		if ($clipId <= 0 || !in_array($vote, [-1, 0, 1], true))
		{
			throw new \InvalidArgumentException('Invalid rating request.');
		}

		if ($this->user->guest && !preg_match('/^[a-f0-9]{64}$/', $clientId))
		{
			throw new \InvalidArgumentException('Invalid rating request.');
		}

		$voterKey = $this->user->guest
			? 'browser:' . $clientId
			: 'user:' . (int) $this->user->id;
		$voterHash = hash_hmac(
			'sha256',
			$voterKey,
			(string) Factory::getConfig()->get('secret')
		);
		$now = Factory::getDate()->toSql();
		$this->database->transactionStart();

		try
		{
			if ($vote === 0)
			{
				$query = $this->database->getQuery(true)
					->delete($this->database->quoteName('#__audioarchive_ratings'))
					->where($this->database->quoteName('clip_id') . ' = :clipId')
					->where($this->database->quoteName('voter_hash') . ' = :voterHash')
					->bind(':clipId', $clipId, ParameterType::INTEGER)
					->bind(':voterHash', $voterHash, ParameterType::STRING);
				$this->database->setQuery($query)->execute();
			}
			else
			{
				$query = 'INSERT INTO ' . $this->database->quoteName('#__audioarchive_ratings')
					. ' (' . implode(', ', [
						$this->database->quoteName('clip_id'),
						$this->database->quoteName('voter_hash'),
						$this->database->quoteName('vote'),
						$this->database->quoteName('created'),
						$this->database->quoteName('modified'),
					]) . ') VALUES ('
					. implode(', ', [
						(string) $clipId,
						$this->database->quote($voterHash),
						(string) $vote,
						$this->database->quote($now),
						$this->database->quote($now),
					]) . ') ON DUPLICATE KEY UPDATE '
					. $this->database->quoteName('vote') . ' = VALUES(' . $this->database->quoteName('vote') . '), '
					. $this->database->quoteName('modified') . ' = VALUES(' . $this->database->quoteName('modified') . ')';
				$this->database->setQuery($query)->execute();
			}

			$result = $this->getCounts($clipId);
			$result['vote'] = $vote;
			$this->database->transactionCommit();

			return $result;
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}
	}

	/**
	 * @brief Return aggregate votes for one clip.
	 *
	 * @param int $clipId Clip identifier.
	 *
	 * @return array{up:int, down:int, score:int}
	 */
	public function getCounts(int $clipId): array
	{
		$query = $this->database->getQuery(true)
			->select([
				'SUM(CASE WHEN ' . $this->database->quoteName('vote') . ' = 1 THEN 1 ELSE 0 END) AS ' . $this->database->quoteName('up_count'),
				'SUM(CASE WHEN ' . $this->database->quoteName('vote') . ' = -1 THEN 1 ELSE 0 END) AS ' . $this->database->quoteName('down_count'),
			])
			->from($this->database->quoteName('#__audioarchive_ratings'))
			->where($this->database->quoteName('clip_id') . ' = :clipId')
			->bind(':clipId', $clipId, ParameterType::INTEGER);
		$row = $this->database->setQuery($query)->loadObject();
		$up = (int) ($row->up_count ?? 0);
		$down = (int) ($row->down_count ?? 0);

		return [
			'up' => $up,
			'down' => $down,
			'score' => $up - $down,
		];
	}
}
