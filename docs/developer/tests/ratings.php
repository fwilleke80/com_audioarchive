<?php
/** @brief Verify account migration, conflict resolution, ownership and permission policies. */
require __DIR__ . '/collections.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/RatingService.php';
class RatingDatabase extends CollectionDatabase
{
	/** @brief Translate only the MySQL duplicate-ignore spelling for SQLite. */
	public function execute(): void
	{
		$this->sql = str_replace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $this->sql);
		parent::execute();
	}
}
class TestRatings extends Punga\Component\Audioarchive\Site\Service\RatingService
{
	/** @brief Keep aggregate-query fixture independent of Joomla parameter binding. */
	public function getCounts(int $id): array
	{
		return ['up'=>0, 'down'=>0, 'score'=>0];
	}
}
$startChecks = $checks;
$db = new RatingDatabase();
$db->pdo->exec('CREATE TABLE test_audioarchive_ratings (clip_id INTEGER, voter_hash TEXT, vote INTEGER, created TEXT, modified TEXT, UNIQUE(clip_id,voter_hash))');
$client = str_repeat('a',64);
$browser = hash_hmac('sha256','browser:'.$client,'test-secret');
$account = hash_hmac('sha256','user:7','test-secret');
$db->pdo->exec("INSERT INTO test_audioarchive_ratings VALUES (1,'$browser',1,'old','old'),(2,'$browser',-1,'old','old'),(1,'$account',-1,'new','new')");
$owner->guest = false;
$options = new Joomla\Registry\Registry(['rating_permission'=>'registered']);
$ratings = new TestRatings($db,$options,$owner);
$state = $ratings->synchronise($client,[1,2]);
check($state['account'] && $state['imported'], 'authenticated migration acknowledged');
check($state['ratings']->{1}['vote'] === -1 && $state['ratings']->{2}['vote'] === -1, 'account wins conflicts; missing vote imported');
check((int)$db->setQuery('SELECT COUNT(*) FROM test_audioarchive_ratings')->loadResult() === 2, 'migration removes duplicate guest votes');
check($ratings->synchronise($client,[1,2])['ratings'] == $state['ratings'], 'migration retry is idempotent');
check($ratings->synchronise(str_repeat('b',64),[1,2])['ratings'] == $state['ratings'], 'second browser receives same account ratings');
$other->guest = false;
$foreignRatings = new TestRatings($db,$options,$other);
check($foreignRatings->synchronise($client,[1,2])['ratings']->{1}['vote'] === 0, 'consumed browser votes cannot transfer to another account');
$guest = new Joomla\CMS\User\User();
$guest->guest = true;
$guestRatings = new TestRatings($db,$options,$guest);
check(!$guestRatings->canVote(), 'registered-only excludes guest');
$options->data['rating_permission'] = 'none';
check(!$ratings->canVote() && !$guestRatings->canVote(), 'nobody excludes all identities');
$db->pdo->exec("INSERT INTO test_audioarchive_ratings VALUES (3,'$browser',1,'old','old')");
check(!$ratings->synchronise($client,[3])['imported'], 'disabled ratings cannot migrate');
check((int)$db->setQuery("SELECT COUNT(*) FROM test_audioarchive_ratings WHERE voter_hash='$browser'")->loadResult() === 1, 'disabled policy preserves unclaimed guest vote');
$options->data['rating_permission'] = 'all';
check($ratings->canVote() && $guestRatings->canVote(), 'all enables both identities');
$options->data['enable_ratings'] = 0;
check(!$ratings->canVote() && !$guestRatings->canVote(), 'master disable respected');
echo ($checks-$startChecks) . " rating assertions passed.\n";
