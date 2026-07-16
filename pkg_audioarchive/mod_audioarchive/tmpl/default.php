<?php

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\String\StringHelper;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;

\defined('_JEXEC') or die;

$modulePresentation = in_array((string) $params->get('presentation', 'default'), ['default', 'compact', 'featured'], true)
	? (string) $params->get('presentation', 'default')
	: 'default';
$playerPresentation = in_array((string) $params->get('player_presentation', 'default'), ['minimal', 'compact', 'default', 'featured'], true)
	? (string) $params->get('player_presentation', 'default')
	: 'default';
$preferredDataView = in_array((string) $params->get('preferred_data_view', 'waveform'), ['waveform', 'spectrogram'], true)
	? (string) $params->get('preferred_data_view', 'waveform')
	: 'waveform';
$moduleClass = trim(
	'mod-audioarchive mod-audioarchive--' . $modulePresentation . ' '
	. htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8')
);
$showTitle = (int) $params->get('show_title', 1) === 1;
$linkTitle = (int) $params->get('link_title', 1) === 1;
$showPlayer = (int) $params->get('show_player', 1) === 1;
$showDuration = (int) $params->get('show_duration', 1) === 1;
$showDate = (int) $params->get('show_date', 1) === 1;
$showCategory = (int) $params->get('show_category', 0) === 1;
$showTags = (int) $params->get('show_tags', 1) === 1;
$showDescription = (int) $params->get('show_description', 0) === 1;
$showDetailLink = (int) $params->get('show_detail_link', 1) === 1;
$showDownload = (int) $params->get('show_download', 0) === 1;
$showCounts = (int) $params->get('show_counts', 0) === 1;
$descriptionLength = max(20, (int) $params->get('description_length', 160));
$displayDate = (string) $params->get('display_date', 'uploaded');
$widePlayer = $playerPresentation !== 'minimal';
$componentParams = ComponentHelper::getParams('com_audioarchive');
?>
<div
	class="com-audioarchive <?php echo $moduleClass; ?>"
	data-audioarchive-play-count-url="<?php echo htmlspecialchars($playCountUrl, ENT_QUOTES, 'UTF-8'); ?>"
	data-audioarchive-token-name="<?php echo htmlspecialchars($playCountToken, ENT_QUOTES, 'UTF-8'); ?>"
	data-audioarchive-status-playing="<?php echo htmlspecialchars(Text::_('MOD_AUDIOARCHIVE_STATUS_PLAYING'), ENT_QUOTES, 'UTF-8'); ?>"
	data-audioarchive-status-paused="<?php echo htmlspecialchars(Text::_('MOD_AUDIOARCHIVE_STATUS_PAUSED'), ENT_QUOTES, 'UTF-8'); ?>"
	data-audioarchive-status-error="<?php echo htmlspecialchars(Text::_('MOD_AUDIOARCHIVE_STATUS_ERROR'), ENT_QUOTES, 'UTF-8'); ?>"
>
	<div class="mod-audioarchive-items">
		<?php foreach ($items as $item) : ?>
			<?php
			$totalSeconds = max(0, (int) floor((int) $item->duration_ms / 1000));
			$duration = $totalSeconds >= 3600 ? gmdate('H:i:s', $totalSeconds) : gmdate('i:s', $totalSeconds);
			$audioId = 'mod-audioarchive-player-' . (int) $module->id . '-' . (int) $item->id;
			$mime = trim((string) $item->mime_type) ?: 'application/octet-stream';
			$dateValue = match ($displayDate)
			{
				'recorded' => $item->recorded_at,
				'published' => $item->publish_up,
				default => $item->uploaded_at,
			};
			$itemShowDownload = $showDownload && !empty($item->can_download) && (string) $item->download_url !== '';
			$plainDescription = trim(strip_tags((string) $item->description));

			if (StringHelper::strlen($plainDescription) > $descriptionLength)
			{
				$plainDescription = rtrim(StringHelper::substr($plainDescription, 0, $descriptionLength - 1)) . '…';
			}
			?>
			<article class="mod-audioarchive-item <?php echo $showPlayer ? 'has-player' : 'no-player'; ?><?php echo $showPlayer && $widePlayer ? ' has-wide-player' : ''; ?> player-presentation-<?php echo htmlspecialchars($playerPresentation, ENT_QUOTES, 'UTF-8'); ?>">
				<?php if ($showTitle) : ?>
					<h3 class="mod-audioarchive-title">
						<?php if ($linkTitle) : ?><a href="<?php echo $item->detail_url; ?>"><?php endif; ?>
						<?php echo htmlspecialchars((string) $item->title, ENT_QUOTES, 'UTF-8'); ?>
						<?php if ($linkTitle) : ?></a><?php endif; ?>
					</h3>
				<?php endif; ?>

				<?php if ($showPlayer) : ?>
					<div class="mod-audioarchive-player mod-audioarchive-player--unified">
						<?php
						echo LayoutHelper::render(
							'player.unified',
							[
								'audioId' => $audioId,
								'clipId' => (int) $item->id,
								'title' => (string) $item->title,
								'streamUrl' => (string) $item->stream_url,
								'waveformUrl' => (string) ($item->waveform_url ?? ''),
								'spectrogramUrl' => (string) ($item->spectrogram_url ?? ''),
								'mime' => $mime,
								'params' => $componentParams,
								'presentation' => $playerPresentation,
								'preferredAnalysisView' => $preferredDataView,
								'labels' => [
									'play' => Text::sprintf('MOD_AUDIOARCHIVE_PLAY_LABEL', $item->title),
									'pause' => Text::sprintf('MOD_AUDIOARCHIVE_PAUSE_LABEL', $item->title),
									'seek' => Text::_('MOD_AUDIOARCHIVE_PLAYER_SEEK'),
									'mute' => Text::_('MOD_AUDIOARCHIVE_PLAYER_MUTE'),
									'unmute' => Text::_('MOD_AUDIOARCHIVE_PLAYER_UNMUTE'),
									'volume' => Text::_('MOD_AUDIOARCHIVE_PLAYER_VOLUME'),
									'fallback' => Text::_('MOD_AUDIOARCHIVE_PLAYER_FALLBACK'),
									'waveformLoading' => Text::_('MOD_AUDIOARCHIVE_WAVEFORM_LOADING'),
									'spectrogramLoading' => Text::_('MOD_AUDIOARCHIVE_SPECTROGRAM_LOADING'),
									'analysisView' => Text::_('MOD_AUDIOARCHIVE_ANALYSIS_VIEW'),
									'waveform' => Text::_('MOD_AUDIOARCHIVE_ANALYSIS_WAVEFORM'),
									'spectrum' => Text::_('MOD_AUDIOARCHIVE_ANALYSIS_SPECTRUM'),
								],
							],
							JPATH_ROOT . '/components/com_audioarchive/layouts'
						);
						?>
					</div>
				<?php endif; ?>

				<?php if ($showDuration || $showDate || $showCategory || $showCounts) : ?>
					<dl class="mod-audioarchive-meta">
						<?php if ($showDuration) : ?><div><dt><?php echo Text::_('MOD_AUDIOARCHIVE_DURATION'); ?></dt><dd><time datetime="PT<?php echo $totalSeconds; ?>S"><?php echo $duration; ?></time></dd></div><?php endif; ?>
						<?php if ($showDate && $dateValue) : ?><div><dt><?php echo Text::_('MOD_AUDIOARCHIVE_DATE'); ?></dt><dd><?php echo HTMLHelper::_('date', $dateValue, Text::_('DATE_FORMAT_LC4')); ?></dd></div><?php endif; ?>
						<?php if ($showCategory) : ?><div><dt><?php echo Text::_('MOD_AUDIOARCHIVE_CATEGORY'); ?></dt><dd><?php echo htmlspecialchars((string) $item->category_title, ENT_QUOTES, 'UTF-8'); ?></dd></div><?php endif; ?>
						<?php if ($showCounts) : ?><div><dt><?php echo Text::_('MOD_AUDIOARCHIVE_PLAYS'); ?></dt><dd><?php echo (int) $item->play_count; ?></dd></div><div><dt><?php echo Text::_('MOD_AUDIOARCHIVE_DOWNLOADS'); ?></dt><dd><?php echo (int) $item->download_count; ?></dd></div><?php endif; ?>
					</dl>
				<?php endif; ?>

				<?php if ($showDescription && $plainDescription !== '') : ?><p class="mod-audioarchive-description"><?php echo htmlspecialchars($plainDescription, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

				<?php if ($showTags && $item->tags) : ?>
					<ul class="com-audioarchive-tag-list mod-audioarchive-tags">
						<?php foreach ($item->tags as $tag) : ?>
							<?php $tagUrl = Route::_(RouteHelper::getArchiveRoute((int) $item->itemid, ['tags' => (string) $tag->alias])); ?>
							<?php $tagDescription = trim((string) ($tag->description_text ?? '')); ?>
							<li><a href="<?php echo $tagUrl; ?>"<?php if ($tagDescription !== '') : ?> title="<?php echo htmlspecialchars($tagDescription, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo htmlspecialchars((string) $tag->title, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ($showDetailLink || $itemShowDownload) : ?>
					<div class="mod-audioarchive-actions">
						<?php if ($showDetailLink) : ?><a href="<?php echo $item->detail_url; ?>"><?php echo Text::_('MOD_AUDIOARCHIVE_OPEN_CLIP'); ?></a><?php endif; ?>
						<?php if ($itemShowDownload) : ?><a href="<?php echo $item->download_url; ?>"><?php echo Text::_('MOD_AUDIOARCHIVE_DOWNLOAD'); ?></a><?php endif; ?>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="visually-hidden" aria-live="polite" aria-atomic="true" data-audioarchive-status></p>
</div>
