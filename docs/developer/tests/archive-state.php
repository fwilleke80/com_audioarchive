<?php
/** @brief Regression test for lazy model initialization using the production ArchiveModel. */
namespace Joomla\Registry
{
	class Registry
	{
		/** @brief Hold the component options needed for archive initialization. */
		public function __construct(private array $values = [])
		{
		}
		/** @brief Read one option. */
		public function set(string $key, mixed $value): void
		{
			$this->values[$key] = $value;
		}
		/** @brief Read one setting. */
		public function get(string $key, mixed $default = null): mixed
		{
			return $this->values[$key] ?? $default;
		}
	}
}
namespace Joomla\CMS\Component
{
	class ComponentHelper
	{
		/** @brief Return global settings with menu-like restrictions to verify isolation. */
		public static function getParams(string $name): \Joomla\Registry\Registry
		{
			return new \Joomla\Registry\Registry(['archive_category_restriction' => 9, 'archive_tag_restriction' => [7]]);
		}
	}
}
namespace Joomla\CMS\MVC\Model
{
	class ListModel
	{
		private bool $initialized = false;
		private bool $initializing = false;
		private array $values = [];
		public int $initializations = 0;
		/** @brief Accept the production model's configuration. */
		public function __construct(array $config = [])
		{
			$this->initialized = !empty($config['ignore_request']);
		}
		/** @brief Model Joomla's lazy initialization; fail early on recursive entry. */
		public function getState(?string $key = null, mixed $default = null): mixed
		{
			if (!$this->initialized)
			{
				if ($this->initializing)
				{
					throw new \RuntimeException('Recursive getState() during populateState()');
				}
				$this->initializing = true;
				$this->initializations++;
				$this->populateState();
				$this->initialized = true;
				$this->initializing = false;
			}
			return $key === null ? $this->values : ($this->values[$key] ?? $default);
		}
		/** @brief Store state without invoking initialization, as Joomla does. */
		public function setState(string $key, mixed $value): void
		{
			$this->values[$key] = $value;
		}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		public static object $application;
		/** @brief Return the current test application. */
		public static function getApplication(): object
		{
			return self::$application;
		}
	}
}
namespace
{
	define('_JEXEC', 1);
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Helper/PaginationHelper.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Model/ArchiveModel.php';
	/** @brief Provide deterministic request values to the real model. */
	class Input
	{
		/** @brief Create one request. */
		public function __construct(public array $values)
		{
		}
		/** @brief Return the request dictionary. */
		public function getArray(): array
		{
			return $this->values;
		}
		/** @brief Read an integer input. */
		public function getInt(string $key, int $default = 0): int
		{
			return (int) ($this->values[$key] ?? $default);
		}
		/** @brief Read the current task. */
		public function getCmd(string $key): string
		{
			return (string) ($this->values[$key] ?? '');
		}
	}
	/** @brief Supply request and per-menu session storage. */
	class Application
	{
		/** @brief Initialize request/session state. */
		public function __construct(private Input $input, public array $session = [])
		{
		}
		/** @brief Return the current input. */
		public function getInput(): Input
		{
			return $this->input;
		}
		/** @brief Read session state. */
		public function getUserState(string $key, mixed $default = null): mixed
		{
			return $this->session[$key] ?? $default;
		}
		/** @brief Persist session state. */
		public function setUserState(string $key, mixed $value): void
		{
			$this->session[$key] = $value;
		}
	}
	/** @brief Substitute only parameter retrieval, leaving production state initialization intact. */
	class ArchiveUnderTest extends \Punga\Component\Audioarchive\Site\Model\ArchiveModel
	{
		/** @brief Configure the test menu context. */
		public function __construct(private \Joomla\Registry\Registry $options)
		{
			parent::__construct(['item_id' => 42]);
		}
		/** @brief Return deterministic settings without loading Joomla menus. */
		public function getResolvedParams(): \Joomla\Registry\Registry
		{
			return $this->options;
		}
	}
	$checks = 0;
	/** @brief Fail independently of PHP's assertion settings. */
	function check(bool $condition, string $message): void
	{
		global $checks;
		$checks++;
		if (!$condition)
		{
			throw new \RuntimeException($message);
		}
	}
	$sessionKey = ArchiveUnderTest::getStateSessionKey(42);
	foreach ([
		[false, [], [], 0],
		[true, ['owner' => 7], [], 7],
		[true, [], ['owner' => 9], 9],
		[false, ['owner' => 7], ['owner' => 9], 0],
		[true, ['owner' => -7], [], 0],
		[true, ['audioarchive_reset' => 1], ['owner' => 9], 0],
	] as [$enabled, $request, $stored, $expected])
	{
		$app = new Application(new Input($request), [$sessionKey => $stored]);
		\Joomla\CMS\Factory::$application = $app;
		$model = new ArchiveUnderTest(new \Joomla\Registry\Registry(['archive_show_owner_filter' => $enabled]));
		check($model->getState('filter.owner') === $expected, 'owner resolution');
		check($model->getState('list.limit') === 20, 'default pagination');
		check($model->initializations === 1, 'initialize once across repeated reads');
		check(isset($request['audioarchive_reset']) ? $app->session[$sessionKey] === null : $app->session[$sessionKey]['owner'] === $expected, 'owner session persistence/reset');
	}

	$before = $app->session;
	$model = new \Punga\Component\Audioarchive\Site\Model\ArchiveModel(['ignore_request' => true]);
	$model->configureEmbed([]);
	check($model->getState('list.limit') === 20, 'bare list has bounded default');
	check($model->getState('filter.duration_min_ms') === null, 'optional duration omitted');
	check($model->getResolvedParams()->get('archive_category_restriction') === 0, 'menu restrictions isolated');
	check($model->initializations === 0, 'no request population');
	foreach (['1s' => 1000, '1.25s' => 1250, '500ms' => 500, '2m' => 120000, '1h' => 3600000, '01:02' => 62000, '1:02:03' => 3723000] as $duration => $expected)
	{
		$model = new \Punga\Component\Audioarchive\Site\Model\ArchiveModel(['ignore_request' => true]);
		$model->configureEmbed(['length_min' => $duration, 'maxresults' => '10', 'sort' => 'duration', 'direction' => 'asc']);
		check($model->getState('filter.duration_min_ms') === $expected, 'duration ' . $duration);
		check($model->getState('embed.maxresults') === 10, 'explicit total cap');
		check($model->getState('list.limit') === 20, 'independent default page size');
		check($model->getState('list.ordering') === 'duration', 'explicit sort');
		check($model->getState('list.direction') === 'ASC', 'explicit direction');
	}
	$model = new \Punga\Component\Audioarchive\Site\Model\ArchiveModel(['ignore_request' => true]);
	$model->configureEmbed(['uploaded_from' => '2026-11-01', 'uploaded_to' => '2026-11-15', 'length_max' => '10s', 'sort' => 'SQL injection', 'maxresults' => '999999']);
	check($model->getState('filter.uploaded_from_sql') === '2026-11-01 00:00:00', 'inclusive date start');
	check($model->getState('filter.uploaded_to_sql') === '2026-11-15 23:59:59', 'inclusive date end');
	check($model->getState('filter.duration_max_ms') === 10000 && $model->getState('filter.duration_max_exact') === true, 'exact maximum');
	check($model->getState('list.ordering') === 'uploaded', 'sort allowlist');
	check($model->getState('embed.maxresults') === 1000, 'total cap bound');
	foreach ([['length_min' => 'nonsense'], ['uploaded_from' => '2026-02-30']] as $invalid)
	{
		$model = new \Punga\Component\Audioarchive\Site\Model\ArchiveModel(['ignore_request' => true]);
		$rejected = false;
		try
		{
			$model->configureEmbed($invalid);
		}
		catch (\InvalidArgumentException)
		{
			$rejected = true;
		}
		check($rejected, 'malformed filters rejected');
	}
	check($app->session === $before, 'embed leaves archive session unchanged');
	echo $checks . " archive state assertions passed.\n";
}
