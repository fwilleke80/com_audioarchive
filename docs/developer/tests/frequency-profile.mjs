/** @brief Exercise the actual frequency-profile renderer, including hidden-panel redraws. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/player.js', import.meta.url), 'utf8');
const start = source.indexOf('const formatProfileFrequency =');
const end = source.indexOf('const initialisePlayerFrequencyProfile =', start);
class Element {}
const panel = new Element();
panel.dataset = {};
panel.querySelector = () => null;
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
assert.equal(labels[0], '20 Hz');
assert.ok(labels.includes('20 kHz'));
assert.ok(labels.length > 2, 'intermediate frequencies are labelled');
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

/** @brief Verify spacing and positions for linear and logarithmic sampled axes. */
vm.runInContext('this.ticks = getProfileTicks;', sandbox);
for (const logarithmic of [false, true])
{
	const frequencies = Array.from({length: 512}, (_, i) => logarithmic
		? 20 * (1000 ** (i / 511)) : 20 + 19980 * i / 511);
	for (const width of [140, 320, 800])
	{
		const ticks = sandbox.ticks(frequencies, 8, width - 16, {measureText: text => ({width: text.length * 6})});
		assert.equal(ticks[0].frequency, 20);
		assert.equal(ticks.at(-1).frequency, 20000);
		for (let i = 0; i < ticks.length; i++)
		{
			const tick = ticks[i];
			assert.ok(tick.labelX >= 8 && tick.labelX + tick.width <= width - 8);
			if (i) assert.ok(tick.labelX >= ticks[i - 1].labelX + ticks[i - 1].width + 10);
			const expected = logarithmic ? Math.log(tick.frequency / 20) / Math.log(1000) : (tick.frequency - 20) / 19980;
			assert.ok(Math.abs(tick.x - (8 + expected * (width - 16))) < 0.1);
		}
		if (width === 800) assert.ok(ticks.length >= 5);
	}
}
console.log('Linear/logarithmic tick positions, units, bounds and collision checks passed.');

state.selectedIndex = 1;
state.width = 0;
const readout = {textContent: ''};
panel.querySelector = () => readout;
sandbox.draw({}, state);
assert.equal(readout.textContent, '200 Hz · -20.0 dB');
assert.ok(labels.includes('dB relative'));
assert.ok(labels.includes('-80'));
assert.ok(labels.some(label => label.startsWith('Peak:')));
console.log('Relative dB labels, peak label and selected-sample readout passed.');
