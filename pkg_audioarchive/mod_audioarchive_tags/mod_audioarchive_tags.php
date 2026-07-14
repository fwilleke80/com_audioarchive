<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Willeke\Module\AudioarchiveTags\Site\Helper\AudioarchiveTagsHelper;

\defined('_JEXEC') or die;

$app = Factory::getApplication();
$app->getLanguage()->load(
	'com_audioarchive',
	JPATH_SITE . '/components/com_audioarchive',
	null,
	true
);

$directory = AudioarchiveTagsHelper::getDirectory($params);
$items = (array) $directory->items;

if ($items === [])
{
	return;
}

$assets = $app->getDocument()->getWebAssetManager();

if (!$assets->assetExists('style', 'com_audioarchive.site'))
{
	$assets->registerStyle('com_audioarchive.site', 'com_audioarchive/site.css');
}

$assets->useStyle('com_audioarchive.site');

require ModuleHelper::getLayoutPath('mod_audioarchive_tags', $params->get('layout', 'default'));
