<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

$pagination = $this->pagination;
$totalPages = max(1, (int) $pagination->pagesTotal);
$currentPage = max(1, min((int) $pagination->pagesCurrent, $totalPages));
$windowStart = max(1, $currentPage - 2);
$windowEnd = min($totalPages, $currentPage + 2);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
    <div class="text-body-secondary">
        <?php echo $pagination->getResultsCounter(); ?>
    </div>

    <?php if ($totalPages > 1) : ?>
        <nav aria-label="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_PAGINATION_LABEL')); ?>">
            <ul class="pagination mb-0">
                <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                    <?php if ($currentPage === 1) : ?>
                        <span class="page-link" aria-hidden="true">&laquo;</span>
                    <?php else : ?>
                        <a class="page-link" href="<?php echo $this->getPaginationUrl(1); ?>" aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_START')); ?>">&laquo;</a>
                    <?php endif; ?>
                </li>
                <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                    <?php if ($currentPage === 1) : ?>
                        <span class="page-link" aria-hidden="true">&lsaquo;</span>
                    <?php else : ?>
                        <a class="page-link" href="<?php echo $this->getPaginationUrl($currentPage - 1); ?>" aria-label="<?php echo $this->escape(Text::_('JPREV')); ?>">&lsaquo;</a>
                    <?php endif; ?>
                </li>

                <?php if ($windowStart > 1) : ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $this->getPaginationUrl(1); ?>">1</a>
                    </li>
                    <?php if ($windowStart > 2) : ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($page = $windowStart; $page <= $windowEnd; ++$page) : ?>
                    <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                        <?php if ($page === $currentPage) : ?>
                            <span class="page-link" aria-current="page"><?php echo $page; ?></span>
                        <?php else : ?>
                            <a class="page-link" href="<?php echo $this->getPaginationUrl($page); ?>"><?php echo $page; ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>

                <?php if ($windowEnd < $totalPages) : ?>
                    <?php if ($windowEnd < $totalPages - 1) : ?>
                        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $this->getPaginationUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php endif; ?>

                <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                    <?php if ($currentPage === $totalPages) : ?>
                        <span class="page-link" aria-hidden="true">&rsaquo;</span>
                    <?php else : ?>
                        <a class="page-link" href="<?php echo $this->getPaginationUrl($currentPage + 1); ?>" aria-label="<?php echo $this->escape(Text::_('JNEXT')); ?>">&rsaquo;</a>
                    <?php endif; ?>
                </li>
                <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                    <?php if ($currentPage === $totalPages) : ?>
                        <span class="page-link" aria-hidden="true">&raquo;</span>
                    <?php else : ?>
                        <a class="page-link" href="<?php echo $this->getPaginationUrl($totalPages); ?>" aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_END')); ?>">&raquo;</a>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
