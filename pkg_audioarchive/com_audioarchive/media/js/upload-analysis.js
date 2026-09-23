/** @brief Automatically process each server-granted upload one job per request; failures remain visible. */
export async function processUploads(root)
{
	if (root.dataset.busy === '1')
	{
		return;
	}
	root.dataset.busy = '1';
	const status = root.querySelector('[data-processing-status]');
	const retry = root.querySelector('[data-processing-retry]');
	retry.hidden = true;
	let failed = root.dataset.hadFailure === '1';
	try
	{
		const ids = JSON.parse(root.dataset.ids);
		let attempts = 0;
		while (ids.length)
		{
			if (++attempts > 240)
			{
				throw new Error('Processing timed out');
			}
			const response = await fetch(root.dataset.endpoint, {
				method: 'POST', credentials: 'same-origin', cache: 'no-store',
				headers: {Accept: 'application/json'},
				body: new URLSearchParams({id: String(ids[0]), [root.dataset.token]: '1'}),
			});
			const json = await response.json();
			if (!response.ok || json.success !== true)
			{
				throw new Error('Processing request failed');
			}
			failed ||= json.data.failed;
			root.dataset.hadFailure = failed ? '1' : '0';
			if (json.data.done)
			{
				ids.shift();
				root.dataset.ids = JSON.stringify(ids);
			}
			else if (!json.data.processed)
			{
				await new Promise((resolve) => window.setTimeout(resolve, 1000));
			}
		}
		status.textContent = failed ? root.dataset.failed : root.dataset.complete;
		root.classList.toggle('alert-warning', Boolean(failed));
		if (!failed)
		{
			if (root.dataset.return)
			{
				window.location.assign(root.dataset.return);
			}
			else
			{
				window.location.reload();
			}
		}
	}
	catch
	{
		status.textContent = root.dataset.error;
		retry.hidden = false;
	}
	finally
	{
		root.dataset.busy = '0';
	}
}

/** @brief Resume eligible session uploads when the processing page or owner workspace opens. */
function initialise()
{
	document.querySelectorAll('[data-audioarchive-upload-analysis]').forEach((root) =>
	{
		root.querySelector('[data-processing-retry]').addEventListener('click', () => processUploads(root));
		void processUploads(root);
	});
}
if (document.readyState === 'loading')
{
	document.addEventListener('DOMContentLoaded', initialise);
}
else
{
	initialise();
}
