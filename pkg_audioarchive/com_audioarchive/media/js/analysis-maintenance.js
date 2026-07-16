/**
 * @brief Process queued Audio Archive analysis jobs one request at a time.
 */
const initialiseAudioArchiveAnalysisMaintenance = () =>
{
	const root = document.querySelector('[data-audioarchive-analysis-maintenance]');

	if (!(root instanceof HTMLElement))
	{
		return;
	}

	const processButton = root.querySelector('[data-audioarchive-process-analyses]');
	const regenerateSpectrogramsButton = root.querySelector('[data-audioarchive-regenerate-spectrograms]');
	const progress = root.querySelector('[data-audioarchive-analysis-progress]');
	const progressBar = root.querySelector('[data-audioarchive-analysis-progress-bar]');
	const status = root.querySelector('[data-audioarchive-analysis-status]');
	const processUrl = root.dataset.processUrl || '';
	const regenerateSpectrogramsUrl = root.dataset.regenerateSpectrogramsUrl || '';
	const tokenName = root.dataset.tokenName || '';
	const progressTemplate = root.dataset.progressTemplate || '';
	const failureText = root.dataset.failureText || '';
	const regenerateSpectrogramsConfirm = root.dataset.regenerateSpectrogramsConfirm || '';
	const regenerateSpectrogramsQueued = root.dataset.regenerateSpectrogramsQueued || '';
	let processing = false;

	if (
		!(processButton instanceof HTMLButtonElement)
		|| processUrl === ''
		|| tokenName === ''
		|| progressTemplate === ''
		|| failureText === ''
	)
	{
		return;
	}

	/**
	 * @brief Set the visible analysis status text.
	 *
	 * @param {string} message Status message.
	 *
	 * @return {void}
	 */
	const setStatus = (message) =>
	{
		if (status instanceof HTMLElement)
		{
			status.textContent = message;
		}
	};

	/**
	 * @brief Submit a CSRF-protected maintenance request and decode its JSON response.
	 *
	 * @param {string} url Request URL.
	 *
	 * @return {Promise<object>} Decoded response data.
	 */
	const postMaintenanceRequest = async (url) =>
	{
		const body = new URLSearchParams();
		body.set(tokenName, '1');
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			credentials: 'same-origin',
			body: body.toString(),
		});
		const payload = await response.json();

		if (!response.ok || payload.success === false)
		{
			throw new Error(payload.message || failureText);
		}

		return payload.data || {};
	};

	/**
	 * @brief Process every currently queued analysis job.
	 *
	 * @return {Promise<void>}
	 */
	const processQueue = async () =>
	{
		if (processing)
		{
			return;
		}

		processing = true;
		processButton.disabled = true;

		if (regenerateSpectrogramsButton instanceof HTMLButtonElement)
		{
			regenerateSpectrogramsButton.disabled = true;
		}

		let processed = 0;
		let initialTotal = 0;

		if (progress instanceof HTMLElement)
		{
			progress.hidden = false;
			progress.setAttribute('aria-valuenow', '0');
		}

		if (progressBar instanceof HTMLElement)
		{
			progressBar.style.width = '0%';
		}

		try
		{
			while (true)
			{
				const result = await postMaintenanceRequest(processUrl);

				if (result.processed !== true)
				{
					break;
				}

				processed += 1;
				const remaining = Number.parseInt(String(result.remaining || 0), 10) || 0;
				initialTotal = Math.max(initialTotal, processed + remaining);
				const percentage = initialTotal > 0 ? Math.round((processed / initialTotal) * 100) : 100;

				if (progress instanceof HTMLElement)
				{
					progress.setAttribute('aria-valuenow', String(percentage));
				}

				if (progressBar instanceof HTMLElement)
				{
					progressBar.style.width = `${percentage}%`;
				}

				const title = result.clip_title || `#${result.clip_id || ''}`;
				setStatus(
					progressTemplate
						.replace('{processed}', String(processed))
						.replace('{remaining}', String(remaining))
						.replace('{title}', String(title))
						.replace('{message}', String(result.message || ''))
				);

				if (remaining <= 0)
				{
					break;
				}
			}

			if (progressBar instanceof HTMLElement)
			{
				progressBar.style.width = '100%';
			}

			window.location.reload();
		}
		catch (error)
		{
			processing = false;
			processButton.disabled = false;

			if (regenerateSpectrogramsButton instanceof HTMLButtonElement)
			{
				regenerateSpectrogramsButton.disabled = false;
			}

			setStatus(error instanceof Error ? error.message : String(error));
		}
	};

	processButton.addEventListener('click', () =>
	{
		void processQueue();
	});

	if (
		regenerateSpectrogramsButton instanceof HTMLButtonElement
		&& regenerateSpectrogramsUrl !== ''
		&& regenerateSpectrogramsConfirm !== ''
		&& regenerateSpectrogramsQueued !== ''
	)
	{
		regenerateSpectrogramsButton.addEventListener('click', async () =>
		{
			if (!window.confirm(regenerateSpectrogramsConfirm))
			{
				return;
			}

			regenerateSpectrogramsButton.disabled = true;
			processButton.disabled = true;

			try
			{
				const result = await postMaintenanceRequest(regenerateSpectrogramsUrl);
				const queued = Number.parseInt(String(result.queued || 0), 10) || 0;
				setStatus(regenerateSpectrogramsQueued.replace('{queued}', String(queued)));
				await processQueue();
			}
			catch (error)
			{
				regenerateSpectrogramsButton.disabled = false;
				processButton.disabled = false;
				setStatus(error instanceof Error ? error.message : String(error));
			}
		});
	}
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveAnalysisMaintenance);
