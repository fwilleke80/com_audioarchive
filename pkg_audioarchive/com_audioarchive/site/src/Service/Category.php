<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Categories\Categories;

\defined('_JEXEC') or die;

/**
 * @brief Audio Archive category tree service.
 *
 * Joomla's CategoryFactory resolves this class when component integrations,
 * including Smart Search, request the com_audioarchive category hierarchy.
 */
class Category extends Categories
{
	/**
	 * @brief Construct the Audio Archive category tree.
	 *
	 * @param array<string, mixed> $options Category loading options.
	 */
	public function __construct($options = [])
	{
		$options['table'] = '#__audioarchive_clips';
		$options['extension'] = 'com_audioarchive';
		$options['field'] = 'catid';
		$options['key'] = 'id';
		$options['statefield'] = 'state';

		parent::__construct($options);
	}
}
