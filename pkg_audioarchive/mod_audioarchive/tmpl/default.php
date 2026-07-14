<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Joomla\String\StringHelper;

\defined('_JEXEC') or die;

$presentation = in_array((string) $params->get('presentation', 'default'), ['default', 'compact', 'featured'], true)
	? (string) $params->get('presentation', 'default')
	: 'default';
$moduleClass = trim(
	'mod-audioarchive mod-audioarchive--' . $presentation . ' '
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
			$plainDescription = trim(strip_tags((string) $item->description));
			if (StringHelper::strlen($plainDescription) > $descriptionLength)
			{
				$plainDescription = rtrim(StringHelper::substr($plainDescription, 0, $descriptionLength - 1)) . '…';
			}
			?>
			<article class="mod-audioarchive-item <?php echo $showPlayer ? 'has-player' : 'no-player'; ?>">
				<?php if ($showTitle) : ?>
					<h3 class="mod-audioarchive-title">
						<?php if ($linkTitle) : ?><a href="<?php echo $item->detail_url; ?>"><?php endif; ?>
						<?php echo htmlspecialchars((string) $item->title, ENT_QUOTES, 'UTF-8'); ?>
						<?php if ($linkTitle) : ?></a><?php endif; ?>
					</h3>
				<?php endif; ?>

				<?php if ($showPlayer) : ?>
					<div class="mod-audioarchive-player">
						<button
							class="com-audioarchive-play-button"
							type="button"
							aria-controls="<?php echo $audioId; ?>"
							aria-pressed="false"
							aria-label="<?php echo htmlspecialchars(Text::sprintf('MOD_AUDIOARCHIVE_PLAY_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>"
							title="<?php echo htmlspecialchars(Text::sprintf('MOD_AUDIOARCHIVE_PLAY_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>"
							data-audioarchive-play
							data-clip-id="<?php echo (int) $item->id; ?>"
							data-clip-title="<?php echo htmlspecialchars((string) $item->title, ENT_QUOTES, 'UTF-8'); ?>"
							data-play-label="<?php echo htmlspecialchars(Text::sprintf('MOD_AUDIOARCHIVE_PLAY_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>"
							data-pause-label="<?php echo htmlspecialchars(Text::sprintf('MOD_AUDIOARCHIVE_PAUSE_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>"
							data-error-label="<?php echo htmlspecialchars(Text::sprintf('MOD_AUDIOARCHIVE_PLAY_ERROR_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>"
						><span data-audioarchive-icon aria-hidden="true">▶</span></button>
						<audio id="<?php echo $audioId; ?>" class="com-audioarchive-row-audio" preload="none">
							<source src="<?php echo $item->stream_url; ?>" type="<?php echo htmlspecialchars($mime, ENT_QUOTES, 'UTF-8'); ?>">
						</audio>
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
							<li><a href="<?php echo $tagUrl; ?>"><?php echo htmlspecialchars((string) $tag->title, ENT_QUOTES, 'UTF-8'); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ($showDetailLink || $showDownload) : ?>
					<div class="mod-audioarchive-actions">
						<?php if ($showDetailLink) : ?><a href="<?php echo $item->detail_url; ?>"><?php echo Text::_('MOD_AUDIOARCHIVE_OPEN_CLIP'); ?></a><?php endif; ?>
						<?php if ($showDownload) : ?><a href="<?php echo $item->download_url; ?>"><?php echo Text::_('MOD_AUDIOARCHIVE_DOWNLOAD'); ?></a><?php endif; ?>
					</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="visually-hidden" aria-live="polite" aria-atomic="true" data-audioarchive-status></p>
</div>
