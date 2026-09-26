/** @brief Verify retries, concurrent request sharing, board switching and failure recovery. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/social.js',import.meta.url),'utf8');
let calls=0;
let fail=true;
const gains=new Map();
const routes=new Map();
const context={normalizationGains:gains,detailRoutes:routes,pendingDetailRequests:new Map(),unavailableDetailIds:new Set(),
 AbortController,URL,URLSearchParams,validGain:(v)=>v,applyDetailRoutes(){},routesUrl:'/routes',
 window:{location:{href:'https://example.test/'},setTimeout:(fn,ms)=>ms===10000 ? 0 : setTimeout(fn,0),clearTimeout},
 fetch:async(url,options)=>
 {
  calls++;
  if(fail) return {ok:false};
  const ids=new URLSearchParams(options.body).get('ids').split(',');
  return {ok:true,json:async()=>({success:true,items:Object.fromEntries(ids.map(id=>[id,{id:Number(id),normalization_gain:4}])),routes:Object.fromEntries(ids.map(id=>[id,'/clip/'+id]))})};
 }};
vm.createContext(context);
const a=source.indexOf('\tconst ensureClipMetadata =');
const b=source.indexOf('\n\tconst resolveDetailRoutes',a);
vm.runInContext(source.slice(a,b)+'\nglobalThis.ensure = ensureClipMetadata;',context);
await assert.rejects(context.ensure([1]));
assert.equal(calls,3);
assert.equal(context.pendingDetailRequests.size,0);
assert.equal(context.unavailableDetailIds.size,0);
assert.equal(gains.size,0);
fail=false;
await Promise.all([context.ensure([1]),context.ensure([1]),context.ensure([2])]);
assert.equal(calls,5,'same clip shares in-flight request while another board loads independently');
assert.equal(gains.get(1),4);
assert.equal(gains.get(2),4);
assert.equal(routes.get(2),'/clip/2');
await context.ensure([1,2]);
assert.equal(calls,5,'successful metadata is cached');
console.log('Sound board metadata retry, request sharing, cross-board loading and recovery passed.');
