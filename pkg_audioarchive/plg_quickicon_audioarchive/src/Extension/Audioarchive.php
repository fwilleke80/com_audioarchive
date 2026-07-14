<?php

namespace Willeke\Plugin\Quickicon\Audioarchive\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Event\SubscriberInterface;
use Joomla\Module\Quickicon\Administrator\Event\QuickIconsEvent;

\defined('_JEXEC') or die;

/**
 * @brief Add an Audio Archive shortcut to Joomla's administrator dashboard.
 */
final class Audioarchive extends CMSPlugin implements SubscriberInterface
{
	/** @var bool Load plugin language files automatically. */
	protected $autoloadLanguage = true;

	/**
	 * @brief Return events handled by the plugin.
	 *
	 * @return array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onGetIcons' => 'onGetIcons',
		];
	}

	/**
	 * @brief Add the Audio Archive dashboard shortcut.
	 *
	 * @param QuickIconsEvent $event Joomla Quick Icons event.
	 *
	 * @return void
	 */
	public function onGetIcons(QuickIconsEvent $event): void
	{
		if (!$this->getApplication()->isClient('administrator'))
		{
			return;
		}

		if ($event->getContext() !== 'site_quickicon')
		{
			return;
		}

		$identity = $this->getApplication()->getIdentity();

		if (!$identity || !$identity->authorise('core.manage', 'com_audioarchive'))
		{
			return;
		}

		$result = $event->getArgument('result', []);
		$result[] = [
			[
				'link' => Route::_('index.php?option=com_audioarchive&view=dashboard'),
				'image' => 'icon-music',
				'icon' => '',
				'text' => Text::_('PLG_QUICKICON_AUDIOARCHIVE_TITLE'),
				'id' => 'plg_quickicon_audioarchive',
				'group' => 'MOD_QUICKICON_SITE',
			],
		];

		$event->setArgument('result', $result);
	}
}
