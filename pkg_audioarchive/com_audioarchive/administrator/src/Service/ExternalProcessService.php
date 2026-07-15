<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * @brief Execute bounded external processes without invoking a shell.
 */
final class ExternalProcessService
{
	/**
	 * @brief Run a command and stream standard output directly into a file.
	 *
	 * @param string[] $command Executable and argument array.
	 * @param string $outputPath Destination for standard output.
	 * @param int $timeoutSeconds Maximum execution time.
	 *
	 * @return array{exit_code:int,stderr:string}
	 */
	public function runToFile(array $command, string $outputPath, int $timeoutSeconds): array
	{
		if ($command === [] || trim((string) $command[0]) === '')
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_COMMAND_EMPTY'));
		}

		$directory = dirname($outputPath);

		if (!is_dir($directory) || !is_writable($directory))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_OUTPUT_NOT_WRITABLE'));
		}

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['file', $outputPath, 'wb'],
			2 => ['pipe', 'w'],
		];
		$pipes = [];
		$process = @proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

		if (!is_resource($process))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_START_FAILED'));
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[2], false);
		$stderr = '';
		$exitCode = -1;
		$timedOut = false;
		$deadline = microtime(true) + max(1, $timeoutSeconds);

		do
		{
			$stderr .= stream_get_contents($pipes[2]) ?: '';

			if (strlen($stderr) > 65536)
			{
				$stderr = substr($stderr, -65536);
			}

			$status = proc_get_status($process);

			if (!$status['running'])
			{
				$exitCode = (int) $status['exitcode'];
				break;
			}

			usleep(50000);
		}
		while (microtime(true) < $deadline);

		$status = proc_get_status($process);

		if ($status['running'])
		{
			$timedOut = true;
			proc_terminate($process);
			usleep(200000);
			$status = proc_get_status($process);

			if ($status['running'] && function_exists('posix_kill') && isset($status['pid']))
			{
				@posix_kill((int) $status['pid'], 9);
			}
		}

		$stderr .= stream_get_contents($pipes[2]) ?: '';
		fclose($pipes[2]);
		$closeCode = proc_close($process);

		if ($exitCode < 0 && $closeCode >= 0)
		{
			$exitCode = $closeCode;
		}

		if ($timedOut)
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_TIMEOUT'));
		}

		return [
			'exit_code' => $exitCode,
			'stderr' => trim($stderr),
		];
	}
}
