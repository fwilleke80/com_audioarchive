<?php
/** @brief Verify the embed view with Joomla's actual StandardRules and NomenuRules. */
namespace Joomla\CMS\Component\Router\Rules
{
	interface RulesInterface
	{
	}
}
namespace Joomla\CMS\Component\Router
{
	class RouterView
	{
		public object $menu;
		/** @brief Define the same unkeyed view registered by the component. */
		public function getViews(): array
		{
			return ['embed' => (object) ['name' => 'embed', 'key' => null, 'children' => []]];
		}
		/** @brief Identify the component. */
		public function getName(): string
		{
			return 'audioarchive';
		}
		/** @brief Return the view ancestry. */
		public function getPath(array $query): array
		{
			return ['embed' => true];
		}
	}
}
namespace
{
	define('_JEXEC', 1);
	$directory = getenv('JOOMLA_ROUTER_RULES');
	if (!$directory || !is_file($directory . '/StandardRules.php') || !is_file($directory . '/NomenuRules.php'))
	{
		fwrite(STDERR, "Set JOOMLA_ROUTER_RULES to Joomla libraries/src/Component/Router/Rules.\n");
		exit(2);
	}
	require $directory . '/StandardRules.php';
	require $directory . '/NomenuRules.php';
	$router = new \Joomla\CMS\Component\Router\RouterView();
	$router->menu = new class
	{
		public ?object $active = null;
		/** @brief Return a public embed menu item. */
		public function getItem(int $id): ?object
		{
			return $id === 7 ? (object) ['component' => 'com_audioarchive', 'query' => ['option' => 'com_audioarchive', 'view' => 'embed']] : null;
		}
		/** @brief Return the parsed menu context. */
		public function getActive(): ?object
		{
			return $this->active;
		}
	};
	/** @brief Fail on a route contract regression. */
	function check(bool $condition, string $message): void
	{
		if (!$condition)
		{
			throw new \RuntimeException($message);
		}
	}
	$standard = new \Joomla\CMS\Component\Router\Rules\StandardRules($router);
	$nomenu = new \Joomla\CMS\Component\Router\Rules\NomenuRules($router);
	$query = ['option' => 'com_audioarchive', 'view' => 'embed', 'id' => 42, 'Itemid' => 7, 'links' => 0];
	$segments = [];
	$standard->build($query, $segments);
	check(!isset($query['view']) && $segments === [] && $query['id'] === 42 && $query['links'] === 0, 'Menu route leaves clip ID and appearance in query');
	$router->menu->active = $router->menu->getItem(7);
	$vars = ['id' => 42, 'links' => 0];
	$standard->parse($segments, $vars);
	check($vars['view'] === 'embed' && $vars['id'] === 42, 'Menu route parses to embed view');
	$router->menu->active = null;
	$query = ['option' => 'com_audioarchive', 'view' => 'embed', 'id' => 42, 'Itemid' => 0];
	$segments = [];
	$standard->build($query, $segments);
	$nomenu->build($query, $segments);
	check($segments === ['embed'] && !isset($query['view']) && $query['id'] === 42, 'Component fallback emits embed segment');
	$vars = ['id' => 42];
	$nomenu->parse($segments, $vars);
	check($vars['view'] === 'embed' && $segments === [] && $vars['id'] === 42, 'Component fallback parses');
	echo "Joomla native menu and menu-less embed route build/parse checks passed.\n";
}
