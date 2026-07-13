<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Component\Router\Rules\MenuRules as CoreMenuRules;

\defined('_JEXEC') or die;

/**
 * @brief Preserve an explicitly selected Archive menu context for clip routes.
 */
class MenuRules extends CoreMenuRules
{
	/**
	 * @brief Choose the menu item used to build a route.
	 *
	 * Joomla's generic rule may replace an explicitly supplied Archive Itemid
	 * when several menu items point at the same unkeyed Archive view. A clip is
	 * a child of that view, so a valid supplied Archive item remains authoritative.
	 *
	 * @param array<string, mixed> $query Route query.
	 *
	 * @return void
	 */
	public function preprocess(&$query)
	{
		if (($query['view'] ?? '') === 'clip' && (int) ($query['Itemid'] ?? 0) > 0)
		{
			$item = $this->router->menu->getItem((int) $query['Itemid']);

			if (
				$item !== null
				&& (string) $item->component === 'com_audioarchive'
				&& (string) ($item->query['view'] ?? '') === 'archive'
			)
			{
				return;
			}
		}

		parent::preprocess($query);
	}
}
