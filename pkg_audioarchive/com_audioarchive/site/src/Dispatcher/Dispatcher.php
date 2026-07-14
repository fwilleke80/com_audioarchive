<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_audioarchive
 *
 * @copyright   Copyright (C) 2026 Frank Willeke.
 * @license     GNU General Public License version 2 or later
 */

namespace Willeke\Component\Audioarchive\Site\Dispatcher;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * @brief Site component dispatcher with archive-wide access enforcement.
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * @brief Dispatch the requested frontend controller after checking the configured access level.
     *
     * This check runs before any archive view, clip view, stream, download, or counter task.
     * It therefore also protects direct non-menu routes such as /component/audioarchive.
     *
     * @return void
     */
    public function dispatch()
    {
        $params          = ComponentHelper::getParams('com_audioarchive');
        $requiredAccess  = (int) $params->get('frontend_access_level', 1);
        $authorisedViews = array_map('intval', $this->app->getIdentity()->getAuthorisedViewLevels());

        if (!in_array($requiredAccess, $authorisedViews, true))
        {
            $this->app->getLanguage()->load(
                'com_audioarchive',
                JPATH_SITE . '/components/com_audioarchive'
            );

            if ($this->app->getIdentity()->guest)
            {
                $returnUrl = base64_encode(Uri::getInstance()->toString());
                $loginUrl  = Route::_(
                    'index.php?option=com_users&view=login&return=' . rawurlencode($returnUrl),
                    false
                );

                $this->app->enqueueMessage(Text::_('COM_AUDIOARCHIVE_LOGIN_REQUIRED'), 'info');
                $this->app->redirect($loginUrl);

                return;
            }

            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        parent::dispatch();
    }
}
