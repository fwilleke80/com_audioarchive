/** @brief Verify reusable gain graphs, user volume independence and activation fallback. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/normalization.js', import.meta.url), 'utf8');
let sources = 0;
let levels = [];
let blocked = false;
class Context
{
    state = 'suspended';
    currentTime = 0;
    destination = {};
    async resume()
    {
        if (!blocked) this.state = 'running';
    }
    createGain()
    {
        const node = {gain: {value: 1, setValueAtTime(value) {this.value = value;}}, connect() {}, disconnect() {}};
        levels.push(node);
        return node;
    }
    createMediaElementSource()
    {
        sources++;
        return {connect() {}, disconnect() {}};
    }
}
globalThis.window = {AudioContext: Context};
const load = async (suffix) => import('data:text/javascript;base64,' + Buffer.from(source + '\n//' + suffix).toString('base64'));
const module = await load('normal');
const audio = {volume: 0.3, muted: true};
await module.prepareNormalization(audio, 1);
assert.equal(sources, 0);
await Promise.all([module.prepareNormalization(audio, 2), module.prepareNormalization(audio, 3)]);
assert.equal(sources, 1, 'concurrent activation creates only one source');
assert.equal(levels[0].gain.value, 3);
await module.prepareNormalization(audio, 0.5);
assert.equal(sources, 1);
assert.equal(levels[0].gain.value, 0.5);
assert.equal(audio.volume, 0.3);
assert.equal(audio.muted, true);
module.updateNormalization(audio, undefined);
assert.equal(levels[0].gain.value, 1);
for (const value of [NaN, Infinity, -2, 0, 1001]) assert.equal(module.validGain(value), 1);
blocked = true;
const blockedModule = await load('blocked');
await blockedModule.prepareNormalization({}, 2);
assert.equal(sources, 1, 'refused activation preserves native audio routing');
window = {};
const unsupported = await load('unsupported');
await unsupported.prepareNormalization({}, 2);
assert.equal(sources, 1);
console.log('Normalization browser logic assertions passed.');
