<?php
\defined('_JEXEC') or die;

$items = array_slice($items, 0, 1);
$params->set('presentation', 'featured');

require __DIR__ . '/default.php';
