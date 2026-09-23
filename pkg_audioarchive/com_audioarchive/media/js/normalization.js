import {acquirePlaybackSession, releasePlaybackSession} from './audio-session.js?v=0.13.2.7';
/**
 * @brief Normalised varispeed playback without MediaElementAudioSourceNode.
 * @details Keep native streaming when gain is unity or decoding is unavailable.
 * Enhanced players share a facade; the DOM element itself is never patched.
 */
const controllers = new WeakMap();
const padGraphs = new WeakMap();
const padGains = new WeakMap();
const decoded = new Map();
const loading = new Map();
const CACHE_BYTES = 64 * 1024 * 1024;
const MAX_ENCODED_BYTES = 32 * 1024 * 1024;
const MAX_DECODED_BYTES = 128 * 1024 * 1024;
let cachedBytes = 0;
let sharedContext = null;

/** @brief Reject malformed or excessive gain without preventing playback. */
export function validGain(value)
{
	const gain = Number(value);
	return Number.isFinite(gain) && gain > 0 && gain <= 1000 ? gain : 1;
}

/** @brief Bound the download even when the server omits Content-Length. */
async function readAudio(response)
{
	if (!response.ok || Number(response.headers.get('Content-Length')) > MAX_ENCODED_BYTES)
	{
		await response.body?.cancel();
		throw new Error('Audio response is unavailable or too large for buffer playback.');
	}
	if (!response.body?.getReader)
	{
		const bytes = await response.arrayBuffer();
		if (bytes.byteLength > MAX_ENCODED_BYTES)
		{
			throw new Error('Audio response exceeds the buffer limit.');
		}
		return bytes;
	}
	const reader = response.body.getReader();
	const chunks = [];
	let size = 0;
	try
	{
		while (true)
		{
			const {done, value} = await reader.read();
			if (done)
			{
				break;
			}
			size += value.byteLength;
			if (size > MAX_ENCODED_BYTES)
			{
				await reader.cancel();
				throw new Error('Audio response exceeds the buffer limit.');
			}
			chunks.push(value);
		}
	}
	finally
	{
		reader.releaseLock();
	}
	const bytes = new Uint8Array(size);
	let offset = 0;
	for (const chunk of chunks)
	{
		bytes.set(chunk, offset);
		offset += chunk.byteLength;
	}
	return bytes.buffer;
}

/** @brief Reuse decoded clips within this page only, with an LRU cache budget. */
async function getBuffer(context, url)
{
	if (decoded.has(url))
	{
		const entry = decoded.get(url);
		decoded.delete(url);
		decoded.set(url, entry);
		return entry.buffer;
	}
	if (!loading.has(url))
	{
		const request = (async () =>
		{
			const response = await fetch(url, {credentials: 'same-origin'});
			const buffer = await context.decodeAudioData(await readAudio(response));
			const bytes = buffer.length * buffer.numberOfChannels * 4;
			if (bytes > MAX_DECODED_BYTES || !Number.isFinite(buffer.duration) || buffer.duration <= 0)
			{
				throw new Error('Decoded audio exceeds the buffer limit.');
			}
			if (bytes <= CACHE_BYTES)
			{
				while (cachedBytes + bytes > CACHE_BYTES && decoded.size)
				{
					const oldest = decoded.keys().next().value;
					cachedBytes -= decoded.get(oldest).bytes;
					decoded.delete(oldest);
				}
				decoded.set(url, {buffer, bytes});
				cachedBytes += bytes;
			}
			return buffer;
		})();
		loading.set(url, request);
		// Register both outcomes without creating an unhandled rejected promise.
		request.then(() => loading.delete(url), () => loading.delete(url));
	}
	return loading.get(url);
}

/** @brief An application-only media facade preserving the element's DOM/event API. */
class NormalizedPlayback
{
	/** @brief Track transport state independently of the idle native audio element. */
	constructor(element)
	{
		this.element = element;
		this.gain = 1;
		this.buffer = null;
		this.bufferMode = false;
		this.duration = NaN;
		this.context = null;
		this.node = null;
		this.level = null;
		this.offset = element.currentTime || 0;
		this.rate = element.playbackRate || 1;
		this.volume = element.volume;
		this.muted = element.muted;
		this.startedAt = 0;
		this.playing = false;
		this.pending = false;
		this.finished = false;
		this.generation = 0;
		this.timer = null;
		this.proxy = new Proxy(element,
		{
			get: (target, key) =>
			{
				if (key === 'play') return () => this.play();
				if (key === 'pause') return () => this.pause();
				if (key === 'load') return () =>
				{
					this.reset();
					target.load();
					target.playbackRate = this.rate;
				};
				if (key === 'removeAttribute') return (name) =>
				{
					if (name === 'src') this.reset();
					target.removeAttribute(name);
				};
				if (key === 'currentTime') return this.position();
				if (key === 'duration' && this.bufferMode) return this.duration;
				if (key === 'paused') return this.pending ? false : (this.bufferMode ? !this.playing : target.paused);
				if (key === 'ended' && this.bufferMode) return this.finished;
				if (key === 'volume') return this.volume;
				if (key === 'muted') return this.muted;
				const value = Reflect.get(target, key, target);
				return typeof value === 'function' ? value.bind(target) : value;
			},
			set: (target, key, value) =>
			{
				if (key === 'currentTime')
				{
					this.seek(Number(value));
					return true;
				}
				if (key === 'src') this.reset();
				const position = this.position();
				Reflect.set(target, key, value, target);
				if (key === 'playbackRate')
				{
					this.offset = position;
					this.startedAt = this.context?.currentTime || 0;
					this.rate = target.playbackRate;
					this.node?.playbackRate.setValueAtTime(this.rate, this.context.currentTime);
				}
				if (key === 'volume' || key === 'muted')
				{
					this[key] = key === 'volume' ? Number(value) : Boolean(value);
					this.applyGain();
					// iOS may ignore native volume writes; our gain node still honours them.
					this.emit('volumechange');
				}
				if (key === 'loop' && this.node) this.node.loop = Boolean(value);
				return true;
			},
		});
	}

	/** @brief Keep events on the original element so existing listeners remain valid. */
	emit(name)
	{
		this.element.dispatchEvent(new Event(name));
	}

	/** @brief Derive progress from the audio clock, never from a JavaScript timer. */
	position()
	{
		if (!this.bufferMode) return this.pending ? this.offset : this.element.currentTime;
		const position = this.offset + (this.playing ? (this.context.currentTime - this.startedAt) * this.rate : 0);
		return this.element.loop ? position % this.duration : Math.min(this.duration, position);
	}

	/** @brief Keep the user's volume/mute independent from normalisation gain. */
	applyGain()
	{
		this.level?.gain.setValueAtTime(this.muted ? 0 : this.volume * this.gain, this.context.currentTime);
	}

	/** @brief Disconnect a one-shot source; stopping must not generate a natural end event. */
	stopNode()
	{
		clearInterval(this.timer);
		this.timer = null;
		if (this.node)
		{
			this.node.onended = null;
			try
			{
				this.node.stop();
			}
			catch
			{
				// A source whose start failed is still safe to disconnect.
			}
			this.node.disconnect();
			this.node.buffer = null;
			this.node = null;
		}
	}

	/** @brief Start/resume from the current offset with native Web Audio varispeed. */
	startNode()
	{
		this.stopNode();
		const node = this.context.createBufferSource();
		node.buffer = this.buffer;
		node.loop = this.element.loop;
		node.playbackRate.value = this.rate;
		node.connect(this.level);
		node.onended = () =>
		{
			if (this.node !== node) return;
			this.offset = this.duration;
			this.playing = false;
			this.finished = true;
			this.stopNode();
			this.releaseBuffer();
			releasePlaybackSession(this);
			this.emit('timeupdate');
			this.emit('pause');
			this.emit('ended');
		};
		this.node = node;
		this.startedAt = this.context.currentTime;
		node.start(0, this.offset);
		this.playing = true;
		this.finished = false;
		// Notification cadence only; the audio engine schedules every sample.
		this.timer = setInterval(() => this.emit('timeupdate'), 200);
	}

	/** @brief Cancel pending starts and preserve the audible position for resume. */
	pause()
	{
		const active = this.playing || this.pending;
		this.offset = this.position();
		this.generation++;
		this.pending = false;
		this.playing = false;
		this.stopNode();
		this.releaseBuffer();
		releasePlaybackSession(this);
		this.element.pause();
		if (active) this.emit('pause');
	}

	/** @brief Paused/finished players must not pin buffers evicted from the shared cache. */
	releaseBuffer()
	{
		this.buffer = null;
		this.level?.disconnect();
		this.level = null;
	}

	/** @brief Drop per-player buffers and graph references on source changes/removal. */
	reset()
	{
		this.pause();
		this.bufferMode = false;
		this.duration = NaN;
		this.offset = 0;
		this.finished = false;
	}

	/** @brief Seek paused or running buffers without duplicating play-count events. */
	seek(position)
	{
		if (!Number.isFinite(position)) return;
		this.offset = Math.max(0, this.bufferMode ? Math.min(position, this.duration) : position);
		this.finished = false;
		if (!this.bufferMode)
		{
			this.element.currentTime = this.offset;
			return;
		}
		this.emit('seeking');
		if (this.playing) this.startNode();
		this.emit('timeupdate');
		this.emit('seeked');
	}

	/** @brief Load on demand; a later pause/source change always cancels this start. */
	async play()
	{
		if (this.playing || this.pending) return;
		if (!this.bufferMode && this.gain === 1) return this.element.play();
		const token = ++this.generation;
		this.offset = this.position();
		this.pending = true;
		acquirePlaybackSession(this);
		this.emit('waiting');
		try
		{
			const Context = window.AudioContext || window.webkitAudioContext;
			if (!Context) throw new Error('Web Audio unavailable.');
			this.context = sharedContext ||= new Context();
			// Resume and unlock within the original user interaction, before fetching.
			const resume = this.context.state === 'running' ? Promise.resolve() : this.context.resume();
			const unlock = this.context.createBufferSource();
			unlock.buffer = this.context.createBuffer(1, 1, this.context.sampleRate);
			unlock.connect(this.context.destination);
			unlock.onended = () => unlock.disconnect();
			unlock.start();
			await resume;
			if (token !== this.generation) return;
			if (this.context.state !== 'running') throw new Error('Audio context did not resume.');
			if (!this.buffer)
			{
				// Conservatively avoid decoding very long recordings on mobile devices.
				if (Number.isFinite(this.element.duration) && this.element.duration * this.context.sampleRate * 8 > MAX_DECODED_BYTES)
				{
					throw new Error('Recording exceeds the buffer duration budget.');
				}
				const url = this.element.getAttribute('src') || this.element.querySelector('source[src]')?.src || this.element.currentSrc;
				if (!url) throw new Error('No audio source.');
				const buffer = await getBuffer(this.context, url);
				if (token !== this.generation) return;
				this.buffer = buffer;
				this.bufferMode = true;
				this.duration = buffer.duration;
				this.level = this.context.createGain();
				this.level.connect(this.context.destination);
				this.emit('durationchange');
			}
			if (token !== this.generation) return;
			// A phone interruption can suspend the context during download/decode.
			if (this.context.state !== 'running') await this.context.resume();
			if (token !== this.generation) return;
			if (this.context.state !== 'running') throw new Error('Audio context was interrupted.');
			this.pending = false;
			if (this.offset >= this.buffer.duration) this.offset = 0;
			this.applyGain();
			this.startNode();
			this.emit('play');
			if (this.playing) this.emit('playing');
		}
		catch (error)
		{
			if (token !== this.generation) return;
			this.pending = false;
			this.bufferMode = false;
			this.buffer = null;
			this.stopNode();
			this.level?.disconnect();
			this.level = null;
			this.playing = false;
			releasePlaybackSession(this);
			// Preserve the established native fallback when Web Audio cannot be used.
			// Never reconnect a media element to Web Audio, including on failure.
			this.element.currentTime = this.offset;
			this.element.dispatchEvent(new CustomEvent('audioarchive:normalizationfallback', {detail: {reason: error.message}}));
			return this.element.play();
		}
	}
}

/** @brief Return the same facade for a DOM element across player, playlist and share UI. */
export function playbackFor(audio)
{
	if (!audio) return audio;
	if (!controllers.has(audio))
	{
		const controller = new NormalizedPlayback(audio);
		controllers.set(audio, controller);
		controllers.set(controller.proxy, controller);
	}
	return controllers.get(audio).proxy;
}

/** @brief Start native/buffer playback as one cancellable operation. */
export function playNormalized(audio, gain)
{
	const playback = playbackFor(audio);
	updateNormalization(playback, gain);
	return playback.play();
}

/** @brief Apply gain without altering transport rate, user volume or waveform data. */
export function updateNormalization(audio, gain)
{
	playbackFor(audio);
	const controller = controllers.get(audio);
	controller.gain = validGain(gain);
	controller.applyGain();
}

/** @brief Release a finished sound-board voice, including any pending start. */
export function releaseNormalization(audio)
{
	controllers.get(audio)?.reset();
	const graph = padGraphs.get(audio);
	if (graph)
	{
		graph.source.disconnect();
		graph.level.disconnect();
		padGraphs.delete(audio);
	}
}

/**
 * @brief Preserve streaming normalisation for ordinary sound-board pads at fixed 1x.
 * @details Pads have no varispeed control. Their existing path avoids introducing
 * a full-file decoding delay into recorded pad events. Speed-capable players use
 * playNormalized instead; the chromatic sampler already uses buffer sources.
 */
export async function prepareNormalization(audio, requestedGain)
{
	const gain = validGain(requestedGain);
	padGains.set(audio, gain);
	let graph = padGraphs.get(audio);
	if (!graph && gain === 1)
	{
		return;
	}
	try
	{
		const AudioContextClass = window.AudioContext || window.webkitAudioContext;
		if (!AudioContextClass)
		{
			return;
		}
		const context = graph?.context || (sharedContext ||= new AudioContextClass());
		// Do not redirect native audio into a suspended graph when activation is refused.
		if (context.state !== 'running')
		{
			await context.resume();
		}
		if (context.state !== 'running')
		{
			return;
		}
		graph = padGraphs.get(audio);
		if (!graph)
		{
			const level = context.createGain();
			level.gain.value = gain;
			level.connect(context.destination);
			const source = context.createMediaElementSource(audio);
			graph = {context, source, level};
			padGraphs.set(audio, graph);
			source.connect(level);
		}
		graph.level.gain.setValueAtTime(padGains.get(audio) ?? gain, context.currentTime);
	}
	catch
	{
		if (graph)
		{
			graph.level.gain.value = 1;
		}
		// Initial setup failure leaves the ordinary media element playable.
	}
}

