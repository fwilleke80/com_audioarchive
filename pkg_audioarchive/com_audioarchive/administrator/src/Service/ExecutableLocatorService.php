<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Resolve and validate administrator-configured external executables.
 */
final class ExecutableLocatorService
{
	/** @var Registry */
	private Registry $params;

	/**
	 * @brief Construct the executable locator.
	 *
	 * @param Registry $params Component parameters.
	 */
	public function __construct(Registry $params)
	{
		$this->params = $params;
	}

	/**
	 * @brief Locate an executable and verify that it can be launched.
	 *
	 * @param string $program Program name such as ffmpeg.
	 *
	 * @return array{path:string,version:string,permission_adjusted:bool}
	 */
	public function locate(string $program): array
	{
		if (!$this->isFunctionAvailable('proc_open'))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_EXECUTION_UNAVAILABLE'));
		}

		$program = strtolower(trim($program));

		if (!preg_match('/^[a-z0-9._-]+$/', $program))
		{
			throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_INVALID_EXECUTABLE'));
		}

		$parameter = $program . '_path';
		$configuredPath = $this->normaliseConfiguredPath((string) $this->params->get($parameter, ''));
		$candidates = [];

		if ($configuredPath !== '')
		{
			$candidates[] = [
				'path' => $this->resolveConfiguredPath($configuredPath),
				'configured' => true,
			];
		}

		foreach ([$program, '/usr/bin/' . $program, '/usr/local/bin/' . $program] as $candidate)
		{
			$candidates[] = [
				'path' => $candidate,
				'configured' => false,
			];
		}

		$unique = [];

		foreach ($candidates as $candidate)
		{
			$unique[(string) $candidate['path']] = $candidate;
		}

		$errors = [];

		foreach ($unique as $candidate)
		{
			$path = (string) $candidate['path'];
			$configured = (bool) $candidate['configured'];
			$permissionAdjusted = false;

			if ($this->isAbsolutePath($path))
			{
				clearstatcache(true, $path);

				if (!is_file($path) || !is_readable($path))
				{
					$errors[] = Text::sprintf('COM_AUDIOARCHIVE_PROCESS_ERROR_FILE_MISSING_OR_UNREADABLE', $path);
					continue;
				}

				if (!is_executable($path) && $configured)
				{
					$permissionAdjusted = $this->tryAddExecutePermission($path);
				}

				clearstatcache(true, $path);

				if (!is_executable($path))
				{
					$errors[] = Text::sprintf('COM_AUDIOARCHIVE_PROCESS_ERROR_FILE_NOT_EXECUTABLE', $path);
					continue;
				}
			}

			$probe = $this->probeVersion($path);

			if ($probe['available'])
			{
				return [
					'path' => $path,
					'version' => (string) $probe['version'],
					'permission_adjusted' => $permissionAdjusted,
				];
			}

			$errors[] = $path . ': ' . (string) $probe['error'];
		}

		throw new \RuntimeException(
			$errors !== []
				? implode('; ', $errors)
				: Text::sprintf('COM_AUDIOARCHIVE_PROCESS_ERROR_NOT_FOUND', ucfirst($program))
		);
	}

	/**
	 * @brief Resolve an absolute path or a Joomla-root-relative path.
	 *
	 * @param string $path Configured path.
	 *
	 * @return string Resolved path.
	 */
	private function resolveConfiguredPath(string $path): string
	{
		if ($this->isAbsolutePath($path))
		{
			return Path::clean($path);
		}

		$root = rtrim(Path::clean(JPATH_ROOT), DIRECTORY_SEPARATOR);
		$resolved = Path::clean($root . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
		$normalisedRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($root) : $root;
		$normalisedResolved = DIRECTORY_SEPARATOR === '\\' ? strtolower($resolved) : $resolved;

		if (!str_starts_with($normalisedResolved, $normalisedRoot . DIRECTORY_SEPARATOR))
		{
			throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_PATH_ESCAPE'));
		}

		return $resolved;
	}

	/**
	 * @brief Remove matching surrounding quotes from a configured path.
	 *
	 * @param string $path Configured path.
	 *
	 * @return string Normalised path.
	 */
	private function normaliseConfiguredPath(string $path): string
	{
		$path = trim($path);

		if (strlen($path) >= 2)
		{
			$first = $path[0];
			$last = $path[strlen($path) - 1];

			if (($first === '"' && $last === '"') || ($first === "'" && $last === "'"))
			{
				$path = trim(substr($path, 1, -1));
			}
		}

		return $path;
	}

	/**
	 * @brief Probe an executable's version without invoking a shell.
	 *
	 * @param string $path Executable path or command name.
	 *
	 * @return array{available:bool,version:string,error:string}
	 */
	private function probeVersion(string $path): array
	{
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$pipes = [];
		$process = @proc_open([$path, '-version'], $descriptors, $pipes, null, null, ['bypass_shell' => true]);

		if (!is_resource($process))
		{
			return ['available' => false, 'version' => '', 'error' => Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_START_FAILED')];
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$output = '';
		$exitCode = -1;
		$deadline = microtime(true) + 5.0;

		do
		{
			$output .= stream_get_contents($pipes[1]) ?: '';
			$output .= stream_get_contents($pipes[2]) ?: '';
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
			proc_terminate($process);
		}

		$output .= stream_get_contents($pipes[1]) ?: '';
		$output .= stream_get_contents($pipes[2]) ?: '';
		fclose($pipes[1]);
		fclose($pipes[2]);
		$closeCode = proc_close($process);

		if ($exitCode < 0 && $closeCode >= 0)
		{
			$exitCode = $closeCode;
		}

		$output = trim($output);

		if ($exitCode !== 0 || $output === '')
		{
			return [
				'available' => false,
				'version' => '',
				'error' => $output !== '' ? (strtok($output, "\r\n") ?: Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_VERSION_PROBE_FAILED')) : Text::_('COM_AUDIOARCHIVE_PROCESS_ERROR_VERSION_PROBE_FAILED'),
			];
		}

		return [
			'available' => true,
			'version' => strtok($output, "\r\n") ?: '',
			'error' => '',
		];
	}

	/**
	 * @brief Add Unix execute bits while preserving existing permissions.
	 *
	 * @param string $path Absolute path.
	 *
	 * @return bool True when executable afterward.
	 */
	private function tryAddExecutePermission(string $path): bool
	{
		if (DIRECTORY_SEPARATOR === '\\' || !$this->isFunctionAvailable('chmod'))
		{
			return false;
		}

		$permissions = @fileperms($path);

		if ($permissions === false || !@chmod($path, ($permissions & 0777) | 0111))
		{
			return false;
		}

		clearstatcache(true, $path);

		return is_executable($path);
	}

	/**
	 * @brief Determine whether a path is absolute.
	 *
	 * @param string $path Candidate path.
	 *
	 * @return bool True for absolute paths.
	 */
	private function isAbsolutePath(string $path): bool
	{
		return str_starts_with($path, '/')
			|| str_starts_with($path, '\\')
			|| preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
	}

	/**
	 * @brief Determine whether a PHP function is callable and enabled.
	 *
	 * @param string $function Function name.
	 *
	 * @return bool True when callable.
	 */
	private function isFunctionAvailable(string $function): bool
	{
		$disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

		return function_exists($function) && !in_array($function, $disabled, true);
	}
}
