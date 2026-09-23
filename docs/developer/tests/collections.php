<?php
/** @brief Execute collection ownership, transactions, sharing and import policy with real SQLite queries. */
require __DIR__ . '/multi-user-policy.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/CollectionService.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/CollectionArchiveService.php';

/** @brief Extend the policy-test adapter with transactional object persistence. */
class CollectionDatabase extends Database
{
	/** @brief Execute a production mutation. */
	public function execute(): void
	{
		$this->pdo->exec($this->sql);
	}
	/** @brief Start a real SQLite transaction. */
	public function transactionStart(): void
	{
		$this->pdo->beginTransaction();
	}
	/** @brief Commit all writes. */
	public function transactionCommit(): void
	{
		$this->pdo->commit();
	}
	/** @brief Undo all writes after a rejected operation. */
	public function transactionRollback(): void
	{
		$this->pdo->rollBack();
	}
	/** @brief Insert a Joomla-style object and optionally return its generated ID. */
	public function insertObject(string $table, object $object, ?string $key = null): void
	{
		$fields = get_object_vars($object);
		if ($key && empty($fields[$key]))
		{
			unset($fields[$key]);
		}
		$sql = 'INSERT INTO ' . $this->quoteName($table) . ' (' . implode(',', array_keys($fields)) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
		$this->pdo->prepare($sql)->execute(array_values($fields));
		if ($key)
		{
			$object->$key = (int) $this->pdo->lastInsertId();
		}
	}
	/** @brief Update an object by its explicit primary key. */
	public function updateObject(string $table, object $object, string $key, bool $nulls = false): void
	{
		$fields = get_object_vars($object);
		$id = $fields[$key];
		unset($fields[$key]);
		$sql = 'UPDATE ' . $this->quoteName($table) . ' SET ' . implode(',', array_map(static fn(string $name): string => $name . '=?', array_keys($fields))) . ' WHERE ' . $key . '=?';
		$this->pdo->prepare($sql)->execute([...array_values($fields), $id]);
	}
	/** @brief Return portable associative records. */
	public function loadAssocList(): array
	{
		return $this->pdo->query($this->sql)->fetchAll(PDO::FETCH_ASSOC);
	}
}
$startChecks = $checks;
$db = new CollectionDatabase();
$db->pdo->exec("PRAGMA foreign_keys=ON;
CREATE TABLE test_categories (id INTEGER, extension TEXT, published INTEGER, access INTEGER, lft INTEGER, rgt INTEGER);
INSERT INTO test_categories VALUES (2,'com_audioarchive',1,1,1,2);
CREATE TABLE test_users (id INTEGER, username TEXT);
INSERT INTO test_users VALUES (7,'owner'),(8,'other');
CREATE TABLE test_audioarchive_clips (id INTEGER PRIMARY KEY, uuid TEXT, title TEXT, created_by INTEGER, visibility_mode TEXT, state INTEGER, access INTEGER, catid INTEGER, publish_up TEXT, publish_down TEXT);
INSERT INTO test_audioarchive_clips VALUES (1,'public-clip','Public',7,'normal',1,1,2,NULL,NULL),(2,'private-clip','Secret',7,'private',1,1,2,NULL,NULL),(3,'other-private','Foreign',8,'private',1,1,2,NULL,NULL);
CREATE TABLE test_audioarchive_collections (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT UNIQUE, user_id INTEGER, kind TEXT, title TEXT, pad_count INTEGER, share_token TEXT, created TEXT, modified TEXT);
CREATE TABLE test_audioarchive_collection_items (collection_id INTEGER REFERENCES test_audioarchive_collections(id) ON DELETE CASCADE, position INTEGER, clip_id INTEGER REFERENCES test_audioarchive_clips(id) ON DELETE CASCADE, PRIMARY KEY(collection_id,position));
CREATE TABLE test_audioarchive_collection_state (user_id INTEGER PRIMARY KEY, revision INTEGER);
CREATE TABLE test_audioarchive_user_profiles (user_id INTEGER PRIMARY KEY, collection_storage_preference TEXT, default_soundboard_id INTEGER DEFAULT 0, created TEXT, modified TEXT);");
$options = new Joomla\Registry\Registry(['collections_storage'=>'server']);
$owner = new Joomla\CMS\User\User(7);
$other = new Joomla\CMS\User\User(8);
$service = new Punga\Component\Audioarchive\Site\Service\CollectionService($db, $options, $owner);
$foreign = new Punga\Component\Audioarchive\Site\Service\CollectionService($db, $options, $other);
$guestService = new Punga\Component\Audioarchive\Site\Service\CollectionService($db, $options, new Joomla\CMS\User\User());
$id = 'playlist-1234567890';
$boardId = 'soundboard-1234567890';
$playlist = ['id'=>$id,'name'=>'Owned','items'=>[['uuid'=>'public-clip'],['uuid'=>'private-clip']]];
check($service->state()['revision'] === 0, 'empty account is read-only/lazy');
$result = $service->playlists([$playlist], 0);
check($result['revision'] === 1 && count($result['state']['playlists']) === 1, 'create playlist');
check($result['state']['playlists'][0]['items'][1]['title'] === 'Secret', 'owner resolves private title');
check($foreign->state()['playlists'] === [], 'other owner list isolation');
rejectContribution(fn() => $foreign->playlists([$playlist], 0), 'foreign UUID cannot overwrite');
rejectContribution(fn() => $service->playlists([], 0), 'stale tab cannot delete');
rejectContribution(fn() => $guestService->playlists([], 0), 'guest cannot mutate');
check($service->state()['revision'] === 1, 'failed operations leave revision unchanged');
$bad = ['id'=>'second-list-12345678','name'=>'Bad','items'=>[['uuid'=>'other-private']]];
rejectContribution(fn() => $service->playlists([$playlist,$bad],1), 'atomic batch rejects foreign private clip');
check(count($service->state()['playlists']) === 1, 'failed batch rolled back');
$result = $service->board(['id'=>$boardId,'name'=>'Board','items'=>[null,['uuid'=>'public-clip']]],1);
check($result['state']['boards'][0]['items'][0] === null, 'empty pad positions preserved');
$result = $service->defaultBoard($boardId,2);
check($result['state']['defaultBoard'] === $boardId, 'default board owned and persisted');
rejectContribution(fn() => $foreign->defaultBoard($boardId,0), 'foreign default rejected');
$result = $service->share($id,false,3);
$token = $result['token'];
check(strlen($token) === 48, 'opaque share token');
$shared = $guestService->shared($token);
check(count($shared['items']) === 1 && $shared['unavailable'] === 1, 'shared clips independently authorized');
check(!str_contains(json_encode($shared), 'Secret') && !str_contains(json_encode($shared), 'private-clip'), 'sharing omits private metadata and identity');
$result = $service->share($id,false,4);
check($result['token'] === $token, 'copying share again preserves existing links');
$result = $service->share($id,true,5);
rejectContribution(fn() => $guestService->shared($token), 'revocation invalidates token');
$result = $service->importCollection('playlist',['name'=>'Imported','items'=>[['uuid'=>'public-clip'],['uuid'=>'other-private']]],6);
check($result['skipped'] === 1 && count($result['state']['playlists']) === 1, 'incomplete import preserves browser collection without partial server copy');
$db->pdo->exec('UPDATE test_audioarchive_clips SET state=-2 WHERE id=1');
$result = $service->board(['id'=>$boardId,'name'=>'Renamed','items'=>[null,['uuid'=>'public-clip']]],7);
check($result['state']['boards'][0]['items'][1]['id'] === 0, 'retained inaccessible pad is opaque');
check($result['state']['boards'][0]['items'][1]['title'] === '', 'no stale title for inaccessible clip');
$db->pdo->exec('UPDATE test_audioarchive_clips SET state=1 WHERE id=1');
$export = Punga\Component\Audioarchive\Administrator\Service\CollectionArchiveService::export($db);
check($export[0]['username'] === 'owner' && !isset($export[0]['user_id'], $export[0]['share_token']), 'portable owner and no live sharing secret');
check($export[0]['items'][0]['uuid'] === 'public-clip', 'portable clip identity');
$restoreResult = ['warnings' => []];
$db->pdo->exec("UPDATE test_audioarchive_collections SET share_token='old-token'");
Punga\Component\Audioarchive\Administrator\Service\CollectionArchiveService::restore($db, $export, ['owner'=>7], ['public-clip'=>1,'private-clip'=>2], 'overwrite', true, $restoreResult);
check($restoreResult['warnings'] === [], 'portable collection restore resolves owner and clips');
check((int) $db->setQuery('SELECT COUNT(*) FROM test_audioarchive_collections WHERE share_token IS NOT NULL')->loadResult() === 0, 'restore revokes previous share secrets');
check($service->state()['defaultBoard'] === $boardId, 'restore preserves default board preference');
check($service->state()['revision'] === 10, 'restore invalidates stale tabs');
$db->pdo->exec('DELETE FROM test_audioarchive_clips WHERE id=1');
check((int) $db->setQuery('SELECT COUNT(*) FROM test_audioarchive_collection_items WHERE clip_id=1')->loadResult() === 0, 'permanent clip deletion cascades membership');
$result = $service->deleteBoard($boardId,10);
check($result['state']['boards'] === [] && $result['state']['defaultBoard'] === '', 'deleted default gracefully falls back');
$import = ['name'=>'Complete','items'=>[['uuid'=>'private-clip']]];
$first = $service->importCollection('playlist', $import, $service->state()['revision']);
$retry = $service->importCollection('playlist', $import, $service->state()['revision']);
check($first['id'] === $retry['id'] && count($retry['state']['playlists']) === 2, 'complete import retry cannot duplicate collections');
$db->setQuery('UPDATE test_audioarchive_collections SET title=' . $db->quote('Edited after import') . ' WHERE uuid=' . $db->quote($first['id']))->execute();
rejectContribution(fn() => $service->importCollection('playlist', $import, $service->state()['revision']), 'retry cannot confirm cleanup after destination was edited');
$options->data['collections_storage'] = 'browser';
rejectContribution(fn() => $service->playlists([],9), 'browser-only policy blocks server mutation');
$options->data['collections_guest_browser'] = 0;
check($guestService->state()['backend'] === 'disabled', 'guest persistence policy');
check($db->lockBalance === 0, 'all mutation and snapshot locks released');
echo ($checks - $startChecks) . " collection assertions passed.\n";
