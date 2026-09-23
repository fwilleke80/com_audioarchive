/** @brief Exercise the actual frequency-profile renderer, including hidden-panel redraws. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/player.js', import.meta.url), 'utf8');
const start = source.indexOf('const formatProfileFrequency =');
const end = source.indexOf('const initialisePlayerFrequencyProfile =', start);
class Element {}
const panel = new Element();
panel.hidden = false;
let width = 320;
let clears = 0;
let fills = 0;
let strokes = 0;
const points = [];
const labels = [];
const context =
{
	setTransform() {},
	clearRect()
	{
		clears++;
	},
	fillRect() {},
	beginPath() {},
	closePath() {},
	moveTo(x, y)
	{
		points.push([x, y]);
	},
	lineTo(x, y)
	{
		points.push([x, y]);
	},
	stroke()
	{
		strokes++;
	},
	fill()
	{
		fills++;
	},
	fillText(text)
	{
		labels.push(text);
	},
	measureText: () => ({width: 35}),
};
const canvas =
{
	style: {removeProperty() {}},
	closest: () => panel,
	getBoundingClientRect: () => ({width, height: 120}),
	getContext: () => context,
};
const sandbox =
{
	HTMLElement: Element,
	window: {devicePixelRatio: 2},
	getComputedStyle: () => ({getPropertyValue: () => ''}),
};
vm.createContext(sandbox);
vm.runInContext(source.slice(start, end) + '\nthis.draw = drawPlayerFrequencyProfile;', sandbox);
const state =
{
	canvas,
	frequencies: [20, 200, 2000, 20000],
	levels: [-60, -20, 0, -40],
	dynamicRange: 80,
};
sandbox.draw({}, state);
assert.equal(fills, 1, 'the profile area is actually plotted');
assert.equal(strokes, 2, 'grid and frequency curve are drawn');
assert.equal(canvas.width, 640);
assert.ok(points.every(([x, y]) => Number.isFinite(x) && Number.isFinite(y)));
assert.deepEqual(labels, ['20 Hz', '20k Hz']);
sandbox.draw({}, state);
assert.equal(clears, 1, 'unchanged dimensions reuse the plot');
panel.hidden = true;
width = 480;
sandbox.draw({}, state);
assert.equal(clears, 1);
panel.hidden = false;
sandbox.draw({}, state);
assert.equal(clears, 2, 'showing/resizing the panel redraws the curve');
assert.equal(canvas.width, 960);
console.log('Frequency-profile plot, labels, high-DPI sizing and hidden-panel redraw checks passed.');
