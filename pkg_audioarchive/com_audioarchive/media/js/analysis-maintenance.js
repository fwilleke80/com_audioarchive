/**
 * @brief Process queued Punga Audio Archive analysis jobs one request at a time.
 */
const initialiseAudioArchiveAnalysisMaintenance = () =>
{
	const root = document.querySelector('[data-audioarchive-analysis-maintenance]');

	if (!(root instanceof HTMLElement))
	{
		return;
	}

	const processButton = root.querySelector('[data-audioarchive-process-analyses]');
	const progress = root.querySelector('[data-audioarchive-analysis-progress]');
	const progressBar = root.querySelector('[data-audioarchive-analysis-progress-bar]');
	const status = root.querySelector('[data-audioarchive-analysis-status]');
	const processUrl = root.dataset.processUrl || '';
	const tokenName = root.dataset.tokenName || '';
	const progressTemplate = root.dataset.progressTemplate || '';
	const failureText = root.dataset.failureText || '';
	const completeText = root.dataset.completeText || '';
	const stateLabels = {
		pending: root.dataset.statePending || 'Pending',
		processing: root.dataset.stateProcessing || 'Processing',
		finished: root.dataset.stateFinished || 'Finished',
		failed: root.dataset.stateFailed || 'Failed',
	};
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
	 * @brief Find a queue row by its job identifier.
	 *
	 * @param {number} jobId Job identifier.
	 *
	 * @return {HTMLTableRowElement|null} Matching queue row.
	 */
	const findJobRow = (jobId) =>
	{
		const row = root.querySelector(`[data-audioarchive-analysis-job-id="${jobId}"]`);

		return row instanceof HTMLTableRowElement ? row : null;
	};

	/**
	 * @brief Find the next pending row in displayed processing order.
	 *
	 * @return {HTMLTableRowElement|null} Next pending queue row.
	 */
	const findNextPendingRow = () =>
	{
		const row = root.querySelector('[data-audioarchive-analysis-job-state="pending"]');

		return row instanceof HTMLTableRowElement ? row : null;
	};

	/**
	 * @brief Update one queue row's live processing state.
	 *
	 * @param {HTMLTableRowElement} row Queue row.
	 * @param {'pending'|'processing'|'finished'|'failed'} state New state.
	 *
	 * @return {void}
	 */
	const setRowState = (row, state) =>
	{
		const badge = row.querySelector('[data-audioarchive-analysis-job-status]');
		const badgeClasses = {
			pending: 'bg-secondary',
			processing: 'bg-warning text-dark',
			finished: 'bg-success',
			failed: 'bg-danger',
		};

		row.dataset.audioarchiveAnalysisJobState = state;
		row.classList.toggle('table-primary', state === 'processing');
		row.classList.toggle('table-success', state === 'finished');

		if (badge instanceof HTMLElement)
		{
			badge.className = `badge ${badgeClasses[state]}`;
			badge.textContent = stateLabels[state];
		}
	};

	/**
	 * @brief Increment the displayed attempt count when a job starts.
	 *
	 * @param {HTMLTableRowElement} row Queue row.
	 *
	 * @return {void}
	 */
	const incrementAttempts = (row) =>
	{
		const cell = row.querySelector('[data-audioarchive-analysis-job-attempts]');

		if (!(cell instanceof HTMLTableCellElement))
		{
			return;
		}

		const attempts = Number.parseInt(cell.dataset.audioarchiveAnalysisJobAttempts || '0', 10) || 0;
		const maximumAttempts = Number.parseInt(cell.dataset.audioarchiveAnalysisJobMaximumAttempts || '0', 10) || 0;
		const nextAttempts = attempts + 1;
		cell.dataset.audioarchiveAnalysisJobAttempts = String(nextAttempts);
		cell.textContent = `${nextAttempts} / ${maximumAttempts}`;
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
		setStatus('');
		let activeRow = null;
		let processed = 0;
		const initialTotal = root.querySelectorAll('[data-audioarchive-analysis-job-state="pending"]').length;

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
				activeRow = findNextPendingRow();

				if (activeRow === null)
				{
					break;
				}

				setRowState(activeRow, 'processing');
				incrementAttempts(activeRow);

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
					setRowState(activeRow, 'pending');
					break;
				}

				const processedRow = findJobRow(Number.parseInt(String(result.job_id || 0), 10) || 0);

				if (processedRow !== null && processedRow !== activeRow)
				{
					setRowState(activeRow, 'pending');
					activeRow = processedRow;
				}

				setRowState(activeRow, result.success === true ? 'finished' : 'failed');
				processed += 1;
				const remaining = Number.parseInt(String(result.remaining || 0), 10) || 0;
				const percentage = initialTotal > 0
					? Math.min(100, Math.round((processed / initialTotal) * 100))
					: 100;

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
				activeRow = null;

				if (remaining <= 0)
				{
					break;
				}
			}

			processing = false;
			processButton.disabled = findNextPendingRow() === null;

			if (progress instanceof HTMLElement)
			{
				progress.setAttribute('aria-valuenow', '100');
			}

			if (progressBar instanceof HTMLElement)
			{
				progressBar.style.width = '100%';
			}

			if (completeText !== '')
			{
				setStatus(completeText);
			}
		}
		catch (error)
		{
			processing = false;

			if (activeRow !== null)
			{
				setRowState(activeRow, 'pending');
			}

			processButton.disabled = findNextPendingRow() === null;
			setStatus(error instanceof Error ? error.message : String(error));
		}
	};

	processButton.addEventListener('click', () =>
	{
		void processQueue();
	});
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveAnalysisMaintenance);
