/** @brief Check account board destinations remain inside the open archive menu. */
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/playlist.js', import.meta.url), 'utf8');
class Element
{
 constructor()
 {
  this.dataset={};this.children=[];this.attributes={};this.handlers={};this.hidden=false;
 }
 setAttribute(key,value) {this.attributes[key]=value;}
 getAttribute(key) {return this.attributes[key];}
 addEventListener(type,fn) {this.handlers[type]=fn;}
 append(...items) {this.children.push(...items);}
 appendChild(item) {this.append(item);}
 replaceChildren() {this.children=[];}
 insertAdjacentHTML() {}
 focus() {}
 closest() {return {dataset:{audioarchiveSoundboardPadCount:'12'}};}
 querySelector(selector)
 {
  if(selector==='[data-audioarchive-add-to-toggle]') return toggle;
  if(selector==='[data-audioarchive-add-to-popover]') return popover;
  return this.children[0];
 }
}
const menu=new Element();
menu.dataset={soundboardEnabled:'1',playlistsEnabled:'0',clipId:'7'};
const toggle=new Element();
const popover=new Element();
let complete;
let closed=0;
const context=vm.createContext({HTMLButtonElement:Element,HTMLElement:Element,Collections:{backend:'server'},
 document:{querySelectorAll() {return [menu];},createElement() {return new Element();}},
 closePlaylistPopovers(retained) {if(!retained) {closed++;popover.hidden=true;}},positionPlaylistPopover() {},
 renderSoundboardChoices(container,clip,capacity,onComplete)
 {
  assert.equal(container,popover.children[1]);assert.equal(clip.id,7);assert.equal(capacity,12);complete=onComplete;
 }});
const start=source.indexOf('function initialiseAddToMenus()');
vm.runInContext(source.slice(start,source.indexOf('\n/**',start)),context);
context.initialiseAddToMenus();
await toggle.handlers.click();
const choice=popover.children[0];
choice.handlers.click();
assert.equal(popover.hidden,false,'parent remains open');
assert.equal(popover.children[1].hidden,false,'nested destinations are visible');
assert.equal(closed,0,'opening submenu never dismisses parent');
choice.handlers.click();
assert.equal(popover.children[1].hidden,true,'second click collapses submenu');
choice.handlers.click();
complete();
assert.equal(closed,1,'successful choice dismisses parent');
console.log('Nested board chooser preserves parent, toggles and closes on successful selection.');
