/** @brief Exercise real recording playback routing and layered JSON normalization without changing live pads. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/social.js', import.meta.url),'utf8');
const voices = [];
const live = [{id:11,uuid:'live'}];
const context = {boardPreloader:null,board:live, recordingPlayback:{board:[{id:22}],layerBoards:{1:[{id:33}]},polyphonyByLayer:new Map()}, streamTemplate:'/clip/987654321',
 soundboardPolyphonic:true, activeVoices:new Set(), voicesByPad:new Map(), pads:[{classList:{add(){},remove(){}}}],
 ensureClipMetadata:()=>Promise.resolve(), normalizationGains:new Map(), prepareNormalization:()=>Promise.resolve(), playbackFor:(audio)=>audio,
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

/** @brief Ready preloaded pads use the shared voice path at unshifted pitch. */
const buffered=[];
context.boardPreloader={get:()=>({}),takeStream:()=>null};
context.normalizationGains.set(11,2);
context.setSamplerAudioSessionActive=()=>{};
context.unlockSamplerAudio=()=>{};
context.createAudioContext=()=>({state:'running'});
context.SAMPLER_ROOT_MIDI_NOTE=60;
context.startSamplerVoice=(...args)=>buffered.push(args);
vm.runInContext("playRecordingPad(0,'pad',false);",context);
assert.equal(buffered.length,1);
assert.equal(buffered[0][2].id,11);
assert.equal(buffered[0][4],60);
assert.equal(buffered[0][5],127);
assert.equal(buffered[0][7],false,'normal pad does not require chromatic selection');
console.log('Ready buffer pad routing retains root pitch and full velocity.');

/** @brief Saved polyphony and mixed event types do not follow the current live mode or permission. */
const stopped = [];
const pitched = [];
context.polyphonic = false;
context.soundboardPolyphonic = false;
context.stopRecordingVoices = (layer) => stopped.push(layer);
context.playSamplerNoteFromPad = (...args) => pitched.push(args);
a = source.indexOf('\tconst applyRecordingPlaybackEvent =');
b = source.indexOf('\n\tconst runRecordingPlaybackFrame', a);
vm.runInContext(source.slice(a, b) + '\nglobalThis.applyEvent = applyRecordingPlaybackEvent;', context);
context.recording = {initialState:{polyphony:true},events:[{type:'polyphony',t:0,enabled:false,layer:1}]};
a = source.indexOf('\t\tconst initialPlaybackPolyphony =');
b = source.indexOf('\n\n\t\trecordingPlayback =', a);
vm.runInContext(source.slice(a, b) + '\nrecordingPlayback.polyphonyByLayer = initialPlaybackPolyphony;', context);
assert.equal(context.recordingPlayback.polyphonyByLayer.get(0), true, 'base saved polyphony survives disabled live permission');
assert.equal(context.recordingPlayback.polyphonyByLayer.get(1), false, 'overdub retains recorded monophony');
context.boardPreloader = null;
vm.runInContext("applyEvent({type:'pad',pad:0,layer:0}); applyEvent({type:'pad',pad:0,layer:0});", context);
assert.equal(stopped.length, 0, 'recorded polyphonic pads do not cut each other off');
vm.runInContext("applyEvent({type:'note',pad:0,note:67,velocity:91,layer:1});", context);
assert.deepEqual(pitched[0], [0,67,91,'recording',1], 'chromatic overdub preserves pitch and velocity over pads');
vm.runInContext("applyEvent({type:'polyphony',enabled:false,layer:0});", context);
assert.deepEqual(stopped, [0], 'recorded monophony affects only its own layer');
vm.runInContext("applyEvent({type:'polyphony',enabled:true,layer:0}); applyEvent({type:'polyphony',enabled:true,layer:1});", context);
assert.equal(context.recordingPlayback.polyphonyByLayer.get(0), true);
assert.equal(context.recordingPlayback.polyphonyByLayer.get(1), true);
vm.runInContext("applyEvent({type:'note',pad:0,note:48,velocity:127,layer:0}); applyEvent({type:'pad',pad:0,layer:1});", context);
assert.deepEqual(pitched[1], [0,48,127,'recording',0], 'base chromatic notes retain absolute pitch');
assert.equal(voices.at(-1), '/clip/33', 'pad overdub over chromatic base uses its own clip');
assert.equal(context.board, live, 'mixed playback leaves live board intact');
console.log('Saved polyphony survives disabled live permission; mixed-mode layers retain pitches and mappings.');
