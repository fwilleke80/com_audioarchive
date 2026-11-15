<?php

namespace Punga\Component\Audioarchive\Site\View\Clipselection;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/** @brief Render selected clips through the existing clip detail view and templates. */
class HtmlView extends \Punga\Component\Audioarchive\Site\View\Clip\HtmlView
{
    /**
     * @brief Reuse clip templates, including installed template overrides.
     * @param string|null $tpl Optional subtemplate.
     * @return void
     */
    public function display($tpl = null)
    {
        $this->addTemplatePath(JPATH_SITE . '/components/com_audioarchive/tmpl/clip');
        $this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_audioarchive/clip');
        parent::display($tpl);
    }
}
