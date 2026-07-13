<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Willeke\Component\Audioarchive\Administrator\Service\IntegrityService;

\defined('_JEXEC') or die;

/**
 * @brief Model for non-destructive archive maintenance and integrity reporting.
 */
class MaintenanceModel extends BaseDatabaseModel
{
    /**
     * @brief Generate the current integrity report.
     *
     * @return array<string, mixed> Integrity report.
     */
    public function getReport(): array
    {
        $service = new IntegrityService(
            $this->getDatabase(),
            ComponentHelper::getParams('com_audioarchive')
        );

        return $service->run();
    }
}
