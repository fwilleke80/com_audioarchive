<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$showPagination = (int) $this->params->get('archive_show_pagination', 1) === 1;
$showPageSize = (int) $this->params->get('archive_show_page_size', 1) === 1;

if (!$showPagination && !$showPageSize)
{
	return;
}

$queryValues = $this->getQueryValues();
unset($queryValues['limit']);
$currentPage = max(1, (int) $this->pagination->pagesCurrent);
$totalPages = max(1, (int) $this->pagination->pagesTotal);
?>
<div class="com-audioarchive-pagination-bar">
	<?php if ($showPageSize) : ?>
		<form class="com-audioarchive-page-size" method="get" action="<?php echo $this->getArchiveUrl(); ?>">
			<input type="hidden" name="task" value="archive.applyFilters">
			<?php foreach ($queryValues as $key => $value) : ?>
				<?php if (is_array($value)) : ?>
					<?php foreach ($value as $entry) : ?><input type="hidden" name="<?php echo $this->escape($key); ?>[]" value="<?php echo $this->escape((string) $entry); ?>"><?php endforeach; ?>
				<?php else : ?>
					<input type="hidden" name="<?php echo $this->escape($key); ?>" value="<?php echo $this->escape((string) $value); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<label for="audioarchive-page-size"><?php echo Text::_('COM_AUDIOARCHIVE_PAGE_SIZE'); ?></label>
			<select id="audioarchive-page-size" class="form-select" name="limit">
				<?php foreach ($this->pageSizeOptions as $pageSize) : ?>
					<option value="<?php echo (int) $pageSize; ?>"<?php echo (int) $this->state->get('list.limit') === (int) $pageSize ? ' selected' : ''; ?>><?php echo (int) $pageSize; ?></option>
				<?php endforeach; ?>
			</select>
			<button class="btn btn-secondary" type="submit"><?php echo Text::_('COM_AUDIOARCHIVE_PAGE_SIZE_APPLY'); ?></button>
		</form>
	<?php endif; ?>

	<?php if ($showPagination && $totalPages > 1) : ?>
		<nav class="com-audioarchive-pagination" aria-label="<?php echo Text::_('COM_AUDIOARCHIVE_PAGINATION_LABEL'); ?>">
			<ul class="pagination">
				<li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
					<?php if ($currentPage === 1) : ?>
						<span class="page-link" aria-hidden="true">&laquo;</span>
					<?php else : ?>
						<a class="page-link" href="<?php echo $this->getPaginationPageUrl(1); ?>" aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_START')); ?>">
							<span aria-hidden="true">&laquo;</span>
						</a>
					<?php endif; ?>
				</li>
				<li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
					<?php if ($currentPage === 1) : ?>
						<span class="page-link" aria-hidden="true">&lsaquo;</span>
					<?php else : ?>
						<a class="page-link" href="<?php echo $this->getPaginationPageUrl($currentPage - 1); ?>" rel="prev" aria-label="<?php echo $this->escape(Text::_('JPREV')); ?>">
							<span aria-hidden="true">&lsaquo;</span>
						</a>
					<?php endif; ?>
				</li>

				<?php foreach ($this->getCompactPaginationPages() as $page) : ?>
					<?php if ($page === null) : ?>
						<li class="page-item disabled com-audioarchive-pagination-ellipsis" aria-hidden="true">
							<span class="page-link">…</span>
						</li>
					<?php elseif ($page === $currentPage) : ?>
						<li class="page-item active" aria-current="page">
							<span class="page-link"><?php echo (int) $page; ?></span>
						</li>
					<?php else : ?>
						<li class="page-item">
							<a class="page-link" href="<?php echo $this->getPaginationPageUrl((int) $page); ?>" aria-label="<?php echo $this->escape(Text::sprintf('COM_AUDIOARCHIVE_PAGINATION_PAGE', (int) $page, $totalPages)); ?>"><?php echo (int) $page; ?></a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>

				<li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
					<?php if ($currentPage === $totalPages) : ?>
						<span class="page-link" aria-hidden="true">&rsaquo;</span>
					<?php else : ?>
						<a class="page-link" href="<?php echo $this->getPaginationPageUrl($currentPage + 1); ?>" rel="next" aria-label="<?php echo $this->escape(Text::_('JNEXT')); ?>">
							<span aria-hidden="true">&rsaquo;</span>
						</a>
					<?php endif; ?>
				</li>
				<li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
					<?php if ($currentPage === $totalPages) : ?>
						<span class="page-link" aria-hidden="true">&raquo;</span>
					<?php else : ?>
						<a class="page-link" href="<?php echo $this->getPaginationPageUrl($totalPages); ?>" aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_END')); ?>">
							<span aria-hidden="true">&raquo;</span>
						</a>
					<?php endif; ?>
				</li>
			</ul>
		</nav>
	<?php endif; ?>
</div>
