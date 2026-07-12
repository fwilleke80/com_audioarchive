<?php

\defined('_JEXEC') or die;

if ((int) $this->pagination->pagesTotal > 1)
{
	echo '<nav class="com-audioarchive-pagination" aria-label="Pagination">';
	echo $this->pagination->getPagesLinks();
	echo '</nav>';
}
