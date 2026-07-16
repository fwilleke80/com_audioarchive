<?php

namespace Willeke\Component\Audioarchive\Administrator\Service\Analysis;

use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Willeke\Component\Audioarchive\Administrator\Service\ManagedStorageService;

\defined('_JEXEC') or die;

/**
 * @brief Dispatch generic audio-analysis generation to registered generators.
 */
final class AnalysisManagerService
{
	/** @var array<string, AnalysisGeneratorInterface> */
	private array $generators = [];

	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/** @var AnalysisRepositoryService */
	private AnalysisRepositoryService $repository;

	/** @var ManagedStorageService */
	private ManagedStorageService $storage;

	/**
	 * @brief Construct the analysis manager and register built-in generators.
	 *
	 * @param DatabaseInterface $database Joomla database connection.
	 * @param Registry $params Component parameters.
	 * @param User $user Current administrator.
	 */
	public function __construct(DatabaseInterface $database, Registry $params, User $user)
	{
		$this->database = $database;
		$this->repository = new AnalysisRepositoryService($database);
		$this->storage = new ManagedStorageService($params);
		$this->register(new WaveformGeneratorService($database, $params, $user));
		$this->register(new SpectrogramGeneratorService($database, $params, $user));
	}

	/**
	 * @brief Register one generator by its stable analysis type.
	 *
	 * @param AnalysisGeneratorInterface $generator Generator instance.
	 *
	 * @return void
	 */
	public function register(AnalysisGeneratorInterface $generator): void
	{
		$type = strtolower(trim($generator->getAnalysisType()));

		if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $type))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_ANALYSIS_ERROR_INVALID_TYPE'));
		}

		$this->generators[$type] = $generator;
	}

	/**
	 * @brief Generate and persist one analysis.
	 *
	 * @param string $analysisType Analysis type.
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return AnalysisGenerationResult Generated result.
	 */
	public function generate(string $analysisType, int $clipId, array $options = []): AnalysisGenerationResult
	{
		$analysisType = strtolower(trim($analysisType));
		$generator = $this->generators[$analysisType] ?? null;

		if (!$generator instanceof AnalysisGeneratorInterface)
		{
			throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_ANALYSIS_ERROR_GENERATOR_NOT_REGISTERED', $analysisType));
		}

		$this->database->transactionStart();

		try
		{
			$this->repository->markPending($clipId, $analysisType, $options);
			$this->database->transactionCommit();
		}
		catch (\Throwable $exception)
		{
			$this->database->transactionRollback();
			throw $exception;
		}

		$result = null;

		try
		{
			$result = $generator->generate($clipId, $options);
			$previousKey = $this->repository->saveAvailable($clipId, $result);

			if ($previousKey !== '' && $previousKey !== $result->storageKey)
			{
				try
				{
					$this->storage->deleteManagedFile('analysis', $previousKey);
				}
				catch (\Throwable)
				{
					// The new analysis is valid; old-file cleanup can be handled by maintenance.
				}
			}

			return $result;
		}
		catch (\Throwable $exception)
		{
			if ($result instanceof AnalysisGenerationResult)
			{
				try
				{
					$this->storage->deleteManagedFile('analysis', $result->storageKey);
				}
				catch (\Throwable)
				{
				}
			}

			$this->repository->markFailed($clipId, $analysisType, $exception->getMessage(), $options);
			throw $exception;
		}
	}

	/**
	 * @brief Return the generic analysis repository.
	 *
	 * @return AnalysisRepositoryService Repository.
	 */
	public function getRepository(): AnalysisRepositoryService
	{
		return $this->repository;
	}
}
