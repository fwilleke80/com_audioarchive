<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_audioarchive
 *
 * @copyright   Copyright (C) 2026 Frank Willeke.
 * @license     GNU General Public License version 2 or later
 */

namespace Willeke\Component\Audioarchive\Administrator\Extension;

use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Tag\TagServiceInterface;
use Joomla\CMS\Tag\TagServiceTrait;

\defined('_JEXEC') or die;

/**
 * @brief Component extension class.
 */
class AudioarchiveComponent extends MVCComponent implements CategoryServiceInterface, RouterServiceInterface, TagServiceInterface
{
	use CategoryServiceTrait;
	use RouterServiceTrait;
	use TagServiceTrait
    {
        CategoryServiceTrait::getTableNameForSection insteadof TagServiceTrait;
        CategoryServiceTrait::getStateColumnForSection insteadof TagServiceTrait;
    }

    /**
     * @brief Return the database table used for category and tag item counts.
     *
     * @param string|null $section Optional component section.
     *
     * @return string
     */
    protected function getTableNameForSection(?string $section = null)
    {
        return 'audioarchive_clips';
    }
}
