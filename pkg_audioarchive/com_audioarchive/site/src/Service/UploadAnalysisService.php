<?php
namespace Punga\Component\Audioarchive\Site\Service;

use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Punga\Component\Audioarchive\Administrator\Service\Analysis\AnalysisJobService;

\defined('_JEXEC') or die;

/** @brief Restrict automatic processing to server-issued upload receipts in the current user's session. */
final class UploadAnalysisService
{
	/** @brief Bind the database, component policy and authenticated uploader. */
	public function __construct(private DatabaseInterface $db, private Registry $params, private User $user)
	{
	}

	/** @brief Capture the jobs of a just-created owned clip; caller stores this receipt server-side. */
	public function grant(int $clipId, string $return): array
	{
		$this->assertOwner($clipId);
		$jobs = $this->db->setQuery('SELECT id FROM ' . $this->db->quoteName('#__audioarchive_jobs')
			. ' WHERE clip_id=' . $clipId . " AND state='pending' AND job_type IN ('generate_analysis_waveform','generate_analysis_spectrogram','generate_analysis_frequency_profile') ORDER BY id")->loadColumn() ?: [];
		return ['user' => (int) $this->user->id, 'clip' => $clipId, 'jobs' => array_map('intval', $jobs), 'expires' => time() + 86400, 'return' => $return];
	}

	/** @brief Validate receipt identity, expiry and current ownership independently of edit/process ACL. */
	public function validate(int $clipId, array $grant): void
	{
		if ((int) $this->user->id <= 0 || (int) ($grant['user'] ?? 0) !== (int) $this->user->id
			|| (int) ($grant['clip'] ?? 0) !== $clipId || (int) ($grant['expires'] ?? 0) < time()
			|| !is_array($grant['jobs'] ?? null) || count($grant['jobs']) > 10)
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		$this->assertOwner($clipId);
	}

	/** @brief Process one granted job and return only safe frontend state, never internal error paths. */
	public function process(int $clipId, array $grant): array
	{
		$this->validate($clipId, $grant);
		$ids = array_values(array_filter(array_map('intval', $grant['jobs']), static fn(int $id): bool => $id > 0));
		if ($ids === [])
		{
			return ['done' => true, 'failed' => false, 'processed' => false];
		}
		$result = (new AnalysisJobService($this->db, $this->params, $this->user))->processGrantedForClip($clipId, $ids);
		$states = $this->db->setQuery('SELECT state FROM ' . $this->db->quoteName('#__audioarchive_jobs') . ' WHERE clip_id=' . $clipId . ' AND id IN (' . implode(',', $ids) . ')')->loadColumn() ?: [];
		return ['done' => !array_intersect(['pending', 'running'], $states), 'failed' => in_array('failed', $states, true), 'processed' => (bool) ($result['processed'] ?? false)];
	}

	/** @brief Ownership grants this narrow upload operation, never access to another user's queue. */
	private function assertOwner(int $clipId): void
	{
		$clip = $this->db->setQuery('SELECT created_by, state FROM ' . $this->db->quoteName('#__audioarchive_clips') . ' WHERE id=' . $clipId)->loadObject();
		if (!$clip || (int) $this->user->id <= 0 || (int) $clip->created_by !== (int) $this->user->id || (int) $clip->state === -2)
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}
}
