/**
 * @brief Browser queue for Audio Archive bulk uploads.
 */
(() =>
{
    'use strict';

    const form = document.getElementById('audioarchive-bulk-upload-form');

    if (!form)
    {
        return;
    }

    const fileInput = document.getElementById('audioarchive-bulk-upload-files');
    const dropzone = document.getElementById('audioarchive-bulk-upload-dropzone');
    const selectButton = document.getElementById('audioarchive-bulk-upload-select');
    const startButton = document.getElementById('audioarchive-bulk-upload-start');
    const clearButton = document.getElementById('audioarchive-bulk-upload-clear');
    const summary = document.getElementById('audioarchive-bulk-upload-summary');
    const queueBody = document.getElementById('audioarchive-bulk-upload-queue');
    const emptyMessage = document.getElementById('audioarchive-bulk-upload-empty');
    const tableWrapper = document.getElementById('audioarchive-bulk-upload-table-wrapper');
    const endpoint = form.dataset.uploadEndpoint || '';
    const tokenName = form.dataset.tokenName || '';
    const jobs = [];
    let running = false;
    let batchMetadata = null;

    /**
     * @brief Translate a Joomla language key.
     *
     * @param {string} key Language key.
     * @param {string} fallback Fallback text.
     * @returns {string} Translation.
     */
    const translate = (key, fallback) =>
    {
        if (window.Joomla && Joomla.Text)
        {
            return Joomla.Text._(key, fallback);
        }

        return fallback;
    };

    /**
     * @brief Escape untrusted text for HTML insertion.
     *
     * @param {unknown} value Source value.
     * @returns {string} Escaped text.
     */
    const escapeHtml = (value) =>
    {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };


    /**
     * @brief Remove markup from a server message before rendering it.
     *
     * @param {unknown} value Source value.
     * @returns {string} Plain text.
     */
    const stripHtml = (value) =>
    {
        const element = document.createElement('div');
        element.innerHTML = String(value ?? '');

        return element.textContent || '';
    };

    /**
     * @brief Format a byte count for the queue.
     *
     * @param {number} bytes Number of bytes.
     * @returns {string} Human-readable size.
     */
    const formatBytes = (bytes) =>
    {
        if (!Number.isFinite(bytes) || bytes <= 0)
        {
            return '0 B';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / (1024 ** index);

        return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };

    /**
     * @brief Generate a queue-local identifier.
     *
     * @returns {string} Identifier.
     */
    const createId = () =>
    {
        if (window.crypto && typeof window.crypto.randomUUID === 'function')
        {
            return window.crypto.randomUUID();
        }

        return `upload-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    };

    /**
     * @brief Return the queue row for a job.
     *
     * @param {object} job Upload job.
     * @returns {HTMLTableRowElement|null} Queue row.
     */
    const getRow = (job) => document.getElementById(`audioarchive-upload-${job.id}`);

    /**
     * @brief Update summary and button availability.
     *
     * @returns {void}
     */
    const updateControls = () =>
    {
        const pending = jobs.filter((job) => job.state === 'pending').length;
        const uploading = jobs.filter((job) => job.state === 'uploading').length;
        const complete = jobs.filter((job) => job.state === 'complete').length;
        const failed = jobs.filter((job) => job.state === 'failed').length;
        const cancelled = jobs.filter((job) => job.state === 'cancelled').length;
        const total = jobs.length;

        emptyMessage.classList.toggle('d-none', total > 0);
        tableWrapper.classList.toggle('d-none', total === 0);
        startButton.disabled = running || pending === 0;
        clearButton.disabled = running || (complete + failed + cancelled) === 0;
        selectButton.disabled = running;
        fileInput.disabled = running;
        dropzone.classList.toggle('is-disabled', running);
        ['jform_catid', 'jform_tags', 'jform_access', 'jform_state', 'jform_recorded_at'].forEach((id) =>
        {
            const field = document.getElementById(id);

            if (field)
            {
                field.disabled = running;
            }
        });
        summary.textContent = total > 0
            ? translate('COM_AUDIOARCHIVE_BULK_UPLOAD_SUMMARY', '%1$d files: %2$d pending, %3$d complete, %4$d failed')
                .replace('%1$d', String(total))
                .replace('%2$d', String(pending + uploading))
                .replace('%3$d', String(complete))
                .replace('%4$d', String(failed))
            : '';
    };

    /**
     * @brief Set a job's progress bar.
     *
     * @param {object} job Upload job.
     * @param {number} percent Percentage from zero to 100.
     * @returns {void}
     */
    const setProgress = (job, percent) =>
    {
        const row = getRow(job);

        if (!row)
        {
            return;
        }

        const progress = row.querySelector('.progress-bar');
        const value = Math.max(0, Math.min(100, Math.round(percent)));
        progress.style.width = `${value}%`;
        progress.setAttribute('aria-valuenow', String(value));
        progress.textContent = value > 8 ? `${value}%` : '';
    };

    /**
     * @brief Render action controls for a job state.
     *
     * @param {object} job Upload job.
     * @returns {void}
     */
    const renderActions = (job) =>
    {
        const row = getRow(job);

        if (!row)
        {
            return;
        }

        const cell = row.querySelector('.com-audioarchive-upload-actions');
        cell.replaceChildren();

        if (job.state === 'complete' && job.result && job.result.edit_url)
        {
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = job.result.edit_url;
            link.textContent = translate('COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_EDIT', 'Edit clip');
            cell.appendChild(link);
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';

        if (job.state === 'uploading')
        {
            button.textContent = translate('COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_CANCEL', 'Cancel');
            button.addEventListener('click', () =>
            {
                if (job.xhr)
                {
                    job.xhr.abort();
                }
            });
        }
        else if (job.state === 'failed' || job.state === 'cancelled')
        {
            button.textContent = translate('COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_RETRY', 'Retry');
            button.addEventListener('click', () =>
            {
                job.state = 'pending';
                job.message = '';
                job.result = null;
                setProgress(job, 0);
                updateJob(job);
                startQueue();
            });
        }
        else
        {
            button.textContent = translate('COM_AUDIOARCHIVE_BULK_UPLOAD_ACTION_REMOVE', 'Remove');
            button.addEventListener('click', () =>
            {
                const index = jobs.indexOf(job);

                if (index >= 0)
                {
                    jobs.splice(index, 1);
                }

                row.remove();
                updateControls();
            });
        }

        cell.appendChild(button);
    };

    /**
     * @brief Render a job's status and result.
     *
     * @param {object} job Upload job.
     * @returns {void}
     */
    const updateJob = (job) =>
    {
        const row = getRow(job);

        if (!row)
        {
            return;
        }

        const badge = row.querySelector('.com-audioarchive-upload-status');
        const result = row.querySelector('.com-audioarchive-upload-result');
        const states =
        {
            pending: ['bg-secondary', 'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_PENDING', 'Pending'],
            uploading: ['bg-info text-dark', 'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_UPLOADING', 'Uploading'],
            complete: ['bg-success', 'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_COMPLETE', 'Complete'],
            failed: ['bg-danger', 'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_FAILED', 'Failed'],
            cancelled: ['bg-secondary', 'COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_CANCELLED', 'Cancelled'],
        };
        const state = states[job.state] || states.pending;
        badge.className = `badge com-audioarchive-upload-status ${state[0]}`;
        badge.textContent = translate(state[1], state[2]);

        if (job.state === 'complete' && job.result)
        {
            const details = [
                `<strong>${escapeHtml(job.result.title || job.file.name)}</strong>`,
                escapeHtml(job.result.duration || ''),
                escapeHtml(job.result.codec || job.result.container || ''),
                escapeHtml(job.result.category || ''),
            ].filter(Boolean).join(' · ');
            const messages = Array.isArray(job.result.messages)
                ? job.result.messages
                    .map((entry) => `<div>${escapeHtml(stripHtml(entry.message))}</div>`)
                    .join('')
                : '';
            let duplicate = '';

            if (job.result.duplicate && job.result.duplicate.clip_id)
            {
                const duplicateTitle = escapeHtml(job.result.duplicate.title || job.result.duplicate.filename || '');
                const duplicateLink = job.result.duplicate.edit_url
                    ? `<a href="${escapeHtml(job.result.duplicate.edit_url)}">${escapeHtml(translate('COM_AUDIOARCHIVE_DUPLICATE_EDIT_LINK', 'Edit existing clip'))}</a>`
                    : '';
                duplicate = `<div class="small text-warning mt-1">${escapeHtml(translate('COM_AUDIOARCHIVE_WARNING_DUPLICATE_ALLOWED', 'Exact duplicate accepted: %s')).replace('%s', duplicateTitle)} ${duplicateLink}</div>`;
            }

            result.innerHTML = `${details}${messages ? `<div class="small text-warning mt-1">${messages}</div>` : ''}${duplicate}`;
        }
        else
        {
            result.innerHTML = escapeHtml(stripHtml(job.message || ''));
        }

        renderActions(job);
        updateControls();
    };

    /**
     * @brief Add selected files to the queue.
     *
     * @param {FileList|File[]} files Browser files.
     * @returns {void}
     */
    const addFiles = (files) =>
    {
        if (running)
        {
            return;
        }

        Array.from(files).forEach((file) =>
        {
            const job =
            {
                id: createId(),
                file,
                state: 'pending',
                message: '',
                result: null,
                xhr: null,
            };
            jobs.push(job);
            const row = document.createElement('tr');
            row.id = `audioarchive-upload-${job.id}`;
            row.innerHTML = `
                <td>
                    <div class="fw-semibold text-break">${escapeHtml(file.name)}</div>
                    <div class="small text-body-secondary">${escapeHtml(formatBytes(file.size))}</div>
                </td>
                <td class="com-audioarchive-upload-progress-cell">
                    <span class="badge bg-secondary com-audioarchive-upload-status mb-2"></span>
                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                </td>
                <td class="com-audioarchive-upload-result"></td>
                <td class="com-audioarchive-upload-actions text-end"></td>
            `;
            queueBody.appendChild(row);
            updateJob(job);
        });

        fileInput.value = '';
        updateControls();
    };

    /**
     * @brief Read and freeze batch metadata for the current queue run.
     *
     * @returns {object} Form values.
     */
    const readBatchMetadata = () =>
    {
        const tags = Array.from(document.querySelectorAll('#jform_tags option:checked'))
            .map((option) => option.value)
            .filter(Boolean);

        return {
            catid: document.getElementById('jform_catid')?.value || '',
            tags,
            access: document.getElementById('jform_access')?.value || '1',
            state: document.getElementById('jform_state')?.value || '0',
            recordedAt: document.getElementById('jform_recorded_at')?.value || '',
        };
    };

    /**
     * @brief Build one multipart request.
     *
     * @param {object} job Upload job.
     * @returns {FormData} Request body.
     */
    const buildRequest = (job) =>
    {
        const data = new FormData();
        data.append('jform[catid]', batchMetadata.catid);
        data.append('jform[access]', batchMetadata.access);
        data.append('jform[state]', batchMetadata.state);
        data.append('jform[recorded_at]', batchMetadata.recordedAt);
        batchMetadata.tags.forEach((tag) => data.append('jform[tags][]', tag));
        data.append('jform[audio_file]', job.file, job.file.name);
        data.append(tokenName, '1');

        return data;
    };

    /**
     * @brief Parse a Joomla JSON response or a generic server response.
     *
     * @param {XMLHttpRequest} xhr Completed request.
     * @returns {object|null} Parsed response.
     */
    const parseResponse = (xhr) =>
    {
        if (xhr.response && typeof xhr.response === 'object')
        {
            return xhr.response;
        }

        try
        {
            return JSON.parse(xhr.responseText || '');
        }
        catch (error)
        {
            return null;
        }
    };

    /**
     * @brief Upload a single queue item.
     *
     * @param {object} job Upload job.
     * @returns {Promise<void>} Completion promise.
     */
    const uploadJob = (job) => new Promise((resolve) =>
    {
        job.state = 'uploading';
        job.message = '';
        updateJob(job);

        const xhr = new XMLHttpRequest();
        job.xhr = xhr;
        xhr.open('POST', endpoint, true);
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', (event) =>
        {
            if (event.lengthComputable)
            {
                setProgress(job, (event.loaded / event.total) * 100);
            }
        });

        xhr.addEventListener('load', () =>
        {
            const response = parseResponse(xhr);

            if (response && response.success === true)
            {
                job.state = 'complete';
                job.result = response.data || {};
                setProgress(job, 100);
            }
            else
            {
                job.state = 'failed';
                job.message = response && response.message
                    ? response.message
                    : translate('COM_AUDIOARCHIVE_BULK_UPLOAD_NO_RESPONSE', 'The server returned no usable response.');
            }

            job.xhr = null;
            updateJob(job);
            resolve();
        });

        xhr.addEventListener('error', () =>
        {
            job.state = 'failed';
            job.message = translate('COM_AUDIOARCHIVE_BULK_UPLOAD_NETWORK_ERROR', 'The upload request failed.');
            job.xhr = null;
            updateJob(job);
            resolve();
        });

        xhr.addEventListener('abort', () =>
        {
            job.state = 'cancelled';
            job.message = translate('COM_AUDIOARCHIVE_BULK_UPLOAD_STATUS_CANCELLED', 'Cancelled');
            job.xhr = null;
            updateJob(job);
            resolve();
        });

        xhr.send(buildRequest(job));
    });

    /**
     * @brief Process all pending jobs sequentially.
     *
     * @returns {Promise<void>} Queue completion promise.
     */
    const startQueue = async () =>
    {
        if (running || !jobs.some((job) => job.state === 'pending'))
        {
            return;
        }

        if (!form.reportValidity())
        {
            return;
        }

        batchMetadata = readBatchMetadata();
        running = true;
        updateControls();

        while (true)
        {
            const job = jobs.find((candidate) => candidate.state === 'pending');

            if (!job)
            {
                break;
            }

            await uploadJob(job);
        }

        running = false;
        batchMetadata = null;
        updateControls();
    };

    selectButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => addFiles(fileInput.files));
    startButton.addEventListener('click', startQueue);
    clearButton.addEventListener('click', () =>
    {
        jobs.filter((job) => job.state !== 'pending' && job.state !== 'uploading').forEach((job) =>
        {
            getRow(job)?.remove();
        });

        for (let index = jobs.length - 1; index >= 0; index -= 1)
        {
            if (jobs[index].state !== 'pending' && jobs[index].state !== 'uploading')
            {
                jobs.splice(index, 1);
            }
        }

        updateControls();
    });

    ['dragenter', 'dragover'].forEach((eventName) =>
    {
        dropzone.addEventListener(eventName, (event) =>
        {
            event.preventDefault();

            if (!running)
            {
                dropzone.classList.add('is-dragover');
            }
        });
    });

    ['dragleave', 'drop'].forEach((eventName) =>
    {
        dropzone.addEventListener(eventName, (event) =>
        {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) =>
    {
        if (!running && event.dataTransfer)
        {
            addFiles(event.dataTransfer.files);
        }
    });

    dropzone.addEventListener('click', () =>
    {
        if (!running)
        {
            fileInput.click();
        }
    });

    dropzone.addEventListener('keydown', (event) =>
    {
        if (!running && (event.key === 'Enter' || event.key === ' '))
        {
            event.preventDefault();
            fileInput.click();
        }
    });

    form.addEventListener('submit', (event) => event.preventDefault());
    updateControls();
})();
