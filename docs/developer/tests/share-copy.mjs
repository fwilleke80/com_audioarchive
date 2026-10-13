/** @brief Test production Copy link handlers with a captured playback position. */
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/social.js', import.meta.url), 'utf8');
const element = () => ({dataset:{},textContent:'',value:'',events:{},addEventListener(name,fn)
{
 this.events[name]=fn;
},focus() {},select() {}});
const fields = Object.fromEntries(['start','url-field','time','feedback','confirm','close'].map(name => [`[data-share-${name}]`,element()]));
const dialog = element();
dialog.querySelector = key => fields[key];
dialog.showModal = () => {};
dialog.close = () => {};
const copy = element();
const toggle = element();
const menu = {dataset:{shareUrl:'https://example.test/clip?start=3&t=4&pitch=2',shareClipId:'42',shareCopiedLabel:'Copied',copyFailedLabel:'Failed'},querySelector(key)
{
 return {'[data-audioarchive-share-toggle]':toggle,'[data-audioarchive-share-copy]':copy,'[data-share-dialog]':dialog}[key] ?? null;
}};
let copied='';
const audio = {currentTime:12.25};
const context = vm.createContext({URL,Number,String,Math,document:{title:'Clip',querySelectorAll:()=>[menu],querySelector:()=>audio,addEventListener() {}},window:{location:{href:'https://example.test/'},addEventListener() {}},playbackFor:a=>a,copyText:async value=>{copied=value;return true;},setShareMenuOpen() {},initialiseShareMenuKeyboard() {}});
const start=source.indexOf('function initialiseShareButtons()');
const end=source.indexOf('\n/**',start);
vm.runInContext(source.slice(start,end)+'\ninitialiseShareButtons();',context);
copy.events.click();
assert.equal(fields['[data-share-start]'].checked,false);
assert.equal(new URL(fields['[data-share-url-field]'].value).searchParams.has('start'),false);
assert.equal(new URL(fields['[data-share-url-field]'].value).searchParams.has('t'),false);
fields['[data-share-start]'].checked=true;
audio.currentTime=99;
fields['[data-share-start]'].events.change();
assert.equal(new URL(fields['[data-share-url-field]'].value).searchParams.get('start'),'12.25');
await fields['[data-share-confirm]'].events.click();
assert.equal(copied,fields['[data-share-url-field]'].value);
assert.equal(fields['[data-share-feedback]'].textContent,'Copied');
audio.currentTime=0;copy.events.click();
assert.equal(fields['[data-share-start]'].checked,false);
assert.equal(fields['[data-share-start]'].disabled,true);
console.log('Copy dialog timestamp opt-in, captured time, reset, zero position and confirmation passed.');
