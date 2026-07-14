/**
 * @brief Incremental inbox import and bulk replacement UI.
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
	const startLabel = document.getElementById('audioarchive-import-start-label');
	const stopButton = document.getElementById('audioarchive-import-stop');
	const summary = document.getElementById('audioarchive-import-summary');
	const empty = document.getElementById('audioarchive-import-empty');
	const tableWrapper = document.getElementById('audioarchive-import-table-wrapper');
	const tableBody = document.getElementById('audioarchive-import-files');
	const metadataCard = document.getElementById('audioarchive-import-metadata-card');
	const importInfo = document.getElementById('audioarchive-import-info');
	const replacementInfo = document.getElementById('audioarchive-replacement-info');
	const contextHeading = document.getElementById('audioarchive-import-context-heading');
	const tokenName = form.dataset.tokenName;
	const scanEndpoint = form.dataset.scanEndpoint;
	const inspectEndpoint = form.dataset.inspectEndpoint;
	const importEndpoint = form.dataset.importEndpoint;
	const replacementEndpoint = form.dataset.replacementEndpoint;
	let jobs = [];
	let running = false;
	let stopRequested = false;
	let batchMetadata = null;

	/**
	 * @brief Translate a Joomla language string.
	 *
	 * @param {string} key Language key.
	 * @param {string} fallback Fallback text.
	 *
	 * @returns {string} Translation.
	 */
	const translate = (key, fallback) => Joomla.Text._(key, fallback);

	/**
	 * @brief Return the selected inbox operation mode.
	 *
	 * @returns {string} import or replace.
	 */
	const currentMode = () => document.getElementById('jform_operation_mode')?.value === 'replace'
		? 'replace'
		: 'import';

	/**
	 * @brief Escape text for HTML insertion.
	 *
	 * @param {unknown} value Value.
	 *
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
	 *
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
	 *
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
	 *
	 * @returns {HTMLElement|null} Row.
	 */
	const getRow = (job) => document.getElementById(`audioarchive-import-${job.id}`);

	/**
	 * @brief Return selected executable jobs.
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
		[
			'jform_operation_mode',
			'jform_recursive',
			'jform_duplicate_policy',
			'jform_delete_source',
			'jform_retain_previous_original',
			'jform_category_mode',
			'jform_catid',
			'jform_create_missing_categories',
			'jform_tags',
			'jform_access',
			'jform_state',
			'jform_recorded_at',
		].forEach((id) =>
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
	 * @brief Render the replacement target cell.
	 *
	 * @param {object} job Job.
	 *
	 * @returns {string} HTML fragment.
	 */
	const renderReplacementTarget = (job) =>
	{
		if (!job.analysis)
		{
			return '';
		}

		if (job.analysis.match)
		{
			const match = job.analysis.match;
			const title = escapeHtml(match.title || `#${match.clip_id}`);
			const linkedTitle = match.edit_url
				? `<a href="${escapeHtml(match.edit_url)}">${title}</a>`
				: title;
			const currentMedia = [match.filename, match.codec || match.container, match.duration]
				.filter(Boolean)
				.map(escapeHtml)
				.join(' · ');
			return `<div class="fw-semibold">${linkedTitle}</div><div class="small text-body-secondary">${currentMedia}</div>`;
		}

		if (Array.isArray(job.analysis.matches) && job.analysis.matches.length > 1)
		{
			const links = job.analysis.matches.map((match) =>
			{
				const title = escapeHtml(match.title || `#${match.clip_id}`);
				return match.edit_url ? `<a href="${escapeHtml(match.edit_url)}">${title}</a>` : title;
			}).join(', ');
			return `<div class="small text-warning">${escapeHtml(translate('COM_AUDIOARCHIVE_REPLACEMENT_AMBIGUOUS_LIST', 'Multiple clips match:'))} ${links}</div>`;
		}

		return `<code>${escapeHtml(job.analysis.normalised_basename || '')}</code>`;
	};

	/**
	 * @brief Render a job's state, metadata, and actions.
	 *
	 * @param {object} job Job.
	 *
	 * @returns {void}
	 */
	const renderJob = (job) =>
	{
		const row = getRow(job);

		if (!row)
		{
			return;
		}

		const replacing = currentMode() === 'replace';
		const states = {
			discovered: ['bg-secondary', 'COM_AUDIOARCHIVE_IMPORT_STATUS_DISCOVERED', 'Discovered'],
			analysing: ['bg-info text-dark', 'COM_AUDIOARCHIVE_IMPORT_STATUS_ANALYSING', 'Analysing'],
			ready: ['bg-success', 'COM_AUDIOARCHIVE_IMPORT_STATUS_READY', 'Ready'],
			ineligible: ['bg-warning text-dark', 'COM_AUDIOARCHIVE_IMPORT_STATUS_INELIGIBLE', 'Unavailable'],
			processing: [
				'bg-info text-dark',
				replacing ? 'COM_AUDIOARCHIVE_REPLACEMENT_STATUS_REPLACING' : 'COM_AUDIOARCHIVE_IMPORT_STATUS_IMPORTING',
				replacing ? 'Replacing' : 'Importing',
			],
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
		const context = row.querySelector('.com-audioarchive-import-category');
		const metadata = row.querySelector('.com-audioarchive-import-metadata');
		const result = row.querySelector('.com-audioarchive-import-result');
		const actions = row.querySelector('.com-audioarchive-import-actions');
		actions.replaceChildren();

		if (job.analysis)
		{
			if (replacing)
			{
				context.innerHTML = renderReplacementTarget(job);
				const replacement = job.analysis.replacement || {};
				const replacementMedia = [
					replacement.filename,
					replacement.codec || replacement.container,
					replacement.duration,
				].filter(Boolean).map(escapeHtml).join(' · ');
				const warnings = Array.isArray(job.analysis.warnings)
					? job.analysis.warnings.map((warning) => `<div class="small text-warning">${escapeHtml(warning)}</div>`).join('')
					: '';
				metadata.innerHTML = `<div>${replacementMedia}</div>${warnings}`;
			}
			else
			{
				const categoryPath = job.analysis.category_path || '';
				const categoryCreation = job.analysis.category_will_create
					? `<div class="small text-info">${escapeHtml(translate('COM_AUDIOARCHIVE_IMPORT_CATEGORY_WILL_CREATE', 'Missing categories will be created during import.'))}</div>`
					: '';
				context.innerHTML = `<span class="text-break">${escapeHtml(categoryPath)}</span>${categoryCreation}`;
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
		}
		else
		{
			context.textContent = '';
			metadata.textContent = '';
		}

		if (job.state === 'complete' && job.result)
		{
			const sourceState = job.result.source_deleted
				? translate('COM_AUDIOARCHIVE_IMPORT_SOURCE_REMOVED', 'Source removed from inbox')
				: translate('COM_AUDIOARCHIVE_IMPORT_SOURCE_PRESERVED', 'Source retained in inbox');
			const previousState = replacing
				? (job.result.previous_original_retained
					? translate('COM_AUDIOARCHIVE_REPLACEMENT_PREVIOUS_RETAINED', 'Previous original retained for maintenance cleanup')
					: translate('COM_AUDIOARCHIVE_REPLACEMENT_PREVIOUS_DELETED', 'Previous original deleted'))
				: '';
			const warnings = Array.isArray(job.result.warnings)
				? job.result.warnings.map((warning) => `<div class="small text-warning">${escapeHtml(warning)}</div>`).join('')
				: '';
			const previousMarkup = previousState !== ''
				? `<div class="small text-body-secondary">${escapeHtml(previousState)}</div>`
				: '';
			result.innerHTML = `<strong>${escapeHtml(job.result.title || job.filename)}</strong><div class="small text-body-secondary">${escapeHtml(sourceState)}</div>${previousMarkup}${warnings}`;

			if (!replacing)
			{
				context.textContent = job.result.category || job.analysis?.category_path || '';
			}

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
	 *
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
				<td class="com-audioarchive-import-category"></td>
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
	 *
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
		data.append('operation_mode', currentMode());
		data.append('duplicate_policy', document.getElementById('jform_duplicate_policy')?.value || 'component');
		data.append('category_mode', document.getElementById('jform_category_mode')?.value || 'selected');
		data.append('catid', document.getElementById('jform_catid')?.value || '0');
		data.append('create_missing_categories', document.querySelector('input[name="jform[create_missing_categories]"]:checked')?.value || '0');

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
	 * @brief Freeze batch values for the current run.
	 *
	 * @returns {object} Batch values.
	 */
	const readBatchMetadata = () => ({
		mode: currentMode(),
		categoryMode: document.getElementById('jform_category_mode')?.value || 'selected',
		catid: document.getElementById('jform_catid')?.value || '',
		createMissingCategories: document.querySelector('input[name="jform[create_missing_categories]"]:checked')?.value || '0',
		tags: Array.from(document.querySelectorAll('#jform_tags option:checked')).map((option) => option.value).filter(Boolean),
		access: document.getElementById('jform_access')?.value || '1',
		state: document.getElementById('jform_state')?.value || '0',
		recordedAt: document.getElementById('jform_recorded_at')?.value || '',
		duplicatePolicy: document.getElementById('jform_duplicate_policy')?.value || 'component',
		deleteSource: document.querySelector('input[name="jform[delete_source]"]:checked')?.value || '0',
		retainPreviousOriginal: document.querySelector('input[name="jform[retain_previous_original]"]:checked')?.value || '1',
	});

	/**
	 * @brief Import or replace one selected job.
	 *
	 * @param {object} job Job.
	 *
	 * @returns {Promise<void>}
	 */
	const processJob = async (job) =>
	{
		job.state = 'processing';
		job.message = '';
		renderJob(job);
		const data = new FormData();
		data.append('path', job.path);
		let endpoint = importEndpoint;

		if (batchMetadata.mode === 'replace')
		{
			endpoint = replacementEndpoint;
			data.append('delete_source', batchMetadata.deleteSource);
			data.append('retain_previous_original', batchMetadata.retainPreviousOriginal);
		}
		else
		{
			data.append('jform[operation_mode]', 'import');
			data.append('jform[category_mode]', batchMetadata.categoryMode);
			data.append('jform[catid]', batchMetadata.catid);
			data.append('jform[create_missing_categories]', batchMetadata.createMissingCategories);
			data.append('jform[access]', batchMetadata.access);
			data.append('jform[state]', batchMetadata.state);
			data.append('jform[recorded_at]', batchMetadata.recordedAt);
			data.append('jform[duplicate_policy]', batchMetadata.duplicatePolicy);
			data.append('jform[delete_source]', batchMetadata.deleteSource);
			data.append('jform[recursive]', document.querySelector('input[name="jform[recursive]"]:checked')?.value || '0');
			batchMetadata.tags.forEach((tag) => data.append('jform[tags][]', tag));
		}

		try
		{
			const response = await request(endpoint, data);
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
		if (currentMode() === 'import' && document.formvalidator && !document.formvalidator.isValid(form))
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

			await processJob(job);
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

	/**
	 * @brief Update category controls for folder-derived import mode.
	 *
	 * @returns {void}
	 */
	const updateCategoryMode = () =>
	{
		const folderMode = (document.getElementById('jform_category_mode')?.value || 'selected') === 'folders';
		const createField = document.getElementById('jform_create_missing_categories')?.closest('.control-group');

		if (createField)
		{
			createField.classList.toggle('d-none', !folderMode);
		}

		if (folderMode && currentMode() === 'import')
		{
			const recursive = document.querySelector('input[name="jform[recursive]"][value="1"]');

			if (recursive && !recursive.checked)
			{
				recursive.click();
			}
		}
	};

	/**
	 * @brief Apply UI changes for import or replacement mode.
	 *
	 * @param {boolean} clearJobs Whether existing scan results are discarded.
	 *
	 * @returns {void}
	 */
	const updateOperationMode = (clearJobs) =>
	{
		const replacing = currentMode() === 'replace';
		metadataCard?.classList.toggle('d-none', replacing);
		importInfo?.classList.toggle('d-none', replacing);
		replacementInfo?.classList.toggle('d-none', !replacing);
		const duplicateGroup = document.getElementById('jform_duplicate_policy')?.closest('.control-group');
		const retainGroup = document.getElementById('jform_retain_previous_original')?.closest('.control-group');
		duplicateGroup?.classList.toggle('d-none', replacing);
		retainGroup?.classList.toggle('d-none', !replacing);

		if (contextHeading)
		{
			contextHeading.textContent = replacing
				? translate('COM_AUDIOARCHIVE_REPLACEMENT_TARGET_COLUMN', 'Matched clip')
				: translate('COM_AUDIOARCHIVE_IMPORT_CATEGORY_COLUMN', 'Category');
		}

		if (startLabel)
		{
			startLabel.textContent = replacing
				? translate('COM_AUDIOARCHIVE_REPLACEMENT_START', 'Replace selected files')
				: translate('COM_AUDIOARCHIVE_IMPORT_START', 'Import selected files');
		}

		if (clearJobs)
		{
			setJobs([]);
		}

		updateCategoryMode();
		updateControls();
	};

	document.getElementById('jform_category_mode')?.addEventListener('change', updateCategoryMode);
	document.getElementById('jform_operation_mode')?.addEventListener('change', () => updateOperationMode(true));
	updateOperationMode(false);
})();
