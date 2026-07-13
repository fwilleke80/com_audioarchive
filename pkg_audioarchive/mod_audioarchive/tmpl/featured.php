<?php
\defined('_JEXEC') or die;
$items = array_slice($items, 0, 1);
$params->set('moduleclass_sfx', trim((string) $params->get('moduleclass_sfx', '') . ' mod-audioarchive--featured'));
require __DIR__ . '/default.php';
