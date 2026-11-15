<?php
/** @brief Standalone embed document, independent of the active Joomla template. */
\defined('_JEXEC') or die;
?>
<!doctype html>
<html lang="<?php echo $escape($app->getLanguage()->getTag()); ?>" dir="<?php echo $app->getLanguage()->isRtl() ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo $escape((string) $clip->title); ?></title>
<link rel="stylesheet" href="<?php echo $escape($assetRoot . 'css/player.css?v=0.15.0'); ?>">
<link rel="stylesheet" href="<?php echo $escape($assetRoot . 'css/embed.css?v=0.15.0'); ?>">
<script type="module" src="<?php echo $escape($assetRoot . 'js/player.js?v=0.15.0'); ?>"></script>
<script type="module" src="<?php echo $escape($assetRoot . 'js/embed.js?v=0.15.0'); ?>"></script>
</head>
<body>
<main class="com-audioarchive audioarchive-embed" data-embed-event-url="<?php echo $escape($eventUrl); ?>" data-embed-token="<?php echo $escape($eventToken); ?>">
<header><a href="<?php echo $escape($detailUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $escape((string) $clip->title); ?></a></header>
<?php echo $html; ?>
<footer>
<a href="<?php echo $escape($detailUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $escape(\Joomla\CMS\Language\Text::_('COM_AUDIOARCHIVE_EMBED_OPEN')); ?></a>
<?php if ($downloadUrl !== '') : ?>
<a href="<?php echo $escape($downloadUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $escape(\Joomla\CMS\Language\Text::_('COM_AUDIOARCHIVE_DOWNLOAD_ORIGINAL')); ?></a>
<?php endif; ?>
</footer>
</main>
</body>
</html>
