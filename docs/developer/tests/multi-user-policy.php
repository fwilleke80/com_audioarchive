<?php
/** @brief Exercise production access/quota services against SQLite with minimal Joomla adapters. */
namespace Joomla\Database
{
	interface DatabaseInterface
	{
	}
}
namespace Joomla\CMS\User
{
	interface UserFactoryInterface
	{
	}
	class User
	{
		/** @brief Create an identity with explicit test permissions. */
		public function __construct(public int $id = 0, public array $permissions = [], public array $levels = [1], public array $groups = [2])
		{
		}
		/** @brief Resolve test ACL without assuming ownership grants actions. */
		public function authorise(string $action, string $asset): bool
		{
			return $this->permissions[$asset . ':' . $action] ?? $this->permissions[$action] ?? false;
		}
		/** @brief Return visitor view levels. */
		public function getAuthorisedViewLevels(): array
		{
			return $this->levels;
		}
		/** @brief Return all applicable groups. */
		public function getAuthorisedGroups(): array
		{
			return $this->groups;
		}
	}
}
namespace Joomla\Registry
{
	class Registry
	{
		/** @brief Initialize component options. */
		public function __construct(public array $data = [])
		{
		}
		/** @brief Read an option. */
		public function get(string $key, mixed $default = null): mixed
		{
			return $this->data[$key] ?? $default;
		}
	}
}
namespace Joomla\CMS\Language
{
	class Text
	{
		/** @brief Return untranslated keys for error assertions. */
		public static function _(string $key): string
		{
			return $key;
		}
	}
}
namespace Joomla\CMS
{
	class Factory
	{
		public static array $users = [];
		public static array $messages = [];
		/** @brief Provide a minimal user factory. */
		public static function getContainer(): object
		{
			return new class
			{
				public function get(string $class): object
				{
					return new class
					{
						public function loadUserById(int $id): object
						{
							return Factory::$users[$id] ?? new \Joomla\CMS\User\User($id);
						}
					};
				}
			};
		}
		/** @brief Collect explicit override warnings. */
		public static function getApplication(): object
		{
			return new class
			{
				public function enqueueMessage(string $message, string $type): void
				{
					Factory::$messages[] = $message;
				}
			};
		}
	}
}
namespace Joomla\CMS\Form
{
	/** @brief Minimal form adapter for production choice generation and bound values. */
	class Form
	{
		public array $fields = [];
		public array $values = [];
		public function getValue($name, $group = null, $default = null)
		{
			return $this->values[$name] ?? $default;
		}
		public function setValue($name, $group, $value): void
		{
			$this->values[$name] = $value;
		}
		public function setField($field, $group, $replace, $fieldset): void
		{
			$this->fields[(string) $field['name']] = $field;
		}
		public function setFieldAttribute($name, $attribute, $value): void
		{
			if (isset($this->fields[$name]))
			{
				$this->fields[$name][$attribute] = $value;
			}
		}
		public function removeField($name): void
		{
			unset($this->fields[$name]);
		}
	}
}
namespace
{
	define('_JEXEC', 1);
	define('JPATH_ROOT', __DIR__);
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/ClipAccessService.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/UserQuotaService.php';

	/** @brief Build the subset of Joomla query syntax used by the production services. */
	class Query
	{
		private string $select = '*';
		private string $from = '';
		private array $where = [];
		private array $joins = [];
		private string $ordering = '';
		/** @brief Preserve production ordering in the SQLite query adapter. */
		public function order(string $ordering): self
		{
			$this->ordering = $ordering;
			return $this;
		}
		public function select(array|string $fields): self
		{
			$this->select = is_array($fields) ? implode(', ', $fields) : $fields;
			return $this;
		}
		public function from(string $table): self
		{
			$this->from = $table;
			return $this;
		}
		public function where(string $condition): self
		{
			$this->where[] = $condition;
			return $this;
		}
		public function innerJoin(string $join): self
		{
			$this->joins[] = ' INNER JOIN ' . $join;
			return $this;
		}
		public function __toString(): string
		{
			return 'SELECT ' . $this->select . ' FROM ' . $this->from . implode('', $this->joins) . ($this->where ? ' WHERE ' . implode(' AND ', $this->where) : '') . ($this->ordering ? ' ORDER BY ' . $this->ordering : '');
		}
	}
	/** @brief Execute the generated SQL against a real in-memory database. */
	class Database implements \Joomla\Database\DatabaseInterface
	{
		public \PDO $pdo;
		protected string $sql = '';
		public int $lockBalance = 0;
		public function __construct()
		{
			$this->pdo = new \PDO('sqlite::memory:');
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		}
		public function getQuery(bool $new): Query
		{
			return new Query();
		}
		public function quoteName(string $name, ?string $alias = null): string
		{
			$name = str_replace('#__', 'test_', $name);
			return '`' . str_replace('.', '`.`', $name) . '`' . ($alias ? ' AS `' . $alias . '`' : '');
		}
		public function quote(string $value): string
		{
			return $this->pdo->quote($value);
		}
		public function getPrefix(): string
		{
			return 'test_';
		}
		public function setQuery(string|Query $query): self
		{
			$this->sql = (string) $query;
			return $this;
		}
		public function loadResult(): mixed
		{
			if (str_contains($this->sql, 'GET_LOCK('))
			{
				$this->lockBalance++;
				return 1;
			}
			if (str_contains($this->sql, 'RELEASE_LOCK('))
			{
				$this->lockBalance--;
				return 1;
			}
			return $this->pdo->query($this->sql)->fetchColumn();
		}
		public function loadObject(): ?object
		{
			return $this->pdo->query($this->sql)->fetchObject() ?: null;
		}
		public function loadObjectList(): array
		{
			return $this->pdo->query($this->sql)->fetchAll(\PDO::FETCH_OBJ);
		}
		public function loadColumn(): array
		{
			return $this->pdo->query($this->sql)->fetchAll(\PDO::FETCH_COLUMN);
		}
	}
	$checks = 0;
	/** @brief Fail deterministically without depending on PHP assertion configuration. */
	function check(bool $condition, string $label): void
	{
		global $checks;
		$checks++;
		if (!$condition)
		{
			throw new \RuntimeException($label);
		}
	}
	$db = new Database();
	$db->pdo->exec("CREATE TABLE test_categories (id INTEGER, extension TEXT, published INTEGER, access INTEGER, lft INTEGER, rgt INTEGER);
		INSERT INTO test_categories VALUES (1,'com_audioarchive',1,1,1,6),(2,'com_audioarchive',1,1,2,3),(3,'com_audioarchive',1,2,4,5);
		CREATE TABLE test_audioarchive_clips (id INTEGER, created_by INTEGER, visibility_mode TEXT, state INTEGER, access INTEGER, catid INTEGER, publish_up TEXT, publish_down TEXT);
		INSERT INTO test_audioarchive_clips VALUES (1,7,'normal',1,1,2,NULL,NULL),(2,7,'private',1,1,2,NULL,NULL),(3,7,'private',0,1,2,NULL,NULL),(4,7,'normal',1,1,3,NULL,NULL),(5,0,'private',1,1,2,NULL,NULL),(6,7,'normal',-2,1,2,NULL,NULL),(7,7,'normal',1,1,2,'2999-01-01',NULL);
		CREATE TABLE test_audioarchive_files (clip_id INTEGER, file_role TEXT, file_size INTEGER);
		INSERT INTO test_audioarchive_files VALUES (1,'original',30),(2,'original',40),(6,'original',10),(1,'preview',9999);
		CREATE TABLE test_audioarchive_group_quotas (group_id INTEGER, storage_quota_bytes INTEGER, clip_quota INTEGER);
		CREATE TABLE test_audioarchive_user_profiles (user_id INTEGER, storage_quota_override_bytes INTEGER, clip_quota_override INTEGER);");
	$guest = new \Joomla\CMS\User\User();
	$owner = new \Joomla\CMS\User\User(7);
	$editor = new \Joomla\CMS\User\User(7, ['core.edit.own' => true]);
	$moderator = new \Joomla\CMS\User\User(9, ['audioarchive.manage.private' => true]);
	$otherEditor = new \Joomla\CMS\User\User(8, ['core.edit' => true]);
	$clip = static fn(int $id): object => $db->setQuery('SELECT * FROM test_audioarchive_clips WHERE id=' . $id)->loadObject();
	$policy = static fn($user): object => new \Punga\Component\Audioarchive\Administrator\Service\ClipAccessService($db, $user);
	check($policy($guest)->canView($clip(1)), 'guest normal');
	check(!$policy($guest)->canView($clip(2)), 'guest private');
	check($policy($owner)->canView($clip(2)), 'owner published private');
	check(!$policy($owner)->canView($clip(3)), 'ownership is not preview ACL');
	check($policy($editor)->canView($clip(3)), 'owner edit preview');
	check(!$policy($otherEditor)->canView($clip(2)), 'edit does not grant private visibility');
	check($policy($moderator)->canView($clip(2)), 'private manager');
	check(!$policy($moderator)->canEdit($clip(2)), 'private manager is not editor');
	check(!$policy($moderator)->canView($clip(3)), 'preview still needs edit');
	check(!$policy($guest)->canView($clip(4)), 'category access');
	check(!$policy($guest)->canView($clip(5)), 'unowned is not guest owned');
	check($policy($moderator)->canView($clip(5)), 'manager orphan recovery');
	check(!$policy($editor)->canView($clip(6)), 'trash not previewable');
	check(!$policy($guest)->canView($clip(7)), 'schedule');
	$query=$db->getQuery(true)->select('a.id')->from('test_audioarchive_clips a');
	$policy($editor)->applyPublicVisibilityFilter($query);
	check($db->setQuery($query)->loadColumn() === [1], 'discovery excludes private preview and scheduled');
	$db->pdo->exec('UPDATE test_categories SET published=0 WHERE id=1');
	check(!$policy($guest)->canView($clip(1)), 'ancestor publication');
	$db->pdo->exec('UPDATE test_categories SET published=1 WHERE id=1');
	$options = new \Joomla\Registry\Registry(['quota_storage_enabled'=>1,'quota_storage_mode'=>'custom','quota_storage_default_mb'=>1,'quota_clips_enabled'=>1,'quota_clips_mode'=>'custom','quota_clips_default'=>10]);
	$quota = new \Punga\Component\Audioarchive\Administrator\Service\UserQuotaService($db, $options, $owner);
	\Joomla\CMS\Factory::$users[7] = new \Joomla\CMS\User\User(7, [], [1], [2,3]);
	check($quota->getUsage(7) === ['storage'=>80,'clips'=>6], 'originals and trash count; derived excluded');
	check($quota->getEffectiveQuota(7)['storage'] === 1048576, 'MB default');
	$db->pdo->exec('INSERT INTO test_audioarchive_group_quotas VALUES (2,100,7),(3,200,NULL)');
	check($quota->getEffectiveQuota(7)['storage'] === 200, 'largest group');
	check($quota->getEffectiveQuota(7)['clips'] === 7, 'inherit dimension');
	$db->pdo->exec('UPDATE test_audioarchive_group_quotas SET storage_quota_bytes=-1 WHERE group_id=2');
	check($quota->getEffectiveQuota(7)['storage'] === -1, 'unlimited group wins');
	$db->pdo->exec('INSERT INTO test_audioarchive_user_profiles VALUES (7,90,6)');
	check($quota->getEffectiveQuota(7)['storage'] === 90, 'user override wins');
	$quota->assertIncrease(7, 10, 0);
	check(true, 'exact limit permitted');
	try
	{
		$quota->assertIncrease(7, 11, 0);
		check(false, 'byte limit');
	}
	catch (\RuntimeException $e)
	{
		check($e->getMessage() === 'COM_AUDIOARCHIVE_QUOTA_EXCEEDED', 'byte rejection');
	}
	try
	{
		$quota->assertIncrease(7, 0, 1);
		check(false, 'clip limit');
	}
	catch (\RuntimeException $e)
	{
		check($e->getMessage() === 'COM_AUDIOARCHIVE_QUOTA_EXCEEDED', 'count rejection');
	}
	$db->pdo->exec('UPDATE test_audioarchive_user_profiles SET storage_quota_override_bytes=0');
	$quota->assertIncrease(7, 0, 0);
	check(true, 'reduction allowed while over quota');
	$override = new \Punga\Component\Audioarchive\Administrator\Service\UserQuotaService($db,$options,new \Joomla\CMS\User\User(9,['audioarchive.quota.override'=>true]));
	$override->assertIncrease(7,100,1,true);
	check(count(\Joomla\CMS\Factory::$messages) === 2, 'explicit override warnings');
	try
	{
		$quota->withOwnerLocks([7,7,9], function (): void
		{
			throw new \RuntimeException('callback failed');
		});
	}
	catch (\RuntimeException $e)
	{
		check($db->lockBalance === 0, 'locks released after failure');
	}
	$options->data['quota_storage_enabled']=0;
	check($quota->getEffectiveQuota(7)['storage'] === -1, 'disabled ignores overrides');
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/ContributionService.php';
	$db->pdo->exec("ALTER TABLE test_categories ADD COLUMN title TEXT DEFAULT 'Category';
		CREATE TABLE test_viewlevels (id INTEGER, title TEXT, ordering INTEGER);
		INSERT INTO test_viewlevels VALUES (1,'Public',0),(2,'Registered',1),(3,'Special',2);
		CREATE TABLE test_tags (id INTEGER, access INTEGER, published INTEGER);
		INSERT INTO test_tags VALUES (2,1,1),(3,2,1),(4,1,0);");
	$contributor = new \Joomla\CMS\User\User(7, ['core.create'=>true,'core.edit.own'=>true]);
	$contributions = new \Punga\Component\Audioarchive\Site\Service\ContributionService($db, $contributor);
	$config = new \Joomla\Registry\Registry(['frontend_allowed_access'=>[1,2],'allow_private_clips'=>1]);
	$input = ['title'=>'Test','catid'=>2,'access'=>1,'state'=>1,'created_by'=>99,'id'=>999,'visibility_mode'=>'private','tags'=>[2], 'uuid'=>'forged','play_count'=>100,'rules'=>['core.edit'=>true]];
	$clean = $contributions->sanitise($input, $config);
	check($clean['id'] === 0 && $clean['created_by'] === 7, 'frontend cannot forge owner or existing ID');
	check($clean['state'] === 0, 'ordinary contributor cannot publish');
	check($clean['visibility_mode'] === 'private', 'private contributor creation');
	check(!isset($clean['uuid'], $clean['play_count'], $clean['rules']), 'managed fields excluded');
	check($clean['tags'] === [2], 'existing public tags accepted');
	/** @brief Require a rejected contribution without swallowing test assertion failures. */
	function rejectContribution(callable $operation, string $label): void
	{
		$rejected = false;
		try
		{
			$operation();
		}
		catch (\RuntimeException | \InvalidArgumentException $e)
		{
			$rejected = true;
		}
		check($rejected, $label);
	}
	rejectContribution(fn() => $contributions->sanitise(array_replace($input, ['catid'=>3]), $config), 'hidden category blocked');
	rejectContribution(fn() => $contributions->sanitise(array_replace($input, ['access'=>3]), $config), 'restricted access rejected');
	foreach ([[3], [4], ['#newtag#'], [999]] as $tags)
	{
		rejectContribution(fn() => $contributions->sanitise(array_replace($input, ['tags'=>$tags]), $config), 'unavailable or new tag rejected');
	}
	$config->data['upload_allowed_access'] = [2,3];
	check(array_map(fn($row) => (int) $row->id, $contributions->accessLevels($config)) === [2], 'menu cannot widen global access levels');
	unset($config->data['upload_allowed_access']);
	$config->data['frontend_upload_categories'] = [3];
	rejectContribution(fn() => $contributions->sanitise($input, $config), 'global category list enforced');
	$config->data['frontend_upload_categories'] = [2];
	$config->data['upload_allowed_categories'] = [3];
	rejectContribution(fn() => $contributions->sanitise($input, $config), 'menu categories only narrow global list');
	unset($config->data['frontend_upload_categories'], $config->data['upload_allowed_categories']);
	$config->data['upload_category_mode'] = 'fixed';
	$config->data['upload_fixed_category'] = 2;
	check($contributions->sanitise(array_replace($input, ['catid'=>999]), $config)['catid'] === 2, 'fixed menu category enforced');
	unset($config->data['upload_category_mode']);
	$config->data['allow_private_clips'] = 0;
	check($contributions->sanitise($input, $config)['visibility_mode'] === 'normal', 'private feature disabled for new uploads');
	$config->data['allow_private_clips'] = 1;
	$config->data['upload_choose_visibility'] = 0;
	$config->data['upload_default_visibility'] = 'normal';
	check($contributions->sanitise($input, $config)['visibility_mode'] === 'normal', 'fixed menu visibility enforced');
	unset($config->data['upload_choose_visibility']);
	$registered = $contributions->sanitise(array_replace($input, ['visibility_mode'=>'registered','access'=>1]), $config);
	check($registered['access'] === 2 && $registered['visibility_mode'] === 'normal', 'registered preset uses Joomla ACL');
	$public = $contributions->sanitise(array_replace($input, ['visibility_mode'=>'public','access'=>2]), $config);
	check($public['access'] === 1 && $public['visibility_mode'] === 'normal', 'public preset resolves conflicting posted access');
	$config->data['upload_allowed_access'] = [1];
	rejectContribution(fn() => $contributions->sanitise(array_replace($input, ['visibility_mode'=>'registered']), $config), 'registered preset cannot bypass menu access restrictions');
	unset($config->data['upload_allowed_access']);
	$config->data['allow_private_clips'] = 0;
	check($contributions->sanitise(array_replace($input, ['visibility_mode'=>'registered']), $config)['access'] === 2, 'registered choice independent of private feature');
	$config->data['allow_private_clips'] = 1;
	$config->data['upload_choose_visibility'] = 0;
	$config->data['upload_default_visibility'] = 'registered';
	check($contributions->sanitise(array_replace($input, ['visibility_mode'=>'public']), $config)['access'] === 2, 'fixed registered default cannot be bypassed');
	unset($config->data['upload_choose_visibility']);
	$form = new \Joomla\CMS\Form\Form();
	$form->values = ['visibility_mode'=>'normal','access'=>2];
	$registeredClip = clone $clip(1);
	$registeredClip->access = 2;
	$contributions->configureForm($form, $config, $registeredClip);
	check($form->values['visibility_mode'] === 'registered', 'editing reconstructs Registered visibility from stored ACL');
	$config->data['allow_private_clips'] = 0;
	$form = new \Joomla\CMS\Form\Form();
	$form->values = ['visibility_mode'=>'normal','access'=>1];
	$contributions->configureForm($form, $config);
	$options = array_map(static fn($option): string => (string) $option['value'], iterator_to_array($form->fields['visibility_mode']->option, false));
	check(in_array('registered', $options, true) && !in_array('private', $options, true), 'form offers Registered independently of Private');
	check((string) $form->fields['catid']['type'] === 'list', 'multiple permitted categories keep the selector');
	$config->data['frontend_upload_categories'] = [2];
	$contributions->configureForm($form, $config);
	check((string) $form->fields['catid']['type'] === 'hidden' && $form->values['catid'] === 2, 'single permitted category is selected and hidden');
	unset($config->data['frontend_upload_categories']);
	$config->data['allow_private_clips'] = 1;
	$edited = $contributions->sanitise(array_replace($input, ['state'=>-2, 'publish_up'=>'2999-01-01']), $config, $clip(1));
	check($edited['state'] === 1 && $edited['publish_up'] === null, 'edit-own cannot forge state or publication dates');
	check($edited['id'] === 1 && $edited['created_by'] === 7, 'editing preserves persisted identity');
	$foreign = clone $clip(1);
	$foreign->created_by = 8;
	rejectContribution(fn() => $contributions->sanitise($input, $config, $foreign), 'edit-own rejects another owner');
	$guestContributions = new \Punga\Component\Audioarchive\Site\Service\ContributionService($db, $guest);
	rejectContribution(fn() => $guestContributions->sanitise($input, $config), 'guest cannot submit');
	$noCreate = new \Punga\Component\Audioarchive\Site\Service\ContributionService($db, new \Joomla\CMS\User\User(8));
	rejectContribution(fn() => $noCreate->sanitise($input, $config), 'login alone cannot create');
	$db->pdo->exec("INSERT INTO test_categories VALUES (4,'com_audioarchive',1,1,7,8,'Other');");
	check($contributions->sanitise(array_replace($input, ['catid'=>4]), $config, $clip(1))['state'] === 0, 'moving into moderated category unpublishes');
	$contributor->permissions['core.edit.state'] = true;
	$config->data['upload_default_state'] = 1;
	check($contributions->sanitise($input, $config)['state'] === 1, 'publisher may use published menu default');
	$config->data['upload_choose_state'] = 1;
	check($contributions->sanitise(array_replace($input, ['state'=>0]), $config)['state'] === 0, 'publisher can choose unpublished');
	$config->data['upload_default_access'] = 99;
	$withoutAccess = $input;
	unset($withoutAccess['access']);
	check($contributions->sanitise($withoutAccess, $config)['access'] === 1, 'stale access default falls back to permitted level');
	check($contributions->sanitise(array_replace($input, ['catid'=>3]), $config, $clip(4))['catid'] === 3, 'editable clip can retain its now-hidden category');
	echo $checks . " policy/quota/contribution assertions passed.\n";
}
