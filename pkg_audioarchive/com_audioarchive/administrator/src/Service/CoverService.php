<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** @brief Private cover storage shared by clips and account playlists; no public image route. */
final class CoverService
{
    public const MAX_BYTES = 2097152;

    /** @brief Bind image mutations to the authenticated actor. */
    public function __construct(private DatabaseInterface $db, private Registry $params, private User $user)
    {
    }

    /** @brief Authorise the actual entity, never a client-supplied owner. */
    private function entity(string $kind, string $id): object
    {
        if ((int) $this->user->id <= 0)
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        if ($kind === 'clip')
        {
            $row = $this->db->setQuery('SELECT * FROM #__audioarchive_clips WHERE id=' . (int) $id)->loadObject();
            if (!$row || !(new ClipAccessService($this->db, $this->user))->canEdit($row))
            {
                throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        }
        elseif ($kind === 'playlist')
        {
            $row = $this->db->setQuery('SELECT * FROM #__audioarchive_collections WHERE kind=' . $this->db->quote('playlist') . ' AND uuid=' . $this->db->quote($id) . ' AND user_id=' . (int) $this->user->id)->loadObject();
            if (!$row)
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_SAVE_FIRST'), 404);
            }
        }
        else
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        return $row;
    }

    /** @brief Inspect actual image bytes, not the browser's extension or declared MIME. */
    public static function inspect(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_INVALID'), 400);
        }
        $info = @getimagesizefromstring($bytes);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $mime !== ($info['mime'] ?? '') || $info[0] < 1 || $info[1] < 1
            || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8388608)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_INVALID'), 400);
        }
        return ['mime_type' => $mime, 'width' => (int) $info[0], 'height' => (int) $info[1], 'file_size' => strlen($bytes)];
    }

    /** @brief Validate a pending multipart upload before changing clip data. */
    public static function prepareUpload(array $file): array
    {
        $path = (string) ($file['tmp_name'] ?? '');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($path)
            || filesize($path) > self::MAX_BYTES)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_INVALID'), 400);
        }
        $bytes = file_get_contents($path);
        return self::inspect($bytes) + ['image_data' => base64_encode($bytes)];
    }

    /** @brief Commit a cover inside the authorised clip save's transaction and owner lock. */
    public function saveWithClip(int $id, ?array $image): void
    {
        $clip = $this->db->setQuery('SELECT id, created_by FROM #__audioarchive_clips WHERE id=' . $id)->loadObject();
        if (!$clip)
        {
            throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_ERROR'));
        }
        $where = "entity_type='clip' AND entity_id=" . $id;
        $oldSize = (int) $this->db->setQuery('SELECT file_size FROM #__audioarchive_covers WHERE ' . $where)->loadResult();
        if ($image !== null)
        {
            (new UserQuotaService($this->db, $this->params, $this->user))->assertIncrease((int) $clip->created_by, max(0, $image['file_size'] - $oldSize), 0);
        }
        $this->db->setQuery('DELETE FROM #__audioarchive_covers WHERE ' . $where)->execute();
        if ($image !== null)
        {
            $record = (object) (['entity_type' => 'clip', 'entity_id' => $id, 'modified' => gmdate('Y-m-d H:i:s')] + $image);
            $this->db->insertObject('#__audioarchive_covers', $record);
        }
    }

    /** @brief Save an owned playlist cover within the collection transaction and owner lock. */
    public function saveWithPlaylist(string $uuid, ?array $image): void
    {
        $entity = $this->entity('playlist', $uuid);
        $where = "entity_type='playlist' AND entity_id=" . (int) $entity->id;
        $oldSize = (int) $this->db->setQuery('SELECT file_size FROM #__audioarchive_covers WHERE ' . $where)->loadResult();
        if ($image !== null)
        {
            (new UserQuotaService($this->db, $this->params, $this->user))->assertIncrease((int) $entity->user_id, max(0, $image['file_size'] - $oldSize), 0);
        }
        $this->db->setQuery('DELETE FROM #__audioarchive_covers WHERE ' . $where)->execute();
        if ($image !== null)
        {
            $record = (object) (['entity_type' => 'playlist', 'entity_id' => (int) $entity->id, 'modified' => gmdate('Y-m-d H:i:s')] + $image);
            $this->db->insertObject('#__audioarchive_covers', $record);
        }
    }

    /** @brief Read or change one cover; bytes remain in protected database storage. */
    public function manage(string $kind, string $id, string $action, array $file = []): array
    {
        $entity = $this->entity($kind, $id);
        $where = 'entity_type=' . $this->db->quote($kind) . ' AND entity_id=' . (int) $entity->id;
        if (!in_array($action, ['status', 'upload', 'remove'], true))
        {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 400);
        }
        $image = null;
        if ($action === 'upload')
        {
            $path = (string) ($file['tmp_name'] ?? '');
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($path)
                || filesize($path) > self::MAX_BYTES)
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_INVALID'), 400);
            }
            $bytes = file_get_contents($path);
            $image = self::inspect($bytes);
            $image['image_data'] = base64_encode($bytes);
        }
        if ($action !== 'status')
        {
            $owner = (int) ($kind === 'clip' ? $entity->created_by : $entity->user_id);
            $operation = function () use ($kind, $id, $entity, $where, $image, $owner): void
            {
            $this->db->transactionStart();
            try
            {
                // Serialise cover writes to this entity, including concurrent replacements.
                $table = $kind === 'clip' ? '#__audioarchive_clips' : '#__audioarchive_collections';
                $this->db->setQuery('SELECT id FROM ' . $table . ' WHERE id=' . (int) $entity->id . ' FOR UPDATE')->loadResult();
                $entity = $this->entity($kind, $id);
                if ($owner !== (int) ($kind === 'clip' ? $entity->created_by : $entity->user_id))
                {
                    throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_ERROR'), 400);
                }
                $oldSize = (int) $this->db->setQuery('SELECT file_size FROM #__audioarchive_covers WHERE ' . $where)->loadResult();
                if ($image !== null)
                {
                    $owner = (int) ($kind === 'clip' ? $entity->created_by : $entity->user_id);
                    try
                    {
                        (new UserQuotaService($this->db, $this->params, $this->user))->assertIncrease($owner, max(0, $image['file_size'] - $oldSize), 0);
                    }
                    catch (\RuntimeException $error)
                    {
                        throw new \RuntimeException($error->getMessage(), 400, $error);
                    }
                }
                $this->db->setQuery('DELETE FROM #__audioarchive_covers WHERE ' . $where)->execute();
                if ($image !== null)
                {
                    $record = (object) (['entity_type' => $kind, 'entity_id' => (int) $entity->id, 'modified' => gmdate('Y-m-d H:i:s')] + $image);
                    $this->db->insertObject('#__audioarchive_covers', $record);
                }
                $this->db->transactionCommit();
            }
            catch (\Throwable $error)
            {
                $this->db->transactionRollback();
                throw $error;
            }
            };
            (new UserQuotaService($this->db, $this->params, $this->user))->withOwnerLocks([$owner], $operation);
        }
        $cover = $this->db->setQuery('SELECT width, height, file_size, mime_type FROM #__audioarchive_covers WHERE ' . $where)->loadAssoc();
        return ['cover' => $cover ?: null];
    }
}
