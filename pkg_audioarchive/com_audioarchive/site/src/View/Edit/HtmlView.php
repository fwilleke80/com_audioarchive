<?php

namespace Willeke\Component\Audioarchive\Site\View\Edit;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\EditModel;
use Willeke\Component\Audioarchive\Site\Service\FrontendEditingService;

\defined('_JEXEC') or die;

/**
 * @brief Frontend clip edit form.
 */
class HtmlView extends BaseHtmlView
{
	/** @var Form */
	public Form $form;

	/** @var object */
	public object $item;

	/** @var int */
	public int $itemId = 0;

	/** @var string */
	public string $returnValue = '';

	/**
	 * @brief Display the frontend edit form after repeating all access checks.
	 *
	 * @param string|null $tpl Template name.
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		$application = Factory::getApplication();
		$identity = $application->getIdentity();
		$id = $application->getInput()->getInt('id', 0);

		/** @var EditModel $model */
		$model = $this->getModel();
		$model->setUseExceptions(true);
		$item = $model->getItem($id);

		if (
			!FrontendEditingService::isEnabled($application)
			|| $item === false
			|| $item === null
			|| !FrontendEditingService::canEdit($identity, $item)
		)
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$form = $model->getForm([], true);

		if (!$form)
		{
			throw new \RuntimeException($model->getError(), 500);
		}

		$this->item = $item;
		$this->form = $form;
		$this->itemId = $application->getInput()->getInt('Itemid', 0);
		$return = $application->getInput()->get('return', '', 'base64');
		$decodedReturn = $return !== '' ? base64_decode($return, true) : false;

		if (!is_string($decodedReturn) || !Uri::isInternal($decodedReturn))
		{
			$decodedReturn = Route::_(
				RouteHelper::getClipRoute((int) $item->id, $this->itemId),
				false
			);
			$return = base64_encode($decodedReturn);
		}

		$this->returnValue = $return;
		$this->setDocumentTitle(Text::sprintf('COM_AUDIOARCHIVE_FRONTEND_EDIT_TITLE', (string) $item->title));
		$this->getDocument()->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useScript('keepalive')
			->useScript('form.validate');

		parent::display($tpl);
	}
}
