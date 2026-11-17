/** @brief Exercise the production page handlers with a minimal DOM and observable persistence. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/playlist.js', import.meta.url), 'utf8').replace(/^import .*;$/gm, '');
class Element
{
    constructor()
    {
        this.events = {};
        this.dataset = {};
        this.disabled = false;
        this.children = [];
        this.classList = {add() {}, remove() {}};
    }
    addEventListener(name, fn) { this.events[name] = fn; }
    replaceChildren() { this.children = []; }
    appendChild(node) { this.children.push(node); }
    querySelector() { return null; }
    closest() { return this; }
}
class Select extends Element {}
class Html extends Element {}
const controls = new Map();
for (const key of ['select','rename','create','delete','items','status']) controls.set(`[data-audioarchive-playlist-${key}]`, key === 'select' ? new Select() : new Element());
controls.set('[data-playlist-save]', new Element());
controls.set('[data-playlist-cancel]', new Element());
const root = new Element();
root.dataset = {editPending: 'Pending', editSaved: 'Saved', editCancelled: 'Cancelled', editConflict: 'Conflict'};
root.querySelector = selector => controls.get(selector) || null;
root.querySelectorAll = () => [];
let saved = {version: 1, selectedId: 'playlist-first-123456', playlists: [{id: 'playlist-first-123456', name: 'Original', items: [{uuid: '11111111-1111-4111-8111-111111111111', title: 'One'}, {uuid: '22222222-2222-4222-8222-222222222222', title: 'Two'}]}, {id: 'playlist-second-123456', name: 'Second', items: []}]};
let fail = false;
let prompt = 'Renamed';
const writes = [];
const pending = new Map();
const events = {};
const Collections = {backend: 'server', read: () => structuredClone(saved), mount() {}, refresh: async () => true, write: async (key, value, options) =>
{
    writes.push({value: structuredClone(value), options});
    if (fail) return false;
    saved = structuredClone(value);
    return true;
}};
const context = vm.createContext({Collections, mountPlaylistCovers: () => ({pending, refresh() {}, clear() { pending.clear(); }}), playbackFor: () => null,
    document: {readyState: 'loading', addEventListener() {}, querySelector: () => root, createElement: () => new Element()},
    window: {crypto, location: {hash: '', href: 'https://example.test/'}, addEventListener: (name, fn) => events[name] = fn, prompt: () => prompt, confirm: () => true},
    HTMLSelectElement: Select, HTMLElement: Html, HTMLButtonElement: Html, HTMLAudioElement: Html, Element,
    URL, URLSearchParams, structuredClone, crypto, console, fetch: async () => ({ok: true, json: async () => ({success: true, items: {}})})});
vm.runInContext(source, context);
await vm.runInContext('initialisePlaylistPage()', context);
const tick = () => new Promise(resolve => setImmediate(resolve));
await tick();
const click = key => controls.get(key).events.click();
await click('[data-audioarchive-playlist-rename]');
assert.equal(writes.length, 0, 'rename stays pending');
assert.equal(controls.get('[data-playlist-save]').disabled, false);
await click('[data-playlist-cancel]');
await tick();
assert.equal(controls.get('[data-audioarchive-playlist-select]').children[0].textContent, 'Original (2)');
assert.equal(writes.length, 0, 'Cancel does not write');
await click('[data-audioarchive-playlist-rename]');
const remove = new Html();
remove.dataset = {action: 'remove', index: '0'};
controls.get('[data-audioarchive-playlist-items]').events.click({target: remove});
await tick();
assert.equal(writes.length, 0, 'clip removal stays pending');
pending.set(saved.selectedId, {name: 'cover.png'});
fail = true;
await click('[data-playlist-save]');
assert.equal(writes.at(-1).value.playlists[0].name, 'Renamed');
assert.equal(writes.at(-1).value.playlists[0].items.length, 1);
assert.equal(pending.size, 1, 'failed save preserves artwork selection');
assert.equal(controls.get('[data-playlist-save]').disabled, false, 'failed save can retry');
fail = false;
await click('[data-playlist-save]');
assert.equal(saved.playlists[0].name, 'Renamed');
assert.equal(saved.playlists[0].items.length, 1);
assert.equal(writes.at(-1).options.covers.length, 1);
assert.equal(pending.size, 0);
assert.equal(controls.get('[data-playlist-save]').disabled, true);
prompt = 'Draft';
await click('[data-audioarchive-playlist-rename]');
const before = writes.length;
saved.playlists[0].name = 'External';
await click('[data-playlist-save]');
assert.equal(writes.length, before, 'new external snapshot cannot be overwritten');
assert.equal(controls.get('[data-audioarchive-playlist-status]').textContent, 'Conflict');
await click('[data-playlist-cancel]');
await tick();
assert.equal(controls.get('[data-audioarchive-playlist-select]').children[0].textContent, 'External (1)');
console.log('Playlist editor pending rename/removal, Cancel, failed Save, retry and conflict checks passed.');

Collections.backend = 'browser';
saved = {version: 1, selectedId: '', playlists: []};
await vm.runInContext('initialisePlaylistPage()', context);
await tick();
prompt = 'First browser playlist';
await click('[data-audioarchive-playlist-create]');
await tick();
const prior = writes.length;
assert.equal(saved.playlists.length, 0, 'new browser playlist waits for Save');
await click('[data-playlist-save]');
assert.equal(writes.length, prior + 1);
assert.equal(saved.playlists[0].name, 'First browser playlist');
await click('[data-audioarchive-playlist-delete]');
await tick();
assert.equal(saved.playlists[0].name, 'First browser playlist', 'deletion stays pending');
await click('[data-playlist-cancel]');
await tick();
assert.equal(controls.get('[data-audioarchive-playlist-select]').children[0].textContent, 'First browser playlist (0)');
console.log('First browser playlist creation and pending deletion cancellation passed.');
