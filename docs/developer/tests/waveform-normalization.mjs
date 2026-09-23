/** @brief Exercise production canvas drawing and gain-sensitive layer caching with mocked canvases. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const base = new URL('../../../pkg_audioarchive/com_audioarchive/media/js/', import.meta.url);
const source = await readFile(new URL('player.js', base), 'utf8');
const normalization = await readFile(new URL('normalization.js', base), 'utf8');
const {validGain} = await import('data:text/javascript;base64,' + Buffer.from(normalization).toString('base64'));
const start = source.indexOf('const drawPeakLayer =');
const end = source.indexOf('const drawPlayerWaveform =', start);
class Element {}
const panel = new Element();
panel.hidden = false;
const points = [];
let clears = 0;
const context = {
    setTransform() {},
    clearRect()
    {
        clears++;
    },
    beginPath() {},
    moveTo(x, y)
    {
        points.push([x, y]);
    },
    lineTo(x, y)
    {
        points.push([x, y]);
    },
    stroke() {},
};
const canvas = () => ({
    getContext: () => context,
    closest: () => panel,
    style: {removeProperty() {}},
    getBoundingClientRect: () => ({width: 100, height: 100}),
});
const sandbox = {validGain, HTMLElement: Element, window: {devicePixelRatio: 2}, getComputedStyle: () => ({getPropertyValue: () => ''})};
vm.createContext(sandbox);
vm.runInContext(source.slice(start, end) + '\nthis.prepare = prepareWaveformLayers;', sandbox);
const peaks = Object.freeze([Object.freeze([-8192, 8192])]);
const state = {canvas: canvas(), playedCanvas: canvas(), peaks};
const player = {dataset: {normalizationGain: '2'}};
assert.equal(sandbox.prepare(player, state), true);
assert.deepEqual(points, [[50,26.5],[50,73.5],[50,26.5],[50,73.5]], 'both layers scale about centre');
assert.equal(clears, 2);
sandbox.prepare(player, state);
assert.equal(clears, 2, 'unchanged gain and dimensions reuse layers');
player.dataset.normalizationGain = '1';
sandbox.prepare(player, state);
assert.equal(clears, 4, 'gain change invalidates cached layers');
assert.deepEqual(points.slice(-2), [[50,38.25],[50,61.75]]);
player.dataset.normalizationGain = '100';
sandbox.prepare(player, state);
assert.deepEqual(points.slice(-2), [[50,3],[50,97]], 'oversized coordinates stay within drawing range');
player.dataset.normalizationGain = 'invalid';
sandbox.prepare(player, state);
assert.deepEqual(points.slice(-2), [[50,38.25],[50,61.75]], 'invalid gain falls back to original scale');
assert.deepEqual(peaks, [[-8192,8192]], 'stored peaks are immutable');
console.log('Waveform drawing, gain cache and fallback assertions passed.');
