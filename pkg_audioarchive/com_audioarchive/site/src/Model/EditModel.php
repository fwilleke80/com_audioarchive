<?php

namespace Willeke\Component\Audioarchive\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Willeke\Component\Audioarchive\Administrator\Model\ClipModel as AdministratorClipModel;
use Willeke\Component\Audioarchive\Site\Service\FrontendEditingService;

\defined('_JEXEC') or die;

/**
 * @brief Frontend model for editing existing Audio Archive clips.
 */
class EditModel extends AdministratorClipModel
{
	/**
	 * @brief Return the administrator clip table from the site model.
	 *
	 * @param string $name Requested table name.
	 * @param string $prefix MVC client prefix.
	 * @param array $options Table construction options.
	 *
	 * @return \Joomla\CMS\Table\Table Clip table.
	 */
	public function getTable($name = 'Clip', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable('Clip', 'Administrator', $options);
	}

	/**
	 * @brief Populate the edited clip identifier from the request.
	 *
	 * @return void
	 */
	protected function populateState()
	{
		$this->setState('edit.id', Factory::getApplication()->getInput()->getInt('id', 0));
	}

	/**
	 * @brief Return the frontend metadata edit form.
	 *
	 * @param array $data Submitted form data.
	 * @param bool $loadData Whether stored data should be loaded.
	 *
	 * @return Form|false Frontend edit form or false.
	 */
	public function getForm($data = [], $loadData = true)
	{
		Form::addFormPath(JPATH_SITE . '/components/com_audioarchive/forms');
		$form = $this->loadForm(
			'com_audioarchive.edit',
			'edit',
			['control' => 'jform', 'load_data' => $loadData]
		);

		if (!$form)
		{
			return false;
		}

		$id = (int) ($data['id'] ?? $this->getState('edit.id', 0));
		$item = $id > 0 ? $this->getItem($id) : null;
		$user = $this->getCurrentUser();

		if ($item === null || !FrontendEditingService::canEditState($user, $item))
		{
			foreach (['state', 'access', 'publish_up', 'publish_down'] as $field)
			{
				$form->removeField($field);
			}
		}

		return $form;
	}

	/**
	 * @brief Load clip data or the most recent invalid submission.
	 *
	 * @return mixed Form data.
	 */
	protected function loadFormData()
	{
		$application = Factory::getApplication();
		$data = $application->getUserState('com_audioarchive.edit.edit.data', []);

		if (empty($data))
		{
			$data = $this->getItem((int) $this->getState('edit.id', 0));
		}

		$this->preprocessData('com_audioarchive.clip', $data);

		return $data;
	}
}
