/** @brief Test server-owned selected state and guest-cache isolation in the real rating initializer. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/social.js', import.meta.url), 'utf8');
const start = source.indexOf('async function initialiseRatings()');
const end = source.indexOf('\ninitialiseReturnNavigation();', start);
const buttons = [1,-1].map((vote) => ({dataset: {audioarchiveRatingVote: String(vote)}, disabled: false,
 attributes: {}, setAttribute(key,value)
 {
  this.attributes[key] = value;
 }, classList: {toggle() {}}}));
const counts = {up:{},down:{},status:{}};
const widget = {dataset:{clipId:'12',audioarchiveRatingCanVote:'1'},
 querySelectorAll()
 {
  return buttons;
 },
 querySelector(selector)
 {
  return selector.includes('-up]') ? counts.up : selector.includes('-down]') ? counts.down : counts.status;
 }};
buttons.forEach((button) => { button.closest = () => widget; });
let click;
const writes = [];
const requests = [];
const context = {FormData, Number, String, Array, Object, Set, Error,
 RATING_VOTES_KEY:'votes', getRatingClientId:()=>'a'.repeat(64),
 writeStorage:(...args)=>writes.push(args),
 document:{querySelector:()=>({dataset:{audioarchiveRatingEndpoint:'/rate',audioarchiveRatingToken:'csrf'}}),
 querySelectorAll:()=>[widget], addEventListener:(event,fn)=>{click=fn;}},
 fetch:async (url,options)=>
 {
  requests.push(options.body);
  return {ok:true,json:async()=>requests.length===1
   ? {success:true,account:true,imported:true,ratings:{12:{vote:1,up:3,down:1}}}
   : {success:true,vote:0,up:2,down:1}};
 }};
vm.createContext(context);
vm.runInContext(source.slice(start,end),context);
await vm.runInContext('initialiseRatings()',context);
assert.equal(buttons[0].attributes['aria-pressed'],'true');
assert.equal(buttons[1].attributes['aria-pressed'],'false');
assert.equal(buttons[0].disabled,false);
assert.equal(counts.up.textContent,'3');
assert.equal(requests[0].get('operation'),'sync');
await click({target:{closest:()=>buttons[0]}});
assert.equal(requests[1].get('vote'),'0');
assert.equal(buttons[0].attributes['aria-pressed'],'false');
assert.equal(writes.length,1,'Only the consumed guest cache is cleared; account votes never enter it');
console.log('Rating UI: authoritative selection, toggle removal and browser-cache isolation passed.');
