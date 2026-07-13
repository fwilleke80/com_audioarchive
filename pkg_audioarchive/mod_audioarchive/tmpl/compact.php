<?php
\defined('_JEXEC') or die;
$params->set('show_description', 0);
$params->set('show_category', 0);
$params->set('show_tags', 0);
$params->set('show_counts', 0);
$params->set('show_detail_link', 0);
$params->set('moduleclass_sfx', trim((string) $params->get('moduleclass_sfx', '') . ' mod-audioarchive--compact'));
require __DIR__ . '/default.php';
