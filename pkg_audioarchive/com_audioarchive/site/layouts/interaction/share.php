<?php

use Joomla\CMS\Language\Text;
use Punga\Component\Audioarchive\Site\Service\EmbedService;

\defined('_JEXEC') or die;

$url = (string) ($displayData['url'] ?? '');
$title = (string) ($displayData['title'] ?? '');
$includePlayerState = !empty($displayData['includePlayerState']);
$embedId = (int) ($displayData['clipId'] ?? 0);
$canEmbed = EmbedService::isAllowed($embedId);
$embedAppearance = $canEmbed ? EmbedService::appearance() : [];
$escapeEmbed = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div
	class="com-audioarchive-share-menu"
	data-audioarchive-share-menu
	data-share-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
	data-share-clip-id="<?php echo (int) ($displayData['clipId'] ?? 0); ?>"
	data-share-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
	<?php if ($includePlayerState) : ?>data-share-player-state="1"<?php endif; ?>
	data-copy-failed-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_COPY_FAILED'), ENT_QUOTES, 'UTF-8'); ?>"
	data-share-copied-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_COPIED'), ENT_QUOTES, 'UTF-8'); ?>"
>
	<button
		type="button"
		class="btn btn-sm btn-outline-secondary com-audioarchive-share-toggle"
		data-audioarchive-share-toggle
		aria-haspopup="menu"
		aria-expanded="false"
	>
		<span class="icon-share-alt" aria-hidden="true"></span>
		<?php echo Text::_('COM_AUDIOARCHIVE_SHARE'); ?>
	</button>
	<div class="com-audioarchive-share-popover" data-audioarchive-share-popover role="menu" hidden>
		<button type="button" class="com-audioarchive-share-option" data-audioarchive-share-copy role="menuitem">
			<span class="icon-link" aria-hidden="true"></span>
			<span data-audioarchive-share-copy-text><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?></span>
		</button>
        <?php if ($canEmbed) : ?>
        <button type="button" class="com-audioarchive-share-option" data-embed-open role="menuitem"><span class="icon-code" aria-hidden="true"></span><span><?php echo Text::_('COM_AUDIOARCHIVE_EMBED'); ?></span></button>
        <?php endif; ?>
		<button
			type="button"
			class="com-audioarchive-share-option"
			data-audioarchive-share-native
			role="menuitem"
			data-share-unavailable-label="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER_UNAVAILABLE'), ENT_QUOTES, 'UTF-8'); ?>"
		>
			<span class="icon-share-alt" aria-hidden="true"></span>
			<span><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_BROWSER'); ?></span>
		</button>
	</div>
	<dialog class="com-audioarchive-copy-dialog" data-share-dialog aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?>">
		<label><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?><input type="text" readonly data-share-url-field class="form-control"></label>
		<label><input type="checkbox" data-share-start> <?php echo Text::_('COM_AUDIOARCHIVE_SHARE_START'); ?> <span data-share-time></span></label>
		<div><button type="button" class="btn btn-primary" data-share-confirm><?php echo Text::_('COM_AUDIOARCHIVE_SHARE_COPY_LINK'); ?></button>
		<button type="button" class="btn btn-secondary" data-share-close><?php echo Text::_('JCLOSE'); ?></button></div>
		<p data-share-feedback role="status"></p>
	</dialog>
    <?php if ($canEmbed) : ?>
    <dialog class="com-audioarchive-copy-dialog com-audioarchive-embed-dialog" data-embed-dialog
        aria-label="<?php echo $escapeEmbed(Text::_('COM_AUDIOARCHIVE_EMBED')); ?>"
        data-embed-url="<?php echo $escapeEmbed(EmbedService::url('player', $embedId)); ?>"
        data-event-url="<?php echo $escapeEmbed(EmbedService::url('created', $embedId)); ?>"
        data-event-token="<?php echo $escapeEmbed(EmbedService::token($embedId, 'copy')); ?>"
        data-copied-label="<?php echo $escapeEmbed(Text::_('COM_AUDIOARCHIVE_EMBED_COPIED')); ?>">
        <h2><?php echo Text::_('COM_AUDIOARCHIVE_EMBED'); ?></h2>
        <label><?php echo Text::_('COM_AUDIOARCHIVE_EMBED_PRESENTATION'); ?>
            <select class="form-select" data-embed-presentation>
            <?php foreach (['minimal', 'compact', 'default', 'featured'] as $presentation) : ?>
                <option value="<?php echo $presentation; ?>" <?php echo $presentation === 'default' ? 'selected' : ''; ?>><?php echo Text::_('COM_AUDIOARCHIVE_EMBED_' . strtoupper($presentation)); ?></option>
            <?php endforeach; ?>
            </select>
        </label>
        <?php if ($embedAppearance['show_title']) : ?>
        <label><input type="checkbox" data-embed-title checked> <?php echo Text::_('COM_AUDIOARCHIVE_EMBED_SHOW_TITLE'); ?></label>
        <?php if ($embedAppearance['link_title']) : ?>
        <label><input type="checkbox" data-embed-link-title checked> <?php echo Text::_('COM_AUDIOARCHIVE_EMBED_LINK_TITLE'); ?></label>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($embedAppearance['show_original'] || $embedAppearance['show_download']) : ?>
        <label><input type="checkbox" data-embed-links checked> <?php echo Text::_('COM_AUDIOARCHIVE_EMBED_SHOW_LINKS'); ?></label>
        <?php endif; ?>
        <iframe data-embed-preview title="<?php echo $escapeEmbed(Text::_('COM_AUDIOARCHIVE_EMBED_PREVIEW')); ?>" loading="lazy"></iframe>
        <label><?php echo Text::_('COM_AUDIOARCHIVE_EMBED_CODE'); ?><textarea class="form-control" rows="5" readonly data-embed-code></textarea></label>
        <p><?php echo Text::_('COM_AUDIOARCHIVE_EMBED_HELP'); ?></p>
        <div><button type="button" class="btn btn-primary" data-embed-copy><?php echo Text::_('COM_AUDIOARCHIVE_EMBED_COPY'); ?></button>
        <button type="button" class="btn btn-secondary" data-embed-close><?php echo Text::_('JCLOSE'); ?></button></div>
        <p data-embed-feedback role="status"></p>
    </dialog>
    <?php endif; ?>
	<span class="visually-hidden" aria-live="polite" data-audioarchive-share-status></span>
</div>
