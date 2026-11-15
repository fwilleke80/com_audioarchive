<?php
/** @brief Exercise ticket scoping, expiry, replay accounting and rollback against SQLite. */
namespace Joomla\Database
{
    interface DatabaseInterface {}
}
namespace Joomla\CMS
{
    class Factory
    {
        /** @brief Return fixture secret provider. */
        public static function getApplication(): object
        {
            return new class
            {
                /** @brief Supply a deterministic fixture secret. */
                public function get(string $key): string
                {
                    return 'download-test-secret';
                }
            };
        }
    }
}
namespace
{
    define('_JEXEC', 1);
    require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/DownloadTicketService.php';
    use Punga\Component\Audioarchive\Site\Service\DownloadTicketService as Tickets;
    /** @brief Minimal SQLite adapter executing the production SQL. */
    class Database implements \Joomla\Database\DatabaseInterface
    {
        public \PDO $pdo;
        private string $query;
        private int $affected = 0;
        /** @brief Create isolated tables. */
        public function __construct()
        {
            $this->pdo = new \PDO('sqlite::memory:');
            $this->pdo->exec('CREATE TABLE audioarchive_download_tickets (token_hash TEXT PRIMARY KEY, clip_id INTEGER, user_id INTEGER, context TEXT, expires_at INTEGER, counted INTEGER)');
            $this->pdo->exec('CREATE TABLE audioarchive_clips (id INTEGER PRIMARY KEY, download_count INTEGER DEFAULT 0)');
            $this->pdo->exec('INSERT INTO audioarchive_clips(id) VALUES(7)');
        }
        /** @brief Quote SQL identifiers. */
        public function quoteName(string $name): string
        {
            return '"' . str_replace('#__', '', $name) . '"';
        }
        /** @brief Quote SQL values. */
        public function quote(string $value): string
        {
            return $this->pdo->quote($value);
        }
        /** @brief Select query. */
        public function setQuery(string $query): self
        {
            $this->query = $query;
            return $this;
        }
        /** @brief Execute mutation. */
        public function execute(): void
        {
            $this->affected = $this->pdo->exec($this->query);
        }
        /** @brief Fetch scalar. */
        public function loadResult(): mixed
        {
            return $this->pdo->query($this->query)->fetchColumn();
        }
        /** @brief Read affected rows. */
        public function getAffectedRows(): int
        {
            return $this->affected;
        }
        /** @brief Insert fixture-compatible object. */
        public function insertObject(string $table, object $row): void
        {
            $values = (array) $row;
            $this->pdo->prepare('INSERT INTO ' . $this->quoteName($table) . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')')->execute(array_values($values));
        }
        /** @brief Begin atomic accounting. */
        public function transactionStart(): void
        {
            $this->pdo->beginTransaction();
        }
        /** @brief Commit atomic accounting. */
        public function transactionCommit(): void
        {
            $this->pdo->commit();
        }
        /** @brief Roll back atomic accounting. */
        public function transactionRollback(): void
        {
            $this->pdo->rollBack();
        }
    }
    /** @brief Fail visibly on regression. */
    function check(bool $value, string $message): void
    {
        if (!$value)
        {
            throw new \RuntimeException($message);
        }
    }
    $proof = Tickets::proof(7, 3, 'site');
    check(Tickets::validProof($proof, 7, 3, 'site'), 'valid proof');
    check(!Tickets::validProof($proof, 8, 3, 'site'), 'clip binding');
    check(!Tickets::validProof($proof, 7, 4, 'site'), 'user binding');
    check(!Tickets::validProof($proof, 7, 3, 'embed'), 'context binding');
    check(!Tickets::validProof(Tickets::proof(7, 3, 'site', time() - 1), 7, 3, 'site'), 'expired proof');
    $db = new Database();
    $tickets = new Tickets($db);
    $token = $tickets->issue(7, 3, 'site');
    check($tickets->valid($token, 7, 3, 'site'), 'issued ticket');
    check(!$tickets->valid($token, 7, 0, 'embed'), 'ticket binding');
    check(!$tickets->valid('invalid', 7, 3, 'site'), 'malformed ticket');
    check($tickets->countOnce($token, 7, 3, 'site'), 'first request counted');
    check(!$tickets->countOnce($token, 7, 3, 'site'), 'retry not counted');
    check($tickets->valid($token, 7, 3, 'site'), 'retry remains valid');
    check((int) $db->pdo->query('SELECT download_count FROM audioarchive_clips')->fetchColumn() === 1, 'one counter increment');
    $db->pdo->exec('UPDATE audioarchive_download_tickets SET expires_at = 1');
    check(!$tickets->valid($token, 7, 3, 'site'), 'expired ticket');
    $orphan = $tickets->issue(99, 0, 'embed');
    try
    {
        $tickets->countOnce($orphan, 99, 0, 'embed');
        throw new \LogicException('Expected missing clip failure');
    }
    catch (\RuntimeException $expected)
    {
        check((int) $db->pdo->query('SELECT counted FROM audioarchive_download_tickets')->fetchColumn() === 0, 'rollback preserves uncounted ticket');
    }
    echo "Download ticket tests passed.\n";
}
