/** @brief Exercise production editing, seeking, draft lifecycle and pointer handlers without a Joomla server. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const moduleSource = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/recording-editor.js', import.meta.url), 'utf8');
const {editRecording, recordingSeekState, RecordingEditor} = await import('data:text/javascript;base64,' + Buffer.from(moduleSource).toString('base64'));
const original = {id:'one', durationMs:2000, initialState:{polyphony:true}, board:[{id:1,title:'Base'}], layerBoards:{1:[{id:2,title:'Overdub'},{id:3,title:'Other'}]}, events:[{type:'polyphony',t:0,layer:1,enabled:false},{type:'note',t:100,pad:0,note:60,velocity:91,duration:200,layer:0},{type:'pad',t:500,pad:0,layer:1},{type:'polyphony',t:700,layer:1,enabled:true}]};
const fingerprint = JSON.stringify(original);
let result = editRecording(original, 'move', 1, {t:600,note:67});
assert.equal(result.events[2].note,67);
assert.equal(result.events[2].layer,0);
assert.equal(result.events[2].velocity,91);
assert.deepEqual(result.layerBoards, original.layerBoards);
assert.equal(JSON.stringify(original),fingerprint);
result=editRecording(result,'add',-1,{type:'note',t:2200,pad:1,layer:1,note:65,velocity:127,duration:250});
assert.equal(result.durationMs,3200);
assert.equal(result.events.at(-1).pad,1);
assert.throws(()=>editRecording(original,'add',-1,{type:'pad',t:0,pad:1,layer:0}));
result=editRecording(original,'delete',2);
assert.equal(result.events.length,3);
assert.equal(result.events.at(-1).type,'polyphony');
result=editRecording(original,'move',1,{t:-50,note:150,duration:9000000});
assert.equal(result.events.find(e=>e.type==='note').t,0);
assert.equal(result.events.find(e=>e.type==='note').note,127);
assert.equal(result.durationMs,7200000);
let seek=recordingSeekState(original,600);
assert.equal(seek.index,3);
assert.equal(seek.polyphony.get(0),true);
assert.equal(seek.polyphony.get(1),false);
seek=recordingSeekState(original,700);
assert.equal(seek.index,3,'event exactly at seek point remains scheduled');
assert.equal(recordingSeekState(original,701).polyphony.get(1),true);
assert.equal(recordingSeekState(original,-1).index,0);
assert.equal(recordingSeekState(original,9000).time,2000);

/** @brief Minimal event controls for testing the actual editor's asynchronous lifecycle. */
class Control
{
    constructor()
    {
        this.handlers={};this.dataset={};this.options=[];this.value='';this.style={};
        this.classList={toggle(){}};
    }
    addEventListener(name, handler) { this.handlers[name]=handler; }
    replaceChildren() { this.options=[]; }
    add(option) { this.options.push(option); if(this.options.length===1)this.value=option.value; }
    querySelectorAll() { return Object.values(controls); }
    getBoundingClientRect() { return {left:0,top:0}; }
    closest() { return this; }
    setPointerCapture() {}
}
const controls=Object.fromEntries(['toggle','panel','layer','pad','undo','message','cancel','save'].map(key=>[key,new Control()]));
const container=new Control();container.dataset.labels=JSON.stringify({layer:'Layer',error:'Save failed',invalid:'Invalid'});
container.querySelector=(selector)=>controls[selector.match(/data-edit-(.*)\]/)[1]];
const root={querySelector:selector=>selector==='[data-edit-toggle]'?controls.toggle:selector==='[data-edit-help]'?null:container};const roll=new Control();
globalThis.window={addEventListener(){}};
globalThis.Option=class {constructor(text,value){this.text=text;this.value=value;}};
let stored=original, saveSuccess=false, auditioned=[], seeks=[], rendered=0;
const callbacks={blocked:()=>false,selected:()=>stored,render:()=>{rendered++;editor.sync();},zoom:()=>2,stopPreview(){},stopAudition(){},audition:(recording,event)=>auditioned.push(event),seek:t=>seeks.push(t),save:async draft=>{if(saveSuccess)stored=draft;return saveSuccess;}};
const editor=new RecordingEditor(root,roll,callbacks);
controls.toggle.handlers.click();
assert.notEqual(editor.draft,original);
editor.rows=[{type:'note',value:67},{type:'note',value:66},{type:'pad',value:0},{type:'pad',value:1}];
controls.layer.value='1';controls.layer.handlers.change();
controls.pad.value='1';
editor.doubleClick({target:{closest:()=>null},clientX:264,clientY:24});
assert.equal(editor.draft.events.find(e=>e.t===1000).layer,1);
assert.equal(editor.draft.events.find(e=>e.t===1000).note,67);
assert.equal(JSON.stringify(original),fingerprint);
controls.undo.handlers.click();
assert.equal(JSON.stringify(editor.draft),fingerprint);
const node=new Control();node.dataset.eventIndex='1';
const pointer={target:node,button:0,pointerId:1,clientX:84,clientY:24};
editor.pointerDown(pointer);
editor.pointerMove({...pointer,clientX:124,clientY:43});
editor.pointerUp({...pointer,clientX:124,clientY:43});
assert.equal(editor.draft.events.find(e=>e.type==='note').t,300);
assert.equal(editor.draft.events.find(e=>e.type==='note').note,66);
editor.suppressClick=false;
editor.click(pointer);
await new Promise(resolve=>setTimeout(resolve,300));
assert.equal(auditioned[0].note,66,'single click auditions edited pitch');
auditioned=[];
editor.click(pointer);
editor.doubleClick(pointer);
await new Promise(resolve=>setTimeout(resolve,310));
assert.equal(auditioned.length,0,'double-click delete cancels audition');
assert.equal(editor.draft.events.filter(e=>e.type==='note').length,0);
await controls.save.handlers.click();
assert.ok(editor.draft,'failed save retains draft');
assert.equal(controls.message.textContent,'Save failed');
assert.equal(stored,original);
controls.cancel.handlers.click();
assert.equal(editor.draft,null);
assert.equal(stored,original);
controls.toggle.handlers.click();
editor.change('move',1,{t:1200});
saveSuccess=true;
await controls.save.handlers.click();
assert.equal(editor.draft,null);
assert.equal(stored.events.find(e=>e.type==='note').t,1200);
editor.click({target:{closest:()=>null},clientX:264,clientY:0});
assert.deepEqual(seeks,[1000]);

/** @brief Render the actual production roll and verify events retain indices across mixed layers. */
const social=fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/social.js',import.meta.url),'utf8');
const elements=[];
const rollFixture={replaceChildren(){elements.length=0;},appendChild(node){elements.push(node);},style:{}};
const context={recordingRoll:rollFixture,recordingEdit:{draft:stored,layer:1},recordingZoom:2,recordingSeekPosition:500,document:{createElement:()=>({style:{setProperty(){}},dataset:{},setAttribute(){}})},getMidiNoteName:n=>String(n),getRecordingPadColor:()=>'',formatSoundboardRecordingTime:String,updateRecordingPlayhead(){},root:{dataset:{}}};
vm.createContext(context);
let a=social.indexOf('\tconst renderRecordingRoll =');let b=social.indexOf('\n\tconst renderSoundboardRecordings',a);
vm.runInContext(social.slice(a,b)+'\nglobalThis.renderRoll=renderRecordingRoll;',context);
context.renderRoll(stored);
assert.equal(elements.filter(e=>e.dataset?.eventIndex!==undefined).length,2);
assert.ok(context.recordingEdit.rows.some(row=>row.type==='pad'&&row.value===1));
assert.ok(context.recordingEdit.rows.some(row=>row.type==='note'&&row.value===48));
console.log('Recording edits, boundaries, mixed-layer mappings, seek state, save failure/cancel/undo, drag, double-click and production roll rendering passed.');
