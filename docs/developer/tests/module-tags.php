<?php
/** @brief Run production module eligibility SQL with reference-bound parameters against SQLite. */
namespace Joomla\Database
{
	interface DatabaseInterface
	{
	}
	class ParameterType
	{
		public const INTEGER = 1;
		public const STRING = 2;
	}
}
namespace Joomla\Registry
{
	class Registry
	{
		public function __construct(private array $values = [])
		{
		}
		public function get($key, $default = null)
		{
			return $this->values[$key] ?? $default;
		}
	}
}
namespace Joomla\CMS\User
{
	class User
	{
		public int $id = 0;
		public function getAuthorisedViewLevels(): array
		{
			return [1];
		}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		public static function getApplication(): object
		{
			return new class
			{
				public function getIdentity(): object
				{
					return new \Joomla\CMS\User\User();
				}
				public function getLanguage(): object
				{
					return new class
					{
						public function getTag(): string
						{
							return 'en-GB';
						}
					}
					;
				}
				public function get($key, $default)
				{
					return $default;
				}
			}
			;
		}
		public static function getDate(...$args): object
		{
			return new class
			{
				public function toSql(): string
				{
					return '2026-09-23 12:00:00';
				}
				public function format($format): string
				{
					return '2026-09-23';
				}
			}
			;
		}
	}
}
namespace
{
	define('_JEXEC', 1);
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/ClipAccessService.php';
	require __DIR__ . '/../../../pkg_audioarchive/mod_audioarchive/src/Helper/AudioarchiveHelper.php';
	/** @brief Emulate Joomla query reference binding, including independently numbered nested placeholders. */
	class TagQuery
	{
		public array $bindings = [];
		private array $select = [], $where = [], $joins = [];
		private string $from = '';
		private int $counter = 0;
		public function select($value): self
		{
			$this->select = array_merge($this->select, (array) $value); return $this;
		}
		public function from($value): self
		{
			$this->from = $value; return $this;
		}
		public function innerJoin($value): self
		{
			$this->joins[] = ' INNER JOIN ' . $value; return $this;
		}
		public function where($value): self
		{
			$this->where[] = $value; return $this;
		}
		public function extendWhere($outer, $values, $inner): self
		{
			return $this->where('(' . implode(' ' . $inner . ' ', $values) . ')');
		}
		public function bind($name, &$value, $type): self
		{
			$this->bindings[$name] = &$value; return $this;
		}
		public function whereIn($column, $values, $type): self
		{
			$names = [];
			foreach ($values as $value)
			{
				$name = ':preparedArray' . $this->counter++;
				$this->bindings[$name] = $value;
				$names[] = $name;
			}
			return $this->where($column . ' IN (' . implode(',', $names) . ')');
		}
		public function __toString(): string
		{
			return 'SELECT ' . implode(',', $this->select) . ' FROM ' . $this->from . implode('', $this->joins) . ' WHERE ' . implode(' AND ', $this->where);
		}
	}
	class TagDatabase implements \Joomla\Database\DatabaseInterface
	{
		public PDO $pdo;
		public function __construct()
		{
			$this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		public function getQuery($new): TagQuery
		{
			return new TagQuery();
		}
		public function quoteName($name, $alias = null): string
		{
			return '`' . str_replace('.', '`.`', str_replace('#__', 't_', $name)) . '`' . ($alias ? ' AS `' . $alias . '`' : '');
		}
		public function quote($value): string
		{
			return $this->pdo->quote($value);
		}
	}
	$db = new TagDatabase();
	$db->pdo->exec("CREATE TABLE t_categories(id INTEGER,title TEXT,extension TEXT,published INTEGER,access INTEGER,lft INTEGER,rgt INTEGER);
	INSERT INTO t_categories VALUES(1,'Audio','com_audioarchive',1,1,1,4),(2,'Hidden','com_audioarchive',0,1,2,3);
	CREATE TABLE t_audioarchive_clips(id INTEGER,title TEXT,alias TEXT,description TEXT,duration_ms INTEGER,recorded_at TEXT,uploaded_at TEXT,publish_up TEXT,publish_down TEXT,catid INTEGER,language TEXT,play_count INTEGER,download_count INTEGER,visibility_mode TEXT,state INTEGER,access INTEGER);
	CREATE TABLE t_audioarchive_files(clip_id INTEGER,file_role TEXT,is_available INTEGER,mime_type TEXT,file_extension TEXT);
	CREATE TABLE t_contentitem_tag_map(content_item_id INTEGER,type_alias TEXT,tag_id INTEGER);");
	for ($id = 1; $id <= 8; $id++)
	{
		$db->pdo->exec("INSERT INTO t_audioarchive_clips VALUES($id,'Clip','clip','',1000,NULL,NULL,NULL,NULL,1,'*',0,0,'normal',1,1); INSERT INTO t_audioarchive_files VALUES($id,'original',1,'audio/mpeg','mp3');");
		$tags = $id === 1 ? [1] : ($id === 2 ? [2] : ($id === 3 ? [1,2] : range(1,7)));
		foreach ($tags as $tag)
		{
			$type = $id === 8 ? 'com_content.article' : 'com_audioarchive.clip';
			$db->pdo->exec("INSERT INTO t_contentitem_tag_map VALUES($id,'$type',$tag)");
		}
	}
	$db->pdo->exec("UPDATE t_audioarchive_clips SET visibility_mode='private' WHERE id=5; UPDATE t_audioarchive_clips SET state=0 WHERE id=6; UPDATE t_audioarchive_clips SET access=2 WHERE id=7;");
	$method = new ReflectionMethod(\Punga\Module\Audioarchive\Site\Helper\AudioarchiveHelper::class, 'getEligibleQuery');
	$checks = 0;
	foreach ([['all',[1,2],[3,4]],['any',[1,2],[1,2,3,4]],['all',range(1,7),[4]],['any',range(1,7),[1,2,3,4]],['all',[1,99],[]],['any',[99],[]],['all',[1,1,2],[3,4]]] as [$mode,$tags,$expected])
	{
		$query = $method->invoke(null, $db, new \Joomla\Registry\Registry(['tags'=>$tags,'tag_mode'=>$mode]));
		$sql = (string) $query;
		preg_match_all('/:[A-Za-z][A-Za-z0-9_]*/', $sql, $matches);
		if (count(array_unique($matches[0])) !== count($query->bindings)) throw new RuntimeException('Parameter count mismatch');
		$stmt = $db->pdo->prepare($sql);
		$stmt->execute($query->bindings);
		$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
		$ids = array_map(static fn($row): int => (int) $row->id, $rows);
		sort($ids);
		if ($ids !== $expected) throw new RuntimeException('Incorrect ' . $mode . ' result: ' . json_encode($ids));
		$daily = new ReflectionMethod(\Punga\Module\Audioarchive\Site\Helper\AudioarchiveHelper::class, 'selectDaily');
		$one = $daily->invoke(null, $rows, 1, 42);
		$two = $daily->invoke(null, $rows, 1, 42);
		if ($one !== $two || ($one && !in_array((int) $one[0]->id, $expected, true))) throw new RuntimeException('Daily selection escaped criteria');
		$checks++;
	}
	$manifest = simplexml_load_file(__DIR__ . '/../../../pkg_audioarchive/mod_audioarchive/mod_audioarchive.xml');
	$fields = $manifest->xpath('//field[@name="tags"]');
	if ((string) $fields[0]['mode'] !== 'ajax' || (string) $fields[0]['custom'] !== 'deny') throw new RuntimeException('Tag selector is not searchable');
	echo "$checks module SQL/filter/daily cases passed.\n";
}
