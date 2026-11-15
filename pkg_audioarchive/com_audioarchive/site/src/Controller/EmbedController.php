<?php

namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;
use Punga\Component\Audioarchive\Site\Service\DownloadAccessService;
use Punga\Component\Audioarchive\Site\Service\EmbedService;

\defined('_JEXEC') or die;

/** @brief Serve isolated public players and recheck embedding on every media/event request. */
class EmbedController extends StreamController
{
	/** @brief Render a complete, template-free embed document. */
	public function player(): void
	{
		$app = Factory::getApplication();
		$id = $app->getInput()->getInt('id');
		$clip = $this->requireClip($id);
		$params = ComponentHelper::getParams('com_audioarchive');
		$presentation = EmbedService::presentation($app->getInput()->getCmd('presentation', 'default'));
		$data = [
			'audioId' => 'audioarchive-embed-' . $id, 'clipId' => $id, 'title' => (string) $clip->title,
			'streamUrl' => EmbedService::url('play', $id), 'presentation' => $presentation,
			'mime' => (string) $clip->mime_type, 'durationMs' => (int) $clip->duration_ms, 'params' => $params,
		];
		$media = EmbedService::media();
		foreach (['waveform' => 'waveformUrl', 'spectrogram' => 'spectrogramUrl', 'frequency_profile' => 'frequencyProfileUrl'] as $type => $key)
		{
			if ($presentation === 'featured' && $media->getPublicAnalysis($id, $type) !== null)
			{
				$data[$key] = EmbedService::url('analysis', $id, ['type' => $type]);
			}
		}
		$data['labels'] = [];
		foreach (['play' => 'PLAY_LABEL', 'pause' => 'PAUSE_LABEL', 'seek' => 'PLAYER_SEEK', 'mute' => 'PLAYER_MUTE',
			'unmute' => 'PLAYER_UNMUTE', 'fallback' => 'PLAYER_FALLBACK', 'waveformLoading' => 'WAVEFORM_LOADING',
			'spectrogramLoading' => 'SPECTROGRAM_LOADING', 'frequencyProfileLoading' => 'FREQUENCY_PROFILE_LOADING',
			'analysisView' => 'ANALYSIS_VIEW', 'waveform' => 'ANALYSIS_WAVEFORM', 'spectrum' => 'ANALYSIS_SPECTRUM',
			'frequencyProfile' => 'ANALYSIS_FREQUENCY_PROFILE', 'frequencyProfileSummary' => 'FREQUENCY_PROFILE_SUMMARY',
			'varispeed' => 'PLAYER_VARISPEED', 'varispeedReset' => 'PLAYER_VARISPEED_RESET',
			'varispeedNormal' => 'PLAYER_VARISPEED_NORMAL', 'varispeedOctave' => 'PLAYER_VARISPEED_OCTAVE_ABBR'] as $key => $suffix)
		{
			$data['labels'][$key] = in_array($key, ['play', 'pause'], true)
				? Text::sprintf('COM_AUDIOARCHIVE_' . $suffix, (string) $clip->title)
				: Text::_('COM_AUDIOARCHIVE_' . $suffix);
		}
		$html = LayoutHelper::render('player.unified', $data, null, ['component' => 'com_audioarchive', 'client' => 0]);
		$detailUrl = Route::_(RouteHelper::getClipRoute($id), false, Route::TLS_IGNORE, true);
		$downloadUrl = DownloadAccessService::canDownload($params, new User()) ? EmbedService::url('download', $id) : '';
		$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		$assetRoot = Uri::root() . 'media/com_audioarchive/';
		$eventUrl = EmbedService::url('countPlay', $id);
		$eventToken = EmbedService::token($id, 'play');
		while (ob_get_level() > 0)
		{
			ob_end_clean();
		}
		// This standalone public document alone is frameable; normal Joomla pages are untouched.
		header_remove('X-Frame-Options');
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: private, no-store');
		header('X-Content-Type-Options: nosniff');
		header('Content-Security-Policy: frame-ancestors *; base-uri \'self\'; object-src \'none\'');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		require JPATH_COMPONENT . '/tmpl/embed/document.php';
		$app->close();
	}

	/** @brief Stream only if embedding is still permitted. */
	public function play(): void
	{
		$this->requireClip(Factory::getApplication()->getInput()->getInt('id'));
		parent::play();
	}

	/** @brief Enforce embedding and anonymous download permissions. */
	public function download(): void
	{
		$this->requireClip(Factory::getApplication()->getInput()->getInt('id'));
		if (!DownloadAccessService::canDownload(ComponentHelper::getParams('com_audioarchive'), new User()))
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_EMBED_UNAVAILABLE'));
		}
		parent::download();
	}

	/** @brief Guard analysis requests against permission changes after the frame loaded. */
	public function analysis(): void
	{
		$this->requireClip(Factory::getApplication()->getInput()->getInt('id'));
		parent::analysis();
	}

	/** @brief Count actual embedded playback and emit only audio.play_embedded. */
	public function countPlay(): void
	{
		$clip = $this->eventClip('play');
		$counted = false;
		if ((int) ComponentHelper::getParams('com_audioarchive')->get('enable_play_counts', 1) === 1
			&& $this->shouldCount('play', (int) $clip->id, 30))
		{
			EmbedService::media()->incrementPlayCount((int) $clip->id);
			$this->recordPungaAnalyticsEvent('audio.play_embedded', $clip);
			$counted = true;
		}
		$this->sendJson(200, ['success' => true, 'counted' => $counted]);
	}

	/** @brief Record successful embed-code copying, not opening or previewing the dialog. */
	public function created(): void
	{
		$clip = $this->eventClip('copy');
		$this->recordPungaAnalyticsEvent('audio.embed', $clip);
		$this->sendJson(200, ['success' => true]);
	}

	/** @brief Validate a cookie-independent event POST before dispatch. */
	private function eventClip(string $purpose): object
	{
		$input = Factory::getApplication()->getInput();
		if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
		{
			$this->sendJson(405, ['success' => false]);
		}
		$id = $input->getInt('id');
		if (!EmbedService::validToken($input->post->getString('embed_token'), $id, $purpose))
		{
			$this->sendJson(403, ['success' => false]);
		}
		return $this->requireClip($id);
	}

	/** @brief Return only a public, published, explicitly embeddable clip or an opaque error. */
	private function requireClip(int $id): object
	{
		$clip = EmbedService::clip($id);
		if ($clip === null)
		{
			$this->sendError(404, Text::_('COM_AUDIOARCHIVE_EMBED_UNAVAILABLE'));
		}
		return $clip;
	}
}
