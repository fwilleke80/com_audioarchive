<?php

namespace Punga\Component\Audioarchive\Site\View\Edit;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;
use Punga\Component\Audioarchive\Site\Model\EditModel;
use Punga\Component\Audioarchive\Site\Service\FrontendEditingService;

\defined('_JEXEC') or die;

/**
 * @brief Frontend clip edit form.
 */
class HtmlView extends BaseHtmlView
{
	/** @var string Rendered featured player for an accessible clip. */
	public string $playerHtml = '';

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

		$params = clone \Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive');
		$override = $application->getMenu()->getActive()?->getParams()->get('player_pause_behavior', '');
		if (in_array($override, ['pause', 'stop'], true))
		{
			$params->set('player_pause_behavior', $override);
		}
		$media = new \Punga\Component\Audioarchive\Site\Service\PublicMediaService(
			Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), $params, $identity
		);
		$playable = $media->getPublicClip((int) $item->id);
		if ($playable !== null)
		{
			$player = [
				'audioId' => 'audioarchive-edit-' . (int) $item->id,
				'clipId' => (int) $item->id,
				'title' => (string) $item->title,
				'streamUrl' => Route::_(RouteHelper::getPlaybackRoute((int) $item->id, $this->itemId)),
				'mime' => (string) ($playable->mime_type ?? ''),
				'params' => $params,
				'presentation' => 'featured',
				'labels' => [
					'play' => Text::sprintf('COM_AUDIOARCHIVE_PLAY_LABEL', $item->title),
					'pause' => Text::sprintf('COM_AUDIOARCHIVE_PAUSE_LABEL', $item->title),
					'seek' => Text::_('COM_AUDIOARCHIVE_PLAYER_SEEK'),
					'mute' => Text::_('COM_AUDIOARCHIVE_PLAYER_MUTE'),
					'unmute' => Text::_('COM_AUDIOARCHIVE_PLAYER_UNMUTE'),
				'volume' => Text::_('COM_AUDIOARCHIVE_PLAYER_VOLUME'),
				'fallback' => Text::_('COM_AUDIOARCHIVE_PLAYER_FALLBACK'),
				'waveformLoading' => Text::_('COM_AUDIOARCHIVE_WAVEFORM_LOADING'),
				'spectrogramLoading' => Text::_('COM_AUDIOARCHIVE_SPECTROGRAM_LOADING'),
				'frequencyProfileLoading' => Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_LOADING'),
				'analysisView' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_VIEW'),
				'waveform' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_WAVEFORM'),
				'spectrum' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_SPECTRUM'),
				'frequencyProfile' => Text::_('COM_AUDIOARCHIVE_ANALYSIS_FREQUENCY_PROFILE'),
				'frequencyProfileSummary' => Text::_('COM_AUDIOARCHIVE_FREQUENCY_PROFILE_SUMMARY'),
				'varispeed' => Text::_('COM_AUDIOARCHIVE_PLAYER_VARISPEED'),
				'varispeedReset' => Text::_('COM_AUDIOARCHIVE_PLAYER_VARISPEED_RESET'),
				'varispeedNormal' => Text::_('COM_AUDIOARCHIVE_PLAYER_VARISPEED_NORMAL'),
				'varispeedOctave' => Text::_('COM_AUDIOARCHIVE_PLAYER_VARISPEED_OCTAVE_ABBR'),
				],
			];
			foreach (['waveform' => 'waveformUrl', 'spectrogram' => 'spectrogramUrl', 'frequency_profile' => 'frequencyProfileUrl'] as $type => $key)
			{
				if ($media->getPublicAnalysis((int) $item->id, $type) !== null)
				{
					$player[$key] = Route::_(RouteHelper::getAnalysisRoute((int) $item->id, $type, $this->itemId));
				}
			}
			$this->playerHtml = \Joomla\CMS\Layout\LayoutHelper::render('player.unified', $player, null, ['component' => 'com_audioarchive', 'client' => 0]);
			$this->getDocument()->getWebAssetManager()->useStyle('com_audioarchive.player-style')->useScript('com_audioarchive.player');
		}

		parent::display($tpl);
	}
}
