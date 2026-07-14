<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Willeke\Component\Audioarchive\Site\Helper\RouteHelper;
use Willeke\Module\Audioarchive\Site\Helper\AudioarchiveHelper;

\defined('_JEXEC') or die;

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

if (!$assets->assetExists('script', 'com_audioarchive.player'))
{
	$assets->registerScript('com_audioarchive.player', 'com_audioarchive/player.js', [], ['type' => 'module'], ['core']);
}

$assets
	->useStyle('com_audioarchive.site')
	->useScript('com_audioarchive.player')
	->registerAndUseStyle('mod_audioarchive.site', 'mod_audioarchive/module.css');

$playCountUrl = '';
$playCountToken = '';

if ((int) Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive')->get('enable_play_counts', 1) === 1)
{
	$playCountUrl = Route::_(RouteHelper::getPlayCountRoute((int) ($items[0]->itemid ?? 0)));
	$playCountToken = Session::getFormToken();
}

$componentParams = Joomla\CMS\Component\ComponentHelper::getParams('com_audioarchive');
$presentation = (string) $params->get('presentation', 'inherit');

if ($presentation === 'inherit')
{
	$presentation = (string) $componentParams->get('default_embed_presentation', 'default');
}

$layout = in_array($presentation, ['default', 'compact', 'featured'], true)
	? $presentation
	: 'default';

$params->set('presentation', $layout);

require ModuleHelper::getLayoutPath('mod_audioarchive', $layout);
