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
const context = vm.createContext({Collections, mountPlaylistCovers: () => ({pending, refresh() {}, clear(id) { pending.delete(id); }}), playbackFor: () => null,
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
assert.equal(writes.length, 1, 'rename saves immediately');
assert.equal(saved.playlists[0].name, 'Renamed');
assert.equal(controls.get('[data-playlist-save]').disabled, true);
pending.set(saved.selectedId, {name: 'staged.png'});
const move = new Html();
move.dataset = {action: 'down', index: '0'};
await controls.get('[data-audioarchive-playlist-items]').events.click({target: move});
await tick();
assert.equal(saved.playlists[0].items[0].title, 'Two', 'reordering saves immediately');
assert.equal(pending.size, 1, 'reordering does not submit pending artwork');
assert.equal(writes.at(-1).options, undefined, 'ordinary saves have no cover payload');
const remove = new Html();
remove.dataset = {action: 'remove', index: '0'};
await controls.get('[data-audioarchive-playlist-items]').events.click({target: remove});
await tick();
assert.equal(saved.playlists[0].items.length, 1, 'removal saves immediately');
pending.set(saved.selectedId, {name: 'cover.png'});
await click('[data-playlist-cancel]');
assert.equal(saved.playlists[0].items.length, 1, 'cover Cancel does not undo saved removal');
assert.equal(saved.playlists[0].name, 'Renamed', 'cover Cancel does not undo saved rename');
pending.set(saved.selectedId, {name: 'cover.png'});
pending.set('playlist-second-123456', {name: 'other.png'});
fail = true;
await click('[data-playlist-save]');
assert.equal(pending.size, 2, 'failed cover save retains both drafts');
fail = false;
await click('[data-playlist-save]');
assert.equal(writes.at(-1).options.covers.length, 1);
assert.equal(pending.size, 1, 'Save cover affects selected playlist only');
assert.equal(pending.has('playlist-second-123456'), true);
prompt = 'Failed name';
fail = true;
await click('[data-audioarchive-playlist-rename]');
assert.equal(saved.playlists[0].name, 'Renamed', 'failed immediate edit rolls back');
assert.equal(controls.get('[data-audioarchive-playlist-select]').children[0].textContent, 'Renamed (1)');
fail = false;
Collections.backend = 'browser';
saved = {version: 1, selectedId: '', playlists: []};
pending.clear();
await vm.runInContext('initialisePlaylistPage()', context);
await tick();
prompt = 'First browser playlist';
await click('[data-audioarchive-playlist-create]');
await tick();
assert.equal(saved.playlists[0].name, 'First browser playlist', 'creation saves immediately');
await click('[data-audioarchive-playlist-delete]');
await tick();
assert.notEqual(saved.playlists[0].name, 'First browser playlist', 'deletion saves immediately');
console.log('Immediate account/browser edits, failure rollback, independent cover Save/Cancel and retained cover drafts passed.');
