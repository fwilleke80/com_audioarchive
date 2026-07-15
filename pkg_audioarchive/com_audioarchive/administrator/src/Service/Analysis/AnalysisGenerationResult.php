<?php

namespace Willeke\Component\Audioarchive\Administrator\Service\Analysis;

\defined('_JEXEC') or die;

/**
 * @brief Immutable result returned by an audio-analysis generator.
 */
final class AnalysisGenerationResult
{
	/**
	 * @brief Construct a generated-analysis result.
	 *
	 * @param string $analysisType Stable analysis type.
	 * @param string $storageKey Managed storage key.
	 * @param string $dataFormat Versioned data format.
	 * @param array<string, mixed> $parameters Generation parameters.
	 * @param string $generator Generator name.
	 * @param string $generatorVersion Generator version.
	 * @param int $fileSize Generated file size in bytes.
	 */
	public function __construct(
		public readonly string $analysisType,
		public readonly string $storageKey,
		public readonly string $dataFormat,
		public readonly array $parameters,
		public readonly string $generator,
		public readonly string $generatorVersion,
		public readonly int $fileSize
	)
	{
	}
}
