<?php
namespace Punga\Component\Audioarchive\Site\Service;

\defined('_JEXEC') or die;

/** @brief Produce a bounded public performance without trusting stored clip titles or arbitrary fields. */
final class RecordingShareService
{
    /** @param callable $resolve Returns public current clip metadata or null for a stored reference. */
    public static function sanitise(array $recording, callable $resolve): array
    {
        $missing = 0;
        $board = static function (mixed $items) use ($resolve, &$missing): array
        {
            $result = [];
            foreach (array_slice(is_array($items) ? array_values($items) : [], 0, 36) as $entry)
            {
                $clip = is_array($entry) ? $resolve($entry) : null;
                if ($entry !== null && !$clip) $missing++;
                $result[] = $clip ? ['id' => (int) $clip->id, 'uuid' => (string) $clip->uuid, 'title' => (string) $clip->title] : null;
            }
            return $result;
        };
        $base = $board($recording['board'] ?? []);
        $layers = [];
        foreach (is_array($recording['layerBoards'] ?? null) ? $recording['layerBoards'] : [] as $layer => $items)
        {
            if (ctype_digit((string) $layer) && (int) $layer < 64) $layers[(int) $layer] = $board($items);
        }
        $duration = max(0, min(7200000, (int) ($recording['durationMs'] ?? 0)));
        $events = [];
        foreach (array_slice(is_array($recording['events'] ?? null) ? $recording['events'] : [], 0, 20000) as $event)
        {
            if (!is_array($event) || !is_numeric($event['t'] ?? null)) continue;
            $layer = max(0, min(63, (int) ($event['layer'] ?? 0)));
            $type = $event['type'] ?? '';
            $clean = ['t' => max(0, min($duration, (int) $event['t'])), 'layer' => $layer];
            if ($type === 'pad' || $type === 'note')
            {
                $pad = (int) ($event['pad'] ?? -1);
                if ($pad < 0 || $pad >= 36 || empty(($layers[$layer] ?? $base)[$pad])) continue;
                $clean += ['type' => $type, 'pad' => $pad];
                if ($type === 'note')
                {
                    $clean += ['note' => max(0, min(127, (int) ($event['note'] ?? 60))), 'velocity' => max(1, min(127, (int) ($event['velocity'] ?? 127))), 'duration' => max(40, min(7200000, (int) ($event['duration'] ?? 250)))];
                }
            }
            elseif ($type === 'polyphony')
            {
                $clean += ['type' => $type, 'enabled' => (bool) ($event['enabled'] ?? true)];
            }
            else continue;
            $events[] = $clean;
        }
        usort($events, static fn(array $a, array $b): int => $a['t'] <=> $b['t']);
        $initial = is_array($recording['initialState'] ?? null) ? $recording['initialState'] : [];
        return ['kind' => 'recording', 'unavailable' => $missing, 'recording' => [
            'id' => 'shared-recording', 'format' => 'punga-audioarchive-soundboard-recording', 'version' => 1,
            'name' => mb_substr(is_string($recording['name'] ?? null) ? $recording['name'] : '', 0, 120),
            'durationMs' => $duration, 'board' => $base, 'layerBoards' => (object) $layers,
            'initialState' => ['mode' => ($initial['mode'] ?? '') === 'chromatic' ? 'chromatic' : 'pad', 'polyphony' => (bool) ($initial['polyphony'] ?? true), 'octave' => 4, 'pad' => -1], 'events' => $events,
        ]];
    }
}
