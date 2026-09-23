<?php
namespace Punga\Component\Audioarchive\Administrator\Rule;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** @brief Reject invalid peak targets on the server as well as in the browser. */
class NormalizationtargetRule extends FormRule
{
    /** @brief Accept finite dBFS targets between -60 and zero. */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= -60.0 && (float) $value <= 0.0;
    }
}
