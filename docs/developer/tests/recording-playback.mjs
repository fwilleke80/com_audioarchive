/** @brief Exercise real recording playback routing and layered JSON normalization without changing live pads. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/social.js', import.meta.url),'utf8');
const voices = [];
const live = [{id:11,uuid:'live'}];
const context = {board:live, recordingPlayback:{board:[{id:22}],layerBoards:{1:[{id:33}]},polyphonyByLayer:new Map()}, streamTemplate:'/clip/987654321',
 soundboardPolyphonic:true, activeVoices:new Set(), voicesByPad:new Map(), pads:[{classList:{add(){},remove(){}}}],
 normalizationGains:new Map(), prepareNormalization:()=>Promise.resolve(), playbackFor:(audio)=>audio,
 updatePadPlayingState(){}, recordPerformanceEvent(){}, countPlay(){}, stopRecordingVoices(){}, stopLiveVoices(){}, stopAllVoices(){}, cleanupVoice(){},
 Audio:class
 {
  constructor(url)
  {
   voices.push(url); this.dataset={};
  }
  addEventListener(){} play(){return Promise.resolve();}
 }, SOUNDBOARD_RECORDING_FORMAT:'punga-audioarchive-soundboard-recording', SOUNDBOARD_RECORDING_VERSION:1,
 SOUNDBOARD_RECORDING_MAX_EVENTS:20000,SOUNDBOARD_RECORDING_MAX_DURATION_MS:7200000,
 normaliseBoard:(value)=>value,createSoundboardRecordingId:()=> 'generated'};
vm.createContext(context);
let a=source.indexOf('\tconst play = (index');
let b=source.indexOf('\n\n\tconst getSelectedRecording',a);
vm.runInContext(source.slice(a,b)+'\nglobalThis.playRecordingPad = play;',context);
try
{
 vm.runInContext("playRecordingPad(0,'recording',false,0); playRecordingPad(0,'recording',false,1); playRecordingPad(0,'pad',false);",context);
}
catch(error)
{
 // Every dependency needed by production play() must be represented by this fixture.
 throw error;
}
assert.deepEqual(voices,['/clip/22','/clip/33','/clip/11']);
assert.equal(context.board,live);
a=source.indexOf('function normaliseSoundboardRecording(');
b=source.indexOf('\n/**',a);
vm.runInContext(source.slice(a,b),context);
const recording = {id:'one',format:context.SOUNDBOARD_RECORDING_FORMAT,version:1,name:'Layered',durationMs:1000,board:live,layerBoards:{1:[{id:33}]},events:[{type:'pad',t:0,pad:0,layer:1}]};
context.input=recording;
const normalized=vm.runInContext('normaliseSoundboardRecording(input,36)',context);
assert.equal(normalized.layerBoards[1][0].id,33);
assert.equal(normalized.events[0].layer,1);
console.log('Recording playback routes base, overdub and live clips independently; layered JSON survives normalization.');
