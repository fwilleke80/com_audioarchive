<?php

namespace Willeke\Component\Audioarchive\Site\View\Archive;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

/**
 * @brief Placeholder public archive view.
 */
class HtmlView extends BaseHtmlView
{
    protected $items;

    /**
     * @brief Display the public foundation page.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $this->items = $this->get('Items');
        parent::display($tpl);
    }
}
