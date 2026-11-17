/** @brief Pending covers survive selection changes and never POST mutations before Save. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const controls = new Map();
for (const key of ['file', 'remove', 'undo', 'preview', 'status'])
{
    controls.set(key, {events: {}, files: [], addEventListener(name, fn) { this.events[name] = fn; }, removeAttribute(name) { delete this[name]; }});
}
const control = {dataset: {coverToken: 'csrf', coverEndpoint: '/cover', coverPending: 'Pending', coverRemoving: 'Removing', coverEmpty: 'Empty', coverSaved: 'Stored'}, querySelector: selector => controls.get(selector.slice(12, -1))};
let selected = 'first';
const requests = [];
let changed = 0;
let blobs = 0;
const revoked = [];
const context = vm.createContext({control, selected: () => selected, exists: id => id !== 'new', changed: () => changed++, FormData,
    URL: {createObjectURL: () => `blob:${++blobs}`, revokeObjectURL: url => revoked.push(url)}, window: {addEventListener() {}},
    fetch: async (url, options) =>
    {
        requests.push(options.body);
        assert.equal(options.body.get('action'), 'status', 'cover widget never mutates');
        return {ok: true, json: async () => ({success: true, data: {cover: {width: 100, height: 100}}})};
    }});
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/playlist-covers.js', import.meta.url), 'utf8').replace('export function', 'function');
vm.runInContext(source + '\nvar editor = mountPlaylistCovers(control, selected, exists, changed);', context);
const editor = context.editor;
await editor.refresh();
controls.get('file').files = [{name: 'first.png'}];
controls.get('file').events.change();
assert.equal(editor.pending.get('first').name, 'first.png');
selected = 'second';
await editor.refresh();
controls.get('remove').events.click();
assert.equal(editor.pending.get('second'), null);
selected = 'first';
await editor.refresh();
assert.equal(controls.get('status').textContent, 'Pending');
assert.equal(controls.get('preview').hidden, false);
controls.get('undo').events.click();
assert.equal(editor.pending.has('first'), false);
assert.equal(editor.pending.has('second'), true);
selected = 'new';
const count = requests.length;
await editor.refresh();
assert.equal(requests.length, count, 'new unsaved playlist needs no status request');
controls.get('file').files = [{name: 'new.png'}];
controls.get('file').events.change();
assert.equal(editor.pending.get('new').name, 'new.png');
editor.clear();
assert.equal(editor.pending.size, 0);
assert.equal(controls.get('preview').hidden, true);
assert.ok(revoked.length > 0);
assert.equal(changed, 4);
console.log('Playlist cover staging, switching, Undo, new playlist and cleanup passed.');
