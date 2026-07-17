<?php

namespace Willeke\Component\Audioarchive\Site\Controller;

use Joomla\CMS\MVC\Controller\FormController;
use Willeke\Component\Audioarchive\Site\Model\EditModel;
use Willeke\Component\Audioarchive\Site\Service\FrontendEditingService;

\defined('_JEXEC') or die;

/**
 * @brief Handle frontend clip editing.
 */
class EditController extends FormController
{
	/** @var string */
	protected $context = 'edit';

	/** @var string */
	protected $view_item = 'edit';

	/** @var string */
	protected $view_list = 'archive';

	/** @var string */
	protected $text_prefix = 'COM_AUDIOARCHIVE';

	/**
	 * @brief Check whether the current user may edit the requested clip.
	 *
	 * @param array $data Candidate form data.
	 * @param string $key Primary-key field name.
	 *
	 * @return bool True when editing is permitted.
	 */
	protected function allowEdit($data = [], $key = 'id')
	{
		if (!FrontendEditingService::isEnabled($this->app))
		{
			return false;
		}

		$id = (int) ($data[$key] ?? $this->input->getInt($key));

		if ($id <= 0)
		{
			return false;
		}

		/** @var EditModel $model */
		$model = $this->getModel();
		$item = $model->getItem($id);

		return $item !== false
			&& $item !== null
			&& FrontendEditingService::canEdit($this->app->getIdentity(), $item);
	}

	/**
	 * @brief Check edit permission and category-move permission before saving.
	 *
	 * @param array $data Validated or submitted form data.
	 * @param string $key Primary-key field name.
	 *
	 * @return bool True when saving is permitted.
	 */
	protected function allowSave($data, $key = 'id')
	{
		if (!$this->allowEdit($data, $key))
		{
			return false;
		}

		$id = (int) ($data[$key] ?? 0);
		/** @var EditModel $model */
		$model = $this->getModel();
		$item = $model->getItem($id);

		if ($item === false || $item === null)
		{
			return false;
		}

		$targetCategory = (int) ($data['catid'] ?? $item->catid);

		if ($targetCategory === (int) $item->catid)
		{
			return true;
		}

		$user = $this->app->getIdentity();
		$targetAsset = 'com_audioarchive.category.' . $targetCategory;

		return $targetCategory > 0
			&& (
				$user->authorise('core.create', $targetAsset)
				|| $user->authorise('core.edit', $targetAsset)
			);
	}

	/**
	 * @brief Preserve the menu context in frontend edit redirects.
	 *
	 * @param int|null $recordId Edited record identifier.
	 * @param string $urlVar URL key used for the identifier.
	 *
	 * @return string Query suffix.
	 */
	protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
	{
		$append = parent::getRedirectToItemAppend($recordId, $urlVar);
		$itemId = $this->input->getInt('Itemid', 0);

		if ($itemId > 0)
		{
			$append .= '&Itemid=' . $itemId;
		}

		return $append;
	}

	/**
	 * @brief Preserve the menu context when no explicit return URL is supplied.
	 *
	 * @return string Query suffix.
	 */
	protected function getRedirectToListAppend()
	{
		$append = parent::getRedirectToListAppend();
		$itemId = $this->input->getInt('Itemid', 0);

		if ($itemId > 0)
		{
			$append .= '&Itemid=' . $itemId;
		}

		return $append;
	}
}
