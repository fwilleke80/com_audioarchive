/** @brief Coordinate media-playback session ownership across players and the sampler. */
const owners = new Set();
let previousType = null;
let changed = false;

/** @brief Request audible media playback before starting Web Audio on iOS. */
export function acquirePlaybackSession(owner)
{
	if (owners.has(owner)) return;
	try
	{
		const session = globalThis.navigator?.audioSession;
		if (!session || typeof session.type !== 'string') return;
		if (owners.size === 0)
		{
			previousType = session.type;
			changed = false;
		}
		// Preserve a page's active microphone/call session when it already permits playback.
		if (session.type !== 'playback' && session.type !== 'play-and-record')
		{
			session.type = 'playback';
			changed = true;
		}
		owners.add(owner);
	}
	catch
	{
		// Unsupported/read-only implementations retain their normal audio behaviour.
	}
}

/** @brief Restore the previous category only after the last cooperating user releases it. */
export function releasePlaybackSession(owner)
{
	if (!owners.delete(owner) || owners.size !== 0) return;
	try
	{
		const session = globalThis.navigator?.audioSession;
		if (changed && session?.type === 'playback' && previousType !== null)
		{
			session.type = previousType;
		}
	}
	catch
	{
		// iOS may refuse a category change while a page is being hidden.
	}
	finally
	{
		previousType = null;
		changed = false;
	}
}
