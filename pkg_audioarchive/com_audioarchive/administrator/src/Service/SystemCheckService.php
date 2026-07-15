<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Collect non-destructive Audio Archive system diagnostics.
 */
class SystemCheckService
{
    /** @var string[] */
    private const REQUIRED_TABLES = [
        'audioarchive_clips',
        'audioarchive_files',
        'audioarchive_waveforms',
        'audioarchive_analyses',
        'audioarchive_jobs',
    ];

    /** @var DatabaseInterface */
    private DatabaseInterface $database;

    /** @var Registry */
    private Registry $params;

    /**
     * @brief Construct the diagnostic service.
     *
     * @param DatabaseInterface $database Joomla database connection.
     * @param Registry $params Audio Archive component parameters.
     */
    public function __construct(DatabaseInterface $database, Registry $params)
    {
        $this->database = $database;
        $this->params = $params;
    }

    /**
     * @brief Run all dashboard diagnostics.
     *
     * @return array<string, mixed> Structured diagnostic result.
     */
    public function run(): array
    {
        $processAvailable = $this->isFunctionAvailable('proc_open');
        $ffprobe = $processAvailable
            ? $this->detectExecutable('ffprobe', (string) $this->params->get('ffprobe_path', ''))
            : $this->missingExecutableResult();
        $ffmpeg = $processAvailable
            ? $this->detectExecutable('ffmpeg', (string) $this->params->get('ffmpeg_path', ''))
            : $this->missingExecutableResult();

        $sections = [
            [
                'title' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_RUNTIME',
                'checks' => $this->runtimeChecks($processAvailable, $ffprobe, $ffmpeg),
            ],
            [
                'title' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_STORAGE',
                'checks' => $this->storageChecks(),
            ],
            [
                'title' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_DATABASE',
                'checks' => $this->databaseChecks(),
            ],
            [
                'title' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_PHP_LIMITS',
                'checks' => $this->phpLimitChecks(),
            ],
        ];

        $overall = 'ok';

        foreach ($sections as $section)
        {
            foreach ($section['checks'] as $check)
            {
                if ($check['status'] === 'error')
                {
                    $overall = 'error';
                    break 2;
                }

                if ($check['status'] === 'warning')
                {
                    $overall = 'warning';
                }
            }
        }

        return [
            'overall' => $overall,
            'sections' => $sections,
            'checked_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /**
     * @brief Build PHP and executable checks.
     *
     * @param bool $processAvailable Whether proc_open can be used.
     * @param array<string, mixed> $ffprobe FFprobe detection result.
     * @param array<string, mixed> $ffmpeg FFmpeg detection result.
     *
     * @return array<int, array<string, string>> Diagnostic rows.
     */
    private function runtimeChecks(bool $processAvailable, array $ffprobe, array $ffmpeg): array
    {
        $fileinfoAvailable = extension_loaded('fileinfo') && function_exists('finfo_open');
        return [
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_PHP_VERSION',
                version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'error',
                PHP_VERSION,
                version_compare(PHP_VERSION, '8.3.0', '>=') ? '' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_PHP_VERSION_TOO_OLD'
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_FILEINFO',
                $fileinfoAvailable ? 'ok' : 'warning',
                $fileinfoAvailable ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_AVAILABLE' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_UNAVAILABLE'
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_PROCESS_EXECUTION',
                $processAvailable ? 'ok' : 'warning',
                $processAvailable ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_AVAILABLE' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_DISABLED',
                $processAvailable ? '' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_PROCESS_EXECUTION_DESC'
            ),
            $this->executableCheck('COM_AUDIOARCHIVE_SYSTEM_CHECK_FFPROBE', $ffprobe),
            $this->executableCheck('COM_AUDIOARCHIVE_SYSTEM_CHECK_FFMPEG', $ffmpeg),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_METADATA_EXTRACTION',
                'ok',
                $ffprobe['available'] ? 'FFprobe' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_PHP_INSPECTOR',
                $ffprobe['available']
                    ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_PHP_INSPECTOR_AVAILABLE'
                    : MediaInspectorService::getVersion()
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_PREVIEW_GENERATION',
                $ffmpeg['available'] ? 'ok' : 'warning',
                $ffmpeg['available'] ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_AVAILABLE' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_UNAVAILABLE'
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_WAVEFORM_GENERATION',
                $ffmpeg['available'] ? 'ok' : 'warning',
                $ffmpeg['available'] ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_AVAILABLE' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_UNAVAILABLE'
            ),
        ];
    }

    /**
     * @brief Build storage-directory diagnostics.
     *
     * @return array<int, array<string, string>> Diagnostic rows.
     */
    private function storageChecks(): array
    {
        return [
            $this->pathCheck(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_ORIGINAL_STORAGE',
                (string) $this->params->get('original_directory', 'audioarchive/originals')
            ),
            $this->pathCheck(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_PREVIEW_STORAGE',
                (string) $this->params->get('preview_directory', 'audioarchive/previews')
            ),
            $this->pathCheck(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_WAVEFORM_STORAGE',
                (string) $this->params->get('waveform_directory', 'audioarchive/waveforms')
            ),
            $this->pathCheck(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_IMPORT_INBOX',
                (string) $this->params->get('import_directory', 'audioarchive/import')
            ),
        ];
    }

    /**
     * @brief Build database schema diagnostics.
     *
     * @return array<int, array<string, string>> Diagnostic rows.
     */
    private function databaseChecks(): array
    {
        $tables = array_map('strtolower', $this->database->getTableList());
        $prefix = strtolower($this->database->getPrefix());
        $missing = [];

        foreach (self::REQUIRED_TABLES as $table)
        {
            if (!in_array($prefix . strtolower($table), $tables, true))
            {
                $missing[] = '#__' . $table;
            }
        }

        $checkoutNullable = false;

        if ($missing === [])
        {
            try
            {
                $query = 'SHOW COLUMNS FROM ' . $this->database->quoteName('#__audioarchive_clips')
                    . ' LIKE ' . $this->database->quote('checked_out');
                $column = $this->database->setQuery($query)->loadObject();
                $checkoutNullable = $column !== null && strtoupper((string) ($column->Null ?? '')) === 'YES';
            }
            catch (\Throwable $exception)
            {
                $checkoutNullable = false;
            }
        }

        $contentTypeExists = false;

        try
        {
            $query = $this->database->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->database->quoteName('#__content_types'))
                ->where($this->database->quoteName('type_alias') . ' = ' . $this->database->quote('com_audioarchive.clip'));
            $contentTypeExists = (int) $this->database->setQuery($query)->loadResult() > 0;
        }
        catch (\Throwable $exception)
        {
            $contentTypeExists = false;
        }

        $defaultCategory = (int) $this->params->get('default_category', 0);
        $defaultCategoryValid = false;
        $defaultCategoryValue = 'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_CONFIGURED';

        if ($defaultCategory > 0)
        {
            try
            {
                $query = $this->database->getQuery(true)
                    ->select([
                        $this->database->quoteName('id'),
                        $this->database->quoteName('title'),
                    ])
                    ->from($this->database->quoteName('#__categories'))
                    ->where($this->database->quoteName('id') . ' = ' . $defaultCategory)
                    ->where($this->database->quoteName('extension') . ' = ' . $this->database->quote('com_audioarchive'))
                    ->where($this->database->quoteName('published') . ' <> -2');
                $category = $this->database->setQuery($query)->loadObject();
                $defaultCategoryValid = $category !== null;

                if ($defaultCategoryValid)
                {
                    $defaultCategoryValue = '#' . (int) $category->id . ' — ' . (string) $category->title;
                }
            }
            catch (\Throwable $exception)
            {
                $defaultCategoryValid = false;
            }
        }

        return [
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_TABLES',
                $missing === [] ? 'ok' : 'error',
                $missing === [] ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_COMPLETE' : implode(', ', $missing)
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_CHECKOUT_SCHEMA',
                $checkoutNullable ? 'ok' : 'error',
                $checkoutNullable ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_CORRECT' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_INCORRECT'
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_CONTENT_TYPE',
                $contentTypeExists ? 'ok' : 'error',
                $contentTypeExists ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_REGISTERED' : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_MISSING'
            ),
            $this->check(
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_DEFAULT_CATEGORY',
                $defaultCategoryValid ? 'ok' : 'warning',
                $defaultCategoryValue
            ),
        ];
    }

    /**
     * @brief Build PHP upload and execution-limit diagnostics.
     *
     * @return array<int, array<string, string>> Diagnostic rows.
     */
    private function phpLimitChecks(): array
    {
        return [
            $this->check('upload_max_filesize', 'neutral', (string) ini_get('upload_max_filesize')),
            $this->check('post_max_size', 'neutral', (string) ini_get('post_max_size')),
            $this->check('max_file_uploads', 'neutral', (string) ini_get('max_file_uploads')),
            $this->check('max_execution_time', 'neutral', (string) ini_get('max_execution_time') . ' s'),
            $this->check('memory_limit', 'neutral', (string) ini_get('memory_limit')),
        ];
    }

    /**
     * @brief Convert executable detection into a diagnostic row.
     *
     * @param string $label Language key for the executable.
     * @param array<string, mixed> $result Detection result.
     *
     * @return array<string, string> Diagnostic row.
     */
    private function executableCheck(string $label, array $result): array
    {
        if (!$result['available'])
        {
            return $this->check(
                $label,
                'warning',
                (string) ($result['value'] ?? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_FOUND'),
                (string) $result['error']
            );
        }

        $detail = trim((string) $result['candidate']);

        if ((string) $result['version'] !== '')
        {
            $detail .= ($detail !== '' ? ' — ' : '') . (string) $result['version'];
        }

        if ((bool) ($result['permission_adjusted'] ?? false))
        {
            $detail .= ($detail !== '' ? ' — ' : '')
                . Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTE_PERMISSION_ADDED');
        }

        return $this->check($label, 'ok', 'COM_AUDIOARCHIVE_SYSTEM_CHECK_AVAILABLE', $detail);
    }

    /**
     * @brief Validate one configured storage path without creating it.
     *
     * @param string $label Language key for the path.
     * @param string $configuredPath Configured absolute or site-relative path.
     *
     * @return array<string, string> Diagnostic row.
     */
    private function pathCheck(string $label, string $configuredPath): array
    {
        $configuredPath = trim($configuredPath);

        if ($configuredPath === '')
        {
            return $this->check(
                $label,
                'error',
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_CONFIGURED'
            );
        }

        $absolutePath = $this->absolutePath($configuredPath);

        if (is_file($absolutePath))
        {
            return $this->check(
                $label,
                'error',
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_PATH_IS_FILE',
                $absolutePath
            );
        }

        if (is_dir($absolutePath))
        {
            return $this->check(
                $label,
                is_writable($absolutePath) ? 'ok' : 'error',
                is_writable($absolutePath)
                    ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_WRITABLE'
                    : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_WRITABLE',
                $absolutePath
            );
        }

        $parent = $absolutePath;

        while (!is_dir($parent))
        {
            $next = dirname($parent);

            if ($next === $parent)
            {
                break;
            }

            $parent = $next;
        }

        $parentWritable = is_dir($parent) && is_writable($parent);

        return $this->check(
            $label,
            $parentWritable ? 'warning' : 'error',
            $parentWritable
                ? 'COM_AUDIOARCHIVE_SYSTEM_CHECK_MISSING_PARENT_WRITABLE'
                : 'COM_AUDIOARCHIVE_SYSTEM_CHECK_MISSING_PARENT_NOT_WRITABLE',
            $absolutePath
        );
    }

    /**
     * @brief Resolve a configured path relative to the Joomla site root.
     *
     * @param string $configuredPath Configured path.
     *
     * @return string Normalised absolute path.
     */
    private function absolutePath(string $configuredPath): string
    {
        if ($this->isAbsolutePath($configuredPath))
        {
            return Path::clean($configuredPath);
        }

        return Path::clean(JPATH_ROOT . '/' . ltrim($configuredPath, '/\\'));
    }

    /**
     * @brief Determine whether a filesystem path is absolute.
     *
     * @param string $path Filesystem path.
     *
     * @return bool True for Unix, Windows drive, or UNC paths.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * @brief Resolve an explicit executable path.
     *
     * @param string $configuredPath Absolute path or path relative to the Joomla site root.
     *
     * @return array{path: string, error: string} Resolved path or validation error.
     */
    private function resolveConfiguredExecutablePath(string $configuredPath): array
    {
        if ($this->isAbsolutePath($configuredPath))
        {
            return [
                'path' => Path::clean($configuredPath),
                'error' => '',
            ];
        }

        $siteRoot = rtrim(Path::clean(JPATH_ROOT), DIRECTORY_SEPARATOR);
        $resolvedPath = Path::clean(
            $siteRoot . DIRECTORY_SEPARATOR . ltrim($configuredPath, '/\\')
        );

        if (!$this->isPathInsideRoot($resolvedPath, $siteRoot))
        {
            return [
                'path' => '',
                'error' => Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_OUTSIDE_ROOT_DESC', $configuredPath),
            ];
        }

        return [
            'path' => $resolvedPath,
            'error' => '',
        ];
    }

    /**
     * @brief Determine whether a path is located inside a root directory.
     *
     * @param string $path Path to test.
     * @param string $root Allowed root directory.
     *
     * @return bool True when the path is a child of the root directory.
     */
    private function isPathInsideRoot(string $path, string $root): bool
    {
        $normalisedPath = rtrim(Path::clean($path), DIRECTORY_SEPARATOR);
        $normalisedRoot = rtrim(Path::clean($root), DIRECTORY_SEPARATOR);

        if (DIRECTORY_SEPARATOR === '\\')
        {
            $normalisedPath = strtolower($normalisedPath);
            $normalisedRoot = strtolower($normalisedRoot);
        }

        return $normalisedPath !== $normalisedRoot
            && str_starts_with($normalisedPath, $normalisedRoot . DIRECTORY_SEPARATOR);
    }

    /**
     * @brief Detect an FFmpeg-family executable and obtain its version.
     *
     * @param string $program Program name.
     * @param string $configuredPath Explicit administrator-configured path.
     *
     * @return array<string, mixed> Detection result.
     */
    private function detectExecutable(string $program, string $configuredPath): array
    {
        $candidates = [];
        $configuredError = '';
        $configuredPath = $this->normaliseConfiguredExecutablePath($configuredPath);

        if ($configuredPath !== '')
        {
            $resolved = $this->resolveConfiguredExecutablePath($configuredPath);

            if ($resolved['path'] !== '')
            {
                $candidates[] = [
                    'path' => $resolved['path'],
                    'configured' => true,
                ];
            }
            else
            {
                $configuredError = $resolved['error'];
            }
        }

        if ((int) $this->params->get('automatic_executable_detection', 1) === 1)
        {
            foreach ([
                $program,
                '/usr/bin/' . $program,
                '/usr/local/bin/' . $program,
            ] as $candidate)
            {
                $candidates[] = [
                    'path' => $candidate,
                    'configured' => false,
                ];
            }
        }

        $uniqueCandidates = [];

        foreach ($candidates as $candidate)
        {
            $uniqueCandidates[(string) $candidate['path']] = $candidate;
        }

        $configuredFailure = $configuredError !== ''
            ? $this->unavailableExecutableResult(
                $configuredPath,
                'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_FOUND',
                $configuredError
            )
            : null;
        $lastResult = $configuredFailure ?? [
            'available' => false,
            'candidate' => '',
            'version' => '',
            'error' => '',
            'value' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_FOUND',
            'permission_adjusted' => false,
        ];

        foreach ($uniqueCandidates as $candidateData)
        {
            $candidate = (string) $candidateData['path'];
            $configured = (bool) $candidateData['configured'];
            $permissionAdjusted = false;

            if ($this->isAbsolutePath($candidate))
            {
                clearstatcache(true, $candidate);

                if (!file_exists($candidate))
                {
                    $lastResult = $this->unavailableExecutableResult(
                        $candidate,
                        'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_FOUND',
                        Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_MISSING_DESC', $candidate)
                    );

                    if ($configured)
                    {
                        $configuredFailure = $lastResult;
                    }

                    continue;
                }

                if (!is_file($candidate))
                {
                    $lastResult = $this->unavailableExecutableResult(
                        $candidate,
                        'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_FOUND',
                        Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_NOT_FILE_DESC', $candidate)
                    );

                    if ($configured)
                    {
                        $configuredFailure = $lastResult;
                    }

                    continue;
                }

                if (!is_readable($candidate))
                {
                    $lastResult = $this->unavailableExecutableResult(
                        $candidate,
                        'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_READABLE',
                        Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_NOT_READABLE_DESC', $candidate)
                    );

                    if ($configured)
                    {
                        $configuredFailure = $lastResult;
                    }

                    continue;
                }

                if (!is_executable($candidate) && $configured)
                {
                    $permissionAdjusted = $this->tryAddExecutePermission($candidate);
                }

                clearstatcache(true, $candidate);

                if (!is_executable($candidate))
                {
                    $lastResult = $this->unavailableExecutableResult(
                        $candidate,
                        'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_EXECUTABLE',
                        Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_NOT_EXECUTABLE_DESC', $candidate)
                    );

                    if ($configured)
                    {
                        $configuredFailure = $lastResult;
                    }

                    continue;
                }
            }

            $result = $this->runVersionCommand($candidate);
            $result['permission_adjusted'] = $permissionAdjusted;

            if ($result['available'])
            {
                return $result;
            }

            $lastResult = $result;

            if ($configured)
            {
                $configuredFailure = $result;
            }
        }

        return $configuredFailure ?? $lastResult;
    }

    /**
     * @brief Normalise an administrator-configured executable path.
     *
     * @param string $configuredPath Configured path, optionally surrounded by quotes.
     *
     * @return string Trimmed path without matching surrounding quotes.
     */
    private function normaliseConfiguredExecutablePath(string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);

        if (strlen($configuredPath) >= 2)
        {
            $first = $configuredPath[0];
            $last = $configuredPath[strlen($configuredPath) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'"))
            {
                $configuredPath = trim(substr($configuredPath, 1, -1));
            }
        }

        return $configuredPath;
    }

    /**
     * @brief Try to add Unix execute bits to an explicitly configured binary.
     *
     * This is limited to administrator-configured files. Existing read and write
     * permissions are preserved.
     *
     * @param string $path Absolute executable path.
     *
     * @return bool True when the file became executable.
     */
    private function tryAddExecutePermission(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '\\' || !$this->isFunctionAvailable('chmod'))
        {
            return false;
        }

        $permissions = @fileperms($path);

        if ($permissions === false)
        {
            return false;
        }

        $mode = ($permissions & 0777) | 0111;

        if (!@chmod($path, $mode))
        {
            return false;
        }

        clearstatcache(true, $path);

        return is_executable($path);
    }

    /**
     * @brief Create a detailed unavailable-executable result.
     *
     * @param string $candidate Candidate path.
     * @param string $value Language key describing the failure type.
     * @param string $error Detailed diagnostic message.
     *
     * @return array<string, mixed> Detection result.
     */
    private function unavailableExecutableResult(string $candidate, string $value, string $error): array
    {
        return [
            'available' => false,
            'candidate' => $candidate,
            'version' => '',
            'error' => $error,
            'value' => $value,
            'permission_adjusted' => false,
        ];
    }

    /**
     * @brief Execute one version probe without invoking a shell.
     *
     * @param string $candidate Executable path or PATH-resolved command name.
     *
     * @return array<string, mixed> Probe result.
     */
    private function runVersionCommand(string $candidate): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = @proc_open([$candidate, '-version'], $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($process))
        {
            return [
                'available' => false,
                'candidate' => $candidate,
                'version' => '',
                'error' => Text::sprintf('COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_START_FAILED_DESC', $candidate),
                'value' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTION_FAILED',
                'permission_adjusted' => false,
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $exitCode = -1;
        $deadline = microtime(true) + min(5, max(1, (int) $this->params->get('process_timeout', 120)));

        do
        {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
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

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($process);

        if ($exitCode < 0 && $closeCode >= 0)
        {
            $exitCode = $closeCode;
        }

        $output = trim($stdout . "\n" . $stderr);

        if ($exitCode !== 0 || $output === '')
        {
            return [
                'available' => false,
                'candidate' => $candidate,
                'version' => '',
                'error' => Text::sprintf(
                    'COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTABLE_VERSION_FAILED_DESC',
                    $candidate,
                    $output !== '' ? strtok($output, "\r\n") : Text::_('COM_AUDIOARCHIVE_SYSTEM_CHECK_NO_VERSION_RESPONSE')
                ),
                'value' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_EXECUTION_FAILED',
                'permission_adjusted' => false,
            ];
        }

        $version = strtok($output, "\r\n") ?: '';

        return [
            'available' => true,
            'candidate' => $candidate,
            'version' => $version,
            'error' => '',
            'value' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_AVAILABLE',
            'permission_adjusted' => false,
        ];
    }

    /**
     * @brief Determine whether a PHP function is callable and not disabled.
     *
     * @param string $function Function name.
     *
     * @return bool True when callable.
     */
    private function isFunctionAvailable(string $function): bool
    {
        $disabled = array_filter(
            array_map('trim', explode(',', (string) ini_get('disable_functions')))
        );

        return function_exists($function) && !in_array($function, $disabled, true);
    }

    /**
     * @brief Return an unavailable executable result.
     *
     * @return array<string, mixed> Detection result.
     */
    private function missingExecutableResult(): array
    {
        return [
            'available' => false,
            'candidate' => '',
            'version' => '',
            'error' => '',
            'value' => 'COM_AUDIOARCHIVE_SYSTEM_CHECK_NOT_FOUND',
            'permission_adjusted' => false,
        ];
    }

    /**
     * @brief Build one diagnostic row.
     *
     * @param string $label Label or language key.
     * @param string $status One of ok, warning, error, or neutral.
     * @param string $value Value or language key.
     * @param string $detail Optional detail or language key.
     *
     * @return array<string, string> Diagnostic row.
     */
    private function check(string $label, string $status, string $value, string $detail = ''): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'value' => $value,
            'detail' => $detail,
        ];
    }
}
