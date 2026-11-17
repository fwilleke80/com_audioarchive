<?php
/** @brief Test the production form-save transaction wrapper with injected clip-save outcomes. */
namespace Joomla\CMS\MVC\Model
{
    class AdminModel {}
}
namespace Joomla\CMS\Component
{
    class ComponentHelper
    {
        /** @brief Return fixture options. */
        public static function getParams(string $name): object
        {
            return $GLOBALS['options'];
        }
    }
}
namespace Punga\Component\Audioarchive\Administrator\Service
{
    /** @brief No audio replacement is required by these cover transaction tests. */
    class AudioUploadService
    {
        /** @brief Preserve the production constructor contract. */
        public function __construct(...$args) {}
        /** @brief Fixture clips have no original file. */
        public function getOriginalFile(int $id): ?object
        {
            return null;
        }
    }
}
namespace
{
    require __DIR__ . '/covers.php';
    require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Model/ClipModel.php';
    /** @brief Inject only the core clip write; use the actual production cover wrapper. */
    class SaveModel extends \Punga\Component\Audioarchive\Administrator\Model\ClipModel
    {
        public bool $fail = false;
        public int $id = 1;
        public string $error = '';
        /** @brief Shared transactional fixture database. */
        public function getDatabase() { return $GLOBALS['coverDb']; }
        /** @brief Authorised fixture editor. */
        public function getCurrentUser() { return $GLOBALS['editor']; }
        /** @brief Read clip state for ownership checks. */
        public function getItem($pk = null) { return $this->getDatabase()->setQuery('SELECT * FROM #__audioarchive_clips WHERE id=' . (int) $pk)->loadObject(); }
        /** @brief Fixture state key prefix. */
        public function getName() { return 'clip'; }
        /** @brief Return the saved entity ID. */
        public function getState($key = null, $default = null) { return $this->id; }
        /** @brief Preserve model errors. */
        public function setError($error) { $this->error = (string) $error; }
        /** @brief Read model error. */
        public function getError($i = null, $toString = true) { return $this->error; }
        /** @brief Simulate successful or failed metadata writes inside the real wrapper transaction. */
        protected function saveClipData($data)
        {
            $this->id = (int) ($data['id'] ?? 0);
            if (!$this->id)
            {
                $this->id = 1000;
                $this->getDatabase()->pdo->exec("INSERT INTO test_audioarchive_clips(id,uuid,title,created_by,visibility_mode) VALUES(1000,'new-cover-clip','New clip',7,'normal')");
            }
            else
            {
                $this->getDatabase()->pdo->exec("UPDATE test_audioarchive_clips SET title='Changed' WHERE id=" . $this->id);
            }
            if ($this->fail) $this->error = 'Injected save failure';
            return !$this->fail;
        }
    }
    $start = $checks;
    $input = new class
    {
        public object $files;
        public object $post;
        /** @brief Separate multipart and scalar request values. */
        public function __construct()
        {
            $this->files = new class
            {
                public array $value = [];
                public function get(...$args): array { return $this->value; }
            };
            $this->post = new class
            {
                public int $remove = 0;
                public function getInt(...$args): int { return $this->remove; }
            };
        }
    };
    $GLOBALS['testApplication'] = new class($input)
    {
        public function __construct(private object $input) {}
        public function getInput(): object { return $this->input; }
    };
    $GLOBALS['coverUploadFixture'] = tempnam(sys_get_temp_dir(), 'cover-save-');
    file_put_contents($GLOBALS['coverUploadFixture'], $png);
    $input->files->value = ['error' => 0, 'tmp_name' => $GLOBALS['coverUploadFixture']];
    $model = new SaveModel();
    $model->fail = true;
    check(!$model->save(['id' => 1]), 'failed clip save rejected');
    check($coverService->manage('clip', '1', 'status')['cover'] === null, 'failed save creates no cover');
    check($coverDb->pdo->query('SELECT title FROM test_audioarchive_clips WHERE id=1')->fetchColumn() === 'Cover clip', 'failed save rolls metadata back');
    $model->fail = false;
    check($model->save(['id' => 1]), 'save commits cover and clip');
    check($coverService->manage('clip', '1', 'status')['cover']['width'] === 1, 'cover committed');
    file_put_contents($GLOBALS['coverUploadFixture'], 'invalid');
    check(!$model->save(['id' => 1]), 'invalid replacement rejected');
    check($coverService->manage('clip', '1', 'status')['cover']['width'] === 1, 'invalid replacement preserves original');
    $input->files->value = [];
    $input->post->remove = 1;
    $model->fail = true;
    check(!$model->save(['id' => 1]), 'failed save with removal');
    check($coverService->manage('clip', '1', 'status')['cover']['width'] === 1, 'removal rolled back');
    $model->fail = false;
    check($model->save(['id' => 1]), 'successful removal save');
    check($coverService->manage('clip', '1', 'status')['cover'] === null, 'removal committed');
    $input->post->remove = 0;
    file_put_contents($GLOBALS['coverUploadFixture'], $png);
    $input->files->value = ['error' => 0, 'tmp_name' => $GLOBALS['coverUploadFixture']];
    check($model->save(['id' => 0]), 'new clip saves with cover');
    check($coverService->manage('clip', '1000', 'status')['cover']['width'] === 1, 'cover associated with new ID');
    $options->data['quota_storage_enabled'] = 1;
    $options->data['quota_storage_mode'] = 'custom';
    $options->data['quota_storage_default_mb'] = 0;
    $coverDb->pdo->exec("UPDATE test_audioarchive_clips SET title='Before quota failure' WHERE id=1");
    check(!$model->save(['id' => 1]), 'cover quota failure rejects the whole save');
    check($coverService->manage('clip', '1', 'status')['cover'] === null, 'quota failure creates no cover');
    check($coverDb->pdo->query('SELECT title FROM test_audioarchive_clips WHERE id=1')->fetchColumn() === 'Before quota failure', 'quota failure rolls metadata back');
    unlink($GLOBALS['coverUploadFixture']);
    unset($GLOBALS['coverUploadFixture']);
    echo ($checks - $start) . " form-save transaction assertions passed.\n";
}
