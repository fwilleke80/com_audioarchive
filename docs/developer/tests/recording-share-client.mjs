/** @brief Check public bootstrap, short link creation, CSRF and account-only sharing in the actual adapter. */
import fs from 'node:fs';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/collections.js',import.meta.url),'utf8');
const token='a'.repeat(48);
const song={id:'shared-recording',name:'Public mix',events:[],board:[]};
let scenario='guest',fail=false,posts=[];
let state={backend:'browser',userId:0,revision:0,boards:[],playlists:[],recordings:[],token:'csrf',sharing:true};
globalThis.window={location:{origin:'https://example.test',href:'https://example.test/sub/index.php?aa_recording='+token},Joomla:{getOptions:()=>({root:'/sub'})},addEventListener(){},setTimeout:fn=>fn()};
globalThis.localStorage={getItem:()=>null,setItem(){throw new Error('Must not write guest collections');}};
globalThis.sessionStorage={getItem:()=>null,setItem(){}};
globalThis.document={querySelector:()=>null,body:{prepend(){}},createElement:()=>({setAttribute(){},classList:{toggle(){}}}),addEventListener(){}};
globalThis.fetch=async(url,options)=>
{
 const action=url.searchParams.get('task');
 if(action==='collection.state' || action==='collection.session')return {ok:true,json:async()=>({success:true,data:state})};
 if(action==='collection.sharedRecording')
 {
  assert.equal(options.cache,'no-store');
  if(fail)return {ok:false,json:async()=>({success:false,message:'Unavailable'})};
  assert.equal(url.searchParams.get('share'),token);
  return {ok:true,json:async()=>({success:true,data:{kind:'recording',unavailable:1,recording:song}})};
 }
 assert.equal(options.method,'POST');
 assert.equal(options.body.get('csrf'),'1');
 const payload=JSON.parse(options.body.get('payload'));
 posts.push({action,payload});
 state={...state,revision:state.revision+1,recordingShares:action.endsWith('revokeRecording')?[]:['song-one']};
 return {ok:true,json:async()=>({success:true,data:{state,token:action.endsWith('revokeRecording')?'':token}})};
};
async function adapter(suffix)
{
 return (await import('data:text/javascript;base64,'+Buffer.from(source+'\n// '+suffix).toString('base64'))).Collections;
}
let api=await adapter('guest');await api.ready();
assert.deepEqual(api.sharedRecording.recording,song);
await assert.rejects(()=>api.recordingShareUrl('song-one'));
assert.equal(posts.length,0);
fail=true;api=await adapter('revoked');await api.ready();
assert.equal(api.sharedRecording.error,true,'revoked links fail closed');
window.location.href='https://example.test/sub/index.php?aa_recording=';
api=await adapter('empty');await api.ready();assert.equal(api.sharedRecording.error,true);
fail=false;window.location.href='https://example.test/sub/index.php?view=soundboard';
state={...state,backend:'server',userId:7,recordings:[{id:'song-one',events:[{t:1}]}]};
api=await adapter('owner');await api.ready();
const url=await api.recordingShareUrl('song-one');
assert.ok(url.startsWith('https://example.test/sub/index.php?'));
assert.equal(new URL(url).searchParams.get('aa_recording'),token);
assert.ok(url.length<180);
assert.deepEqual(Object.keys(posts[0].payload).sort(),['id','revision','userId']);
assert.deepEqual(api.recordingShares,['song-one']);
assert.equal(await api.recordingShareUrl('song-one',true),'');
assert.deepEqual(api.recordingShares,[]);
console.log('Guest/public bootstrap, revoked and empty links, account-only CSRF sharing, subdirectory short URLs and revocation state passed.');
