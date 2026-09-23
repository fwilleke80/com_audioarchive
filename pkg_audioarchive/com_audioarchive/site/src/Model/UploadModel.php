<?php
namespace Punga\Component\Audioarchive\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Punga\Component\Audioarchive\Administrator\Model\ClipModel;
use Punga\Component\Audioarchive\Site\Service\ContributionService;

\defined('_JEXEC') or die;

/** @brief Frontend creation form using the existing clip model and upload pipeline. */
class UploadModel extends ClipModel
{
	/** @brief Always use the administrator clip table. */
	public function getTable($name = 'Clip', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable('Clip', 'Administrator', $options);
	}

	/** @brief This model only creates new clips. */
	protected function populateState()
	{
		$this->setState('upload.id', 0);
	}

	/** @brief Load Joomla fields and apply current contributor/menu restrictions. */
	public function getForm($data = [], $loadData = true)
	{
		Form::addFormPath(JPATH_SITE . '/components/com_audioarchive/forms');
		$policy = new ContributionService($this->getDatabase(), $this->getCurrentUser());
		$params = $policy->settings(true);
		$form = $this->loadForm('com_audioarchive.clip', 'upload', ['control' => 'jform', 'load_data' => $loadData]);
		if ($form)
		{
			$policy->configureForm($form, $params);
		}
		return $form;
	}

	/** @brief Restore input after validation errors or choose permitted defaults. */
	protected function loadFormData()
	{
		$policy = new ContributionService($this->getDatabase(), $this->getCurrentUser());
		$params = $policy->settings(true);
		$categories = $policy->categories($params);
		$levels = $policy->accessLevels($params);
		$allowed = array_map(static fn(object $row): int => (int) $row->id, $levels);
		$defaultAccess = (int) $params->get('upload_default_access', 0);
		$defaultAccess = in_array($defaultAccess, $allowed, true) ? $defaultAccess : ($allowed[0] ?? 0);
		$data = Factory::getApplication()->getUserState('com_audioarchive.upload.data', []);
		if (!$data)
		{
			$data = ['id' => 0, 'catid' => (int) ($categories[0]->id ?? 0), 'access' => $defaultAccess,
				'visibility_mode' => $params->get('allow_private_clips', 0) ? $params->get('upload_default_visibility', 'normal') : 'normal',
				'state' => (int) $params->get('upload_default_state', 0)];
		}
		$this->preprocessData('com_audioarchive.clip', $data);
		return $data;
	}
}
