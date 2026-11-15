/** @brief Record actual embedded playback once per frame; previews do not count. */
const root = document.querySelector('[data-embed-event-url]');
const audio = root?.querySelector('audio');
let recorded = false;
const preview = new URL(window.location.href).searchParams.get('preview') === '1';
audio?.addEventListener('playing', () =>
{
	if (recorded || preview)
	{
		return;
	}
	recorded = true;
	fetch(root.dataset.embedEventUrl, {
		method: 'POST', credentials: 'same-origin',
		body: new URLSearchParams({embed_token: root.dataset.embedToken}),
	}).catch(() => {});
});
