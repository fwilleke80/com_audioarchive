<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

if ((int) $this->params->get('archive_show_active_filters', 1) !== 1 || !$this->hasActiveFilters())
{
	return;
}

$categoryNames = [];
foreach ($this->categoryOptions as $category)
{
	$categoryNames[(int) $category->id] = (string) $category->title;
}

$queryValues = $this->getQueryValues();
$tagNames = [];
$tagAliases = [];
$tagDescriptions = [];
foreach ($this->tagOptions as $tag)
{
	$tagNames[(int) $tag->id] = (string) $tag->title;
	$tagAliases[(int) $tag->id] = trim((string) ($tag->alias ?? ''));
	$tagDescriptions[(int) $tag->id] = trim((string) ($tag->description_text ?? ''));
}

$activeFilters = [];
$search = trim((string) $this->state->get('filter.search'));
if ($search !== '')
{
	$activeFilters[] = [
		'label' => Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_SEARCH', $search),
		'url' => $this->getRemoveFilterUrl('q'),
		'title' => '',
	];
}

$categoryId = (int) $this->state->get('filter.category');
if ($categoryId > 0 && isset($categoryNames[$categoryId]))
{
	$activeFilters[] = [
		'label' => Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_CATEGORY', $categoryNames[$categoryId]),
		'url' => $this->getRemoveFilterUrl('category'),
		'title' => '',
	];
}

foreach ((array) $this->state->get('filter.tags', []) as $tagId)
{
	$tagId = (int) $tagId;

	if (!isset($tagNames[$tagId]))
	{
		continue;
	}

	$activeFilters[] = [
		'label' => Text::sprintf('COM_AUDIOARCHIVE_ACTIVE_TAG', $tagNames[$tagId]),
		'url' => $this->getRemoveFilterUrl('tags', $tagAliases[$tagId] ?? ''),
		'title' => $tagDescriptions[$tagId] ?? '',
	];
}

$filterDefinitions = [
	'duration_min' => 'COM_AUDIOARCHIVE_ACTIVE_DURATION_MIN',
	'duration_max' => 'COM_AUDIOARCHIVE_ACTIVE_DURATION_MAX',
	'recorded_from' => 'COM_AUDIOARCHIVE_ACTIVE_RECORDED_FROM',
	'recorded_to' => 'COM_AUDIOARCHIVE_ACTIVE_RECORDED_TO',
	'uploaded_from' => 'COM_AUDIOARCHIVE_ACTIVE_UPLOADED_FROM',
	'uploaded_to' => 'COM_AUDIOARCHIVE_ACTIVE_UPLOADED_TO',
];

foreach ($filterDefinitions as $queryName => $languageKey)
{
	$value = trim((string) ($queryValues[$queryName] ?? ''));

	if ($value === '')
	{
		continue;
	}

	$activeFilters[] = [
		'label' => Text::sprintf($languageKey, $value),
		'url' => $this->getRemoveFilterUrl($queryName),
		'title' => '',
	];
}
?>
<section class="com-audioarchive-active-filters" aria-labelledby="audioarchive-active-filter-heading">
	<h2 id="audioarchive-active-filter-heading"><?php echo Text::_('COM_AUDIOARCHIVE_ACTIVE_FILTERS'); ?></h2>
	<ul>
		<?php foreach ($activeFilters as $activeFilter) : ?>
			<?php $removeLabel = Text::sprintf('COM_AUDIOARCHIVE_REMOVE_FILTER', $activeFilter['label']); ?>
			<li<?php if ($activeFilter['title'] !== '') : ?> title="<?php echo $this->escape($activeFilter['title']); ?>"<?php endif; ?>>
				<span><?php echo $this->escape($activeFilter['label']); ?></span>
				<a
					href="<?php echo $activeFilter['url']; ?>"
					class="com-audioarchive-active-filter-remove"
					aria-label="<?php echo $this->escape($removeLabel); ?>"
					title="<?php echo $this->escape($removeLabel); ?>"
				>
					<span aria-hidden="true">&times;</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<a href="<?php echo $this->getResetUrl(); ?>"><?php echo Text::_('COM_AUDIOARCHIVE_FILTER_RESET_ALL'); ?></a>
</section>
