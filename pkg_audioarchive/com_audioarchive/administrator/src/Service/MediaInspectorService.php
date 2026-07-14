<?php

namespace Willeke\Component\Audioarchive\Administrator\Service;

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * @brief Inspect supported audio files without external executables.
 *
 * The inspector deliberately reads only format headers and metadata blocks. It
 * never decodes audio samples and therefore remains usable on shared hosting.
 */
class MediaInspectorService
{
    /** @var array<int, int> */
    private const AAC_SAMPLE_RATES = [
        96000,
        88200,
        64000,
        48000,
        44100,
        32000,
        24000,
        22050,
        16000,
        12000,
        11025,
        8000,
        7350,
    ];

    /** @var array<int, string> */
    private const MP4_AUDIO_CODECS = [
        'mp4a' => 'AAC',
        'alac' => 'ALAC',
        'Opus' => 'Opus',
        'fLaC' => 'FLAC',
        'ac-3' => 'AC-3',
        'ec-3' => 'E-AC-3',
    ];

    /**
     * @brief Inspect one local media file.
     *
     * @param string $path Absolute local path.
     * @param string $originalFilename Original client filename.
     *
     * @return array<string, mixed> Normalised technical metadata.
     */
    public function inspect(string $path, string $originalFilename): array
    {
        $result = $this->emptyResult($path, $originalFilename);

        if (!is_file($path) || !is_readable($path))
        {
            $result['errors'][] = Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_NOT_READABLE');
            return $result;
        }

        $result['file_size'] = max(0, (int) filesize($path));
        $result['mime_type'] = $this->detectMimeType($path);
        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $result['extension'] = $extension;

        $extensionParser = match ($extension)
        {
            'm4a', 'mp4' => 'mp4',
            'mp3' => 'mp3',
            'wav' => 'wav',
            'flac' => 'flac',
            'ogg', 'oga', 'opus' => 'ogg',
            'aac' => 'aac',
            'webm' => 'webm',
            default => '',
        };
        $signatureParser = $this->detectParserFromSignature($path);
        $parser = $signatureParser !== '' ? $signatureParser : $extensionParser;

        if ($signatureParser !== '' && $extensionParser !== '' && $signatureParser !== $extensionParser)
        {
            $result['warnings'][] = Text::sprintf(
                'COM_AUDIOARCHIVE_INSPECT_WARNING_EXTENSION_CONTENT',
                $extension,
                $signatureParser
            );
        }

        try
        {
            $parsed = match ($parser)
            {
                'mp4' => $this->inspectMp4($path),
                'mp3' => $this->inspectMp3($path),
                'wav' => $this->inspectWav($path),
                'flac' => $this->inspectFlac($path),
                'ogg' => $this->inspectOgg($path),
                'aac' => $this->inspectAac($path),
                'webm' => $this->inspectWebm($path),
                default => [],
            };
        }
        catch (\Throwable $exception)
        {
            $parsed = [];
            $result['errors'][] = Text::sprintf('COM_AUDIOARCHIVE_INSPECT_ERROR_EXCEPTION', $exception->getMessage());
        }

        foreach ($parsed as $key => $value)
        {
            if ($key === 'warnings' || $key === 'errors')
            {
                $result[$key] = array_merge($result[$key], (array) $value);
                continue;
            }

            $result[$key] = $value;
        }

        if ($result['container_format'] === '')
        {
            $result['errors'][] = Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_UNRECOGNISED');
        }

        if ((int) $result['duration_ms'] <= 0)
        {
            $result['warnings'][] = Text::_('COM_AUDIOARCHIVE_INSPECT_WARNING_DURATION');
        }

        $result['valid'] = $result['errors'] === [] && $result['container_format'] !== '';
        $result['warnings'] = array_values(array_unique(array_filter($result['warnings'])));
        $result['errors'] = array_values(array_unique(array_filter($result['errors'])));

        return $result;
    }

    /**
     * @brief Return the inspector implementation version.
     *
     * @return string Version identifier.
     */
    public static function getVersion(): string
    {
        return 'audioarchive-php-inspector-1';
    }

    /**
     * @brief Build the normalised result skeleton.
     *
     * @param string $path Local file path.
     * @param string $originalFilename Original filename.
     *
     * @return array<string, mixed> Empty result.
     */
    private function emptyResult(string $path, string $originalFilename): array
    {
        return [
            'valid' => false,
            'path' => $path,
            'original_filename' => $originalFilename,
            'extension' => '',
            'mime_type' => '',
            'container_format' => '',
            'audio_codec' => '',
            'file_size' => 0,
            'duration_ms' => 0,
            'sample_rate' => 0,
            'channels' => 0,
            'bitrate' => 0,
            'embedded_title' => '',
            'recorded_at' => null,
            'warnings' => [],
            'errors' => [],
            'inspector' => self::getVersion(),
        ];
    }

    /**
     * @brief Select a parser from binary file signatures.
     *
     * @param string $path Local file path.
     *
     * @return string Parser identifier, or an empty string when unknown.
     */
    private function detectParserFromSignature(string $path): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false)
        {
            return '';
        }

        $header = (string) fread($handle, 64);
        fclose($handle);

        if (strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp')
        {
            return 'mp4';
        }

        if (str_starts_with($header, 'ID3')
            || (strlen($header) >= 2 && ord($header[0]) === 0xff && (ord($header[1]) & 0xe0) === 0xe0))
        {
            if (strlen($header) >= 2 && ord($header[0]) === 0xff && (ord($header[1]) & 0xf6) === 0xf0)
            {
                return 'aac';
            }

            return 'mp3';
        }

        if (strlen($header) >= 12 && str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WAVE')
        {
            return 'wav';
        }

        if (str_starts_with($header, 'fLaC'))
        {
            return 'flac';
        }

        if (str_starts_with($header, 'OggS'))
        {
            return 'ogg';
        }

        if (str_starts_with($header, "\x1A\x45\xDF\xA3"))
        {
            return 'webm';
        }

        return '';
    }

    /**
     * @brief Detect MIME type using PHP Fileinfo.
     *
     * @param string $path Local file path.
     *
     * @return string Detected MIME type.
     */
    private function detectMimeType(string $path): string
    {
        if (!function_exists('finfo_open'))
        {
            return '';
        }

        $handle = finfo_open(FILEINFO_MIME_TYPE);

        if ($handle === false)
        {
            return '';
        }

        $mime = finfo_file($handle, $path);
        finfo_close($handle);

        return is_string($mime) ? strtolower(trim($mime)) : '';
    }

    /**
     * @brief Inspect an ISO Base Media / QuickTime audio file.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectMp4(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false)
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OPEN_MP4')]];
        }

        $fileSize = (int) filesize($path);
        $moov = '';
        $foundFtyp = false;
        $offset = 0;

        while ($offset + 8 <= $fileSize)
        {
            if (fseek($handle, $offset) !== 0)
            {
                break;
            }

            $header = fread($handle, 16);

            if (!is_string($header) || strlen($header) < 8)
            {
                break;
            }

            $size = $this->uint32be(substr($header, 0, 4));
            $type = substr($header, 4, 4);
            $headerSize = 8;

            if ($size === 1 && strlen($header) >= 16)
            {
                $size = $this->uint64be(substr($header, 8, 8));
                $headerSize = 16;
            }
            elseif ($size === 0)
            {
                $size = $fileSize - $offset;
            }

            if ($size < $headerSize || $offset + $size > $fileSize)
            {
                break;
            }

            if ($type === 'ftyp')
            {
                $foundFtyp = true;
            }

            if ($type === 'moov')
            {
                if ($size > 67108864)
                {
                    fclose($handle);
                    return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_MP4_ATOM_SIZE')]];
                }

                if (fseek($handle, $offset + $headerSize) !== 0)
                {
                    break;
                }

                $moov = (string) fread($handle, $size - $headerSize);
                break;
            }

            $offset += $size;
        }

        fclose($handle);

        if (!$foundFtyp || $moov === '')
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_MP4_HEADER')]];
        }

        $durationMs = 0;
        $recordedAt = null;
        $mvhd = $this->findMp4Atom($moov, 'mvhd');

        if ($mvhd !== null && strlen($mvhd) >= 20)
        {
            $version = ord($mvhd[0]);

            if ($version === 1 && strlen($mvhd) >= 32)
            {
                $creation = $this->uint64be(substr($mvhd, 4, 8));
                $timescale = $this->uint32be(substr($mvhd, 20, 4));
                $duration = $this->uint64be(substr($mvhd, 24, 8));
            }
            else
            {
                $creation = $this->uint32be(substr($mvhd, 4, 4));
                $timescale = $this->uint32be(substr($mvhd, 12, 4));
                $duration = $this->uint32be(substr($mvhd, 16, 4));
            }

            if ($timescale > 0)
            {
                $durationMs = (int) round(($duration / $timescale) * 1000);
            }

            $recordedAt = $this->mp4TimestampToSql($creation);
        }

        $codec = '';
        $sampleRate = 0;
        $channels = 0;

        foreach (self::MP4_AUDIO_CODECS as $code => $name)
        {
            $payload = $this->findMp4Atom($moov, $code);

            if ($payload === null)
            {
                continue;
            }

            $codec = $name;

            if (strlen($payload) >= 28)
            {
                $channels = $this->uint16be(substr($payload, 16, 2));
                $sampleRate = $this->uint32be(substr($payload, 24, 4)) >> 16;
            }

            break;
        }

        $title = $this->extractMp4TextItem($moov, "\xA9nam");
        $date = $this->extractMp4TextItem($moov, "\xA9day");
        $metadataDate = $this->normaliseDate($date);

        if ($metadataDate !== null)
        {
            $recordedAt = $metadataDate;
        }

        $bitrate = $durationMs > 0
            ? (int) round(((int) filesize($path) * 8) / ($durationMs / 1000))
            : 0;

        return [
            'container_format' => 'MP4/M4A',
            'audio_codec' => $codec,
            'duration_ms' => $durationMs,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bitrate' => $bitrate,
            'embedded_title' => $title,
            'recorded_at' => $recordedAt,
            'errors' => $codec !== '' ? [] : [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_MP4_NO_AUDIO')],
        ];
    }

    /**
     * @brief Find the payload of the first MP4 atom with a given type.
     *
     * @param string $data Binary atom data.
     * @param string $wantedType Four-byte atom type.
     *
     * @return string|null Atom payload.
     */
    private function findMp4Atom(string $data, string $wantedType): ?string
    {
        $containers = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'stsd', 'udta', 'meta', 'ilst'];
        $length = strlen($data);
        $offset = 0;

        while ($offset + 8 <= $length)
        {
            $size = $this->uint32be(substr($data, $offset, 4));
            $type = substr($data, $offset + 4, 4);
            $headerSize = 8;

            if ($size === 1 && $offset + 16 <= $length)
            {
                $size = $this->uint64be(substr($data, $offset + 8, 8));
                $headerSize = 16;
            }
            elseif ($size === 0)
            {
                $size = $length - $offset;
            }

            if ($size < $headerSize || $offset + $size > $length)
            {
                $offset++;
                continue;
            }

            $payload = substr($data, $offset + $headerSize, $size - $headerSize);

            if ($type === $wantedType)
            {
                return $payload;
            }

            if (in_array($type, $containers, true))
            {
                if ($type === 'meta' && strlen($payload) >= 4)
                {
                    $payload = substr($payload, 4);
                }
                elseif ($type === 'stsd' && strlen($payload) >= 8)
                {
                    $payload = substr($payload, 8);
                }

                $found = $this->findMp4Atom($payload, $wantedType);

                if ($found !== null)
                {
                    return $found;
                }
            }

            $offset += $size;
        }

        return null;
    }

    /**
     * @brief Extract a text item from an MP4 ilst atom.
     *
     * @param string $data MP4 metadata bytes.
     * @param string $itemType Four-byte metadata item type.
     *
     * @return string UTF-8 text value.
     */
    private function extractMp4TextItem(string $data, string $itemType): string
    {
        $item = $this->findMp4Atom($data, $itemType);

        if ($item === null)
        {
            return '';
        }

        $value = $this->findMp4Atom($item, 'data');

        if ($value === null)
        {
            return '';
        }

        if (strlen($value) >= 8)
        {
            $value = substr($value, 8);
        }

        return trim($this->cleanText($value));
    }

    /**
     * @brief Inspect an MPEG Layer III file.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectMp3(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false)
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OPEN_MP3')]];
        }

        $fileSize = (int) filesize($path);
        $start = 0;
        $title = '';
        $date = '';
        $header = fread($handle, 10);

        if (is_string($header) && strlen($header) === 10 && substr($header, 0, 3) === 'ID3')
        {
            $tagSize = $this->synchsafe(substr($header, 6, 4));
            $tagData = (string) fread($handle, min($tagSize, 16777216));
            $start = 10 + $tagSize;
            [$title, $date] = $this->parseId3v2($tagData, ord($header[3]));
        }

        $scanLength = min(max(0, $fileSize - $start), 1048576);
        fseek($handle, $start);
        $scan = (string) fread($handle, $scanLength);
        $frameOffset = $this->findMp3Frame($scan);

        if ($frameOffset < 0)
        {
            fclose($handle);
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_MP3_FRAME')]];
        }

        $frameHeader = substr($scan, $frameOffset, 4);
        $frame = $this->parseMp3Header($frameHeader);

        if ($frame === null)
        {
            fclose($handle);
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_MP3_HEADER')]];
        }

        $absoluteFrameOffset = $start + $frameOffset;
        fseek($handle, $absoluteFrameOffset);
        $firstFrame = (string) fread($handle, min($frame['frame_length'], 4096));
        fclose($handle);

        $durationSeconds = 0.0;
        $xingFrames = $this->parseXingFrameCount($firstFrame, $frame);

        if ($xingFrames > 0)
        {
            $durationSeconds = ($xingFrames * $frame['samples_per_frame']) / $frame['sample_rate'];
        }
        elseif ($frame['bitrate'] > 0)
        {
            $audioBytes = max(0, $fileSize - $absoluteFrameOffset);
            $durationSeconds = ($audioBytes * 8) / $frame['bitrate'];
        }

        return [
            'container_format' => 'MPEG Audio',
            'audio_codec' => 'MP3',
            'duration_ms' => (int) round($durationSeconds * 1000),
            'sample_rate' => $frame['sample_rate'],
            'channels' => $frame['channels'],
            'bitrate' => $frame['bitrate'],
            'embedded_title' => $title,
            'recorded_at' => $this->normaliseDate($date),
        ];
    }

    /**
     * @brief Parse selected ID3v2 text frames.
     *
     * @param string $data Tag data excluding its ten-byte header.
     * @param int $version ID3 major version.
     *
     * @return array{0:string,1:string} Title and date.
     */
    private function parseId3v2(string $data, int $version): array
    {
        $offset = 0;
        $title = '';
        $date = '';
        $length = strlen($data);

        while ($offset + 10 <= $length)
        {
            $id = substr($data, $offset, 4);

            if ($id === "\0\0\0\0" || preg_match('/^[A-Z0-9]{4}$/', $id) !== 1)
            {
                break;
            }

            $sizeBytes = substr($data, $offset + 4, 4);
            $size = $version >= 4 ? $this->synchsafe($sizeBytes) : $this->uint32be($sizeBytes);

            if ($size <= 0 || $offset + 10 + $size > $length)
            {
                break;
            }

            $payload = substr($data, $offset + 10, $size);

            if ($id === 'TIT2')
            {
                $title = $this->decodeId3Text($payload);
            }
            elseif (in_array($id, ['TDRC', 'TYER', 'TDAT'], true) && $date === '')
            {
                $date = $this->decodeId3Text($payload);
            }

            $offset += 10 + $size;
        }

        return [$title, $date];
    }

    /**
     * @brief Decode an ID3 text frame.
     *
     * @param string $payload Frame payload.
     *
     * @return string Decoded UTF-8 text.
     */
    private function decodeId3Text(string $payload): string
    {
        if ($payload === '')
        {
            return '';
        }

        $encoding = ord($payload[0]);
        $value = substr($payload, 1);

        if ($encoding === 1 || $encoding === 2)
        {
            $source = $encoding === 2 ? 'UTF-16BE' : 'UTF-16';
            $converted = function_exists('mb_convert_encoding')
                ? @mb_convert_encoding($value, 'UTF-8', $source)
                : @iconv($source, 'UTF-8//IGNORE', $value);
            $value = is_string($converted) ? $converted : $value;
        }
        elseif ($encoding === 0)
        {
            $converted = function_exists('mb_convert_encoding')
                ? @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1')
                : @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
            $value = is_string($converted) ? $converted : $value;
        }

        return trim($this->cleanText($value));
    }

    /**
     * @brief Find the first plausible MPEG audio frame.
     *
     * @param string $data Scan buffer.
     *
     * @return int Byte offset, or -1.
     */
    private function findMp3Frame(string $data): int
    {
        $length = strlen($data);

        for ($offset = 0; $offset + 4 <= $length; $offset++)
        {
            if (ord($data[$offset]) !== 0xff || (ord($data[$offset + 1]) & 0xe0) !== 0xe0)
            {
                continue;
            }

            if ($this->parseMp3Header(substr($data, $offset, 4)) !== null)
            {
                return $offset;
            }
        }

        return -1;
    }

    /**
     * @brief Decode one MPEG audio frame header.
     *
     * @param string $header Four-byte header.
     *
     * @return array<string, int>|null Parsed header.
     */
    private function parseMp3Header(string $header): ?array
    {
        if (strlen($header) < 4)
        {
            return null;
        }

        $value = $this->uint32be($header);

        if (($value & 0xffe00000) !== 0xffe00000)
        {
            return null;
        }

        $versionBits = ($value >> 19) & 0x3;
        $layerBits = ($value >> 17) & 0x3;
        $bitrateIndex = ($value >> 12) & 0xf;
        $sampleIndex = ($value >> 10) & 0x3;
        $padding = ($value >> 9) & 0x1;
        $channelMode = ($value >> 6) & 0x3;

        if ($versionBits === 1 || $layerBits !== 1 || $bitrateIndex === 0 || $bitrateIndex === 15 || $sampleIndex === 3)
        {
            return null;
        }

        $mpeg1 = $versionBits === 3;
        $sampleRates = [44100, 48000, 32000];
        $sampleRate = $sampleRates[$sampleIndex];

        if ($versionBits === 2)
        {
            $sampleRate = intdiv($sampleRate, 2);
        }
        elseif ($versionBits === 0)
        {
            $sampleRate = intdiv($sampleRate, 4);
        }

        $bitrates = $mpeg1
            ? [0, 32000, 40000, 48000, 56000, 64000, 80000, 96000, 112000, 128000, 160000, 192000, 224000, 256000, 320000]
            : [0, 8000, 16000, 24000, 32000, 40000, 48000, 56000, 64000, 80000, 96000, 112000, 128000, 144000, 160000];
        $bitrate = $bitrates[$bitrateIndex];
        $coefficient = $mpeg1 ? 144 : 72;
        $frameLength = (int) floor(($coefficient * $bitrate) / $sampleRate) + $padding;

        return [
            'version' => $mpeg1 ? 1 : 2,
            'bitrate' => $bitrate,
            'sample_rate' => $sampleRate,
            'channels' => $channelMode === 3 ? 1 : 2,
            'samples_per_frame' => $mpeg1 ? 1152 : 576,
            'frame_length' => $frameLength,
            'has_crc' => (($value >> 16) & 1) === 0 ? 1 : 0,
        ];
    }

    /**
     * @brief Extract a Xing/Info VBR frame count.
     *
     * @param string $frame First MPEG frame bytes.
     * @param array<string, int> $header Parsed MPEG header.
     *
     * @return int Frame count or zero.
     */
    private function parseXingFrameCount(string $frame, array $header): int
    {
        $sideInfo = $header['version'] === 1
            ? ($header['channels'] === 1 ? 17 : 32)
            : ($header['channels'] === 1 ? 9 : 17);
        $offset = 4 + ($header['has_crc'] ? 2 : 0) + $sideInfo;
        $marker = substr($frame, $offset, 4);

        if ($marker !== 'Xing' && $marker !== 'Info')
        {
            return 0;
        }

        if (strlen($frame) < $offset + 12)
        {
            return 0;
        }

        $flags = $this->uint32be(substr($frame, $offset + 4, 4));

        return ($flags & 1) !== 0
            ? $this->uint32be(substr($frame, $offset + 8, 4))
            : 0;
    }

    /**
     * @brief Inspect a RIFF/WAVE file.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectWav(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false)
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OPEN_WAV')]];
        }

        $header = fread($handle, 12);

        if (!is_string($header) || strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE')
        {
            fclose($handle);
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_WAV_HEADER')]];
        }

        $formatCode = 0;
        $channels = 0;
        $sampleRate = 0;
        $byteRate = 0;
        $dataSize = 0;
        $title = '';
        $date = '';

        while (!feof($handle))
        {
            $chunkHeader = fread($handle, 8);

            if (!is_string($chunkHeader) || strlen($chunkHeader) < 8)
            {
                break;
            }

            $id = substr($chunkHeader, 0, 4);
            $size = $this->uint32le(substr($chunkHeader, 4, 4));

            if ($size < 0)
            {
                break;
            }

            if ($id === 'fmt ')
            {
                $data = (string) fread($handle, min($size, 64));

                if (strlen($data) >= 16)
                {
                    $formatCode = $this->uint16le(substr($data, 0, 2));
                    $channels = $this->uint16le(substr($data, 2, 2));
                    $sampleRate = $this->uint32le(substr($data, 4, 4));
                    $byteRate = $this->uint32le(substr($data, 8, 4));
                }

                if ($size > strlen($data))
                {
                    fseek($handle, $size - strlen($data), SEEK_CUR);
                }
            }
            elseif ($id === 'data')
            {
                $dataSize = $size;
                fseek($handle, $size, SEEK_CUR);
            }
            elseif ($id === 'LIST' && $size <= 1048576)
            {
                $list = (string) fread($handle, $size);
                [$title, $date] = $this->parseRiffInfo($list);
            }
            else
            {
                fseek($handle, $size, SEEK_CUR);
            }

            if (($size & 1) === 1)
            {
                fseek($handle, 1, SEEK_CUR);
            }
        }

        fclose($handle);

        $codec = match ($formatCode)
        {
            1 => 'PCM',
            3 => 'IEEE Float PCM',
            6 => 'A-law PCM',
            7 => 'mu-law PCM',
            80, 85 => 'MPEG Audio',
            default => $formatCode > 0 ? 'WAVE format ' . $formatCode : '',
        };
        $durationMs = $byteRate > 0 ? (int) round(($dataSize / $byteRate) * 1000) : 0;

        return [
            'container_format' => 'WAV',
            'audio_codec' => $codec,
            'duration_ms' => $durationMs,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bitrate' => $byteRate * 8,
            'embedded_title' => $title,
            'recorded_at' => $this->normaliseDate($date),
        ];
    }

    /**
     * @brief Parse a RIFF INFO list.
     *
     * @param string $data LIST chunk bytes.
     *
     * @return array{0:string,1:string} Title and date.
     */
    private function parseRiffInfo(string $data): array
    {
        if (substr($data, 0, 4) === 'INFO')
        {
            $data = substr($data, 4);
        }

        $offset = 0;
        $title = '';
        $date = '';

        while ($offset + 8 <= strlen($data))
        {
            $id = substr($data, $offset, 4);
            $size = $this->uint32le(substr($data, $offset + 4, 4));
            $value = substr($data, $offset + 8, $size);
            $value = trim($this->cleanText($value));

            if ($id === 'INAM')
            {
                $title = $value;
            }
            elseif ($id === 'ICRD')
            {
                $date = $value;
            }

            $offset += 8 + $size + ($size & 1);
        }

        return [$title, $date];
    }

    /**
     * @brief Inspect a FLAC file.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectFlac(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false || fread($handle, 4) !== 'fLaC')
        {
            if (is_resource($handle))
            {
                fclose($handle);
            }

            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_FLAC_MARKER')]];
        }

        $sampleRate = 0;
        $channels = 0;
        $totalSamples = 0;
        $title = '';
        $date = '';
        $last = false;

        while (!$last && !feof($handle))
        {
            $header = fread($handle, 4);

            if (!is_string($header) || strlen($header) < 4)
            {
                break;
            }

            $last = (ord($header[0]) & 0x80) !== 0;
            $type = ord($header[0]) & 0x7f;
            $length = $this->uint24be(substr($header, 1, 3));

            if ($length > 16777216)
            {
                fclose($handle);
                return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_FLAC_BLOCK_SIZE')]];
            }

            $data = (string) fread($handle, $length);

            if ($type === 0 && strlen($data) >= 18)
            {
                $packed = substr($data, 10, 8);
                $value = $this->uint64be($packed);
                $sampleRate = (int) (($value >> 44) & 0xfffff);
                $channels = (int) ((($value >> 41) & 0x7) + 1);
                $totalSamples = (int) ($value & 0xfffffffff);
            }
            elseif ($type === 4)
            {
                [$title, $date] = $this->parseVorbisComments($data);
            }
        }

        fclose($handle);
        $durationMs = $sampleRate > 0 ? (int) round(($totalSamples / $sampleRate) * 1000) : 0;
        $bitrate = $durationMs > 0
            ? (int) round(((int) filesize($path) * 8) / ($durationMs / 1000))
            : 0;

        return [
            'container_format' => 'FLAC',
            'audio_codec' => 'FLAC',
            'duration_ms' => $durationMs,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bitrate' => $bitrate,
            'embedded_title' => $title,
            'recorded_at' => $this->normaliseDate($date),
        ];
    }

    /**
     * @brief Inspect an Ogg Vorbis or Ogg Opus file.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectOgg(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false)
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OPEN_OGG')]];
        }

        $head = (string) fread($handle, 262144);

        if (substr($head, 0, 4) !== 'OggS')
        {
            fclose($handle);
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OGG_HEADER')]];
        }

        $codec = '';
        $sampleRate = 0;
        $channels = 0;
        $preSkip = 0;
        $commentMarker = '';

        $opus = strpos($head, 'OpusHead');
        $vorbis = strpos($head, "\x01vorbis");

        if ($opus !== false && strlen($head) >= $opus + 19)
        {
            $codec = 'Opus';
            $channels = ord($head[$opus + 9]);
            $preSkip = $this->uint16le(substr($head, $opus + 10, 2));
            $sampleRate = 48000;
            $commentMarker = 'OpusTags';
        }
        elseif ($vorbis !== false && strlen($head) >= $vorbis + 16)
        {
            $codec = 'Vorbis';
            $channels = ord($head[$vorbis + 11]);
            $sampleRate = $this->uint32le(substr($head, $vorbis + 12, 4));
            $commentMarker = "\x03vorbis";
        }
        else
        {
            fclose($handle);
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OGG_CODEC')]];
        }

        $title = '';
        $date = '';
        $commentPosition = strpos($head, $commentMarker);

        if ($commentPosition !== false)
        {
            $commentData = substr($head, $commentPosition + strlen($commentMarker));
            [$title, $date] = $this->parseVorbisComments($commentData);
        }

        $fileSize = (int) filesize($path);
        $tailSize = min($fileSize, 262144);
        fseek($handle, -$tailSize, SEEK_END);
        $tail = (string) fread($handle, $tailSize);
        fclose($handle);
        $lastPage = strrpos($tail, 'OggS');
        $granule = 0;

        if ($lastPage !== false && strlen($tail) >= $lastPage + 14)
        {
            $granule = $this->uint64le(substr($tail, $lastPage + 6, 8));
        }

        $samples = max(0, $granule - $preSkip);
        $durationMs = $sampleRate > 0 ? (int) round(($samples / $sampleRate) * 1000) : 0;
        $bitrate = $durationMs > 0
            ? (int) round(($fileSize * 8) / ($durationMs / 1000))
            : 0;

        return [
            'container_format' => 'Ogg',
            'audio_codec' => $codec,
            'duration_ms' => $durationMs,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bitrate' => $bitrate,
            'embedded_title' => $title,
            'recorded_at' => $this->normaliseDate($date),
        ];
    }

    /**
     * @brief Parse a Vorbis-comment structure.
     *
     * @param string $data Comment block without codec marker.
     *
     * @return array{0:string,1:string} Title and date.
     */
    private function parseVorbisComments(string $data): array
    {
        if (strlen($data) < 8)
        {
            return ['', ''];
        }

        $offset = 0;
        $vendorLength = $this->uint32le(substr($data, $offset, 4));
        $offset += 4 + $vendorLength;

        if ($offset + 4 > strlen($data))
        {
            return ['', ''];
        }

        $count = min(10000, $this->uint32le(substr($data, $offset, 4)));
        $offset += 4;
        $title = '';
        $date = '';

        for ($index = 0; $index < $count && $offset + 4 <= strlen($data); $index++)
        {
            $length = $this->uint32le(substr($data, $offset, 4));
            $offset += 4;

            if ($length < 0 || $offset + $length > strlen($data))
            {
                break;
            }

            $comment = substr($data, $offset, $length);
            $offset += $length;
            $separator = strpos($comment, '=');

            if ($separator === false)
            {
                continue;
            }

            $key = strtoupper(substr($comment, 0, $separator));
            $value = trim($this->cleanText(substr($comment, $separator + 1)));

            if ($key === 'TITLE' && $title === '')
            {
                $title = $value;
            }
            elseif (in_array($key, ['DATE', 'YEAR', 'RECORDINGDATE'], true) && $date === '')
            {
                $date = $value;
            }
        }

        return [$title, $date];
    }

    /**
     * @brief Inspect raw AAC with ADTS framing.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectAac(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false)
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OPEN_AAC')]];
        }

        $frames = 0;
        $sampleRate = 0;
        $channels = 0;
        $bytes = 0;

        while (!feof($handle))
        {
            $header = fread($handle, 7);

            if (!is_string($header) || strlen($header) < 7)
            {
                break;
            }

            if (ord($header[0]) !== 0xff || (ord($header[1]) & 0xf6) !== 0xf0)
            {
                fclose($handle);
                return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_AAC_ADTS')]];
            }

            $rateIndex = (ord($header[2]) >> 2) & 0x0f;

            if (!isset(self::AAC_SAMPLE_RATES[$rateIndex]))
            {
                fclose($handle);
                return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_AAC_RATE')]];
            }

            $sampleRate = self::AAC_SAMPLE_RATES[$rateIndex];
            $channels = ((ord($header[2]) & 1) << 2) | ((ord($header[3]) >> 6) & 3);
            $frameLength = ((ord($header[3]) & 3) << 11) | (ord($header[4]) << 3) | ((ord($header[5]) >> 5) & 7);

            if ($frameLength < 7)
            {
                fclose($handle);
                return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_AAC_FRAME')]];
            }

            $frames++;
            $bytes += $frameLength;
            fseek($handle, $frameLength - 7, SEEK_CUR);
        }

        fclose($handle);
        $durationSeconds = $sampleRate > 0 ? ($frames * 1024) / $sampleRate : 0.0;

        return [
            'container_format' => 'ADTS',
            'audio_codec' => 'AAC',
            'duration_ms' => (int) round($durationSeconds * 1000),
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bitrate' => $durationSeconds > 0 ? (int) round(($bytes * 8) / $durationSeconds) : 0,
            'embedded_title' => '',
            'recorded_at' => null,
        ];
    }

    /**
     * @brief Inspect essential WebM/Matroska audio metadata.
     *
     * @param string $path File path.
     *
     * @return array<string, mixed> Parsed data.
     */
    private function inspectWebm(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false)
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_OPEN_WEBM')]];
        }

        $data = (string) fread($handle, 16777216);
        fclose($handle);

        if (!str_starts_with($data, "\x1A\x45\xDF\xA3"))
        {
            return ['errors' => [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_WEBM_HEADER')]];
        }

        $timecodeScale = $this->readEbmlUnsignedAfterId($data, "\x2A\xD7\xB1") ?? 1000000;
        $duration = $this->readEbmlFloatAfterId($data, "\x44\x89") ?? 0.0;
        $codecId = '';

        foreach (['A_OPUS', 'A_VORBIS', 'A_FLAC', 'A_AAC'] as $candidate)
        {
            if (str_contains($data, $candidate))
            {
                $codecId = $candidate;
                break;
            }
        }

        $sampleRate = (int) round($this->readEbmlFloatAfterId($data, "\xB5") ?? 0.0);
        $channels = (int) ($this->readEbmlUnsignedAfterId($data, "\x9F") ?? 0);

        if ($codecId === 'A_OPUS')
        {
            $sampleRate = 48000;
            $opusHead = strpos($data, 'OpusHead');

            if ($opusHead !== false && $opusHead + 10 <= strlen($data))
            {
                $channels = ord($data[$opusHead + 9]);
            }
        }

        $codec = match ($codecId)
        {
            'A_OPUS' => 'Opus',
            'A_VORBIS' => 'Vorbis',
            'A_FLAC' => 'FLAC',
            'A_AAC' => 'AAC',
            default => $codecId,
        };
        $durationMs = $duration > 0
            ? (int) round(($duration * $timecodeScale) / 1000000)
            : 0;
        $bitrate = $durationMs > 0
            ? (int) round(((int) filesize($path) * 8) / ($durationMs / 1000))
            : 0;

        return [
            'container_format' => 'WebM/Matroska',
            'audio_codec' => $codec,
            'duration_ms' => $durationMs,
            'sample_rate' => $sampleRate,
            'channels' => $channels,
            'bitrate' => $bitrate,
            'embedded_title' => '',
            'recorded_at' => null,
            'warnings' => $durationMs > 0 ? [] : [Text::_('COM_AUDIOARCHIVE_INSPECT_WARNING_WEBM_PARTIAL')],
            'errors' => $codec !== '' ? [] : [Text::_('COM_AUDIOARCHIVE_INSPECT_ERROR_WEBM_NO_AUDIO')],
        ];
    }

    /**
     * @brief Read an unsigned EBML value immediately after an element ID.
     *
     * @param string $data EBML bytes.
     * @param string $id Binary element ID.
     *
     * @return int|null Value.
     */
    private function readEbmlUnsignedAfterId(string $data, string $id): ?int
    {
        $payload = $this->readEbmlPayloadAfterId($data, $id);

        if ($payload === null || strlen($payload) > 8)
        {
            return null;
        }

        $value = 0;

        for ($index = 0; $index < strlen($payload); $index++)
        {
            $value = ($value << 8) | ord($payload[$index]);
        }

        return $value;
    }

    /**
     * @brief Read a floating-point EBML value immediately after an element ID.
     *
     * @param string $data EBML bytes.
     * @param string $id Binary element ID.
     *
     * @return float|null Value.
     */
    private function readEbmlFloatAfterId(string $data, string $id): ?float
    {
        $payload = $this->readEbmlPayloadAfterId($data, $id);

        if ($payload === null)
        {
            return null;
        }

        if (strlen($payload) === 4)
        {
            $value = unpack('Gvalue', $payload);
            return (float) ($value['value'] ?? 0.0);
        }

        if (strlen($payload) === 8)
        {
            $value = unpack('Evalue', $payload);
            return (float) ($value['value'] ?? 0.0);
        }

        return null;
    }

    /**
     * @brief Read a string EBML value immediately after an element ID.
     *
     * @param string $data EBML bytes.
     * @param string $id Binary element ID.
     *
     * @return string|null Value.
     */
    private function readEbmlStringAfterId(string $data, string $id): ?string
    {
        $payload = $this->readEbmlPayloadAfterId($data, $id);

        return $payload === null ? null : trim($this->cleanText($payload));
    }

    /**
     * @brief Find an EBML element and return its payload.
     *
     * @param string $data EBML bytes.
     * @param string $id Binary element ID.
     *
     * @return string|null Payload.
     */
    private function readEbmlPayloadAfterId(string $data, string $id): ?string
    {
        $position = strpos($data, $id);

        if ($position === false)
        {
            return null;
        }

        $offset = $position + strlen($id);
        [$size, $length] = $this->readEbmlVint($data, $offset);

        if ($length === 0 || $size < 0 || $offset + $length + $size > strlen($data))
        {
            return null;
        }

        return substr($data, $offset + $length, $size);
    }

    /**
     * @brief Decode an EBML variable-length integer.
     *
     * @param string $data EBML bytes.
     * @param int $offset Byte offset.
     *
     * @return array{0:int,1:int} Value and encoded length.
     */
    private function readEbmlVint(string $data, int $offset): array
    {
        if ($offset >= strlen($data))
        {
            return [-1, 0];
        }

        $first = ord($data[$offset]);
        $mask = 0x80;
        $length = 1;

        while ($length <= 8 && ($first & $mask) === 0)
        {
            $mask >>= 1;
            $length++;
        }

        if ($length > 8 || $offset + $length > strlen($data))
        {
            return [-1, 0];
        }

        $value = $first & ($mask - 1);

        for ($index = 1; $index < $length; $index++)
        {
            $value = ($value << 8) | ord($data[$offset + $index]);
        }

        return [$value, $length];
    }

    /**
     * @brief Convert an MP4 epoch timestamp to SQL UTC.
     *
     * @param int $value Seconds since 1904-01-01.
     *
     * @return string|null SQL datetime.
     */
    private function mp4TimestampToSql(int $value): ?string
    {
        if ($value <= 0)
        {
            return null;
        }

        $unix = $value - 2082844800;

        if ($unix <= 0 || $unix > 4102444800)
        {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $unix);
    }

    /**
     * @brief Convert common embedded date strings to SQL UTC.
     *
     * @param string $value Embedded date.
     *
     * @return string|null SQL datetime.
     */
    private function normaliseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '')
        {
            return null;
        }

        if (preg_match('/^(\d{4})(?:[-:.](\d{1,2}))?(?:[-:.](\d{1,2}))?/', $value, $matches) === 1)
        {
            $year = (int) $matches[1];
            $month = isset($matches[2]) ? max(1, min(12, (int) $matches[2])) : 1;
            $day = isset($matches[3]) ? max(1, min(31, (int) $matches[3])) : 1;

            if ($year >= 1900 && $year <= 2200 && checkdate($month, $day, $year))
            {
                return sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day);
            }
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @brief Remove nulls and unsafe control characters from metadata text.
     *
     * @param string $value Raw text.
     *
     * @return string Cleaned text.
     */
    private function cleanText(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return is_string($clean) ? $clean : $value;
    }

    /**
     * @brief Decode a synchsafe 28-bit integer.
     *
     * @param string $bytes Four bytes.
     *
     * @return int Value.
     */
    private function synchsafe(string $bytes): int
    {
        if (strlen($bytes) < 4)
        {
            return 0;
        }

        return ((ord($bytes[0]) & 0x7f) << 21)
            | ((ord($bytes[1]) & 0x7f) << 14)
            | ((ord($bytes[2]) & 0x7f) << 7)
            | (ord($bytes[3]) & 0x7f);
    }

    /** @return int */
    private function uint16be(string $bytes): int
    {
        return strlen($bytes) >= 2 ? (ord($bytes[0]) << 8) | ord($bytes[1]) : 0;
    }

    /** @return int */
    private function uint16le(string $bytes): int
    {
        return strlen($bytes) >= 2 ? ord($bytes[0]) | (ord($bytes[1]) << 8) : 0;
    }

    /** @return int */
    private function uint24be(string $bytes): int
    {
        return strlen($bytes) >= 3 ? (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]) : 0;
    }

    /** @return int */
    private function uint32be(string $bytes): int
    {
        if (strlen($bytes) < 4)
        {
            return 0;
        }

        return (ord($bytes[0]) << 24)
            | (ord($bytes[1]) << 16)
            | (ord($bytes[2]) << 8)
            | ord($bytes[3]);
    }

    /** @return int */
    private function uint32le(string $bytes): int
    {
        if (strlen($bytes) < 4)
        {
            return 0;
        }

        return ord($bytes[0])
            | (ord($bytes[1]) << 8)
            | (ord($bytes[2]) << 16)
            | (ord($bytes[3]) << 24);
    }

    /** @return int */
    private function uint64be(string $bytes): int
    {
        if (strlen($bytes) < 8)
        {
            return 0;
        }

        $value = 0;

        for ($index = 0; $index < 8; $index++)
        {
            $value = ($value << 8) | ord($bytes[$index]);
        }

        return $value;
    }

    /** @return int */
    private function uint64le(string $bytes): int
    {
        if (strlen($bytes) < 8)
        {
            return 0;
        }

        $value = 0;

        for ($index = 7; $index >= 0; $index--)
        {
            $value = ($value << 8) | ord($bytes[$index]);
        }

        return $value;
    }
}
