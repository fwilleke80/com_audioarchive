/** @brief Bounded speculative audio cache; live playback owns its own voice lifetime. */
export class BoardPreloader
{
	/** @param {Object} options Audio context, URL and loading-state callbacks. */
	constructor(options)
	{
		this.options = options;
		this.budget = 64 * 1024 * 1024;
		this.entries = new Map();
		this.streams = new Map();
		this.wanted = new Set();
		this.bytes = 0;
		this.generation = 0;
		this.controller = null;
		this.work = Promise.resolve();
	}

	/** @brief Estimate resampled float PCM conservatively before downloading. */
	estimate(metadata, sampleRate)
	{
		const seconds = Number(metadata?.duration_ms) / 1000;
		const channels = Number(metadata?.channels);
		if (!(seconds > 0 && seconds <= 30 && channels >= 1 && channels <= 8)) return 0;
		const bytes = Math.ceil((seconds + 1) * Math.max(96000, sampleRate, Number(metadata.sample_rate) || 0) * channels * 4);
		return bytes <= 16 * 1024 * 1024 ? bytes : 0;
	}

	/** @brief Retain active buffers until their last voice ends. */
	pin(id)
	{
		const entry = this.entries.get(id);
		if (entry) entry.pins++;
		return () =>
		{
			if (entry) entry.pins--;
			this.prune();
		};
	}

	/** @brief Release obsolete inactive buffers after board changes. */
	prune()
	{
		for (const [id, entry] of this.entries)
		{
			if (!this.wanted.has(id) && !entry.pins)
			{
				this.entries.delete(id);
				this.bytes -= entry.bytes;
			}
		}
	}

	/** @brief Return a ready buffer without waiting or triggering downloads. */
	get(id)
	{
		return this.entries.get(id)?.buffer || null;
	}

	/** @brief Transfer a prepared native element to an on-demand voice. */
	takeStream(id)
	{
		const audio = this.streams.get(id);
		this.streams.delete(id);
		return audio || null;
	}

	/** @brief Cancel obsolete fetches and serialise decoding across board changes. */
	prepare(items, metadata)
	{
		const generation = ++this.generation;
		this.controller?.abort();
		this.wanted = new Set(items.map(item => item.id));
		this.prune();
		for (const audio of this.streams.values())
		{
			audio.removeAttribute('src');
			audio.load();
		}
		this.streams.clear();
		this.work = this.work.catch(() => {}).then(async () =>
		{
			for (const item of items)
			{
				if (generation !== this.generation) return;
				if (this.entries.has(item.id)) continue;
				const context = this.options.context();
				const estimate = this.estimate(metadata.get(item.id), context.sampleRate);
				if (!estimate || this.bytes + estimate > this.budget)
				{
					// Native metadata preload is a browser hint, never a full-file fetch here.
					if (this.streams.size < 2)
					{
						const audio = new Audio();
						audio.preload = 'metadata';
						audio.src = this.options.url(item.id);
						audio.load();
						this.streams.set(item.id, audio);
					}
					continue;
				}
				this.options.loading(item.id, true);
				this.controller = new AbortController();
				const timeout = setTimeout(() => this.controller?.abort(), 15000);
				try
				{
					const response = await fetch(this.options.url(item.id), {credentials:'same-origin', signal:this.controller.signal});
					const limit = 8 * 1024 * 1024;
					if (!response.ok || Number(response.headers.get('Content-Length')) > limit) throw new Error('Preload unavailable');
					if (!response.body?.getReader) throw new Error('Bounded streaming unavailable');
					const reader = response.body.getReader();
					const chunks = [];
					let length = 0;
					while (true)
					{
						const result = await reader.read();
						if (result.done) break;
						length += result.value.byteLength;
						if (length > limit) throw new Error('Preload download limit');
						chunks.push(result.value);
					}
					const encoded = new Uint8Array(length);
					let offset = 0;
					for (const chunk of chunks)
					{
						encoded.set(chunk, offset);
						offset += chunk.byteLength;
					}
					chunks.length = 0;
					if (generation !== this.generation) return;
					const buffer = await context.decodeAudioData(encoded.buffer);
					const bytes = buffer.length * buffer.numberOfChannels * 4;
					if (generation === this.generation && bytes <= estimate && this.bytes + bytes <= this.budget)
					{
						this.entries.set(item.id, {buffer, bytes, pins:0});
						this.bytes += bytes;
					}
				}
				catch
				{
					this.controller?.abort();
					// A speculative failure leaves the established on-demand path available.
				}
				finally
				{
					clearTimeout(timeout);
					this.options.loading(item.id, false);
				}
			}
		}).catch(() => {});
		return this.work;
	}
}
