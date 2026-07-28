<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$clipId = (int) ($displayData['clipId'] ?? 0);
$up = max(0, (int) ($displayData['up'] ?? 0));
$down = max(0, (int) ($displayData['down'] ?? 0));
$canVote = (bool) ($displayData['canVote'] ?? false);
$title = (string) ($displayData['title'] ?? '');
?>
<div
	class="com-audioarchive-rating"
	data-audioarchive-rating
	data-clip-id="<?php echo $clipId; ?>"
	data-audioarchive-rating-can-vote="<?php echo $canVote ? '1' : '0'; ?>"
>
	<button
		type="button"
		class="com-audioarchive-rating-button"
		data-audioarchive-rating-vote="1"
		aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_AUDIOARCHIVE_RATING_UP_LABEL', $title), ENT_QUOTES, 'UTF-8'); ?>"
		aria-pressed="false"
		<?php echo $canVote ? '' : 'disabled'; ?>
	>
		<svg class="com-audioarchive-rating-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path d="M7 10v10H4a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2h3Zm0 10h9.4a2.5 2.5 0 0 0 2.4-1.8l2-7A2.5 2.5 0 0 0 18.4 8H14l.7-3.2A2.3 2.3 0 0 0 12.5 2L7 10Z" />
		</svg>
		<span data-audioarchive-rating-up><?php echo $up; ?></span>
	</button>
	<button
		type="button"
		class="com-audioarchive-rating-button"
		data-audioarchive-rating-vote="-1"
		aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_AUDIOARCHIVE_RATING_DOWN_LABEL', $title), ENT_QUOTES, 'UTF-8'); ?>"
		aria-pressed="false"
		<?php echo $canVote ? '' : 'disabled'; ?>
	>
		<svg class="com-audioarchive-rating-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<path d="M7 14V4H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h3Zm0-10h9.4a2.5 2.5 0 0 1 2.4 1.8l2 7a2.5 2.5 0 0 1-2.4 3.2H14l.7 3.2a2.3 2.3 0 0 1-2.2 2.8L7 14Z" />
		</svg>
		<span data-audioarchive-rating-down><?php echo $down; ?></span>
	</button>
	<span class="visually-hidden" aria-live="polite" data-audioarchive-rating-status></span>
</div>
