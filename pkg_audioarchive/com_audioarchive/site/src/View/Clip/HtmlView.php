<?php

namespace Willeke\Component\Audioarchive\Site\View\Clip;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Component\Audioarchive\Site\Model\ClipModel;

\defined('_JEXEC') or die;

/**
 * @brief Public detail view for one audio clip.
 */
class HtmlView extends BaseHtmlView
{
	/** @var object */
	public object $item;

	/** @var Registry */
	public Registry $params;

	/** @var string */
	public string $streamUrl = '';

	/** @var string */
	public string $downloadUrl = '';

	/** @var string */
	public string $archiveUrl = '';

	/** @var string */
	public string $playCountUrl = '';

	/** @var string */
	public string $playCountToken = '';

	/**
	 * @brief Display one public clip.
	 *
	 * @param string|null $tpl Template name.
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		/** @var ClipModel $model */
		$model = $this->getModel();
		$model->setUseExceptions(true);
		$item = $model->getItem();

		if ($item === null)
		{
			throw new \Exception(Text::_('COM_AUDIOARCHIVE_CLIP_NOT_FOUND'), 404);
		}

		$this->item = $item;
		$this->params = $model->getResolvedParams();
		$application = Factory::getApplication();
		$itemId = $application->getInput()->getInt('Itemid', 0);
		$this->streamUrl = Route::_(RouteHelper::getPlaybackRoute((int) $item->id, $itemId));
		$this->downloadUrl = Route::_(RouteHelper::getDownloadRoute((int) $item->id, $itemId));
		$this->archiveUrl = $itemId > 0
			? Route::_('index.php?Itemid=' . $itemId)
			: Route::_('index.php?option=com_audioarchive&view=archive');

		if ((int) $this->params->get('enable_play_counts', 1) === 1)
		{
			$this->playCountUrl = Route::_(RouteHelper::getPlayCountRoute($itemId));
			$this->playCountToken = Session::getFormToken();
		}

		$document = $this->getDocument();
		$document->setTitle((string) $item->title);
		$document->getWebAssetManager()
			->useStyle('com_audioarchive.site')
			->useScript('com_audioarchive.player');
		$canonical = Route::_(
			RouteHelper::getClipRoute((int) $item->id, $itemId),
			false,
			Route::TLS_IGNORE,
			true
		);
		$document->addHeadLink($canonical, 'canonical');

		parent::display($tpl);
	}
}
