<?php
/** @brief Exercise real embed eligibility SQL, event signatures and controller dispatch against SQLite. */
namespace Joomla\Database
{
	interface DatabaseInterface {}
}
namespace Joomla\Registry
{
	class Registry
	{
		/** @brief Read fixture options. */
		public function get(string $key, mixed $default = null): mixed
		{
			return $GLOBALS['options'][$key] ?? $default;
		}
	}
}
namespace Joomla\CMS\User
{
	class User
	{
		public int $id = 0;
		/** @brief Include a Guest-only level to verify it cannot be embedded. */
		public function getAuthorisedViewLevels(): array
		{
			return [1, 5];
		}
		/** @brief Anonymous actors never gain editor privileges. */
		public function authorise(string $action, string $asset): bool
		{
			return false;
		}
	}
}
namespace Joomla\CMS\Component
{
	class ComponentHelper
	{
		/** @brief Return mutable fixture options. */
		public static function getParams(string $name): \Joomla\Registry\Registry
		{
			return new \Joomla\Registry\Registry();
		}
	}
}
namespace Joomla\CMS\Language
{
	class Text
	{
		/** @brief Retain the error identifier for assertions. */
		public static function _(string $text): string
		{
			return $text;
		}
	}
}
namespace Joomla\CMS\Uri
{
	class Uri
	{
		/** @brief Exercise an installation in a subdirectory. */
		public static function root(): string
		{
			return 'https://audio.example/sub/';
		}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		/** @brief Return the application fixture. */
		public static function getApplication(): object
		{
			return $GLOBALS['app'];
		}
		/** @brief Resolve the SQLite adapter. */
		public static function getContainer(): object
		{
			return new class
			{
				/** @brief Return the database dependency. */
				public function get(string $name): object
				{
					return $GLOBALS['db'];
				}
			};
		}
	}
}
namespace Punga\Component\Audioarchive\Site\Service
{
	class PublicMediaService
	{
		/** @brief Accept the production dependency signature. */
		public function __construct(...$args) {}
		/** @brief Load a public-media fixture after real eligibility has passed. */
		public function getPublicClip(int $id, bool $tags): object
		{
			return (object) ['id' => $id, 'title' => 'Fixture'];
		}
		/** @brief Observe total-play updates. */
		public function incrementPlayCount(int $id): void
		{
			$GLOBALS['plays']++;
		}
	}
}
namespace Punga\Component\Audioarchive\Site\Controller
{
	class StreamController
	{
		/** @brief Observe forwarding to the existing range-serving controller. */
		public function play(): void
		{
			$GLOBALS['forwarded']++;
		}
		/** @brief Observe forwarding to the protected analysis handler. */
		public function analysis(): void
		{
			$GLOBALS['forwarded']++;
		}
		/** @brief Simulate the existing short session throttle. */
		protected function shouldCount(string $type, int $id, int $seconds): bool
		{
			return $GLOBALS['throttle'];
		}
		/** @brief Capture dispatched event identifiers. */
		protected function recordPungaAnalyticsEvent(string $event, object $clip): void
		{
			$GLOBALS['events'][] = $event;
		}
		/** @brief Terminate the simulated response. */
		protected function sendJson(int $status, array $payload): never
		{
			throw new \RuntimeException(json_encode($payload), $status);
		}
		/** @brief Capture opaque denied responses. */
		protected function sendError(int $status, string $message): never
		{
			throw new \RuntimeException($message, $status);
		}
	}
}
namespace
{
	define('_JEXEC', 1);
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/ClipAccessService.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/EmbedService.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Controller/EmbedController.php';
	use Punga\Component\Audioarchive\Site\Service\EmbedService;
	/** @brief Minimal SQL builder retaining the production predicates unchanged. */
	class Query
	{
		public array $select = [];
		public array $where = [];
		public string $from = '';
		/** @brief Append selected columns. */
		public function select(string|array $value): self
		{
			$this->select = array_merge($this->select, (array) $value);
			return $this;
		}
		/** @brief Select a table. */
		public function from(string $value): self
		{
			$this->from = $value;
			return $this;
		}
		/** @brief Append a predicate. */
		public function where(string $value): self
		{
			$this->where[] = $value;
			return $this;
		}
		/** @brief Render a SELECT statement. */
		public function __toString(): string
		{
			return 'SELECT ' . implode(',', $this->select) . ' FROM ' . $this->from . ' WHERE ' . implode(' AND ', $this->where);
		}
	}
	class Database implements \Joomla\Database\DatabaseInterface
	{
		public \PDO $pdo;
		public string $query = '';
		/** @brief Initialise the fixture store. */
		public function __construct()
		{
			$this->pdo = new \PDO('sqlite::memory:');
		}
		/** @brief Create the SQL builder. */
		public function getQuery(bool $new): Query
		{
			return new Query();
		}
		/** @brief Map Joomla table prefixes and quote identifiers. */
		public function quoteName(string $name, ?string $alias = null): string
		{
			return str_replace('#__', '', $name) . ($alias ? ' AS ' . $alias : '');
		}
		/** @brief Quote a value. */
		public function quote(string $value): string
		{
			return $this->pdo->quote($value);
		}
		/** @brief Prepare query execution. */
		public function setQuery(Query|string $query): self
		{
			$this->query = (string) $query;
			return $this;
		}
		/** @brief Execute the real eligibility predicates. */
		public function loadColumn(): array
		{
			return $this->pdo->query($this->query)->fetchAll(\PDO::FETCH_COLUMN);
		}
	}
	$db = new Database();
	$db->pdo->exec("CREATE TABLE audioarchive_clips (id INT, catid INT, allow_embed INT, access INT, state INT, visibility_mode TEXT, publish_up TEXT, publish_down TEXT);
	CREATE TABLE categories (id INT, extension TEXT, lft INT, rgt INT, published INT, access INT);
	CREATE TABLE audioarchive_files (clip_id INT, file_role TEXT, is_available INT);
	INSERT INTO categories VALUES (10, 'com_audioarchive', 1, 8, 1, 1), (11, 'com_audioarchive', 2, 3, 1, 1), (12, 'com_audioarchive', 4, 5, 1, 5);
	INSERT INTO audioarchive_clips VALUES (42,11,1,1,1,'normal',NULL,NULL);
	INSERT INTO audioarchive_files VALUES (42,'original',1);");
	$options = ['enable_external_embeds' => 1, 'enable_play_counts' => 1];
	$input = new class
	{
		public int $id = 42;
		public string $token = '';
		public object $post;
		/** @brief Share token access with the POST fixture. */
		public function __construct()
		{
			$this->post = $this;
		}
		/** @brief Resolve clip ID. */
		public function getInt(string $key): int
		{
			return $this->id;
		}
		/** @brief Resolve the posted event token. */
		public function getString(string $key): string
		{
			return $this->token;
		}
	};
	$app = new class
	{
		/** @brief Supply a test-only signing secret. */
		public function get(string $key): string
		{
			return 'fixture-secret-not-a-deployment-secret';
		}
		/** @brief Return the current request fixture. */
		public function getInput(): object
		{
			return $GLOBALS['input'];
		}
	};
	/** @brief Fail a regression explicitly. */
	function check(bool $value, string $message): void
	{
		if (!$value)
		{
			throw new \RuntimeException($message);
		}
	}
	/** @brief Start a simulated fresh request after a permission change. */
	function resetRequest(): void
	{
		(new \ReflectionProperty(EmbedService::class, 'eligible'))->setValue(null, null);
	}
	check(EmbedService::isAllowed(42), 'Published public clip allowed');
	foreach (["allow_embed=0", "access=2", "access=5", "state=0", "state=-2", "visibility_mode='private'", "publish_up='2999-01-01'", "publish_down='2000-01-01'", 'catid=12'] as $change)
	{
		$db->pdo->beginTransaction();
		$db->pdo->exec('UPDATE audioarchive_clips SET ' . $change);
		resetRequest();
		check(!EmbedService::isAllowed(42), 'Deny ' . $change);
		$db->pdo->rollBack();
	}
	foreach (['published=0', 'access=2', 'access=5'] as $change)
	{
		$db->pdo->beginTransaction();
		$db->pdo->exec('UPDATE categories SET ' . $change . ' WHERE id=10');
		resetRequest();
		check(!EmbedService::isAllowed(42), 'Deny ancestor ' . $change);
		$db->pdo->rollBack();
	}
	foreach (['enable_external_embeds' => 0, 'frontend_access_level' => 2] as $key => $value)
	{
		$options[$key] = $value;
		resetRequest();
		check(!EmbedService::isAllowed(42), 'Deny global ' . $key);
		$options[$key] = 1;
	}
	resetRequest();
	$token = EmbedService::token(42, 'play');
	check(EmbedService::validToken($token, 42, 'play'), 'Valid token');
	check(!EmbedService::validToken($token, 43, 'play'), 'Clip-bound token');
	check(!EmbedService::validToken($token, 42, 'copy'), 'Purpose-bound token');
	check(!EmbedService::validToken(EmbedService::token(42, 'play', time()-1), 42, 'play'), 'Expired token');
	check(!EmbedService::validToken($token . 'x', 42, 'play'), 'Malformed token');
	check(EmbedService::presentation('playlist') === 'default', 'No playlist controls for single clips');
	check(str_starts_with(EmbedService::url('player', 42), 'https://audio.example/sub/index.php?'), 'Subdirectory URL');
	$events = [];
	$plays = 0;
	$throttle = true;
	$forwarded = 0;
	$controller = new \Punga\Component\Audioarchive\Site\Controller\EmbedController();
	/** @brief Execute a controller event and assert its HTTP response. */
	function request(object $controller, string $method, int $expected): void
	{
		try
		{
			$controller->$method();
			throw new \RuntimeException('Expected response termination');
		}
		catch (\RuntimeException $e)
		{
			check($e->getCode() === $expected, $method . ': ' . $e->getMessage());
		}
	}
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$input->token = $token;
	request($controller, 'countPlay', 200);
	check($events === ['audio.play_embedded'] && $plays === 1, 'Exactly one embedded event and one total play');
	$throttle = false;
	request($controller, 'countPlay', 200);
	check(count($events) === 1, 'Throttled replay');
	$throttle = true;
	$options['enable_play_counts'] = 0;
	request($controller, 'countPlay', 200);
	check(count($events) === 1, 'Disabled play counts');
	$input->token = EmbedService::token(42, 'copy');
	request($controller, 'created', 200);
	check($events === ['audio.play_embedded', 'audio.embed'], 'Separate creation event');
	request($controller, 'countPlay', 403);
	$_SERVER['REQUEST_METHOD'] = 'GET';
	request($controller, 'created', 405);
	$controller->play();
	$controller->analysis();
	check($forwarded === 2, 'Reuse protected media delivery');
	$db->pdo->exec('UPDATE audioarchive_clips SET allow_embed=0');
	resetRequest();
	request($controller, 'play', 404);
	request($controller, 'analysis', 404);
	$_SERVER['REQUEST_METHOD'] = 'POST';
	request($controller, 'created', 404);
	echo "Embed public/category/ancestor policy, signatures, revocation and analytics separation passed.\n";
}
