/** @brief Exercise complete Sound Board initialization with empty browser storage and server data.
 * @details Requires jsdom: npm install --prefix /tmp/audioarchive-tests jsdom
 * Run with NODE_PATH=/tmp/audioarchive-tests/node_modules node soundboard-startup.mjs.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {createRequire} from 'node:module';
const {JSDOM} = createRequire(import.meta.url)('jsdom');
const base = new URL('../../../pkg_audioarchive/com_audioarchive/media/js/', import.meta.url);
const adapter = fs.readFileSync(new URL('collections.js', base), 'utf8').replace('export const Collections', 'const Collections');
const source = fs.readFileSync(new URL('social.js', base), 'utf8').replace(/^import .*;\n/gm, '').split('\ninitialiseReturnNavigation();')[0];
const clip = {id: 1, uuid: 'a'.repeat(32), title: 'Saved clip'};
const song = {id: 'song', format: 'punga-audioarchive-soundboard-recording', version: 1, name: 'Account song', durationMs: 1000, board: [clip], initialState: {polyphony: true}, events: [{type: 'pad', t: 0, pad: 0}]};
for (const mode of ['account', 'shared', 'unavailable'])
{
    const shared = mode !== 'account';
    const dom = new JSDOM(`<main><div class="com-audioarchive" data-audioarchive-soundboard data-audioarchive-shared-recording="${shared ? 1 : 0}" data-audioarchive-pad-count="4" data-audioarchive-preload="0">
    <div data-audioarchive-soundboard-pad><span data-audioarchive-soundboard-title></span></div>
    <details><div data-audioarchive-soundboard-recordings><div data-audioarchive-recording-list></div><span data-audioarchive-recording-title></span></div></details>
    </div></main>`, {url: 'https://example.test/soundboard' + (shared ? '?aa_recording=' + 'b'.repeat(48) : ''), runScripts: 'outside-only'});
    const context = dom.getInternalVMContext();
    context.fetch = async (url, options) =>
    {
        assert.equal(options.method, undefined, 'opening a page must not save or import anything');
        if (url.searchParams.get('task') === 'collection.sharedRecording')
        {
            return {ok: mode !== 'unavailable', json: async () => mode === 'unavailable' ? {success: false, message: 'Unavailable'} : {success: true, data: {recording: {...song, name: 'Shared song'}, unavailable: 0}}};
        }
        return {ok: true, json: async () => ({success: true, data: {backend: shared ? 'browser' : 'server', userId: shared ? 0 : 7, revision: 2, token: 'csrf', sharing: true, playlists: [], boards: shared ? [] : [{id: 'one', name: 'Board one', items: [clip]}, {id: 'two', name: 'Board two', items: []}, {id: 'three', name: 'Board three', items: []}], defaultBoard: 'one', recordings: shared ? [] : [song], labels: {server: 'Account storage'}}})};
    };
    vm.runInContext(adapter + '\n' + source, context);
    await vm.runInContext('Collections.ready().then(() => initialiseSoundboard())', context);
    const document = dom.window.document;
    if (mode === 'account')
    {
        assert.equal(document.querySelector('[data-audioarchive-soundboard-title]').textContent, clip.title);
        assert.equal(document.querySelector('[data-audioarchive-recording-list] strong').textContent, song.name);
        assert.deepEqual([...document.querySelectorAll('select option')].map(option => option.textContent), ['Board one ★', 'Board two', 'Board three']);
    }
    else if (mode === 'shared')
    {
        assert.equal(document.querySelector('[data-audioarchive-recording-list] strong').textContent, 'Shared song');
        assert.equal(document.querySelector('select'), null, 'guest share does not mount account controls');
    }
    else assert.equal(document.querySelector('[data-audioarchive-recording-list] strong'), null);
    assert.equal(dom.window.localStorage.length, 0, 'initialization never writes browser copies');
    dom.window.close();
}
console.log('Fresh-browser account boards and recordings, guest recording share and unavailable-link startup passed.');
