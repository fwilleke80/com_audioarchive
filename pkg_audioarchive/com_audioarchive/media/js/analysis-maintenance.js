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

	const button = root.querySelector('[data-audioarchive-process-analyses]');
	const progress = root.querySelector('[data-audioarchive-analysis-progress]');
	const progressBar = root.querySelector('[data-audioarchive-analysis-progress-bar]');
	const status = root.querySelector('[data-audioarchive-analysis-status]');
	const processUrl = root.dataset.processUrl || '';
	const tokenName = root.dataset.tokenName || '';
	const progressTemplate = root.dataset.progressTemplate || '';
	const failureText = root.dataset.failureText || '';

	if (
		!(button instanceof HTMLButtonElement)
		|| processUrl === ''
		|| tokenName === ''
		|| progressTemplate === ''
		|| failureText === ''
	)
	{
		return;
	}

	button.addEventListener('click', async () =>
	{
		button.disabled = true;
		let processed = 0;
		let initialTotal = 0;

		if (progress instanceof HTMLElement)
		{
			progress.hidden = false;
		}

		try
		{
			while (true)
			{
				const body = new URLSearchParams();
				body.set(tokenName, '1');
				const response = await fetch(processUrl, {
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

				const result = payload.data || {};

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

				if (status instanceof HTMLElement)
				{
					const title = result.clip_title || `#${result.clip_id || ''}`;
					status.textContent = progressTemplate
						.replace('{processed}', String(processed))
						.replace('{remaining}', String(remaining))
						.replace('{title}', String(title))
						.replace('{message}', String(result.message || ''));
				}

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
			button.disabled = false;

			if (status instanceof HTMLElement)
			{
				status.textContent = error instanceof Error ? error.message : String(error);
			}
		}
	});
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveAnalysisMaintenance);
