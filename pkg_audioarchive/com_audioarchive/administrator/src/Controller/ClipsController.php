<?php

namespace Willeke\Component\Audioarchive\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/**
 * @brief Controller for the clip list.
 */
class ClipsController extends AdminController
{
    /**
     * @brief Return the item model.
     *
     * @param string $name Model name.
     * @param string $prefix Model prefix.
     * @param array $config Model configuration.
     *
     * @return \Joomla\CMS\MVC\Model\BaseDatabaseModel|false
     */
    public function getModel($name = 'Clip', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
