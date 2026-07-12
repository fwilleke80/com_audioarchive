<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Manage protected Audio Archive storage directories and files.
 */
class ManagedStorageService
{
    /** @var array<string, string> */
    private const ROLE_PARAMETERS = [
        'original' => 'original_directory',
        'preview' => 'preview_directory',
        'waveform' => 'waveform_directory',
        'import' => 'import_directory',
    ];

    /** @var Registry */
    private Registry $params;

    /**
     * @brief Construct the storage service.
     *
     * @param Registry $params Component parameters.
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * @brief Create and protect every configured storage root.
     *
     * @return array<string, string> Absolute path by role.
     */
    public function ensureAllDirectories(): array
    {
        $paths = [];

        foreach (array_keys(self::ROLE_PARAMETERS) as $role)
        {
            $paths[$role] = $this->ensureDirectory($role);
        }

        return $paths;
    }

    /**
     * @brief Resolve, create, and protect one configured storage root.
     *
     * @param string $role Storage role.
     *
     * @return string Absolute root path.
     */
    public function ensureDirectory(string $role): string
    {
        $root = $this->getRoot($role);
        $this->assertNoSymlinkTraversal($root);

        if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root))
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_STORAGE_ERROR_CREATE_ROOT', $role));
        }

        if (!is_writable($root))
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_STORAGE_ERROR_NOT_WRITABLE', $role));
        }

        $realRoot = realpath($root);

        if ($realRoot === false)
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_STORAGE_ERROR_RESOLVE_ROOT', $role));
        }

        $realRoot = Path::clean($realRoot);
        $this->assertSafeRoot($realRoot);

        if ($this->isInsideWebRoot($realRoot))
        {
            $this->writeProtectionFiles($realRoot);
        }

        return $realRoot;
    }

    /**
     * @brief Return one configured storage root without creating it.
     *
     * @param string $role Storage role.
     *
     * @return string Absolute cleaned path.
     */
    public function getRoot(string $role): string
    {
        if (!isset(self::ROLE_PARAMETERS[$role]))
        {
            throw new \InvalidArgumentException(Text::sprintf('COM_AUDIOARCHIVE_STORAGE_ERROR_UNKNOWN_ROLE', $role));
        }

        $parameter = self::ROLE_PARAMETERS[$role];
        $default = 'audioarchive/' . ($role === 'import' ? 'import' : $role . 's');
        $configured = trim((string) $this->params->get($parameter, $default));

        if ($configured === '' || str_contains($configured, "\0"))
        {
            throw new \RuntimeException(Text::sprintf('COM_AUDIOARCHIVE_STORAGE_ERROR_INVALID_ROOT', $role));
        }

        $path = $this->isAbsolutePath($configured)
            ? $configured
            : JPATH_ROOT . DIRECTORY_SEPARATOR . $configured;
        $path = Path::clean($path);
        $this->assertSafeRoot($path);

        return $path;
    }

    /**
     * @brief Move one uploaded original into protected managed storage.
     *
     * @param string $temporaryPath PHP upload temporary path.
     * @param string $uuid Clip UUID.
     * @param string $extension Validated lowercase extension.
     *
     * @return array{storage_key:string,absolute_path:string} Stored path data.
     */
    public function storeOriginal(string $temporaryPath, string $uuid, string $extension): array
    {
        return $this->storeOriginalFile($temporaryPath, $uuid, $extension, '');
    }

    /**
     * @brief Store a replacement original under a fresh managed key.
     *
     * Keeping the replacement under a fresh key allows the existing original
     * to remain untouched until the database transaction has committed.
     *
     * @param string $temporaryPath PHP upload temporary path.
     * @param string $uuid Clip UUID.
     * @param string $extension Validated lowercase extension.
     *
     * @return array{storage_key:string,absolute_path:string} Stored path data.
     */
    public function storeReplacementOriginal(string $temporaryPath, string $uuid, string $extension): array
    {
        return $this->storeOriginalFile($temporaryPath, $uuid, $extension, '-r' . bin2hex(random_bytes(6)));
    }

    /**
     * @brief Store an original using a validated generated basename suffix.
     *
     * @param string $temporaryPath PHP upload temporary path.
     * @param string $uuid Clip UUID.
     * @param string $extension Validated lowercase extension.
     * @param string $suffix Generated filename suffix.
     *
     * @return array{storage_key:string,absolute_path:string} Stored path data.
     */
    private function storeOriginalFile(string $temporaryPath, string $uuid, string $extension, string $suffix): array
    {
        if (!is_file($temporaryPath) || !is_readable($temporaryPath))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_TEMP_NOT_READABLE'));
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_UUID'));
        }

        $extension = strtolower(trim($extension));

        if (!preg_match('/^[a-z0-9]{1,16}$/', $extension))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_EXTENSION'));
        }

        if ($suffix !== '' && !preg_match('/^-r[0-9a-f]{12}$/', $suffix))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_FILENAME_SUFFIX'));
        }

        $root = $this->ensureDirectory('original');
        $normalisedUuid = strtolower($uuid);
        $compactUuid = str_replace('-', '', $normalisedUuid);
        $relativeDirectory = substr($compactUuid, 0, 2) . '/' . substr($compactUuid, 2, 2);
        $basename = $normalisedUuid . $suffix . '.' . $extension;
        $relativePath = $relativeDirectory . '/' . $basename;
        $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_CREATE_SHARD'));
        }

        $this->assertContainedPath($root, $directory);
        $destination = $directory . DIRECTORY_SEPARATOR . $basename;

        if (file_exists($destination))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_ORIGINAL_EXISTS'));
        }

        $temporaryDestination = $destination . '.part-' . bin2hex(random_bytes(6));
        $moved = is_uploaded_file($temporaryPath)
            ? @move_uploaded_file($temporaryPath, $temporaryDestination)
            : @rename($temporaryPath, $temporaryDestination);

        if (!$moved)
        {
            $moved = @copy($temporaryPath, $temporaryDestination);
        }

        if (!$moved || !is_file($temporaryDestination))
        {
            @unlink($temporaryDestination);
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_MOVE'));
        }

        @chmod($temporaryDestination, 0640);

        if (!@rename($temporaryDestination, $destination))
        {
            @unlink($temporaryDestination);
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_FINALISE'));
        }

        return [
            'storage_key' => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
            'absolute_path' => $destination,
        ];
    }

    /**
     * @brief Resolve a managed storage key safely.
     *
     * @param string $role Storage role.
     * @param string $storageKey Relative managed key.
     *
     * @return string Absolute path.
     */
    public function resolveManagedPath(string $role, string $storageKey): string
    {
        if ($storageKey === '' || str_contains($storageKey, "\0"))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_KEY'));
        }

        $normalisedKey = str_replace('\\', '/', $storageKey);

        if (str_starts_with($normalisedKey, '/') || preg_match('#(^|/)\.\.(/|$)#', $normalisedKey))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_KEY_ESCAPE'));
        }

        $root = $this->getRoot($role);
        $path = Path::clean($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalisedKey));
        $this->assertContainedPath($root, dirname($path));

        return $path;
    }

    /**
     * @brief Delete one recorded managed file when it is safely contained.
     *
     * @param string $role Storage role.
     * @param string $storageKey Relative managed key.
     *
     * @return bool True when absent or deleted.
     */
    public function deleteManagedFile(string $role, string $storageKey): bool
    {
        $path = $this->resolveManagedPath($role, $storageKey);

        if (!file_exists($path))
        {
            return true;
        }

        if (is_link($path) || !is_file($path))
        {
            return false;
        }

        $deleted = @unlink($path);

        if ($deleted)
        {
            $this->removeEmptyParents(dirname($path), $this->getRoot($role));
        }

        return $deleted;
    }

    /**
     * @brief Determine whether a configured path is absolute.
     *
     * @param string $path Path to inspect.
     *
     * @return bool True for Unix, UNC, or drive-letter paths.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    /**
     * @brief Reject dangerous storage roots.
     *
     * @param string $path Absolute path.
     *
     * @return void
     */
    private function assertSafeRoot(string $path): void
    {
        $clean = rtrim(Path::clean($path), DIRECTORY_SEPARATOR);
        $siteRoot = rtrim(Path::clean(JPATH_ROOT), DIRECTORY_SEPARATOR);

        if ($clean === '' || $clean === DIRECTORY_SEPARATOR || $clean === $siteRoot)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_UNSAFE_ROOT'));
        }
    }

    /**
     * @brief Reject symbolic links in configured paths unless explicitly enabled.
     *
     * @param string $path Absolute configured path.
     *
     * @return void
     */
    private function assertNoSymlinkTraversal(string $path): void
    {
        if ((int) $this->params->get('allow_symlinks', 0) === 1)
        {
            return;
        }

        $current = $path;
        $existing = [];

        while ($current !== '' && $current !== dirname($current))
        {
            $existing[] = $current;
            $current = dirname($current);
        }

        foreach (array_reverse($existing) as $candidate)
        {
            if (file_exists($candidate) && is_link($candidate))
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_SYMLINK'));
            }
        }
    }

    /**
     * @brief Ensure a path remains inside the given root.
     *
     * @param string $root Storage root.
     * @param string $path Candidate existing directory.
     *
     * @return void
     */
    private function assertContainedPath(string $root, string $path): void
    {
        $realRoot = realpath($root);
        $realPath = realpath($path);

        if ($realRoot === false || $realPath === false)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_PATH_RESOLVE'));
        }

        $realRoot = rtrim(Path::clean($realRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realPath = rtrim(Path::clean($realPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!str_starts_with($realPath, $realRoot))
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_PATH_ESCAPE'));
        }
    }

    /**
     * @brief Determine whether a path is beneath Joomla's public root.
     *
     * @param string $path Absolute resolved path.
     *
     * @return bool True when web-server denial files are required.
     */
    private function isInsideWebRoot(string $path): bool
    {
        $root = rtrim(Path::clean((string) realpath(JPATH_ROOT)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $candidate = rtrim(Path::clean($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($candidate, $root);
    }

    /**
     * @brief Write defence-in-depth web-server denial files.
     *
     * @param string $root Storage root.
     *
     * @return void
     */
    private function writeProtectionFiles(string $root): void
    {
        $files = [
            '.htaccess' => "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n",
            'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
            'index.html' => '<!doctype html><title></title>',
        ];

        foreach ($files as $name => $contents)
        {
            $path = $root . DIRECTORY_SEPARATOR . $name;

            if (!file_exists($path) && @file_put_contents($path, $contents, LOCK_EX) === false)
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_STORAGE_ERROR_PROTECT'));
            }

            @chmod($path, 0640);
        }
    }

    /**
     * @brief Remove empty shard directories without deleting the storage root.
     *
     * @param string $directory Starting directory.
     * @param string $root Storage root.
     *
     * @return void
     */
    private function removeEmptyParents(string $directory, string $root): void
    {
        $root = rtrim(Path::clean($root), DIRECTORY_SEPARATOR);
        $directory = rtrim(Path::clean($directory), DIRECTORY_SEPARATOR);

        while ($directory !== $root && str_starts_with($directory . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR))
        {
            $entries = @scandir($directory);

            if (!is_array($entries) || array_diff($entries, ['.', '..']) !== [])
            {
                break;
            }

            if (!@rmdir($directory))
            {
                break;
            }

            $directory = dirname($directory);
        }
    }
}
