<?php
namespace Punga\Component\Audioarchive\Site\View\Myclips;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Administrator\Service\ClipAccessService;
use Punga\Component\Audioarchive\Administrator\Service\UserQuotaService;
use Punga\Component\Audioarchive\Site\Service\ContributionService;
use Punga\Component\Audioarchive\Site\Service\FrontendEditingService;

\defined('_JEXEC') or die;

/** @brief Display the authenticated owner's contribution workspace. */
class HtmlView extends BaseHtmlView
{
	public array $items = [];
	public array $categories = [];
	public array $quota = [];
	public array $usage = [];
	public $pagination;
	public $params;
	public string $uploadUrl = '';

	/** @brief Add actual ACL decisions and quotas to owned clip rows. */
	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();
		if ((int) $user->id <= 0)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_LOGIN_REQUIRED'), 403);
		}
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$this->params = clone ComponentHelper::getParams('com_audioarchive');
		$menu = $app->getMenu()->getActive();
		if (($menu->query['view'] ?? '') === 'myclips')
		{
			$this->params->set('myclips_show_quota', $menu->getParams()->get('myclips_show_quota', 1));
		}
		$model = $this->getModel();
		$this->items = $model->getItems() ?: [];
		$this->categories = $model->getCategories();
		$this->pagination = $model->getPagination();
		$access = new ClipAccessService($db, $user);
		foreach ($this->items as $item)
		{
			$item->canView = (int) $item->is_available === 1 && $access->canView($item);
			$item->canEdit = FrontendEditingService::isEnabled($app) && $access->canEdit($item);
			$item->canTrash = (int) $item->state !== -2 && $access->canEditState($item);
			$item->canDelete = (int) $item->state === -2 && $access->canDelete($item);
		}
		$quota = new UserQuotaService($db, $this->params, $user);
		$this->quota = $quota->getEffectiveQuota((int) $user->id);
		$this->usage = $quota->getUsage((int) $user->id);
		$policy = new ContributionService($db, $user);
		if ($this->params->get('frontend_upload_enabled', 1) && $policy->categories($this->params) !== [])
		{
			$this->uploadUrl = $policy->route('upload');
		}
		$app->setHeader('Cache-Control', 'private, no-store', true);
		$this->setDocumentTitle(Text::_('COM_AUDIOARCHIVE_MY_CLIPS'));
		parent::display($tpl);
	}
}
