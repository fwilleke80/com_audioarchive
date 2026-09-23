<?php
/** @brief Exercise upload receipts and the real scoped job worker with transactional SQLite. */
namespace Joomla\CMS\Language
{
	class Text
	{
		public static function _($key): string
		{
			return $key;
		}
		public static function sprintf($key, ...$args): string
		{
			return $key;
		}
	}
}
namespace Punga\Component\Audioarchive\Administrator\Service\Analysis
{
	class AnalysisRepositoryService
	{
		public function __construct($db)
		{
		}
		public function markFailed(...$args): void
		{
		}
	}
	class AnalysisManagerService
	{
		public static array $generated = [];
		public function __construct(...$args)
		{
		}
		public function generate($type, $clipId, $options): object
		{
			self::$generated[] = [$type, $clipId];
			return (object) ['parameters'=>[]];
		}
	}
}
namespace
{
	require __DIR__ . '/module-tags.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/Analysis/AnalysisJobService.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/UploadAnalysisService.php';
	require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/ProcessingStatusService.php';
	class JobQuery extends TagQuery
	{
		private string $mode = 'SELECT';
		private array $columns = [], $conditions = [], $joins = [], $sets = [], $orders = [];
		private string $table = '';
		public function select($value): self
		{
			$this->columns = array_merge($this->columns, (array) $value); return $this;
		}
		public function from($value): self
		{
			$this->table = $value; return $this;
		}
		public function update($value): self
		{
			$this->mode = 'UPDATE'; $this->table = $value; return $this;
		}
		public function set($value): self
		{
			$this->sets[] = $value; return $this;
		}
		public function where($value): self
		{
			$this->conditions[] = $value; return $this;
		}
		public function leftJoin($value): self
		{
			$this->joins[] = ' LEFT JOIN ' . $value; return $this;
		}
		public function order($value): self
		{
			$this->orders = array_merge($this->orders, (array) $value); return $this;
		}
		public function __toString(): string
		{
			$start = $this->mode === 'UPDATE' ? 'UPDATE ' . $this->table . ' SET ' . implode(',', $this->sets) : 'SELECT ' . implode(',', $this->columns) . ' FROM ' . $this->table . implode('', $this->joins);
			return $start . ($this->conditions ? ' WHERE ' . implode(' AND ', $this->conditions) : '') . ($this->orders ? ' ORDER BY ' . implode(',', $this->orders) : '');
		}
	}
	class JobDatabase extends TagDatabase
	{
		private string $sql = '';
		private array $bindings = [];
		private int $affected = 0;
		public function getQuery($new): JobQuery
		{
			return new JobQuery();
		}
		public function quoteName($name, $alias = null): string
		{
			return str_replace('`*`', '*', parent::quoteName($name, $alias));
		}
		public function setQuery($query, $offset = 0, $limit = 0): self
		{
			$this->sql = (string) $query . ($limit ? ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset : '');
			$this->bindings = is_object($query) ? $query->bindings : [];
			return $this;
		}
		private function statement(): PDOStatement
		{
			$stmt = $this->pdo->prepare($this->sql);
			$stmt->execute($this->bindings);
			$this->affected = $stmt->rowCount();
			return $stmt;
		}
		public function execute(): void
		{
			$this->statement();
		}
		public function loadObject(): ?object
		{
			return $this->statement()->fetchObject() ?: null;
		}
		public function loadObjectList(): array
		{
			return $this->statement()->fetchAll(PDO::FETCH_OBJ);
		}
		public function loadColumn(): array
		{
			return $this->statement()->fetchAll(PDO::FETCH_COLUMN);
		}
		public function loadResult(): mixed
		{
			return $this->statement()->fetchColumn();
		}
		public function transactionStart(): void
		{
			$this->pdo->beginTransaction();
		}
		public function transactionCommit(): void
		{
			$this->pdo->commit();
		}
		public function transactionRollback(): void
		{
			$this->pdo->rollBack();
		}
		public function getAffectedRows(): int
		{
			return $this->affected;
		}
	}
	$db = new JobDatabase();
	$db->pdo->exec("CREATE TABLE t_audioarchive_clips(id INTEGER,created_by INTEGER,state INTEGER,title TEXT);
	INSERT INTO t_audioarchive_clips VALUES(1,7,0,'Pending upload'),(2,8,1,'Other owner');
	CREATE TABLE t_audioarchive_jobs(id INTEGER PRIMARY KEY,clip_id INTEGER,job_type TEXT,state TEXT,payload TEXT,priority INTEGER DEFAULT 0,created TEXT,attempts INTEGER DEFAULT 0,maximum_attempts INTEGER DEFAULT 3,locked_until TEXT,locked_by TEXT,last_error TEXT,started TEXT,finished TEXT);
	INSERT INTO t_audioarchive_jobs(id,clip_id,job_type,state,payload) VALUES(1,1,'generate_analysis_waveform','pending','{}'),(2,1,'generate_analysis_spectrogram','pending','{}'),(3,2,'generate_analysis_waveform','pending','{}');");
	$user = new \Joomla\CMS\User\User();
	$user->id = 7;
	$params = new \Joomla\Registry\Registry();
	$service = new \Punga\Component\Audioarchive\Site\Service\UploadAnalysisService($db, $params, $user);
	$grant = $service->grant(1, '/my-clips');
	if ($grant['jobs'] !== [1,2]) throw new RuntimeException('Grant did not capture exact upload jobs');
	$db->pdo->exec("INSERT INTO t_audioarchive_jobs(id,clip_id,job_type,state,payload,locked_until) VALUES(4,1,'generate_analysis_frequency_profile','pending','{}',NULL),(5,2,'generate_analysis_waveform','running','{}','2000-01-01'),(6,1,'generate_analysis_waveform','running','{}','2000-01-01');");
	$result = $service->process(1, $grant);
	if (!$result['processed'] || $result['done']) throw new RuntimeException('First scoped job did not run');
	$result = $service->process(1, $grant);
	if (!$result['done']) throw new RuntimeException('Granted jobs not completed');
	$states = $db->pdo->query('SELECT id,state FROM t_audioarchive_jobs ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
	if ($states !== [1=>'completed',2=>'completed',3=>'pending',4=>'pending',5=>'running',6=>'running']) throw new RuntimeException('Worker changed unrelated or subsequently queued jobs');
	$result = $service->process(1, $grant);
	if (!$result['done'] || $result['processed']) throw new RuntimeException('Completed request not safely repeatable');
	foreach ([[], array_replace($grant,['user'=>8]), array_replace($grant,['clip'=>2]), array_replace($grant,['expires'=>1])] as $bad)
	{
		$denied = false;
		try
		{
			$service->process(1, $bad);
		}
		catch (RuntimeException $error)
		{
			$denied = $error->getCode() === 403;
		}
		if (!$denied) throw new RuntimeException('Invalid receipt accepted');
	}
	$db->pdo->exec('UPDATE t_audioarchive_clips SET created_by=8 WHERE id=1');
	try
	{
		$service->process(1, $grant); throw new LogicException('Transferred clip accepted');
	}
	catch (RuntimeException $error)
	{
	}
	$db->pdo->exec('UPDATE t_audioarchive_clips SET created_by=7,state=-2 WHERE id=1');
	try
	{
		$service->process(1, $grant); throw new LogicException('Trashed clip accepted');
	}
	catch (RuntimeException $error)
	{
	}
	$clip = (object) ['metadata_status'=>'available','preview_status'=>'not_required','waveform_status'=>'available','spectrogram_status'=>'disabled','frequency_profile_status'=>'available'];
	if (\Punga\Component\Audioarchive\Site\Service\ProcessingStatusService::summary($clip) !== 'available') throw new RuntimeException('Completed summary incorrect');
	$clip->waveform_status = 'pending';
	if (\Punga\Component\Audioarchive\Site\Service\ProcessingStatusService::summary($clip) !== 'pending') throw new RuntimeException('Pending summary incorrect');
	$clip->spectrogram_status = 'failed';
	if (\Punga\Component\Audioarchive\Site\Service\ProcessingStatusService::summary($clip) !== 'failed') throw new RuntimeException('Failure priority incorrect');
	echo "Upload authorization, exact job scope, repeatability and processing summaries passed.\n";
}
