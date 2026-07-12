/**
 * @brief Incremental server-directory import UI.
 */
(() =>
{
	'use strict';

	const form = document.getElementById('audioarchive-directory-import-form');

	if (!form)
	{
		return;
	}

	const scanButton = document.getElementById('audioarchive-import-scan');
	const selectButton = document.getElementById('audioarchive-import-select');
	const deselectButton = document.getElementById('audioarchive-import-deselect');
	const startButton = document.getElementById('audioarchive-import-start');
	const stopButton = document.getElementById('audioarchive-import-stop');
	const summary = document.getElementById('audioarchive-import-summary');
	const empty = document.getElementById('audioarchive-import-empty');
	const tableWrapper = document.getElementById('audioarchive-import-table-wrapper');
	const tableBody = document.getElementById('audioarchive-import-files');
	const tokenName = form.dataset.tokenName;
	const scanEndpoint = form.dataset.scanEndpoint;
	const inspectEndpoint = form.dataset.inspectEndpoint;
	const importEndpoint = form.dataset.importEndpoint;
	let jobs = [];
	let running = false;
	let stopRequested = false;
	let batchMetadata = null;

	/**
	 * @brief Translate a Joomla language string.
	 *
	 * @param {string} key Language key.
	 * @param {string} fallback Fallback text.
	 * @returns {string} Translation.
	 */
	const translate = (key, fallback) => Joomla.Text._(key, fallback);

	/**
	 * @brief Escape text for HTML insertion.
	 *
	 * @param {unknown} value Value.
	 * @returns {string} Escaped text.
	 */
	const escapeHtml = (value) =>
	{
		const node = document.createElement('div');
		node.textContent = String(value ?? '');
		return node.innerHTML;
	};

	/**
	 * @brief Format a byte count.
	 *
	 * @param {number} bytes Byte count.
	 * @returns {string} Human-readable size.
	 */
	const formatBytes = (bytes) =>
	{
		if (!Number.isFinite(bytes) || bytes <= 0)
		{
			return '0 B';
		}

		const units = ['B', 'KiB', 'MiB', 'GiB'];
		const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
		return `${(bytes / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
	};

	/**
	 * @brief Send one form-encoded JSON request.
	 *
	 * @param {string} endpoint Endpoint URL.
	 * @param {FormData} data Request data.
	 * @returns {Promise<object>} Joomla JSON response.
	 */
	const request = async (endpoint, data) =>
	{
		data.append(tokenName, '1');
		const response = await fetch(endpoint, {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
			headers: {'X-Requested-With': 'XMLHttpRequest'},
		});
		let payload = null;

		try
		{
			payload = await response.json();
		}
		catch (error)
		{
			throw new Error(translate('COM_AUDIOARCHIVE_IMPORT_NO_RESPONSE', 'The server returned no usable response.'));
		}

		if (!response.ok || payload.success !== true)
		{
			throw new Error(payload.message || translate('COM_AUDIOARCHIVE_IMPORT_NETWORK_ERROR', 'The request failed.'));
		}

		return payload;
	};

	/**
	 * @brief Return one row for a job.
	 *
	 * @param {object} job Job.
	 * @returns {HTMLElement|null} Row.
	 */
	const getRow = (job) => document.getElementById(`audioarchive-import-${job.id}`);

	/**
	 * @brief Return all selected importable jobs.
	 *
	 * @returns {object[]} Jobs.
	 */
	const selectedJobs = () => jobs.filter((job) => job.selected && ['ready', 'failed'].includes(job.state));

	/**
	 * @brief Update global controls and summary.
	 *
	 * @returns {void}
	 */
	const updateControls = () =>
	{
		const ready = jobs.filter((job) => job.state === 'ready').length;
		const complete = jobs.filter((job) => job.state === 'complete').length;
		const failed = jobs.filter((job) => job.state === 'failed').length;
		const ineligible = jobs.filter((job) => job.state === 'ineligible').length;
		const selected = jobs.filter((job) => job.selected && ['ready', 'failed'].includes(job.state)).length;
		scanButton.disabled = running;
		selectButton.disabled = running || ready === 0;
		deselectButton.disabled = running || !jobs.some((job) => job.selected);
		startButton.disabled = running || selected === 0;
		stopButton.classList.toggle('d-none', !running);
		stopButton.disabled = !running || stopRequested;
		['jform_recursive', 'jform_duplicate_policy', 'jform_delete_source', 'jform_catid', 'jform_tags', 'jform_access', 'jform_state', 'jform_recorded_at'].forEach((id) =>
		{
			const field = document.getElementById(id);

			if (field)
			{
				field.disabled = running;
			}
		});
		summary.textContent = jobs.length > 0
			? translate('COM_AUDIOARCHIVE_IMPORT_RUN_SUMMARY', '%1$d files: %2$d ready, %3$d selected, %4$d complete, %5$d failed, %6$d unavailable')
				.replace('%1$d', String(jobs.length))
				.replace('%2$d', String(ready))
				.replace('%3$d', String(selected))
				.replace('%4$d', String(complete))
				.replace('%5$d', String(failed))
				.replace('%6$d', String(ineligible))
			: '';
	};

	/**
	 * @brief Render a job's state, metadata and actions.
	 *
	 * @param {object} job Job.
	 * @returns {void}
	 */
	const renderJob = (job) =>
	{
		const row = getRow(job);

		if (!row)
		{
			return;
		}

		const states = {
			discovered: ['bg-secondary', 'COM_AUDIOARCHIVE_IMPORT_STATUS_DISCOVERED', 'Discovered'],
			analysing: ['bg-info text-dark', 'COM_AUDIOARCHIVE_IMPORT_STATUS_ANALYSING', 'Analysing'],
			ready: ['bg-success', 'COM_AUDIOARCHIVE_IMPORT_STATUS_READY', 'Ready'],
			ineligible: ['bg-warning text-dark', 'COM_AUDIOARCHIVE_IMPORT_STATUS_INELIGIBLE', 'Unavailable'],
			importing: ['bg-info text-dark', 'COM_AUDIOARCHIVE_IMPORT_STATUS_IMPORTING', 'Importing'],
			complete: ['bg-success', 'COM_AUDIOARCHIVE_IMPORT_STATUS_COMPLETE', 'Complete'],
			failed: ['bg-danger', 'COM_AUDIOARCHIVE_IMPORT_STATUS_FAILED', 'Failed'],
		};
		const state = states[job.state] || states.discovered;
		const checkbox = row.querySelector('.com-audioarchive-import-select');
		checkbox.checked = Boolean(job.selected);
		checkbox.disabled = running || !['ready', 'failed'].includes(job.state);
		const badge = row.querySelector('.com-audioarchive-import-status');
		badge.className = `badge com-audioarchive-import-status ${state[0]}`;
		badge.textContent = translate(state[1], state[2]);
		const metadata = row.querySelector('.com-audioarchive-import-metadata');
		const result = row.querySelector('.com-audioarchive-import-result');
		const actions = row.querySelector('.com-audioarchive-import-actions');
		actions.replaceChildren();

		if (job.analysis)
		{
			const details = [
				job.analysis.proposed_title,
				job.analysis.duration,
				job.analysis.codec || job.analysis.container,
				job.analysis.recorded_at,
			].filter(Boolean).map(escapeHtml).join(' · ');
			let duplicate = '';

			if (job.analysis.duplicate)
			{
				const duplicateTitle = escapeHtml(job.analysis.duplicate.title || job.analysis.duplicate.filename || '');
				const link = job.analysis.duplicate.edit_url
					? ` <a href="${escapeHtml(job.analysis.duplicate.edit_url)}">${escapeHtml(translate('COM_AUDIOARCHIVE_DUPLICATE_EDIT_LINK', 'Edit existing clip'))}</a>`
					: '';
				duplicate = `<div class="small text-warning">${escapeHtml(translate('COM_AUDIOARCHIVE_IMPORT_DUPLICATE_LABEL', 'Duplicate: %s')).replace('%s', duplicateTitle)}${link}</div>`;
			}

			const warnings = Array.isArray(job.analysis.warnings)
				? job.analysis.warnings.map((warning) => `<div class="small text-warning">${escapeHtml(warning)}</div>`).join('')
				: '';
			metadata.innerHTML = `${details}${duplicate}${warnings}`;
		}
		else
		{
			metadata.textContent = '';
		}

		if (job.state === 'complete' && job.result)
		{
			const sourceState = job.result.source_deleted
				? translate('COM_AUDIOARCHIVE_IMPORT_SOURCE_REMOVED', 'Source removed from inbox')
				: translate('COM_AUDIOARCHIVE_IMPORT_SOURCE_PRESERVED', 'Source retained in inbox');
			result.innerHTML = `<strong>${escapeHtml(job.result.title || job.filename)}</strong><div class="small text-body-secondary">${escapeHtml(sourceState)}</div>`;
			const link = document.createElement('a');
			link.className = 'btn btn-sm btn-outline-primary';
			link.href = job.result.edit_url;
			link.textContent = translate('COM_AUDIOARCHIVE_IMPORT_ACTION_EDIT', 'Edit clip');
			actions.appendChild(link);
		}
		else
		{
			result.textContent = job.message || '';

			if (job.state === 'failed' || job.state === 'ineligible')
			{
				const retry = document.createElement('button');
				retry.type = 'button';
				retry.className = 'btn btn-sm btn-outline-secondary';
				retry.textContent = translate('COM_AUDIOARCHIVE_IMPORT_ACTION_RETRY', 'Retry');
				retry.disabled = running;
				retry.addEventListener('click', () => analyseJob(job));
				actions.appendChild(retry);
			}
		}

		updateControls();
	};

	/**
	 * @brief Add scanned files to the table.
	 *
	 * @param {object[]} files Discovered files.
	 * @returns {void}
	 */
	const setJobs = (files) =>
	{
		jobs = files.map((file, index) => ({
			id: String(index + 1),
			path: file.path,
			filename: file.filename,
			size: Number(file.size || 0),
			state: 'discovered',
			selected: false,
			analysis: null,
			result: null,
			message: '',
		}));
		tableBody.replaceChildren();

		jobs.forEach((job) =>
		{
			const row = document.createElement('tr');
			row.id = `audioarchive-import-${job.id}`;
			row.innerHTML = `
				<td class="text-center"><input type="checkbox" class="form-check-input com-audioarchive-import-select" aria-label="${escapeHtml(job.path)}"></td>
				<td><div class="fw-semibold text-break">${escapeHtml(job.path)}</div><div class="small text-body-secondary">${escapeHtml(formatBytes(job.size))}</div></td>
				<td class="com-audioarchive-import-metadata"></td>
				<td><span class="badge bg-secondary com-audioarchive-import-status"></span></td>
				<td class="com-audioarchive-import-result"></td>
				<td class="com-audioarchive-import-actions text-end"></td>
			`;
			row.querySelector('.com-audioarchive-import-select').addEventListener('change', (event) =>
			{
				job.selected = event.target.checked;
				updateControls();
			});
			tableBody.appendChild(row);
			renderJob(job);
		});

		empty.classList.toggle('d-none', jobs.length > 0);
		tableWrapper.classList.toggle('d-none', jobs.length === 0);
		updateControls();
	};

	/**
	 * @brief Analyse one file.
	 *
	 * @param {object} job Job.
	 * @returns {Promise<void>}
	 */
	const analyseJob = async (job) =>
	{
		job.state = 'analysing';
		job.selected = false;
		job.message = '';
		job.analysis = null;
		renderJob(job);
		const data = new FormData();
		data.append('path', job.path);
		data.append('duplicate_policy', document.getElementById('jform_duplicate_policy')?.value || 'component');

		try
		{
			const response = await request(inspectEndpoint, data);
			job.analysis = response.data || {};
			job.state = job.analysis.eligible ? 'ready' : 'ineligible';
			job.selected = job.analysis.eligible;
			job.message = job.analysis.eligible ? '' : response.message;
		}
		catch (error)
		{
			job.state = 'ineligible';
			job.message = error.message;
		}

		renderJob(job);
	};

	/**
	 * @brief Analyse every discovered file sequentially.
	 *
	 * @returns {Promise<void>}
	 */
	const analyseAll = async () =>
	{
		running = true;
		updateControls();

		for (const job of jobs)
		{
			if (stopRequested)
			{
				break;
			}

			await analyseJob(job);
		}

		running = false;
		stopRequested = false;
		updateControls();
	};

	/**
	 * @brief Freeze batch metadata for the current import run.
	 *
	 * @returns {object} Batch values.
	 */
	const readBatchMetadata = () => ({
		catid: document.getElementById('jform_catid')?.value || '',
		tags: Array.from(document.querySelectorAll('#jform_tags option:checked')).map((option) => option.value).filter(Boolean),
		access: document.getElementById('jform_access')?.value || '1',
		state: document.getElementById('jform_state')?.value || '0',
		recordedAt: document.getElementById('jform_recorded_at')?.value || '',
		duplicatePolicy: document.getElementById('jform_duplicate_policy')?.value || 'component',
		deleteSource: document.querySelector('input[name="jform[delete_source]"]:checked')?.value || '0',
	});

	/**
	 * @brief Import one selected job.
	 *
	 * @param {object} job Job.
	 * @returns {Promise<void>}
	 */
	const importJob = async (job) =>
	{
		job.state = 'importing';
		job.message = '';
		renderJob(job);
		const data = new FormData();
		data.append('path', job.path);
		data.append('jform[catid]', batchMetadata.catid);
		data.append('jform[access]', batchMetadata.access);
		data.append('jform[state]', batchMetadata.state);
		data.append('jform[recorded_at]', batchMetadata.recordedAt);
		data.append('jform[duplicate_policy]', batchMetadata.duplicatePolicy);
		data.append('jform[delete_source]', batchMetadata.deleteSource);
		data.append('jform[recursive]', document.querySelector('input[name="jform[recursive]"]:checked')?.value || '0');
		batchMetadata.tags.forEach((tag) => data.append('jform[tags][]', tag));

		try
		{
			const response = await request(importEndpoint, data);
			job.state = 'complete';
			job.selected = false;
			job.result = response.data || {};
			job.message = '';
		}
		catch (error)
		{
			job.state = 'failed';
			job.message = error.message;
		}

		renderJob(job);
	};

	scanButton.addEventListener('click', async () =>
	{
		running = true;
		stopRequested = false;
		setJobs([]);
		updateControls();
		const data = new FormData();
		data.append('recursive', document.querySelector('input[name="jform[recursive]"]:checked')?.value || '0');

		try
		{
			const response = await request(scanEndpoint, data);
			setJobs(response.data?.files || []);
			summary.textContent = translate('COM_AUDIOARCHIVE_IMPORT_SCAN_SUMMARY', '%d supported files discovered.').replace('%d', String(response.data?.count || 0));
			running = false;
			await analyseAll();
		}
		catch (error)
		{
			running = false;
			summary.textContent = error.message;
			updateControls();
		}
	});

	selectButton.addEventListener('click', () =>
	{
		jobs.forEach((job) =>
		{
			job.selected = job.state === 'ready';
			renderJob(job);
		});
	});

	deselectButton.addEventListener('click', () =>
	{
		jobs.forEach((job) =>
		{
			job.selected = false;
			renderJob(job);
		});
	});

	startButton.addEventListener('click', async () =>
	{
		if (document.formvalidator && !document.formvalidator.isValid(form))
		{
			return;
		}

		batchMetadata = readBatchMetadata();
		running = true;
		stopRequested = false;
		updateControls();

		for (const job of selectedJobs())
		{
			if (stopRequested)
			{
				break;
			}

			await importJob(job);
		}

		running = false;
		stopRequested = false;
		updateControls();
	});

	stopButton.addEventListener('click', () =>
	{
		stopRequested = true;
		stopButton.disabled = true;
	});

	updateControls();
})();
