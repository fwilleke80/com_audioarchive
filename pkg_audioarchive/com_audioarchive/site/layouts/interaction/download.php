<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Punga\Component\Audioarchive\Site\Service\DownloadTicketService;

\defined('_JEXEC') or die;

/** @brief Render a normal link or an explicit ticketed-download action using one shared control. */
Factory::getApplication()->getLanguage()->load('com_audioarchive', JPATH_SITE);
$escapeDownload = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$downloadData = is_array($displayData ?? null) ? $displayData : [];
$downloadId = (int) ($downloadData['clipId'] ?? 0);
$downloadLabel = (string) ($downloadData['label'] ?? Text::_('COM_AUDIOARCHIVE_DOWNLOAD_ORIGINAL'));
$downloadClass = (string) ($downloadData['class'] ?? 'com-audioarchive-download-link');
$downloadContext = ($downloadData['context'] ?? 'site') === 'embed' ? 'embed' : 'site';
$ticketed = DownloadTicketService::enabled();
?>
<?php if (!$ticketed) : ?>
<a class="<?php echo $escapeDownload($downloadClass); ?>" href="<?php echo $escapeDownload(html_entity_decode((string) ($downloadData['url'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>"><?php echo $escapeDownload($downloadLabel); ?></a>
<?php else : ?>
<?php
$downloadActor = $downloadContext === 'embed' ? 0 : (int) Factory::getApplication()->getIdentity()->id;
$downloadEndpoint = Uri::root() . 'index.php?' . http_build_query([
	'option' => 'com_audioarchive', 'task' => ($downloadContext === 'embed' ? 'embed' : 'stream') . '.downloadTicket',
	'format' => 'raw', 'id' => $downloadId,
], '', '&', PHP_QUERY_RFC3986);
?>
<span data-audioarchive-download-control>
<button type="button" class="<?php echo $escapeDownload($downloadClass); ?>" data-audioarchive-download
	data-download-endpoint="<?php echo $escapeDownload($downloadEndpoint); ?>"
	data-download-proof="<?php echo $escapeDownload(DownloadTicketService::proof($downloadId, $downloadActor, $downloadContext)); ?>"
	data-download-error="<?php echo $escapeDownload(Text::_('COM_AUDIOARCHIVE_DOWNLOAD_FAILED')); ?>"
	data-download-busy="<?php echo $escapeDownload(Text::_('COM_AUDIOARCHIVE_DOWNLOAD_PREPARING')); ?>"
><?php echo $escapeDownload($downloadLabel); ?></button>
<span role="status" data-download-status></span>
<noscript><?php echo Text::_('COM_AUDIOARCHIVE_DOWNLOAD_REQUIRES_JS'); ?></noscript>
</span>
<?php if (DownloadTicketService::needsScript()) : ?>
<script type="module" src="<?php echo $escapeDownload(Uri::root() . 'media/com_audioarchive/js/download.js?v=0.15.2'); ?>"></script>
<?php endif; ?>
<?php endif; ?>
