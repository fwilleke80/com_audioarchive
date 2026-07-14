<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * @brief Administrator model for browser bulk uploads.
 */
class UploadModel extends FormModel
{
    /**
     * @brief Return the batch-metadata form.
     *
     * @param array $data Form data.
     * @param bool $loadData Load defaults.
     *
     * @return Form|false
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_audioarchive.upload',
            'upload',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    /**
     * @brief Load component defaults into the upload form.
     *
     * @return object
     */
    protected function loadFormData(): object
    {
        $params = ComponentHelper::getParams('com_audioarchive');
        $categoryId = $this->getValidCategoryId((int) $params->get('default_category', 0));

        return (object) [
            'catid' => $categoryId,
            'tags' => [],
            'access' => (int) $params->get('default_access', 1),
            'state' => (int) $params->get('default_state', 0) === 1 ? 1 : 0,
            'recorded_at' => '',
        ];
    }

    /**
     * @brief Validate that a category belongs to Audio Archive.
     *
     * @param int $categoryId Joomla category identifier.
     *
     * @return int Valid identifier or zero.
     */
    public function getValidCategoryId(int $categoryId): int
    {
        if ($categoryId <= 0)
        {
            return 0;
        }

        $database = $this->getDatabase();
        $extension = 'com_audioarchive';
        $query = $database->getQuery(true)
            ->select($database->quoteName('id'))
            ->from($database->quoteName('#__categories'))
            ->where($database->quoteName('id') . ' = :categoryId')
            ->where($database->quoteName('extension') . ' = :extension')
            ->whereIn($database->quoteName('published'), [0, 1])
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension, ParameterType::STRING);

        return (int) $database->setQuery($query)->loadResult();
    }

    /**
     * @brief Return a category title for an upload result.
     *
     * @param int $categoryId Joomla category identifier.
     *
     * @return string Category title.
     */
    public function getCategoryTitle(int $categoryId): string
    {
        if ($categoryId <= 0)
        {
            return '';
        }

        $database = $this->getDatabase();
        $query = $database->getQuery(true)
            ->select($database->quoteName('title'))
            ->from($database->quoteName('#__categories'))
            ->where($database->quoteName('id') . ' = :categoryId')
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER);

        return (string) $database->setQuery($query)->loadResult();
    }
}
