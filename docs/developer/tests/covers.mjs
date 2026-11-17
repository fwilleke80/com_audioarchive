/** @brief Verify cover selection isolation and upload errors without changing the editor form. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/covers.js', import.meta.url), 'utf8').replace('export function', 'function');
const elements = new Map();
const events = new Map();
for (const key of ['file', 'upload', 'remove', 'status'])
{
    elements.set(key, {disabled: false, textContent: '', value: '', files: [new File(['image'], 'cover.png')], addEventListener: (event, callback) => events.set(`${key}:${event}`, callback)});
}
let selected = 'playlist-one';
let requests = [];
const control =
{
    dataset: {coverKind: 'playlist', coverEndpoint: '/cover', coverToken: 'token', coverFirst: 'Save first', coverEmpty: 'No cover', coverSaved: 'Saved', coverError: 'Error'},
    querySelector: (selector) => elements.get(selector.match(/data-cover-(\w+)/)[1]),
    addEventListener: (event, callback) => events.set(event, callback),
};
const context = vm.createContext({document: {querySelectorAll: () => []}, FormData, fetch: (url, options) => new Promise(resolve => requests.push({url, body: options.body, resolve})), control, selected: () => selected});
vm.runInContext(source + '\nmountCover(control, selected);', context);
assert.equal(requests[0].body.get('id'), 'playlist-one');
selected = 'playlist-two';
events.get('audioarchive:cover-selection')();
requests[0].resolve({ok: true, json: async () => ({success: true, data: {cover: {width: 1, height: 1, file_size: 20}}})});
await new Promise(resolve => setImmediate(resolve));
assert.equal(requests.length, 2);
assert.equal(requests[1].body.get('id'), 'playlist-two');
assert.equal(elements.get('status').textContent, '');
requests[1].resolve({ok: true, json: async () => ({success: true, data: {cover: null}})});
await new Promise(resolve => setImmediate(resolve));
assert.equal(elements.get('status').textContent, 'No cover');
events.get('upload:click')();
assert.equal(requests[2].body.get('action'), 'upload');
assert.equal(requests[2].body.get('cover').name, 'cover.png');
assert.equal(requests[2].body.get('token'), '1');
requests[2].resolve({ok: false, json: async () => ({success: false, message: 'Quota exceeded'})});
await new Promise(resolve => setImmediate(resolve));
assert.equal(elements.get('status').textContent, 'Quota exceeded');
assert.equal(elements.get('upload').disabled, false);
selected = '';
events.get('audioarchive:cover-selection')();
assert.equal(elements.get('upload').disabled, true);
assert.equal(elements.get('status').textContent, 'Save first');
console.log('Cover selection isolation and error recovery passed.');
