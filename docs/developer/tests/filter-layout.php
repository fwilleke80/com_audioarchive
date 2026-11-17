<?php
/** @brief Render every filter visibility combination and verify field grouping and omission. */
namespace Joomla\CMS\Language
{
    class Text
    {
        /** @brief Keep fixture labels readable. */
        public static function _(string $key): string
        {
            return $key;
        }
    }
}
namespace
{
    define('_JEXEC', 1);
    /** @brief Supply option/state reads to the actual template. */
    class Values
    {
        /** @brief Store fixture values. */
        public function __construct(private array $values = [])
        {
        }
        /** @brief Read a fixture value. */
        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }
    }
    /** @brief Render the production archive filter form. */
    class View
    {
        public array $filterErrors = [];
        public array $categoryOptions = [];
        public array $ownerOptions = [];
        public array $tagOptions;
        public int $maximumDurationSeconds = 19;
        public Values $state;
        /** @brief Set available controls and an actual selectable tag. */
        public function __construct(public Values $params)
        {
            $this->state = new Values();
            $this->tagOptions = [(object) ['id' => 1, 'alias' => 'tag', 'title' => 'Tag']];
        }
        /** @brief No active fixture filters. */
        public function hasActiveFilters(): bool
        {
            return false;
        }
        /** @brief Return fixture form action. */
        public function getArchiveUrl(): string
        {
            return '/archive';
        }
        /** @brief Return fixture reset URL. */
        public function getResetUrl(): string
        {
            return '/archive';
        }
        /** @brief Escape all fixture values. */
        public function escape(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        /** @brief Capture the real template. */
        public function render(): string
        {
            ob_start();
            include __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/tmpl/archive/default_filters.php';
            return ob_get_clean();
        }
    }
    $keys = ['archive_show_owner_filter', 'archive_show_search', 'archive_show_category_filter', 'archive_show_duration_filter', 'archive_show_recorded_filter', 'archive_show_uploaded_filter', 'archive_show_tag_filter'];
    $names = ['owner', 'q', 'category', 'duration_min', 'recorded_from', 'uploaded_from', 'tags[]'];
    for ($mask = 0; $mask < 128; ++$mask)
    {
        $options = [];
        foreach ($keys as $index => $key) $options[$key] = ($mask >> $index) & 1;
        $html = (new View(new Values($options)))->render();
        if ($mask === 0)
        {
            if (str_contains($html, '<form')) throw new \RuntimeException('Empty panel rendered');
            continue;
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach ($names as $index => $name)
        {
            $count = $xpath->query('//*[@name="' . $name . '"]')->length;
            if ($count !== (($mask >> $index) & 1)) throw new \RuntimeException('Visibility mismatch: ' . $mask . ' ' . $name);
        }
        $dates = $xpath->query('//*[contains(@class,"filter-dates")]');
        if ($dates->length !== (($mask & 48) ? 1 : 0)) throw new \RuntimeException('Empty date row');
        if (($mask & 8) && ($mask & 48))
        {
            $duration = $xpath->query('//fieldset[contains(@class,"filter-duration")]')->item(0);
            $previous = $duration->previousSibling;
            while ($previous && $previous->nodeType !== XML_ELEMENT_NODE) $previous = $previous->previousSibling;
            if (!$previous || !str_contains($previous->getAttribute('class'), 'filter-dates')) throw new \RuntimeException('Duration must follow date row');
        }
        $primary = $xpath->query('//*[contains(concat(" ",@class," ")," com-audioarchive-filter-primary ")]');
        if ($primary->length !== (($mask & 63) ? 1 : 0)) throw new \RuntimeException('Empty primary column');
        if (($mask & 64) && $xpath->query('//fieldset[contains(@class,"filter-tags")]/parent::div[contains(@class,"filter-grid")]')->length !== 1)
        {
            throw new \RuntimeException('Tags must be independent of primary field rows');
        }
    }
    echo "All 128 filter visibility combinations passed.\n";
}
