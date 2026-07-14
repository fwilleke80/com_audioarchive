<?php

namespace Willeke\Component\Audioarchive\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Administrator\Service\AudioUploadService;
use Willeke\Component\Audioarchive\Administrator\Service\BulkReplacementService;
use Willeke\Component\Audioarchive\Administrator\Service\DirectoryImportService;
use Willeke\Component\Audioarchive\Administrator\Service\ImportCategoryService;

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
		$data->operation_mode = 'import';
		$data->recursive = (int) $params->get('recursive_import', 0);
		$data->duplicate_policy = 'component';
		$data->delete_source = (int) $params->get('delete_inbox_after_import', 1);
		$data->retain_previous_original = 1;
		$data->category_mode = 'selected';
		$data->create_missing_categories = 1;

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
	 * @brief Inspect an inbox file and resolve its unique replacement target.
	 *
	 * @param string $relativePath Relative inbox path.
	 *
	 * @return array<string, mixed> Replacement analysis.
	 */
	public function prepareInboxReplacement(string $relativePath): array
	{
		$params = $this->getComponentParams();
		$directoryService = new DirectoryImportService($params);
		$source = $directoryService->resolveSource($relativePath);
		$matcher = new BulkReplacementService($this->getDatabase());
		$matches = $matcher->findMatches((string) $source['filename']);
		$prepared = null;
		$identical = false;

		if (count($matches) === 1)
		{
			$match = $matches[0];
			$prepared = $this->createAudioUploadService()->prepareLocalFile(
				(string) $source['real_path'],
				(string) $source['relative_path'],
				'warn',
				(int) $match->clip_id
			);
			$currentChecksum = strtolower(trim((string) $match->checksum_sha256));
			$newChecksum = strtolower(trim((string) ($prepared['checksum_sha256'] ?? '')));
			$identical = $currentChecksum !== ''
				&& $newChecksum !== ''
				&& hash_equals($currentChecksum, $newChecksum);
		}

		return [
			'source' => $source,
			'normalised_basename' => $matcher->normaliseBasename((string) $source['filename']),
			'matches' => $matches,
			'prepared' => $prepared,
			'identical' => $identical,
			'eligible' => count($matches) === 1 && $prepared !== null && !$identical,
		];
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
	 * @brief Preview a category assignment for one inbox file.
	 *
	 * @param string $relativePath Inbox-relative file path.
	 * @param string $mode Selected-category or folder-derived mode.
	 * @param int $baseCategoryId Optional base category.
	 * @param bool $createMissing Whether missing folder categories may be created.
	 *
	 * @return array<string, mixed> Category plan.
	 */
	public function planCategory(
		string $relativePath,
		string $mode,
		int $baseCategoryId,
		bool $createMissing
	): array
	{
		$fallbackCategoryId = $this->getDefaultCategoryId();
		$service = new ImportCategoryService($this->getDatabase(), $this->getCurrentUser());

		return $service->plan(
			$relativePath,
			$mode,
			$this->getValidCategoryId($baseCategoryId),
			$fallbackCategoryId,
			$createMissing
		);
	}

	/**
	 * @brief Resolve and create the category assignment for one inbox file.
	 *
	 * @param string $relativePath Inbox-relative file path.
	 * @param string $mode Selected-category or folder-derived mode.
	 * @param int $baseCategoryId Optional base category.
	 * @param bool $createMissing Whether missing folder categories may be created.
	 *
	 * @return array<string, mixed> Resolved category data.
	 */
	public function resolveCategory(
		string $relativePath,
		string $mode,
		int $baseCategoryId,
		bool $createMissing
	): array
	{
		$fallbackCategoryId = $this->getDefaultCategoryId();
		$service = new ImportCategoryService($this->getDatabase(), $this->getCurrentUser());

		return $service->resolve(
			$relativePath,
			$mode,
			$this->getValidCategoryId($baseCategoryId),
			$fallbackCategoryId,
			$createMissing
		);
	}

	/**
	 * @brief Return the configured valid default category.
	 *
	 * @return int Category identifier or zero.
	 */
	private function getDefaultCategoryId(): int
	{
		return $this->getValidCategoryId((int) $this->getComponentParams()->get('default_category', 0));
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
