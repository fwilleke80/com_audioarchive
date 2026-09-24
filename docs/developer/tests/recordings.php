<?php
/** @brief Verify recording ownership, conflict safety, validation, deletion and portable snapshots. */
require __DIR__ . '/collections.php';
require __DIR__ . '/../../../pkg_audioarchive/com_audioarchive/administrator/src/Service/RecordingArchiveService.php';
$startChecks = $checks;
$recording = ['id'=>'recording-one','format'=>'punga-audioarchive-soundboard-recording','version'=>1,'name'=>'Performance','durationMs'=>1000,'board'=>[['id'=>2,'uuid'=>'private-clip','title'=>'Secret']], 'events'=>[['t'=>0,'type'=>'pad','pad'=>0]], 'layerBoards'=>[1=>[['id'=>2,'uuid'=>'private-clip']]]];
$revision = $service->state()['revision'];
$saved = $service->recordings([$recording], $revision);
check($saved['state']['recordings'][0]['events'] === $recording['events'], 'performance data round trip');
check($foreign->state()['recordings'] === [] && $guestService->state()['recordings'] === [], 'recordings never leak to other identities');
rejectContribution(fn() => $guestService->recordings([$recording], 0), 'guest cannot persist account recording');
rejectContribution(fn() => $service->recordings([], $revision), 'stale tab cannot delete newer recording');
rejectContribution(fn() => $service->recordings([$recording,$recording], $saved['revision']), 'duplicate identity rejected');
$invalid = $recording;
$invalid['events'] = array_fill(0,20001,[]);
rejectContribution(fn() => $service->recordings([$invalid], $saved['revision']), 'event bound enforced');
$portable = Punga\Component\Audioarchive\Administrator\Service\RecordingArchiveService::export($db);
check(!isset($portable[0]['recordings'][0]['board'][0]['id']), 'portable snapshot removes local clip ID');
$result = ['warnings'=>[]];
Punga\Component\Audioarchive\Administrator\Service\RecordingArchiveService::restore($db,$portable,['owner'=>7],['private-clip'=>222],'overwrite',$result);
$state = $service->state();
check($state['recordings'][0]['board'][0]['id'] === 222 && $state['recordings'][0]['layerBoards'][1][0]['id'] === 222, 'restore remaps base and overdub boards');
check($state['revision'] > $saved['revision'], 'restore invalidates stale tabs');
check($service->recordings([], $state['revision'])['state']['recordings'] === [], 'owner can delete recordings');
check($db->lockBalance === 0, 'locks released after rejected writes');
echo ($checks-$startChecks) . " recording assertions passed.\n";
