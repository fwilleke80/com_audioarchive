/** @brief Check opening-click lifetime, full-board disabling and destination selection. */
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/board-chooser.js', import.meta.url), 'utf8');
const listeners = [];
const writes = [];
let menu;
/** @brief Supply the DOM operations exercised by the chooser. */
function element()
{
 return {dataset:{},style:{},children:[],handlers:{},textContent:'Add',offsetWidth:100,offsetHeight:100,
 setAttribute() {},getAttribute() {return null;},focus() {},getBoundingClientRect() {return {left:10,bottom:20};},
 contains(target) {return target===this || this.children.includes(target);},
 replaceChildren() {this.children=[];},append(child) {this.children.push(child);},remove() {this.removed=true;},
 addEventListener(type,fn) {this.handlers[type]=fn;},
 querySelectorAll() {return this.children.filter(child=>!child.disabled);},
 querySelector() {return this.children.find(child=>!child.disabled);}};
}
const document = {querySelector() {return null;},createElement:element,body:{append(value) {menu=value;}},
 addEventListener(type,fn,capture) {listeners.push({type,fn,capture});},
 removeEventListener(type,fn,capture) {const i=listeners.findIndex(item=>item.fn===fn && item.capture===capture);if(i>=0) listeners.splice(i,1);}};
const context = vm.createContext({document,window:{innerWidth:800,innerHeight:600,addEventListener() {},removeEventListener() {},alert() {assert.fail('Unexpected error');}},
 Collections:{boardChoiceLabels:{full:'Full',added:'Already added'},boardChoices:[{id:'full',name:'Default',isDefault:true,items:[{id:1}]},{id:'free',name:'Other',items:[null]}],async addToBoard(...args) {writes.push(args);}}});
vm.runInContext(source.replace(/^import .*\n/,'').replaceAll('export function','function'),context);
const trigger=element();
context.openSoundboardChooser(trigger,{id:2},1);
// The opening item click is already in bubble phase when the menu registers listeners.
for(const listener of [...listeners])
{
 if(!listener.capture) listener.fn({target:element()});
}
assert.ok(!menu.removed,'opening item click must leave chooser visible');
assert.equal(menu.children[0].disabled,true);
assert.equal(menu.children[1].disabled,false);
await menu.children[1].handlers.click();
assert.equal(writes[0][0],'free');
assert.ok(menu.removed);
assert.equal(listeners.length,0,'close removes capture listener');
context.openSoundboardChooser(trigger,{id:2},1);
for(const listener of [...listeners]) listener.fn({target:element()});
assert.ok(menu.removed,'subsequent outside click closes chooser');
console.log('Chooser opening click, full/free destinations, selection and outside dismissal passed.');
