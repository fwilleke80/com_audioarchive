<?php
namespace Punga\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** @brief Validate optional descriptive metadata independently of ownership and technical audio data. */
final class ClipMetadataService
{
    /** @brief Build only explicitly requested batch changes; untouched fields never get cleared. */
    public static function batchChanges(array $batch): array
    {
        $changes = [];
        foreach (['licence', 'creator', 'contributors', 'recording_location', 'genre_style', 'creation_year', 'lyrics'] as $field)
        {
            $mode = $batch['metadata_mode'][$field] ?? 'keep';
            if (!in_array($mode, ['keep', 'set', 'clear'], true))
            {
                throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_METADATA_INVALID'));
            }
            if ($mode === 'keep')
            {
                continue;
            }
            $value = $mode === 'clear' ? '' : ($batch['metadata'][$field] ?? '');
            $changes[$field] = self::normalise([$field => $value])[$field];
        }
        return $changes;
    }

    /** @brief Return bounded plain-text fields and a nullable creation year for forms and restore. */
    public static function normalise(array $data): array
    {
        $result = [];
        foreach (['licence' => 255, 'creator' => 255, 'contributors' => 8000, 'recording_location' => 255, 'genre_style' => 255, 'lyrics' => 60000] as $field => $limit)
        {
            $value = $data[$field] ?? '';
            if (!is_scalar($value))
            {
                throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_METADATA_INVALID'));
            }
            $value = trim(str_replace(["\r\n", "\r"], "\n", strip_tags((string) $value)));
            // Text columns have a byte limit; short VARCHAR fields have a character limit.
            if (mb_strlen($value, 'UTF-8') > $limit || strlen($value) > 60000)
            {
                throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_METADATA_TOO_LONG'));
            }
            $result[$field] = $value;
        }
        $year = $data['creation_year'] ?? '';
        if (!is_scalar($year) || ($year !== '' && (!preg_match('/^[0-9]{1,4}$/D', (string) $year) || (int) $year < 1)))
        {
            throw new \InvalidArgumentException(Text::_('COM_AUDIOARCHIVE_METADATA_YEAR_INVALID'));
        }
        $result['creation_year'] = $year === '' ? null : (int) $year;
        return $result;
    }
}
