<?php
namespace Punga\Component\Audioarchive\Site\View\Upload;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Site\Service\ContributionService;
use Punga\Component\Audioarchive\Administrator\Service\UserQuotaService;

\defined('_JEXEC') or die;

/** @brief Render a contributor's Joomla upload form and quota summary. */
class HtmlView extends BaseHtmlView
{
	public $form;
	public $params;
	public array $usage = [];
	public array $quota = [];
	public string $workspace = '';
	public bool $canUpload = false;
	public array $processingGrant = [];

	/** @brief Recheck login, menu access and category eligibility before showing upload controls. */
	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		if ($app->getInput()->getCmd('layout') === 'processing')
		{
			$id = $app->getInput()->getInt('id');
			$grants = (array) $app->getUserState('com_audioarchive.upload.analyses', []);
			$this->processingGrant = (array) ($grants[$id] ?? []);
			(new \Punga\Component\Audioarchive\Site\Service\UploadAnalysisService($db, \Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive'), $app->getIdentity()))->validate($id, $this->processingGrant);
			$app->setHeader('Cache-Control', 'private, no-store', true);
			$this->setLayout('processing');
			$this->getDocument()->getWebAssetManager()->useStyle('com_audioarchive.site')->useScript('com_audioarchive.upload-analysis');
			$this->setDocumentTitle(Text::_('COM_AUDIOARCHIVE_UPLOAD_ANALYSIS_TITLE'));
			parent::display($tpl);
			return;
		}
		$policy = new ContributionService($db, $app->getIdentity());
		$this->params = $policy->settings(true);
		$this->canUpload = $policy->categories($this->params) !== [] && $policy->accessLevels($this->params) !== [];
		$this->form = $this->getModel()->getForm();
		$this->workspace = $policy->route('myclips');
		$service = new UserQuotaService($db, $this->params, $app->getIdentity());
		$this->usage = $service->getUsage((int) $app->getIdentity()->id);
		$this->quota = $service->getEffectiveQuota((int) $app->getIdentity()->id);
		$app->setHeader('Cache-Control', 'private, no-store', true);
		$this->setDocumentTitle(Text::_('COM_AUDIOARCHIVE_FRONTEND_UPLOAD'));
		$this->getDocument()->getWebAssetManager()->useScript('form.validate')->useScript('keepalive')->useStyle('com_audioarchive.site');
		parent::display($tpl);
	}
}
