<?php
/** @brief Exercise private cover policy, image validation, removal and portable backup round trips. */
require __DIR__ . '/cover-upload-fixture.php';
require __DIR__ . '/collections.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/CoverService.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/CoverArchiveService.php';

use Punga\Component\Audioarchive\Administrator\Service\CoverService;
use Punga\Component\Audioarchive\Administrator\Service\CoverArchiveService;

/** @brief Execute production SQL with SQLite's transaction equivalent of row locks. */
class CoverDatabase extends CollectionDatabase
{
    /** @brief SQLite fixture serialises the entire write transaction. */
    public function setQuery(string|Query $query): self
    {
        parent::setQuery(str_replace(' FOR UPDATE', '', (string) $query));
        return $this;
    }
    /** @brief Load safe status metadata. */
    public function loadAssoc(): array|false
    {
        return $this->pdo->query($this->sql)->fetch(PDO::FETCH_ASSOC);
    }
}
$start = $checks;
$coverDb = new CoverDatabase();
$coverDb->pdo = $db->pdo;
$coverDb->pdo->exec('DROP TABLE test_audioarchive_covers');
$coverDb->pdo->exec('CREATE TABLE test_audioarchive_covers (entity_type TEXT, entity_id INTEGER, mime_type TEXT, width INTEGER, height INTEGER, file_size INTEGER, image_data TEXT, modified TEXT, PRIMARY KEY(entity_type,entity_id))');
$coverDb->pdo->exec("INSERT INTO test_audioarchive_clips(id,uuid,title,created_by,visibility_mode,state,access,catid) VALUES(1,'cover-clip','Cover clip',7,'normal',1,1,2)");
$coverDb->pdo->exec('CREATE TABLE test_audioarchive_group_quotas (group_id INTEGER, storage_quota_bytes INTEGER, clip_quota INTEGER); CREATE TABLE test_audioarchive_files (clip_id INTEGER, file_role TEXT, file_size INTEGER)');
$coverDb->pdo->exec('ALTER TABLE test_audioarchive_user_profiles ADD COLUMN storage_quota_override_bytes INTEGER; ALTER TABLE test_audioarchive_user_profiles ADD COLUMN clip_quota_override INTEGER');
$editor = new Joomla\CMS\User\User(7, ['core.edit.own' => true]);
$coverService = new CoverService($coverDb, $options, $editor);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$info = CoverService::inspect($png);
check($info['width'] === 1 && $info['mime_type'] === 'image/png', 'real PNG accepted');
foreach (['', '<svg xmlns="http://www.w3.org/2000/svg"/>', '<?php echo 1;', str_repeat('a', CoverService::MAX_BYTES + 1)] as $invalid)
{
    rejectContribution(fn() => CoverService::inspect($invalid), 'invalid image rejected');
}
$largeHeader = substr_replace($png, pack('N', 5000), 16, 4);
rejectContribution(fn() => CoverService::inspect($largeHeader), 'excessive image dimensions rejected');
rejectContribution(fn() => (new CoverService($coverDb, $options, new Joomla\CMS\User\User()))->manage('clip', '1', 'status'), 'guest cannot read cover status');
rejectContribution(fn() => (new CoverService($coverDb, $options, new Joomla\CMS\User\User(8)))->manage('clip', '1', 'remove'), 'foreign user cannot remove clip cover');
rejectContribution(fn() => $coverService->manage('clip', '1', 'upload', ['error' => 0, 'tmp_name' => __FILE__]), 'local path is not an HTTP upload');
$GLOBALS['coverUploadFixture'] = tempnam(sys_get_temp_dir(), 'cover-test-');
file_put_contents($GLOBALS['coverUploadFixture'], $png);
$file = ['tmp_name' => $GLOBALS['coverUploadFixture'], 'error' => UPLOAD_ERR_OK, 'name' => 'arbitrary.exe', 'type' => 'text/plain'];
$uploaded = $coverService->manage('clip', '1', 'upload', $file);
check($uploaded['cover']['mime_type'] === 'image/png', 'upload validates bytes instead of supplied name/type');
$coverService->manage('clip', '1', 'upload', $file);
check((int) $coverDb->pdo->query('SELECT COUNT(*) FROM test_audioarchive_covers')->fetchColumn() === 1, 'replacement retains only one image');
file_put_contents($GLOBALS['coverUploadFixture'], 'invalid');
rejectContribution(fn() => $coverService->manage('clip', '1', 'upload', $file), 'invalid replacement rejected');
check($coverService->manage('clip', '1', 'status')['cover']['width'] === 1, 'failed replacement preserves cover');
unlink($GLOBALS['coverUploadFixture']);
unset($GLOBALS['coverUploadFixture']);
$clip = $coverDb->pdo->query('SELECT * FROM test_audioarchive_clips WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$rows = [['entity_type' => 'clip', 'uuid' => $clip['uuid'], 'image_data' => base64_encode($png)]];
CoverArchiveService::restore($coverDb, $rows, [$clip['uuid'] => 1], [$clip['uuid'] => true], []);
check($coverService->manage('clip', '1', 'status')['cover']['width'] === 1, 'restored cover status');
check(!isset($coverService->manage('clip', '1', 'status')['cover']['image_data']), 'status never exposes image bytes');
$export = CoverArchiveService::export($coverDb);
check($export[0]['image_data'] === base64_encode($png) && !isset($export[0]['entity_id']), 'portable backup has exact bytes without local ID');
$coverService->manage('clip', '1', 'remove');
check($coverService->manage('clip', '1', 'status')['cover'] === null, 'removal deletes stored cover');
CoverArchiveService::restore($coverDb, $rows, [$clip['uuid'] => 1], [], []);
check($coverService->manage('clip', '1', 'status')['cover'] === null, 'skip policy preserves existing target');
$playlistRow = (object) ['uuid' => 'cover-playlist-12345678', 'user_id' => 7, 'kind' => 'playlist', 'title' => 'Cover test', 'pad_count' => 0];
$coverDb->insertObject('#__audioarchive_collections', $playlistRow, 'id');
rejectContribution(fn() => (new CoverService($coverDb, $options, new Joomla\CMS\User\User(8)))->manage('playlist', $playlistRow->uuid, 'status'), 'playlist cover owner isolation');
$rows = [['entity_type' => 'playlist', 'uuid' => $playlistRow->uuid, 'image_data' => base64_encode($png)]];
CoverArchiveService::restore($coverDb, $rows, [], [], [(int) $playlistRow->id]);
check($coverService->manage('playlist', $playlistRow->uuid, 'status')['cover']['file_size'] === strlen($png), 'playlist restore and status');
$coverService->manage('playlist', $playlistRow->uuid, 'remove');
check($coverService->manage('playlist', $playlistRow->uuid, 'status')['cover'] === null, 'playlist removal');
echo ($checks - $start) . " cover assertions passed.\n";
