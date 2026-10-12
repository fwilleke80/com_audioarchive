<?php
/** @brief Verify the production restore controller enforces ownership, state, ACL and CSRF. */
namespace Joomla\CMS\MVC\Controller
{
	class BaseController
	{
		public function setRedirect($url) {}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		public static $app;
		public static $db;
		public static function getApplication() { return self::$app; }
		public static function getContainer() { return new class { public function get($type) { return Factory::$db; } }; }
	}
}
namespace Joomla\CMS\Component { class ComponentHelper { public static function getParams($name) { return null; } } }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\Router { class Route { public static function _($url, $x = false) { return $url; } } }
namespace Joomla\CMS\Session
{
	class Session
	{
		public static bool $valid = true;
		public static function checkToken() { if (!self::$valid) throw new \RuntimeException('CSRF'); return true; }
	}
}
namespace Punga\Component\Audioarchive\Administrator\Service
{
	class UserQuotaService
	{
		public function __construct(...$args) {}
		public function withOwnerLocks($ids, $callback) { return $callback(); }
	}
	class ClipAccessService
	{
		public static bool $allowed = true;
		public function __construct(...$args) {}
		public function canEditState($item) { return self::$allowed; }
	}
}
namespace Punga\Component\Audioarchive\Site\Service
{
	class ContributionService
	{
		public function __construct(...$args) {}
		public function route($view) { return $view; }
	}
}
namespace
{
	define('_JEXEC', 1);
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Controller/MyclipsController.php';
	use Joomla\CMS\Factory;
	use Joomla\CMS\Session\Session;
	use Punga\Component\Audioarchive\Administrator\Service\ClipAccessService;
	$item = (object) ['id' => 4, 'created_by' => 7, 'state' => -2];
	Factory::$db = new class($item)
	{
		public function __construct(public object $item) {}
		public function quoteName($name) { return $name; }
		public function setQuery($sql) { return $this; }
		public function loadObject() { return $this->item; }
	};
	$model = new class
	{
		public array $writes = [];
		public function publish(&$ids, $state) { $this->writes[] = [$ids, $state]; return true; }
		public function getError() { return ''; }
	};
	Factory::$app = new class($model)
	{
		public function __construct(public object $model) {}
		public function getIdentity() { return (object) ['id' => 7]; }
		public function getInput() { return (object) ['post' => new class { public function getInt($key) { return 4; } }]; }
		public function bootComponent($name) { return $this; }
		public function getMVCFactory() { return $this; }
		public function createModel(...$args) { return $this->model; }
		public function enqueueMessage(...$args) {}
	};
	$controller = new \Punga\Component\Audioarchive\Site\Controller\MyclipsController();
	$controller->restore();
	if ($model->writes !== [[[4], 0]]) throw new \RuntimeException('Restore must unpublish');
	foreach (['owner', 'state', 'permission', 'csrf'] as $case)
	{
		$item->created_by = $case === 'owner' ? 8 : 7;
		$item->state = $case === 'state' ? 1 : -2;
		ClipAccessService::$allowed = $case !== 'permission';
		Session::$valid = $case !== 'csrf';
		$blocked = false;
		try { $controller->restore(); } catch (\RuntimeException $error) { $blocked = true; }
		if (!$blocked || count($model->writes) !== 1) throw new \RuntimeException('Restore bypass: ' . $case);
	}
	echo "Restore destination, ownership, state, permission and CSRF checks passed.\n";
}
