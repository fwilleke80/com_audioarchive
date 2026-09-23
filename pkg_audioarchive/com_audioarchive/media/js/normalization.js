/** @brief Optional media-element normalization, separate from native user volume and mute. */
const graphs = new WeakMap();
const requestedGains = new WeakMap();
let sharedContext = null;

/** @brief Reject malformed or excessive playback gain without preventing playback. */
export function validGain(value)
{
	const gain = Number(value);
	return Number.isFinite(gain) && gain > 0 && gain <= 1000 ? gain : 1;
}

/** @brief Reuse one source node per element; initialize/resume only on the playback interaction. */
export async function prepareNormalization(audio, requestedGain)
{
	const gain = validGain(requestedGain);
	requestedGains.set(audio, gain);
	let graph = graphs.get(audio);
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
		graph = graphs.get(audio);
		if (!graph)
		{
			const level = context.createGain();
			level.gain.value = gain;
			level.connect(context.destination);
			const source = context.createMediaElementSource(audio);
			graph = {context, source, level};
			graphs.set(audio, graph);
			source.connect(level);
		}
		graph.level.gain.setValueAtTime(requestedGains.get(audio) ?? gain, context.currentTime);
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

/** @brief Reset/apply gain synchronously when an existing player changes clips. */
export function updateNormalization(audio, gain)
{
	requestedGains.set(audio, validGain(gain));
	const graph = graphs.get(audio);
	if (graph)
	{
		graph.level.gain.setValueAtTime(validGain(gain), graph.context.currentTime);
	}
}

/** @brief Release a finished ephemeral pad voice; persistent players keep their reusable source. */
export function releaseNormalization(audio)
{
	const graph = graphs.get(audio);
	if (graph)
	{
		graph.source.disconnect();
		graph.level.disconnect();
		graphs.delete(audio);
	}
}
