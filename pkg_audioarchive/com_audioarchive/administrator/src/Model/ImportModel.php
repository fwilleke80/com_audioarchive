<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Administrator\Service\AudioUploadService;
use Willeke\Component\Audioarchive\Administrator\Service\DirectoryImportService;

\defined('_JEXEC') or die;

/**
 * @brief Administrator model for server-directory imports.
 */
class ImportModel extends UploadModel
{
	/**
	 * @brief Return the directory-import form.
	 *
	 * @param array $data Form data.
	 * @param bool $loadData Load defaults.
	 *
	 * @return Form|false
	 */
	public function getForm($data = [], $loadData = true)
	{
		return $this->loadForm(
			'com_audioarchive.import',
			'import',
			['control' => 'jform', 'load_data' => $loadData]
		);
	}

	/**
	 * @brief Load component defaults into the directory-import form.
	 *
	 * @return object
	 */
	protected function loadFormData(): object
	{
		$data = parent::loadFormData();
		$params = ComponentHelper::getParams('com_audioarchive');
		$data->recursive = (int) $params->get('recursive_import', 0);
		$data->duplicate_policy = 'component';
		$data->delete_source = (int) $params->get('delete_inbox_after_import', 1);

		return $data;
	}

	/**
	 * @brief Return the component parameters used by import services.
	 *
	 * @return Registry
	 */
	public function getComponentParams(): Registry
	{
		return ComponentHelper::getParams('com_audioarchive');
	}

	/**
	 * @brief Create the media-upload service with model-owned database access.
	 *
	 * Controllers must not call the protected BaseDatabaseModel::getDatabase() method.
	 *
	 * @return AudioUploadService Configured upload service.
	 */
	public function createAudioUploadService(): AudioUploadService
	{
		return new AudioUploadService(
			$this->getDatabase(),
			$this->getComponentParams(),
			$this->getCurrentUser()
		);
	}

	/**
	 * @brief Scan the configured import inbox.
	 *
	 * @param bool $recursive Whether child directories are included.
	 *
	 * @return array<int, array{path:string,filename:string,size:int,modified:int}>
	 */
	public function scanInbox(bool $recursive): array
	{
		return (new DirectoryImportService($this->getComponentParams()))->scan($recursive);
	}

	/**
	 * @brief Resolve and prepare one inbox file for preview or import.
	 *
	 * @param string $relativePath Relative inbox path.
	 * @param string $duplicatePolicy Effective duplicate policy.
	 *
	 * @return array{source:array<string,mixed>,prepared:array<string,mixed>}
	 */
	public function prepareInboxFile(string $relativePath, string $duplicatePolicy): array
	{
		$params = $this->getComponentParams();
		$directoryService = new DirectoryImportService($params);
		$source = $directoryService->resolveSource($relativePath);
		$uploadService = $this->createAudioUploadService();
		$prepared = $uploadService->prepareLocalFile(
			(string) $source['real_path'],
			(string) $source['relative_path'],
			$duplicatePolicy
		);

		return ['source' => $source, 'prepared' => $prepared];
	}

	/**
	 * @brief Delete one successfully imported source file.
	 *
	 * @param string $relativePath Relative inbox path.
	 *
	 * @return bool True when removed or already absent.
	 */
	public function deleteInboxFile(string $relativePath): bool
	{
		return (new DirectoryImportService($this->getComponentParams()))->deleteSource($relativePath);
	}

	/**
	 * @brief Resolve a form duplicate-policy choice to an effective policy.
	 *
	 * @param string $choice Form choice.
	 *
	 * @return string Effective ignore, warn, or reject policy.
	 */
	public function resolveDuplicatePolicy(string $choice): string
	{
		if (in_array($choice, ['ignore', 'warn', 'reject'], true))
		{
			return $choice;
		}

		$configured = (string) $this->getComponentParams()->get('duplicate_policy', 'warn');

		return in_array($configured, ['ignore', 'warn', 'reject'], true) ? $configured : 'warn';
	}
}
