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
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    public function render(): string
    {
        ob_start();
        include __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/tmpl/clip/default_credits.php';
        return ob_get_clean();
    }
};
$view->item = (object) [];
check(trim($view->render()) === '', 'no empty public metadata panel');
$view->item = (object) ($normal + ['description' => '']);
$view->item->creator = '<script>alert(1)</script>';
$html = $view->render();
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'public metadata is escaped');
check(str_contains($html, "First line\nSecond line"), 'lyrics line breaks preserved');
$portable = json_decode(json_encode($normal, JSON_UNESCAPED_UNICODE), true);
check(ClipMetadataService::normalise($portable) === $normal, 'portable metadata survives JSON round trip');
echo ($checks - $start) . " metadata assertions passed.\n";
