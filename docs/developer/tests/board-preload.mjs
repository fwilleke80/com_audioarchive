/** @brief Verify predecode admission, cache lifetime, long-clip fallback and cancellation. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const source=await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/board-preload.js',import.meta.url),'utf8');
const {BoardPreloader}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
let downloads=0;
let decoding=0;
let peak=0;
let releaseDecode=null;
globalThis.Audio=class
{
 load() {}
 removeAttribute() {}
};
globalThis.fetch=async () =>
{
 downloads++;
 let sent=false;
 return {ok:true,headers:{get() {return '1';}},body:{getReader() {return {async read() {if(sent) return {done:true};sent=true;return {done:false,value:new Uint8Array(1)};}};}}};
};
const loading=[];
const context={sampleRate:48000,async decodeAudioData()
{
 decoding++;peak=Math.max(peak,decoding);
 if(releaseDecode) await new Promise(resolve=>{releaseDecode=resolve;});
 decoding--;
 return {length:48000,numberOfChannels:2};
}};
const cache=new BoardPreloader({context:()=>context,url:id=>String(id),loading:(...args)=>loading.push(args)});
const metadata=new Map([[1,{duration_ms:1000,channels:2,sample_rate:48000}],[2,{duration_ms:300000,channels:2}],[3,{duration_ms:1000,channels:2}]]);
assert.equal(cache.estimate(metadata.get(2),48000),0,'long clips rejected before fetch');
assert.equal(cache.estimate({},48000),0,'unknown dimensions stream');
await cache.prepare([{id:1},{id:2}],metadata);
assert.equal(downloads,1);
assert.ok(cache.get(1));
assert.equal(cache.streams.size,1);
assert.ok(cache.takeStream(2));
assert.equal(cache.streams.size,0);
const unpin=cache.pin(1);
await cache.prepare([],metadata);
assert.ok(cache.get(1),'playing buffer retained across board changes');
unpin();
assert.equal(cache.get(1),null);
assert.equal(cache.bytes,0);
cache.budget=1;
await cache.prepare([{id:1},{id:2},{id:3}],metadata);
assert.equal(downloads,1,'budget admission precedes downloading');
assert.equal(cache.streams.size,2,'native preparation limited to two elements');
cache.budget=64*1024*1024;
releaseDecode=true;
const old=cache.prepare([{id:1}],metadata);
while(typeof releaseDecode!=='function') await new Promise(resolve=>setTimeout(resolve,0));
const next=cache.prepare([{id:3}],metadata);
const release=releaseDecode;releaseDecode=null;release();
await Promise.all([old,next]);
assert.equal(cache.get(1),null,'obsolete decode not cached');
assert.ok(cache.get(3));
assert.equal(peak,1,'decode concurrency stays one across switches');
assert.equal(loading.at(-1)[1],false);
console.log('Preload size admission, budget, pinning, streaming limit and stale-decode checks passed.');
