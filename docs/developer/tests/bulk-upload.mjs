/** @brief Run the production bulk queue against a DOM with deterministic server responses. Requires linkedom. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {parseHTML} = require(process.env.AUDIOARCHIVE_TEST_NODE_MODULES ? `${process.env.AUDIOARCHIVE_TEST_NODE_MODULES}/linkedom` : 'linkedom');
const base = new URL('../../../pkg_audioarchive/com_audioarchive/', import.meta.url);
const template = await readFile(new URL('administrator/tmpl/upload/default.php', base), 'utf8');
const strings = await readFile(new URL('administrator/language/en-GB/com_audioarchive.ini', base), 'utf8');
const translations = Object.fromEntries([...strings.matchAll(/^([A-Z0-9_]+)="(.*)"$/gm)].map((match) => [match[1], match[2]]));
const html = template.slice(template.indexOf('<form')).replace(/<\?php echo Text::_\('([^']+)'\); \?>/g, (_, key) => translations[key] || key).replace(/<\?php[\s\S]*?\?>/g, '');
const {document, window} = parseHTML(html);
const byId = (id) => document.getElementById(`audioarchive-bulk-upload-${id}`);
byId('form').dataset.uploadEndpoint = '/upload';
byId('form').dataset.analysisEndpoint = '/analysis';
byId('form').dataset.tokenName = 'csrf';
byId('form').reportValidity = () => true;
let fileClicks = 0;
byId('files').click = () => {fileClicks++;};
const calls = [];
const context =
{
    window, document, URLSearchParams,
    Joomla: {Text: {_: (key, fallback) => translations[key] || fallback}},
    FormData: class
    {
        constructor() {this.values = new Map();}
        append(key, value) {this.values.set(key, value);}
        get(key) {return this.values.get(key);}
    },
    XMLHttpRequest: class
    {
        constructor() {this.handlers = {}; this.upload = {addEventListener: (_, callback) => {this.progress = callback;}};}
        open() {}
        addEventListener(name, callback) {this.handlers[name] = callback;}
        send(data)
        {
            assert.equal(data.get('csrf'), '1');
            const name = data.get('jform[audio_file]').name;
            this.progress({lengthComputable: true, loaded: 100, total: 100});
            this.response = name === 'failed.wav' ? {success: false, message: 'Upload rejected'} : {success: true, data: {clip_id: name === 'first.wav' ? 1 : 2, title: name, edit_url: '#edit'}};
            this.handlers.load();
        }
    },
    fetch: async (_, request) =>
    {
        const body = new URLSearchParams(request.body);
        assert.equal(body.get('csrf'), '1');
        calls.push(body.get('clip_id'));
        return {ok: true, json: async () => body.get('clip_id') === '2' ? {success: false, message: 'Analysis unavailable'} : {success: true, data: {processed: true, success: true, remaining: calls.length === 1 ? 1 : 0}}};
    },
};
window.Joomla = context.Joomla;
vm.runInNewContext(await readFile(new URL('media/js/bulk-upload.js', base), 'utf8'), context);
assert.equal(byId('clear').hidden, true);
byId('files').files = ['first.wav','second.wav','failed.wav'].map((name) => ({name, size: 128}));
byId('files').dispatchEvent(new window.Event('change'));
assert.equal(byId('dropzone').querySelectorAll('tbody tr').length, 3);
byId('start').dispatchEvent(new window.Event('click'));
for (let step = 0; step < 100 && byId('select').disabled; step++)
{
    await new Promise((resolve) => setImmediate(resolve));
}
assert.equal(byId('select').disabled, false);
assert.deepEqual(calls, ['1','1','2']);
const rows = byId('queue').querySelectorAll('tr');
assert.match(rows[0].textContent, /2 processed, 0 failed/);
assert.match(rows[1].textContent, /Analysis unavailable/);
assert.match(rows[2].textContent, /Upload rejected/);
assert.equal(rows[0].querySelector('.progress').getAttribute('aria-valuenow'), '100');
rows[0].querySelector('a').dispatchEvent(new window.Event('click', {bubbles: true}));
assert.equal(fileClicks, 0, 'row actions must not open file chooser');
byId('clear').dispatchEvent(new window.Event('click'));
assert.equal(byId('queue').querySelectorAll('tr').length, 2);
assert.equal(byId('clear').hidden, true);
assert.match(byId('queue').textContent, /Analysis unavailable/);
assert.match(byId('queue').textContent, /Upload rejected/);
console.log('Bulk queue progress, per-file analyses, failures, clear and row actions passed.');
