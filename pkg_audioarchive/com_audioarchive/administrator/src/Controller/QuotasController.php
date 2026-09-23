<?php
namespace Punga\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** @brief Validate and save component-owned quota rules. */
class QuotasController extends BaseController
{
	/** @brief Save one group rule after CSRF, ACL and bounded integer validation. */
	public function save(): void
	{
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
		$app = Factory::getApplication();
		if (!$app->getIdentity()->authorise('core.options', 'com_audioarchive'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		try
		{
			$input = $app->getInput()->post;
			$groupId = $input->getInt('group_id');
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			if ($groupId <= 0 || !$db->setQuery('SELECT id FROM ' . $db->quoteName('#__usergroups') . ' WHERE id = ' . $groupId)->loadResult())
			{
				throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_QUOTA_INVALID'));
			}
			$bytes = $this->limit($input->getString('storage_mode'), $input->getString('storage_value'), 1048576);
			$clips = $this->limit($input->getString('clips_mode'), $input->getString('clips_value'), 1);
			$now = $db->quote(Factory::getDate()->toSql());
			$db->setQuery('INSERT INTO ' . $db->quoteName('#__audioarchive_group_quotas')
				. ' (group_id, storage_quota_bytes, clip_quota, created, modified) VALUES (' . $groupId . ', '
				. ($bytes === null ? 'NULL' : $bytes) . ', ' . ($clips === null ? 'NULL' : $clips) . ', ' . $now . ', ' . $now . ')'
				. ' ON DUPLICATE KEY UPDATE storage_quota_bytes=VALUES(storage_quota_bytes), clip_quota=VALUES(clip_quota), modified=VALUES(modified)')->execute();
			$app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_QUOTA_SAVED'));
		}
		catch (\Throwable $exception)
		{
			$app->enqueueMessage($exception->getMessage(), 'error');
		}
		$this->setRedirect(Route::_('index.php?option=com_audioarchive&view=quotas', false));
	}

	/** @brief Parse explicit inherit/unlimited/custom controls without magic UI numbers. */
	private function limit(string $mode, string $value, int $multiplier): ?int
	{
		if ($mode === 'inherit')
		{
			return null;
		}
		if ($mode === 'unlimited')
		{
			return -1;
		}
		if ($mode !== 'custom' || !preg_match('/^\d{1,10}$/', $value) || (int) $value > 2147483647)
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_QUOTA_INVALID'));
		}
		return (int) $value * $multiplier;
	}
}
