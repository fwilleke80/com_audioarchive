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
<link rel="stylesheet" href="<?php echo $escape($assetRoot . 'css/player.css?v=0.15.2'); ?>">
<link rel="stylesheet" href="<?php echo $escape($assetRoot . 'css/embed.css?v=0.15.3'); ?>">
<script type="module" src="<?php echo $escape($assetRoot . 'js/player.js?v=0.15.2'); ?>"></script>
<script type="module" src="<?php echo $escape($assetRoot . 'js/embed.js?v=0.15.2'); ?>"></script>
</head>
<body style="--embed-background:<?php echo $escape($appearance['background']); ?>;--embed-text:<?php echo $escape($appearance['text']); ?>;--embed-link:<?php echo $escape($appearance['link']); ?>;--embed-title:<?php echo $escape($appearance['title']); ?>">
<main class="com-audioarchive audioarchive-embed" data-embed-event-url="<?php echo $escape($eventUrl); ?>" data-embed-token="<?php echo $escape($eventToken); ?>">
<?php if ($appearance['show_title']) : ?>
<header><a href="<?php echo $escape($detailUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $escape((string) $clip->title); ?></a></header>
<?php endif; ?>
<?php echo $html; ?>
<?php if ($appearance['show_original'] || $downloadUrl !== '') : ?>
<footer>
<?php if ($appearance['show_original']) : ?>
<a href="<?php echo $escape($detailUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo $escape(\Joomla\CMS\Language\Text::_('COM_AUDIOARCHIVE_EMBED_OPEN')); ?></a>
<?php endif; ?>
<?php if ($downloadUrl !== '') : ?>
<?php echo \Joomla\CMS\Layout\LayoutHelper::render('interaction.download', ['clipId' => (int) $clip->id, 'url' => $downloadUrl, 'context' => 'embed'], null, ['component' => 'com_audioarchive', 'client' => 0]); ?>
<?php endif; ?>
</footer>
<?php endif; ?>
</main>
</body>
</html>
