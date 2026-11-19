/** @brief Exercise creation, first capture and playhead overdubbing through production DOM controls. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
const {JSDOM} = createRequire(import.meta.url)('jsdom');
const base = new URL('../../../pkg_audioarchive/com_audioarchive/media/js/', import.meta.url);
const html = execFileSync(process.env.PHP_BIN || 'php', [new URL('collection-layout-fixture.php', import.meta.url).pathname, 'soundboard'], {encoding: 'utf8'});
const dom = new JSDOM(html, {url: 'https://example.test/soundboard', runScripts: 'outside-only'});
const c = dom.getInternalVMContext();
const doc = dom.window.document;
const clip = {id: 1, uuid: '11111111-1111-4111-8111-111111111111', title: 'Clip'};
let stored = [{id: 'existing', format: 'punga-audioarchive-soundboard-recording', version: 1, name: 'Existing', durationMs: 3000, board: [clip, {...clip, id: 2, title: 'Other sound'}], initialState: {polyphony: true}, events: [{type: 'pad', t: 100, pad: 0, layer: 0}]}];
const original = structuredClone(stored[0]);
let fail = false, now = 10000, nextFrame = 1;
const frames = new Map();
c.performance.now = () => now;
c.requestAnimationFrame = callback =>
{
    const id = nextFrame++;
    frames.set(id, callback);
    return id;
};
c.cancelAnimationFrame = id => frames.delete(id);
c.prompt = () => 'New piece';
c.alert = () => {};
c.structuredClone = structuredClone;
c.validGain = value => value;
c.prepareNormalization = async () => {};
c.releaseNormalization = () => {};
c.releasePlaybackSession = () => {};
c.acquirePlaybackSession = () => {};
c.HTMLMediaElement.prototype.play = async function () {};
c.HTMLMediaElement.prototype.load = function () {};
c.HTMLMediaElement.prototype.pause = function () {};
c.fetch = async () => ({ok: true, json: async () => ({success: true, items: {1: {id: 1, normalization_gain: 1}, 2: {id: 2, normalization_gain: 1}}, routes: {}})});
c.Collections =
{
    backend: 'server', userId: 7, shared: null, sharing: false, recordingShares: [], mount() {},
    read: (key, fallback) => key.includes('recordings') ? structuredClone(stored) : key.includes('soundboard.v1') ? [clip] : fallback,
    write: async (key, value) =>
    {
        if (fail) return false;
        stored = structuredClone(value);
        return true;
    },
};
doc.querySelector('[data-audioarchive-preload]').dataset.audioarchivePreload = '0';
doc.querySelector('[data-audioarchive-stream-template]').dataset.audioarchiveStreamTemplate = '/clip/987654321.mp3';
doc.querySelector('[data-audioarchive-stream-template]').dataset.audioarchiveRoutesUrl = '/routes';
const editor = fs.readFileSync(new URL('recording-editor.js', base), 'utf8').replace(/export /g, '');
const social = fs.readFileSync(new URL('social.js', base), 'utf8').replace(/^import .*;\n/gm, '').split('\ninitialiseReturnNavigation();')[0];
vm.runInContext(editor + '\n' + social, c);
await vm.runInContext('initialiseSoundboard()', c);
const find = suffix => doc.querySelector(`[data-audioarchive-recording-${suffix}]`);
const settle = async () =>
{
    for (let i = 0; i < 4; i++) await new Promise(resolve => setImmediate(resolve));
};
assert.equal(find('record').disabled, true);
assert.equal(find('play').disabled, true);
assert.equal(doc.querySelector('[data-edit-toggle]').disabled, true);
assert.equal(find('overdub'), null, 'single Record control');
fail = true;
find('new').click();
await settle();
assert.equal(stored.length, 1, 'failed creation does not publish an empty draft');
assert.equal(find('record').disabled, true);
fail = false;
find('new').click();
await settle();
assert.equal(stored.length, 2);
assert.equal(stored[0].name, 'New piece');
assert.equal(stored[0].events.length, 0);
assert.equal(stored[0].durationMs, 0);
const id = stored[0].id;
assert.equal(find('record').disabled, false);
assert.equal(find('play').disabled, true);
assert.equal(doc.querySelector('[data-edit-toggle]').disabled, true);
find('record').click();
now += 250;
doc.querySelector('[data-audioarchive-soundboard-trigger]').click();
await settle();
now += 250;
find('record').click();
await settle();
assert.equal(stored.length, 2, 'first take updates the selected empty recording');
assert.equal(stored[0].id, id);
assert.equal(stored[0].name, 'New piece');
assert.equal(stored[0].events.find(event => event.type === 'pad').t, 250);
assert.equal(find('play').disabled, false);
doc.querySelector('[data-recording-id="existing"]').click();
find('roll').dispatchEvent(new dom.window.MouseEvent('click', {bubbles: true, clientX: 64 + 150 * ((640 - 80) / (3 * 100 * 1.05)), clientY: 0}));
find('record').click();
await settle();
assert.equal(find('record').disabled, false, 'Stop recording remains available during overdub');
assert.equal(find('new').disabled, true);
now += 250;
doc.querySelector('[data-audioarchive-soundboard-trigger]').click();
await settle();
now += 3500;
for (const [frame, callback] of [...frames])
{
    frames.delete(frame);
    callback(now);
}
assert.equal(find('new').disabled, true, 'overdub continues beyond original duration');
assert.ok(Number.parseFloat(find('roll').style.width) <= 640, 'live capture fits viewport');
find('record').click();
await settle();
const result = stored.find(recording => recording.id === 'existing');
assert.deepEqual(JSON.parse(JSON.stringify(result.events[0])), original.events[0]);
assert.equal(result.events.find(event => event.type === 'pad' && event.layer === 1).t, 1750);
assert.equal(result.durationMs, 5250);
assert.equal(result.layerBoards[1][0].id, 1);
assert.equal(find('undo').hidden, false);
find('undo').click();
await settle();
assert.equal(stored.find(recording => recording.id === 'existing').durationMs, original.durationMs);
assert.deepEqual(JSON.parse(JSON.stringify(stored.find(recording => recording.id === 'existing').events)), original.events);
assert.ok(Number.parseFloat(find('roll').style.width) <= 640, 'selection fits viewport');
find('fit').click();
find('zoom-in').click();
assert.ok(Number.parseFloat(find('roll').style.width) > 640, 'manual zoom enlarges roll');
find('fit').click();
assert.ok(Number.parseFloat(find('roll').style.width) <= 640, 'Fit restores full view');
const toggle = doc.querySelector('[data-edit-toggle]');
const parent = toggle.parentElement;
toggle.click();
assert.equal(toggle.hidden, false);
assert.equal(toggle.disabled, false);
assert.equal(toggle.parentElement, parent);
assert.equal(doc.querySelector('[data-edit-save]').hidden, false);
const event = doc.querySelector('[data-event-index]');
event.dispatchEvent(new dom.window.MouseEvent('click', {bubbles: true, clientX: 90, clientY: 26}));
assert.ok(event.classList.contains('is-selected'), 'click immediately highlights event');
const source = doc.querySelector('[data-edit-pad]');
source.value = '1';
source.dispatchEvent(new dom.window.Event('change'));
const poly = doc.querySelector('[data-edit-polyphony]');
poly.checked = false;
poly.dispatchEvent(new dom.window.Event('change'));
assert.equal(stored.find(item => item.id === 'existing').events[0].pad, 0, 'draft is isolated');
await doc.querySelector('[data-edit-save]').click();
await settle();
const edited = stored.find(item => item.id === 'existing');
assert.equal(edited.events[0].pad, 1);
assert.equal(edited.events[0].t, 100);
assert.equal(edited.initialState.polyphony, false);
toggle.click();
poly.checked = true;
poly.dispatchEvent(new dom.window.Event('change'));
toggle.click();
assert.equal(stored.find(item => item.id === 'existing').initialState.polyphony, false, 'Cancel discards polyphony change');
assert.equal(doc.querySelector('[data-edit-panel]').hidden, true);
console.log('Fit, live fitting, stable edit toggle, immediate selection, source reassignment and polyphony save/cancel passed.');
dom.window.close();
console.log('No initial selection, failed/successful creation, first take, playhead overdub, extension and undo passed.');
