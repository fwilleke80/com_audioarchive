<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

/** @brief Simulate PHP's multipart upload marker only for one test-owned temporary file. */
function is_uploaded_file(string $path): bool
{
    return $path !== '' && $path === ($GLOBALS['coverUploadFixture'] ?? null) && is_file($path);
}
