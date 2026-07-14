<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$items = (array) ($displayData['items'] ?? []);
$presentation = (string) ($displayData['presentation'] ?? 'cards');
$presentation = in_array($presentation, ['cards', 'list', 'compact'], true)
	? $presentation
	: 'cards';
$showDescriptions = (bool) ($displayData['show_descriptions'] ?? true);
$showCounts = (bool) ($displayData['show_counts'] ?? false);
?>
<div class="com-audioarchive-tag-directory com-audioarchive-tag-directory--<?php echo htmlspecialchars($presentation, ENT_QUOTES, 'UTF-8'); ?>">
	<?php if ($items === []) : ?>
		<p class="com-audioarchive-tag-directory__empty">
			<?php echo Text::_('COM_AUDIOARCHIVE_TAG_DIRECTORY_EMPTY'); ?>
		</p>
	<?php else : ?>
		<ul class="com-audioarchive-tag-directory__items">
			<?php foreach ($items as $item) : ?>
				<li class="com-audioarchive-tag-directory__item">
					<div class="com-audioarchive-tag-directory__header">
						<a class="com-audioarchive-tag-directory__link" href="<?php echo (string) $item->url; ?>">
							<span class="com-audioarchive-tag-directory__title">
								<?php echo htmlspecialchars((string) $item->title, ENT_QUOTES, 'UTF-8'); ?>
							</span>
							<?php if ($showCounts) : ?>
								<span class="com-audioarchive-tag-directory__count">
									<?php echo Text::plural('COM_AUDIOARCHIVE_TAG_DIRECTORY_CLIP_COUNT', (int) $item->clip_count); ?>
								</span>
							<?php endif; ?>
						</a>
					</div>

					<?php if ($showDescriptions && trim((string) $item->description) !== '') : ?>
						<div class="com-audioarchive-tag-directory__description">
							<?php echo HTMLHelper::_('content.prepare', (string) $item->description, '', 'com_audioarchive.tagdirectory'); ?>
						</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
