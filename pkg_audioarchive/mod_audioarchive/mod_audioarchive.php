<?php

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Module\Audioarchive\Site\Helper\AudioarchiveHelper;

\defined('_JEXEC') or die;

$componentParams = ComponentHelper::getParams('com_audioarchive');
$modulePresentation = strtolower(trim((string) $params->get('presentation', 'default')));
$modulePresentation = in_array($modulePresentation, ['default', 'compact', 'featured'], true)
	? $modulePresentation
	: 'default';
$playerPresentation = strtolower(trim((string) $params->get('player_presentation', 'inherit')));

if ($playerPresentation === 'inherit')
{
	$playerPresentation = strtolower(trim((string) $componentParams->get('default_embed_presentation', 'default')));
}

$playerPresentation = in_array($playerPresentation, ['minimal', 'compact', 'default', 'featured'], true)
	? $playerPresentation
	: 'default';
$params->set('presentation', $modulePresentation);
$params->set('player_presentation', $playerPresentation);
$items = AudioarchiveHelper::getItems($params, $module);

if ($items === [])
{
	return;
}

$app = Factory::getApplication();
$document = $app->getDocument();
$assets = $document->getWebAssetManager();

if (!$assets->assetExists('style', 'com_audioarchive.site'))
{
	$assets->registerStyle('com_audioarchive.site', 'com_audioarchive/site.css');
}

if (!$assets->assetExists('style', 'com_audioarchive.player-style'))
{
	$assets->registerStyle('com_audioarchive.player-style', 'com_audioarchive/player.css');
}

if (!$assets->assetExists('script', 'com_audioarchive.player'))
{
	$assets->registerScript('com_audioarchive.player', 'com_audioarchive/player.js', [], ['type' => 'module'], ['core']);
}

$assets
	->useStyle('com_audioarchive.site')
	->useStyle('com_audioarchive.player-style')
	->useScript('com_audioarchive.player')
	->registerAndUseStyle('mod_audioarchive.site', 'mod_audioarchive/module.css');

$playCountUrl = '';
$playCountToken = '';

if ((int) $componentParams->get('enable_play_counts', 1) === 1)
{
	$playCountUrl = Route::_(RouteHelper::getPlayCountRoute((int) ($items[0]->itemid ?? 0)));
	$playCountToken = Session::getFormToken();
}

require ModuleHelper::getLayoutPath('mod_audioarchive', $modulePresentation);
