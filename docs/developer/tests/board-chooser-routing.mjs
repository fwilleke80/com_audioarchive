/** @brief Verify combined archive actions reach the account chooser before board capacity checks. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/playlist.js', import.meta.url), 'utf8');
const start = source.indexOf('async function addClipToSoundboard(');
const end = source.indexOf('\n/**', start);
const calls = [];
const trigger = {};
const origin = {closest() {return {dataset:{audioarchiveSoundboardPadCount:'12'}};},querySelector() {return trigger;}};
const context = vm.createContext({Collections:{backend:'server'},openSoundboardChooser(...args) {calls.push(args);},playlistReadStorage() {throw new Error('Account action must not read the default board');}});
vm.runInContext(source.slice(start,end),context);
const clip = {id:42,uuid:'clip',title:'Clip'};
await context.addClipToSoundboard(clip,origin);
assert.equal(calls.length,1);
assert.equal(calls[0][0],trigger);
assert.equal(calls[0][1],clip);
assert.equal(calls[0][2],12);
console.log('Archive/playlist account action reaches chooser before default-board capacity checks.');
