<?php
/** @brief Validate descriptive metadata, contribution permissions, form coverage and safe rendering. */
require __DIR__ . '/multi-user-policy.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/ClipMetadataService.php';
use Punga\Component\Audioarchive\Administrator\Service\ClipMetadataService;
$start = $checks;
$sample = ['licence' => 'CC BY 4.0', 'creator' => 'Artist', 'contributors' => "A — guitar\r\nB — bass", 'recording_location' => 'Braunschweig', 'genre_style' => 'Jazz / Ambient', 'creation_year' => '2026', 'lyrics' => "First line\nSecond line"];
$normal = ClipMetadataService::normalise($sample);
check($normal['creation_year'] === 2026, 'year stored as integer');
check($normal['contributors'] === "A — guitar\nB — bass", 'multiline credits preserved');
check(ClipMetadataService::normalise([])['creation_year'] === null, 'old backups and new clips have blank year');
check(ClipMetadataService::normalise(['creation_year' => null])['creation_year'] === null, 'nullable year reloads');
check(ClipMetadataService::normalise(['lyrics' => '<b>Text</b>'])['lyrics'] === 'Text', 'metadata stores plain text');
foreach (['0', '-1', '10000', '2026.5', 'abc', ['2026']] as $year)
{
    rejectContribution(fn() => ClipMetadataService::normalise(['creation_year' => $year]), 'invalid year rejected');
}
rejectContribution(fn() => ClipMetadataService::normalise(['creator' => str_repeat('x', 256)]), 'oversized text rejected');
rejectContribution(fn() => ClipMetadataService::normalise(['lyrics' => str_repeat('ü', 30001)]), 'multibyte text respects database byte capacity');
rejectContribution(fn() => ClipMetadataService::normalise(['licence' => ['invalid']]), 'structured text rejected');
$clean = $contributions->sanitise(array_replace($input, $sample), new Joomla\Registry\Registry(['frontend_allowed_access' => [1, 2], 'allow_private_clips' => 1]));
foreach ($sample as $name => $value)
{
    check($clean[$name] === $value, 'frontend preserves ' . $name);
}
check($clean['created_by'] === 7, 'creative credits do not change ownership');
$root = __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/';
foreach (['administrator/forms/clip.xml', 'site/forms/edit.xml', 'site/forms/upload.xml'] as $form)
{
    $xml = simplexml_load_file($root . $form);
    foreach (array_keys($sample) as $name)
    {
        check(count($xml->xpath('//fieldset[@name="metadata"]/field[@name="' . $name . '"]')) === 1, $form . ' exposes ' . $name);
    }
}
/** @brief Render the actual public metadata layout with safe escaping. */
$view = new class
{
    public object $item;
    public object $params;
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    public function render(): string
    {
        ob_start();
        include __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/tmpl/clip/default_metadata.php';
        return ob_get_clean();
    }
};
$view->params = new class
{
    public array $hidden = [];
    public function get(string $name, mixed $default = null): mixed
    {
        $metadata = ['licence', 'creator', 'contributors', 'recording_location', 'genre_style', 'creation_year', 'lyrics'];
        return in_array(substr($name, 12), $metadata, true) && !in_array($name, $this->hidden, true) ? $default : 0;
    }
};
$view->item = (object) ['duration_ms' => 0, 'file_size' => 0];
check(trim($view->render()) === '', 'no empty public metadata panel');
$view->item = (object) ($normal + ['description' => '', 'duration_ms' => 0, 'file_size' => 0]);
$view->item->creator = '<script>alert(1)</script>';
$html = $view->render();
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'public metadata is escaped');
check(str_contains($html, "First line\nSecond line"), 'lyrics line breaks preserved');
foreach (array_keys($sample) as $name)
{
    $view->params->hidden = ['detail_show_' . $name];
    $filtered = $view->render();
    check(!str_contains($filtered, 'COM_AUDIOARCHIVE_METADATA_' . strtoupper($name)), 'hidden metadata row omitted: ' . $name);
}
$view->params->hidden = array_map(fn(string $name): string => 'detail_show_' . $name, array_keys($sample));
check(trim($view->render()) === '', 'all hidden rows suppress Details card');
$portable = json_decode(json_encode($normal, JSON_UNESCAPED_UNICODE), true);
check(ClipMetadataService::normalise($portable) === $normal, 'portable metadata survives JSON round trip');
check(ClipMetadataService::batchChanges([]) === [], 'empty batch changes no metadata');
check(ClipMetadataService::batchChanges(['metadata' => ['creator' => 'Ignored']]) === [], 'values without explicit action ignored');
$batch = ClipMetadataService::batchChanges(['metadata_mode' => ['creator' => 'set', 'lyrics' => 'clear', 'creation_year' => 'clear'], 'metadata' => ['creator' => 'New artist', 'licence' => 'Ignored']]);
check($batch === ['creator' => 'New artist', 'creation_year' => null, 'lyrics' => ''], 'batch set and clear affect only selected fields');
rejectContribution(fn() => ClipMetadataService::batchChanges(['metadata_mode' => ['creator' => 'invalid']]), 'unknown batch action rejected');
rejectContribution(fn() => ClipMetadataService::batchChanges(['metadata_mode' => ['creation_year' => 'set'], 'metadata' => ['creation_year' => '2026.5']]), 'invalid batch year rejected');
check(ClipMetadataService::batchChanges(['metadata_mode' => ['creation_year' => 'keep'], 'metadata' => ['creation_year' => 'invalid']]) === [], 'unchanged field ignores invalid unused value');
check(str_contains($html, 'audioarchive-details-heading') && !str_contains($html, 'audioarchive-credits-heading'), 'credits use existing Details card');
echo ($checks - $start) . " metadata assertions passed.\n";
