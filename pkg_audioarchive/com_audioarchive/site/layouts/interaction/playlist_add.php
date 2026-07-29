<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Punga\Component\Audioarchive\Site\Helper\RouteHelper;

\defined('_JEXEC') or die;

$clipId = (int) ($displayData['clipId'] ?? 0);
$clipUuid = strtolower(trim((string) ($displayData['clipUuid'] ?? '')));
$title = trim((string) ($displayData['title'] ?? ''));
$itemId = Factory::getApplication()->getInput()->getInt('Itemid', 0);
?>
<div
	class="com-audioarchive-add-to-menu"
	data-audioarchive-add-to-menu
	data-playlist-only="1"
	data-clip-id="<?php echo $clipId; ?>"
	data-clip-uuid="<?php echo htmlspecialchars($clipUuid, ENT_QUOTES, 'UTF-8'); ?>"
	data-clip-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
	data-interaction-url="<?php echo htmlspecialchars(Route::_(RouteHelper::getInteractionRecordRoute($itemId)), ENT_QUOTES, 'UTF-8'); ?>"
	data-interaction-token="<?php echo htmlspecialchars(Session::getFormToken(), ENT_QUOTES, 'UTF-8'); ?>"
	data-label-playlists="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_ADD_TO_PLAYLIST'), ENT_QUOTES, 'UTF-8'); ?>"
	data-label-default-name="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_PLAYLIST_DEFAULT_NAME'), ENT_QUOTES, 'UTF-8'); ?>"
	data-label-create="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_PLAYLIST_CREATE_NEW'), ENT_QUOTES, 'UTF-8'); ?>"
	data-label-name-prompt="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_PLAYLIST_NAME_PROMPT'), ENT_QUOTES, 'UTF-8'); ?>"
	data-label-empty="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_PLAYLIST_NONE'), ENT_QUOTES, 'UTF-8'); ?>"
	data-label-added="<?php echo htmlspecialchars(Text::_('COM_AUDIOARCHIVE_PLAYLIST_CLIP_ADDED'), ENT_QUOTES, 'UTF-8'); ?>"
>
	<button
		type="button"
		class="btn btn-sm btn-outline-secondary com-audioarchive-add-to-toggle"
		data-audioarchive-add-to-toggle
		aria-haspopup="menu"
		aria-expanded="false"
	>
		<span class="icon-plus" aria-hidden="true"></span>
		<?php echo Text::_('COM_AUDIOARCHIVE_ADD_TO_PLAYLIST'); ?>
	</button>
	<div class="com-audioarchive-add-to-popover" data-audioarchive-add-to-popover role="menu" hidden></div>
</div>
