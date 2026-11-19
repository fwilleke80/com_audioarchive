/** @brief Exercise production collection templates and handlers with jsdom; PHP_BIN selects the PHP CLI. */
import {createRequire} from 'node:module';
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {JSDOM} = createRequire(import.meta.url)('jsdom');
const base = new URL('../../../pkg_audioarchive/com_audioarchive/media/js/', import.meta.url).pathname;
const fixture = new URL('collection-layout-fixture.php', import.meta.url).pathname;
const html = page => execFileSync(process.env.PHP_BIN || 'php', [fixture, page], {encoding: 'utf8'});

const clip={id:1,uuid:'11111111-1111-4111-8111-111111111111',title:'Saved clip'};
const song={id:'song',format:'punga-audioarchive-soundboard-recording',version:1,name:'Night rhythm',durationMs:3000,board:[clip],initialState:{polyphony:true},events:[{type:'pad',t:0,pad:0}]};
(async()=>{
 for(const scenario of ['account', 'empty', 'unavailable'])
 {
 const empty = scenario !== 'account';
 const dom=new JSDOM(html('soundboard'),{url:'https://example.test/soundboard',runScripts:'outside-only'});
 const c=dom.getInternalVMContext();c.structuredClone=structuredClone;
 const doc=dom.window.document;
 if(scenario==='unavailable')
 {
     doc.querySelector('[data-audioarchive-soundboard]').dataset.audioarchiveSharedRecording='1';
 }
 doc.querySelector('[data-audioarchive-preload]').dataset.audioarchivePreload='0';
 c.fetch=async(url,options)=>({ok:true,json:async()=>({success:true,data:{backend:'server',userId:7,revision:1,token:'csrf',sharing:true,boards:[{id:'one',name:'Board one',items:[clip],shared:true}],defaultBoard:'one',playlists:[],recordings:empty?[]:[song],labels:{new:'New board',rename:'Rename',delete:'Delete',default:'Set as default',revoke:'Revoke share link'}}})});
 const editor=fs.readFileSync(base+'recording-editor.js','utf8').replace(/export /g,'');
 const adapter=fs.readFileSync(base+'collections.js','utf8').replace('export const Collections','const Collections');
 const social=fs.readFileSync(base+'social.js','utf8').replace(/^import .*;\n/gm,'').split('\ninitialiseReturnNavigation();')[0];
 vm.runInContext(editor+'\n'+adapter+'\n'+social,c);
 await vm.runInContext('Collections.ready().then(()=>initialiseSoundboard())',c);
 assert.equal(doc.querySelectorAll('[data-edit-toggle]').length,1);
 assert.equal(doc.querySelector('[data-audioarchive-recording-list]').tagName,'TBODY');
 assert.ok(doc.querySelector('[data-audioarchive-recording-editor]').contains(doc.querySelector('[data-audioarchive-recording-record]')));
 assert.equal(doc.querySelector('[data-audioarchive-recording-editor]').hidden,false);
 assert.equal(doc.querySelector('[data-audioarchive-recording-play]').disabled,true);
 assert.equal(doc.querySelector('[data-audioarchive-recording-record]').disabled,true);
 if(scenario==='unavailable')
 {
     assert.equal(doc.querySelector('[data-audioarchive-recording-record]').hidden,true);
     assert.equal(doc.querySelector('[data-edit-toggle]').hidden,true);
 }
 if(!empty) {
 doc.querySelector('[data-recording-id]').click();
 doc.querySelector('[data-edit-toggle]').click();
 assert.equal(doc.querySelector('[data-edit-panel]').hidden,false);
 assert.equal(doc.querySelector('[data-edit-help]').hidden,false);
 assert.equal(doc.querySelector('[data-audioarchive-recording-play]').disabled,false);
 assert.equal(doc.querySelector('[data-audioarchive-recording-record]').disabled,true);
 doc.querySelector('[data-edit-toggle]').click();
 assert.equal(doc.querySelector('[data-edit-panel]').hidden,true);
 }
 console.log('Real Sound Board template initialized:',empty?'empty library':'saved recording and edit toolbar');
 dom.window.close();
 }
 const dom = new JSDOM(html('playlists'), {url:'https://example.test/playlists',runScripts:'outside-only'});
 const c = dom.getInternalVMContext();
 c.structuredClone = structuredClone;
 c.playbackFor = () => null;
 c.mountPlaylistCovers = () => ({pending:new Map(),refresh(){},clear(){}});
 const list = {id:'playlist-first-123456',name:'Evening mix',items:[clip, {...clip,uuid:'22222222-2222-4222-8222-222222222222',id:2}]};
 c.Collections = {backend:'server',shared:null,read:()=>({version:1,selectedId:list.id,playlists:[list]}),mount(){},write:async()=>true};
 c.fetch = async () => ({ok:true,json:async()=>({success:true,items:{}})});
 const source = fs.readFileSync(base+'playlist.js','utf8').replace(/^import .*;$/gm,'').split("if (document.readyState === 'loading')")[0];
 vm.runInContext(source,c);
 await vm.runInContext('initialisePlaylistPage()',c);
 await new Promise(resolve=>setTimeout(resolve,0));
 const doc=dom.window.document;
 assert.equal(doc.querySelectorAll('[data-audioarchive-playlist-items] tr').length,2);
 assert.equal(doc.querySelectorAll('[data-audioarchive-playlist-items] details.com-audioarchive-more').length,2);
 assert.ok(doc.querySelector('[data-audioarchive-playlist-items] [data-action="up"]').closest('.com-audioarchive-playlist-order-controls'));
 assert.equal(doc.querySelector('[data-playlist-cover-editor]').hidden,true);
 doc.querySelector('[data-playlist-cover-toggle]').click();
 assert.equal(doc.querySelector('[data-playlist-cover-editor]').hidden,false);
 assert.equal(doc.querySelector('[data-playlist-cover-save]').hidden,true,'opening cover editor does not create pending changes');
 const ui=fs.readFileSync(base+'collection-ui.js','utf8').replace('export function','function');
 vm.runInContext(ui+';initialiseCollectionMenus();',c);
 const menu=doc.querySelector('.com-audioarchive-more');
 menu.open=true;
 await new Promise(resolve=>setTimeout(resolve,0));
 assert.equal(menu.querySelector('.com-audioarchive-more-menu').style.position,'fixed');
 doc.dispatchEvent(new dom.window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
 assert.equal(menu.open,false);
 assert.equal(doc.activeElement,menu.querySelector('summary'));
 menu.open=true;
 doc.body.click();
 assert.equal(menu.open,false,'outside click dismisses menu');
 console.log('Production playlist layout: row menus, collapsed cover editor, clean state and keyboard/outside dismissal passed.');
 dom.window.close();
})().catch(e=>{console.error(e);process.exitCode=1;});
