/** @brief Verify deferred player activation and the no-observer fallback. */
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/player.js', import.meta.url), 'utf8');
const start=source.indexOf("\tconst prepareDeferredPlayer =");
const end=source.indexOf("\n\tdocument.querySelectorAll('[data-audioarchive-native-player]')",start);
for (const supported of [true,false])
{
 class Element {}
 class Audio extends Element
 {
  controls = true;
  hidden = false;
  getAttribute() {return null;}
  querySelector() {return {};}
 }
 class Button extends Element {}
 const players = [true,true,false].map(deferred => ({dataset:{deferInitialization:deferred?'1':'0'},audio:new Audio(),ui:Object.assign(new Element(),{hidden:true}),toggle:new Button(),classList:{add() {}},querySelector(selector) {return selector.includes('custom-ui')?this.ui:this.toggle;},events:{},addEventListener(name,fn)
 {
  this.events[name]=fn;
 }}));
 const initialized = new Set();
 const observed = new Set();
 let callback;
 class Observer
 {
  constructor(fn) {callback=fn;}
  observe(player) {observed.add(player);}
  unobserve(player) {observed.delete(player);}
 }
 const context = vm.createContext({HTMLAudioElement:Audio,HTMLElement:Element,HTMLButtonElement:Button,getCustomPlayerAudio:player=>player.audio,window:supported?{IntersectionObserver:Observer}:{},IntersectionObserver:Observer,document:{querySelectorAll:()=>players},initializeCustomPlayer:player=>initialized.add(player)});
 vm.runInContext(source.slice(start,end),context);
 if (supported)
 {
  assert.equal(initialized.size,1);
  for (const player of players.slice(0,2))
  {
   assert.equal(player.audio.hidden,true);
   assert.equal(player.audio.controls,false);
   assert.equal(player.ui.hidden,false);
   assert.equal(player.toggle.disabled,false);
  }
  assert.equal(observed.size,2);
  callback([{target:players[0],isIntersecting:false}]);
  assert.equal(initialized.size,1);
  callback([{target:players[0],isIntersecting:true}]);
  assert.equal(initialized.size,2);
  assert.equal(observed.size,1);
  players[1].events.click();
  players[1].events.focusin();
  assert.equal(initialized.size,3);
 }
 else assert.equal(initialized.size,3);
}
console.log('Visible/hidden player activation, keyboard focus and observer fallback passed.');
