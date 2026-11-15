<?php

namespace Punga\Component\Audioarchive\Site\Helper;

\defined('_JEXEC') or die;

/** @brief Convert public page numbers and legacy offsets into bounded database offsets. */
abstract class PaginationHelper
{
    /**
     * @brief Prefer page numbers while accepting old start/limitstart links.
     * @param array<string,mixed> $query Public request values.
     * @param int $limit Effective page size.
     * @return int Non-negative database offset.
     */
    public static function offset(array $query, int $limit): int
    {
        $limit = max(1, $limit);
        if (array_key_exists('page', $query))
        {
            $page = max(1, min(intdiv(PHP_INT_MAX, $limit), (int) $query['page']));
            return ($page - 1) * $limit;
        }
        return max(0, (int) ($query['limitstart'] ?? $query['start'] ?? 0));
    }

    /**
     * @brief Return a compact range with null entries representing ellipses.
     * @param int $totalPages Available page count.
     * @param int $currentPage Selected page.
     * @param int $edges Number of pages at each end.
     * @return array<int|null> Page numbers and ellipses.
     */
    public static function compact(int $totalPages, int $currentPage, int $edges = 5): array
    {

        $total = max(1, (int) $totalPages);
        $current = max(1, min($total, (int) $currentPage));
        $edgeSize = max(
            1,
            min(20, (int) $edges)
        );

        if ($total <= ($edgeSize * 2) + 1)
        {
            return range(1, $total);
        }

        $pages = array_merge(
            range(1, min($edgeSize, $total)),
            range(max(1, $current - 1), min($total, $current + 1)),
            range(max(1, $total - $edgeSize + 1), $total)
        );
        $pages = array_values(array_unique(array_map('intval', $pages)));
        sort($pages, SORT_NUMERIC);

        $result = [];
        $previous = null;

        foreach ($pages as $page)
        {
            if ($previous !== null && $page > $previous + 1)
            {
                $result[] = null;
            }

            $result[] = $page;
            $previous = $page;
        }

        return $result;
    }
}
