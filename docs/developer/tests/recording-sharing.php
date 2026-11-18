<?php
/** @brief Execute recording share ownership, revocation and public projection against real SQLite queries. */
require __DIR__ . '/collections.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/site/src/Service/RecordingShareService.php';
$startChecks = $checks;
$db->pdo->exec("INSERT INTO test_audioarchive_clips VALUES (1,'public-clip','Public',7,'normal',1,1,2,NULL,NULL)");
$recording = ['id' => 'song-one', 'format' => 'punga-audioarchive-soundboard-recording', 'version' => 1, 'name' => 'First mix', 'durationMs' => 2000,
    'board' => [['id' => 1, 'uuid' => 'public-clip', 'title' => 'Forged title'], ['id' => 2, 'uuid' => 'private-clip', 'title' => 'Secret']],
    'layerBoards' => [1 => [['id' => 1, 'uuid' => 'public-clip'], ['id' => 9999, 'uuid' => 'deleted-clip', 'title' => 'Deleted title']]],
    'initialState' => ['polyphony' => true, 'mode' => 'chromatic', 'secret' => 'never export'],
    'events' => [['type' => 'pad', 't' => 0, 'pad' => 0], ['type' => 'pad', 't' => 10, 'pad' => 1], ['type' => 'polyphony', 't' => 0, 'layer' => 1, 'enabled' => false], ['type' => 'note', 't' => 100, 'pad' => 0, 'layer' => 1, 'note' => 67, 'velocity' => 91, 'duration' => 250, 'secret' => 'never export'], ['type' => 'note', 't' => 150, 'pad' => 1, 'layer' => 1]], 'ownerSecret' => 'never export'];
$revision = $service->state()['revision'];
$saved = $service->recordings([$recording], $revision);
rejectContribution(fn() => $guestService->shareRecording('song-one', false, 0), 'guest cannot publish');
rejectContribution(fn() => $foreign->shareRecording('song-one', false, $foreign->state()['revision']), 'foreign owner cannot publish');
rejectContribution(fn() => $service->shareRecording('browser-only', false, $saved['revision']), 'unpersisted recording cannot publish');
$share = $service->shareRecording('song-one', false, $saved['revision']);
$token = $share['token'];
check(strlen($token) === 48 && ctype_xdigit($token), 'short opaque token');
check($share['state']['recordingShares'] === ['song-one'], 'owned state contains shared ids');
rejectContribution(fn() => $service->shareRecording('song-one', true, $saved['revision']), 'stale revision cannot revoke');
$public = $guestService->sharedRecording($token);
$projection = $public['recording'];
check($projection['board'][0]['title'] === 'Public', 'title resolves from current database');
check($projection['board'][1] === null, 'private slot remains empty');
check($public['unavailable'] === 2, 'private and deleted slots counted');
$encoded = json_encode($public);
foreach (['Secret','private-clip','deleted-clip','Deleted title','never export','ownerSecret','Forged title'] as $secret)
{
    check(!str_contains($encoded, $secret), 'no leaked stored value: ' . $secret);
}
check(count($projection['events']) === 3, 'inaccessible sound events omitted, polyphony retained');
$note = array_values(array_filter($projection['events'], fn(array $event): bool => $event['type'] === 'note'))[0];
check($note['note'] === 67 && $note['velocity'] === 91 && $note['layer'] === 1, 'overdub pitch, velocity and layer retained');
check(json_encode($service->sharedRecording($token)) === json_encode($public), 'owner also receives guest-only projection');
$again = $service->shareRecording('song-one', false, $share['revision']);
check($again['token'] === $token, 'sharing again keeps link stable');
$recording['name'] = 'Updated mix';
$updated = $service->recordings([$recording], $again['revision']);
check($guestService->sharedRecording($token)['recording']['name'] === 'Updated mix', 'link follows latest saved version');
$db->pdo->exec('UPDATE test_audioarchive_clips SET state=0 WHERE id=1');
$hidden = $guestService->sharedRecording($token);
check($hidden['recording']['board'][0] === null && count($hidden['recording']['events']) === 1, 'unpublished clips disappear at next request');
$db->pdo->exec('UPDATE test_audioarchive_clips SET state=1, access=2 WHERE id=1');
check($guestService->sharedRecording($token)['recording']['board'][0] === null, 'registered-only clip omitted');
$db->pdo->exec('UPDATE test_audioarchive_clips SET access=1 WHERE id=1');
$db->pdo->exec('UPDATE test_categories SET published=0 WHERE id=2');
check($guestService->sharedRecording($token)['recording']['board'][0] === null, 'unpublished category respected');
$db->pdo->exec('UPDATE test_categories SET published=1 WHERE id=2');
$options->data['collections_sharing'] = 0;
rejectContribution(fn() => $guestService->sharedRecording($token), 'global off disables retrieval');
rejectContribution(fn() => $service->shareRecording('song-one', false, $updated['revision']), 'global off disables publishing');
$revoked = $service->shareRecording('song-one', true, $updated['revision']);
$options->data['collections_sharing'] = 1;
rejectContribution(fn() => $guestService->sharedRecording($token), 'revoked token remains unavailable after reenable');
$new = $service->shareRecording('song-one', false, $revoked['revision']);
check($new['token'] !== $token, 'new sharing gets a fresh token');
$deleted = $service->recordings([], $new['revision']);
rejectContribution(fn() => $guestService->sharedRecording($new['token']), 'deleted recording unavailable');
$restored = $service->recordings([$recording], $deleted['revision']);
rejectContribution(fn() => $guestService->sharedRecording($new['token']), 'reusing recording id cannot revive old link');
$share = $service->shareRecording('song-one', false, $restored['revision']);
$db->pdo->exec('DELETE FROM test_users WHERE id=7');
rejectContribution(fn() => $guestService->sharedRecording($share['token']), 'deleted user cannot retain public link');
rejectContribution(fn() => $guestService->sharedRecording('../bad'), 'malformed token rejected');
check($db->lockBalance === 0, 'owner locks released');
echo ($checks - $startChecks) . " recording sharing assertions passed.\n";
