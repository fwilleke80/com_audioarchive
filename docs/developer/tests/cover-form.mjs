/** @brief Exercise local pending cover selection, removal, Undo and resource cleanup. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const events = new Map();
const elements = new Map();
for (const name of ['pending-file', 'pending-remove', 'undo', 'local-preview', 'status'])
{
    const element = {files: [], checked: false, hidden: false, addEventListener: (event, fn) => events.set(`${name}:${event}`, fn), removeAttribute: function (name)
    {
        delete this[name];
    }};
    Object.defineProperty(element, 'value', {set(value)
    {
        if (value === '')
        {
            this.files = [];
        }
    }});
    elements.set(name, element);
}
const root = {dataset: {coverInitial: 'Stored', coverPending: 'Pending', coverRemoving: 'Removing'}, querySelector: selector => elements.get(selector.slice(12, -1)), closest: () => ({addEventListener: (event, fn) => events.set(event, fn)})};
const revoked = [];
let created = 0;
const context = {document: {querySelectorAll: () => [root]}, window: {addEventListener: (event, fn) => events.set(event, fn)}, URL: {createObjectURL: () => `blob:${++created}`, revokeObjectURL: url => revoked.push(url)}, setTimeout: fn => fn(), fetch: () => assert.fail('No request before form save')};
vm.runInNewContext(fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/cover-form.js', import.meta.url), 'utf8'), context);
const file = elements.get('pending-file');
const remove = elements.get('pending-remove');
const preview = elements.get('local-preview');
assert.equal(elements.get('status').textContent, 'Stored');
assert.equal(elements.get('undo').hidden, true);
file.files = [{}];
events.get('pending-file:change')();
assert.equal(preview.hidden, false);
assert.equal(elements.get('status').textContent, 'Pending');
remove.checked = true;
events.get('pending-remove:change')();
assert.equal(file.files.length, 0);
assert.equal(preview.hidden, true);
assert.equal(elements.get('status').textContent, 'Removing');
assert.deepEqual(revoked, ['blob:1']);
events.get('undo:click')();
assert.equal(remove.checked, false);
assert.equal(elements.get('status').textContent, 'Stored');
file.files = [{}];
events.get('pending-file:change')();
events.get('pagehide')();
assert.deepEqual(revoked, ['blob:1', 'blob:2']);
events.get('pageshow')();
assert.equal(preview.src, 'blob:3');
console.log('Pending cover preview, removal, Undo and page lifecycle checks passed.');
