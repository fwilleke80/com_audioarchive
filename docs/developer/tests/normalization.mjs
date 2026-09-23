/** @brief Exercise the production transport with a deterministic audio clock and media elements. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';

let source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/normalization.js', import.meta.url), 'utf8');
const sessionSource = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/audio-session.js', import.meta.url), 'utf8');
source = source.replace('./audio-session.js?v=0.13.2.7', 'data:text/javascript;base64,' + Buffer.from(sessionSource).toString('base64'));
const session = {type: 'auto'};
Object.defineProperty(globalThis, 'navigator', {value: {audioSession: session}, configurable: true});
const contexts = [];
const sources = [];
const levels = [];
const requests = [];
const timers = new Set();
let decodeFails = false;
let interruptDecode = false;
let blocked = false;
let deferFetch = null;
let decodedLength = 480000;
let responseSize = 8;

globalThis.setInterval = (callback) =>
{
	timers.add(callback);
	return callback;
};
globalThis.clearInterval = (callback) => timers.delete(callback);
globalThis.CustomEvent = class extends Event
{
	constructor(type, options)
	{
		super(type);
		this.detail = options?.detail;
	}
};

/** @brief Native methods deliberately depend on their receiver, as browser DOM methods do. */
class Audio extends EventTarget
{
	constructor(src = '/clip-a')
	{
		super();
		this.src = src;
		this.currentTime = 0;
		this.duration = 10;
		this.playbackRate = 1;
		this.volume = 0.4;
		this.muted = false;
		this.paused = true;
		this.ended = false;
		this.loop = false;
		this.nativeStarts = 0;
		this.dataset = {};
	}
	getAttribute(name)
	{
		return this[name];
	}
	removeAttribute(name)
	{
		this[name] = '';
	}
	querySelector()
	{
		return null;
	}
	async play()
	{
		this.nativeStarts++;
		this.paused = false;
		this.dispatchEvent(new Event('play'));
	}
	pause()
	{
		if (!this.paused)
		{
			this.paused = true;
			this.dispatchEvent(new Event('pause'));
		}
	}
	load()
	{
		this.currentTime = 0;
		this.pause();
	}
}

/** @brief Model sample-clock scheduling; a media-element graph is forbidden in this test. */
class Context
{
	constructor()
	{
		assert.equal(session.type, 'playback', 'session is selected before constructing Web Audio');
		this.resumes = 0;
		this.state = 'suspended';
		this.currentTime = 0;
		this.sampleRate = 48000;
		this.destination = {};
		contexts.push(this);
	}
	async resume()
	{
		this.resumes++;
		if (!blocked) this.state = 'running';
	}
	createMediaElementSource()
	{
		throw new Error('Regression: media-element routing must never be used.');
	}
	createBuffer(channels, length, rate)
	{
		return {numberOfChannels: channels, length, duration: length / rate};
	}
	async decodeAudioData()
	{
		if (decodeFails) throw new Error('Unsupported codec');
		if (interruptDecode) this.state = 'interrupted';
		return this.createBuffer(2, decodedLength, this.sampleRate);
	}
	createGain()
	{
		const level =
		{
			gain:
			{
				value: 1,
				setValueAtTime(value)
				{
					this.value = value;
				},
			},
			connect() {},
			disconnect() {},
		};
		levels.push(level);
		return level;
	}
	createBufferSource()
	{
		const node =
		{
			buffer: null,
			playbackRate:
			{
				value: 1,
				setValueAtTime(value)
				{
					this.value = value;
				},
			},
			connect() {},
			disconnect() {},
			start(when = 0, offset = 0)
			{
				this.startOffset = offset;
				this.started = true;
			},
			stop()
			{
				this.stopped = true;
			},
		};
		sources.push(node);
		return node;
	}
}

globalThis.window = {AudioContext: Context};
globalThis.fetch = async (url, options) =>
{
	assert.equal(options.credentials, 'same-origin');
	requests.push(url);
	if (deferFetch) await deferFetch;
	return {ok: true, headers: {get: () => String(responseSize)}, arrayBuffer: async () => new ArrayBuffer(8)};
};
const load = (suffix) => import('data:text/javascript;base64,' + Buffer.from(source + '\n//' + suffix).toString('base64'));
const {playbackFor, playNormalized, updateNormalization, releaseNormalization, prepareNormalization, validGain} = await load('transport');
const native = new Audio();
const audio = playbackFor(native);
assert.ok(audio instanceof Audio);
assert.equal(playbackFor(native), audio, 'player, playlist and share links use the same clock');
assert.equal(playbackFor(audio), audio);
for (const value of [NaN, Infinity, -2, 0, 1001]) assert.equal(validGain(value), 1);
await playNormalized(audio, 1);
assert.equal(native.nativeStarts, 1);
assert.equal(contexts.length, 0, 'disabled/unavailable normalisation retains native streaming');
audio.pause();

let plays = 0;
let ends = 0;
audio.addEventListener('play', () => plays++);
audio.addEventListener('ended', () => ends++);
audio.currentTime = 2;
audio.playbackRate = 0.5;
await playNormalized(audio, 2);
assert.equal(session.type, 'playback');
const context = contexts[0];
let node = sources.at(-1);
assert.equal(node.startOffset, 2, 'shared-link start offset survives decoding');
assert.equal(node.playbackRate.value, 0.5);
assert.equal(levels.at(-1).gain.value, 0.8, 'normalisation and user volume multiply once');
assert.equal(native.nativeStarts, 1, 'normalised playback never starts the media element');
context.currentTime = 4;
assert.equal(audio.currentTime, 4, 'half-speed progress advances two seconds in four clock seconds');
const sourceCount = sources.length;
audio.playbackRate = 0.25;
assert.equal(sources.length, sourceCount, 'speed changes never stop or recreate the running source');
context.currentTime = 8;
assert.equal(audio.currentTime, 5, 'quarter-speed position is continuous at rate changes');
audio.volume = 0.2;
assert.equal(levels.at(-1).gain.value, 0.4);
audio.muted = true;
assert.equal(levels.at(-1).gain.value, 0);
audio.muted = false;
updateNormalization(audio, 3);
assert.ok(Math.abs(levels.at(-1).gain.value - 0.6) < 1e-10);
assert.equal(audio.playbackRate, 0.25);
assert.equal(audio.volume, 0.2);

audio.pause();
assert.equal(audio.currentTime, 5);
assert.equal(session.type, 'auto', 'pause releases the media session');
context.currentTime = 20;
assert.equal(audio.currentTime, 5, 'paused clock stays still');
const downloads = requests.length;
await playNormalized(audio, 3);
assert.equal(requests.length, downloads, 'resume reuses bounded decoded cache');
assert.equal(sources.at(-1).startOffset, 5);
audio.currentTime = 7;
node = sources.at(-1);
assert.equal(node.startOffset, 7);
assert.equal(plays, 2, 'seek does not count a new play');
context.currentTime += 4;
assert.equal(audio.currentTime, 8);
node.onended();
assert.equal(audio.ended, true);
assert.equal(audio.paused, true);
assert.equal(ends, 1);
assert.equal(timers.size, 0);
await playNormalized(audio, 3);
assert.equal(sources.at(-1).startOffset, 0, 'replay restarts at zero');
audio.pause();

// Loop offsets, including changing rate while a source remains alive.
audio.loop = true;
audio.currentTime = 9;
audio.playbackRate = 1;
await playNormalized(audio, 2);
context.currentTime += 3;
assert.equal(audio.currentTime, 2);
audio.playbackRate = 0.5;
context.currentTime += 2;
assert.equal(audio.currentTime, 3);
audio.loop = false;
audio.pause();

// Rapid stop/source changes while the old response is still outstanding.
let resolveFetch;
deferFetch = new Promise((resolve) => {resolveFetch = resolve;});
audio.src = '/slow-old';
audio.load();
const oldStart = playNormalized(audio, 2);
await Promise.resolve();
assert.equal(audio.paused, false, 'a pending start can be cancelled with the pause button');
audio.pause();
audio.src = '/next-native';
audio.load();
await playNormalized(audio, 1);
assert.equal(native.nativeStarts, 2);
resolveFetch();
await oldStart;
assert.equal(native.nativeStarts, 2, 'old request cannot start the new native playlist item');
assert.equal(sources.filter((entry) => entry.started && !entry.stopped && entry.buffer?.length > 1).length, 0);
deferFetch = null;
audio.pause();

// Cleanup while loading a pad must never resurrect that voice.
let resolvePad;
deferFetch = new Promise((resolve) => {resolvePad = resolve;});
const pad = playbackFor(new Audio('/slow-pad'));
const pendingPad = playNormalized(pad, 2);
await Promise.resolve();
releaseNormalization(pad);
resolvePad();
await pendingPad;
assert.equal(pad.paused, true);
deferFetch = null;

// Failed decoding remains playable and emits a fallback reason.
decodeFails = true;
audio.src = '/unsupported';
audio.load();
let fallback = '';
audio.addEventListener('audioarchive:normalizationfallback', (event) => {fallback = event.detail.reason;});
await playNormalized(audio, 2);
assert.equal(native.nativeStarts, 3);
assert.equal(fallback, 'Unsupported codec');
audio.pause();
decodeFails = false;
responseSize = 40 * 1024 * 1024;
audio.src = '/large';
audio.load();
await playNormalized(audio, 2);
assert.equal(native.nativeStarts, 4, 'large downloads use native streaming instead of unbounded buffering');
audio.pause();
responseSize = 8;

// Resume denial and absent Web Audio preserve the native path.
blocked = true;
const denied = await load('denied');
const deniedAudio = new Audio('/denied');
await denied.playNormalized(deniedAudio, 2);
assert.equal(deniedAudio.nativeStarts, 1);
blocked = false;
window = {};
const absent = await load('absent');
const absentAudio = new Audio('/absent');
await absent.playNormalized(absentAudio, 2);
assert.equal(absentAudio.nativeStarts, 1);
window = {AudioContext: Context};

// Cache eviction and oversized decoded files are bounded independently of encoding.
decodedLength = 5 * 1024 * 1024;
for (const url of ['/cache-a', '/cache-b', '/cache-c'])
{
	const cached = playbackFor(new Audio(url));
	await playNormalized(cached, 2);
	cached.pause();
}
const beforeEvictionReload = requests.length;
const evicted = playbackFor(new Audio('/cache-a'));
await playNormalized(evicted, 2);
assert.equal(requests.length, beforeEvictionReload + 1, 'LRU cache evicts old 40 MiB buffers');
evicted.pause();
decodedLength = 20 * 1024 * 1024;
const hugeNative = new Audio('/huge-decoded');
await playNormalized(hugeNative, 2);
assert.equal(hugeNative.nativeStarts, 1);
decodedLength = 480000;

// Concurrent preparations for one URL share a download and use the latest gain.
const concurrentNative = new Audio('/concurrent');
const concurrent = playbackFor(concurrentNative);
const beforeConcurrent = requests.length;
await Promise.all([playNormalized(concurrent, 2), playNormalized(concurrent, 4)]);
assert.equal(requests.length, beforeConcurrent + 1);
assert.equal(levels.at(-1).gain.value, 1.6);
concurrent.pause();

// A natural end can synchronously advance a playlist without an old playing event.
const queueAudio = playbackFor(new Audio('/queue-a'));
let advance;
queueAudio.addEventListener('ended', () =>
{
	queueAudio.src = '/queue-b';
	queueAudio.load();
	advance = playNormalized(queueAudio, 4);
});
await playNormalized(queueAudio, 2);
sources.at(-1).onended();
await advance;
assert.equal(levels.at(-1).gain.value, 1.6);
assert.equal(queueAudio.currentTime, 0);
assert.equal(queueAudio.paused, false);
releaseNormalization(queueAudio);
releaseNormalization(audio);
assert.equal(timers.size, 0, 'cleanup leaves no transport notification timers');
// Recover a context interrupted during the asynchronous download/decode phase.
interruptDecode = true;
const interrupted = playbackFor(new Audio('/interrupted'));
const beforeResume = contexts[0].resumes;
await playNormalized(interrupted, 2);
assert.equal(contexts[0].resumes, beforeResume + 1);
assert.equal(interrupted.paused, false);
assert.equal(session.type, 'playback');
interrupted.pause();
assert.equal(session.type, 'auto');
interruptDecode = false;

// Ordinary pad playback keeps its streaming graph and reusable source at 1x.
let padSources = 0;
contexts[0].createMediaElementSource = (element) =>
{
	assert.equal(element.playbackRate, 1);
	padSources++;
	return {connect() {}, disconnect() {}};
};
const fixedPad = new Audio('/fixed-pad');
await prepareNormalization(fixedPad, 2);
assert.equal(padSources, 1);
await prepareNormalization(fixedPad, 3);
assert.equal(padSources, 1);
assert.equal(levels.at(-1).gain.value, 3);
assert.equal(fixedPad.nativeStarts, 0, 'pad scheduling still decides when native playback starts');
releaseNormalization(fixedPad);
console.log('Normalisation transport: clock, speed, seek, resume, gain, cancellation, playlist and fallback checks passed.');
