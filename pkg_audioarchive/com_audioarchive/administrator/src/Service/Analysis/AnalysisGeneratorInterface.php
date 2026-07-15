<?php

namespace Willeke\Component\Audioarchive\Administrator\Service\Analysis;

\defined('_JEXEC') or die;

/**
 * @brief Generate one kind of derived audio analysis.
 */
interface AnalysisGeneratorInterface
{
	/**
	 * @brief Return the stable analysis type identifier.
	 *
	 * @return string Analysis type.
	 */
	public function getAnalysisType(): string;

	/**
	 * @brief Generate analysis data for one clip.
	 *
	 * @param int $clipId Clip identifier.
	 * @param array<string, mixed> $options Generator options.
	 *
	 * @return AnalysisGenerationResult Generated analysis result.
	 */
	public function generate(int $clipId, array $options = []): AnalysisGenerationResult;
}
