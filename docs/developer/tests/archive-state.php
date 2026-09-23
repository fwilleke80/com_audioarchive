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
		public function get(string $key, mixed $default = null): mixed
		{
			return $this->values[$key] ?? $default;
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
	echo $checks . " archive state assertions passed.\n";
}
