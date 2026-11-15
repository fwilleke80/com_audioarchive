/** @brief Obtain a short-lived URL after an explicit click, then use the browser's normal download path. */
document.addEventListener('click', async (event) =>
{
	const button = event.target.closest?.('[data-audioarchive-download]');
	if (!button || button.disabled)
	{
		return;
	}
	event.preventDefault();
	const status = button.closest('[data-audioarchive-download-control]')?.querySelector('[data-download-status]');
	button.disabled = true;
	button.setAttribute('aria-busy', 'true');
	if (status)
	{
		status.textContent = button.dataset.downloadBusy;
	}
	try
	{
		const response = await fetch(button.dataset.downloadEndpoint,
		{
			method: 'POST', credentials: 'same-origin', cache: 'no-store',
			body: new URLSearchParams({download_proof: button.dataset.downloadProof}),
		});
		const data = await response.json();
		if (!response.ok || !data.success || typeof data.url !== 'string')
		{
			throw new Error('Download ticket unavailable.');
		}
		const url = new URL(data.url, window.location.href);
		if (url.origin !== window.location.origin || !['https:', 'http:'].includes(url.protocol))
		{
			throw new Error('Unexpected download origin.');
		}
		// No audio blob or popup: range requests and large-file transfers stay with the browser.
		window.location.assign(url.toString());
		if (status)
		{
			status.textContent = '';
		}
	}
	catch (error)
	{
		if (status)
		{
			status.textContent = button.dataset.downloadError;
		}
	}
	finally
	{
		button.disabled = false;
		button.removeAttribute('aria-busy');
	}
});
