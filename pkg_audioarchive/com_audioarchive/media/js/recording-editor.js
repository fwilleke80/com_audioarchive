/** @brief Clone the portable recording without sharing mutable events or boards. */
export const cloneRecording = (recording) => JSON.parse(JSON.stringify(recording));

/** @brief Apply one bounded event edit, retaining stable ordering and layer metadata. */
export function editRecording(recording, action, index, value)
{
    const result = cloneRecording(recording);
    if (action === 'delete')
    {
        if (!['note', 'pad'].includes(result.events[index]?.type)) return result;
        result.events.splice(index, 1);
    }
    else
    {
        const event = action === 'add' ? {...value} : {...result.events[index], ...value};
        if (!['note', 'pad'].includes(event.type)) throw new Error('Invalid event');
        const board = result.layerBoards?.[event.layer || 0] || result.board;
        if (!board?.[event.pad]) throw new Error('Choose a populated source pad');
        event.t = Math.max(0, Math.min(7199960, Math.round(event.t)));
        if (event.type === 'note')
        {
            event.note = Math.max(0, Math.min(127, Math.round(event.note)));
            event.duration = Math.max(40, Math.min(7200000 - event.t, Math.round(event.duration || 250)));
            event.velocity = Math.max(1, Math.min(127, Math.round(event.velocity || 127)));
        }
        if (action === 'add')
        {
            if (result.events.length >= 20000) throw new Error('Event limit reached');
            result.events.push(event);
        }
        else result.events[index] = event;
        result.durationMs = Math.min(7200000, Math.max(result.durationMs, event.t + Math.max(1000, event.duration || 0)));
    }
    result.events.sort((a, b) => a.t - b.t);
    return result;
}

/** @brief Restore polyphony at a seek point without replaying earlier audio events. */
export function recordingSeekState(recording, position)
{
    const time = Math.max(0, Math.min(recording.durationMs, position));
    const polyphony = new Map([[0, recording.initialState.polyphony]]);
    let index = 0;
    while (index < recording.events.length && recording.events[index].t < time)
    {
        const event = recording.events[index++];
        if (event.type === 'polyphony') polyphony.set(event.layer || 0, event.enabled);
    }
    return {time, index, polyphony};
}

/** @brief Bind a draft editor to the existing roll; callbacks own playback and persistence. */
export class RecordingEditor
{
    constructor(root, roll, callbacks)
    {
        this.root = root;
        this.roll = roll;
        this.callbacks = callbacks;
        this.draft = null;
        this.history = [];
        this.busy = false;
        this.layer = 0;
        this.controls = root.querySelector('[data-recording-edit-controls]');
        this.labels = JSON.parse(this.controls.dataset.labels);
        this.toggle = this.controls.querySelector('[data-edit-toggle]');
        this.panel = this.controls.querySelector('[data-edit-panel]');
        this.layerSelect = this.controls.querySelector('[data-edit-layer]');
        this.padSelect = this.controls.querySelector('[data-edit-pad]');
        this.undo = this.controls.querySelector('[data-edit-undo]');
        this.message = this.controls.querySelector('[data-edit-message]');
        this.toggle.addEventListener('click', () =>
        {
            if (callbacks.blocked()) return;
            this.draft = cloneRecording(callbacks.selected());
            this.history = [];
            this.original = JSON.stringify(this.draft);
            this.layer = 0;
            this.refreshSources();
            callbacks.render();
        });
        this.controls.querySelector('[data-edit-cancel]').addEventListener('click', () => this.close());
        this.undo.addEventListener('click', () =>
        {
            if (this.busy || !this.history.length) return;
            this.callbacks.stopPreview();
            this.cancelAudition();
            this.draft = this.history.pop();
            callbacks.stopAudition();
            callbacks.render();
        });
        this.controls.querySelector('[data-edit-save]').addEventListener('click', async () =>
        {
            if (this.busy || !this.draft) return;
            this.callbacks.stopPreview();
            this.cancelAudition();
            this.busy = true;
            callbacks.render();
            try
            {
                if (await callbacks.save(this.draft))
                {
                    this.busy = false;
                    this.close();
                    return;
                }
                this.message.textContent = this.labels.error;
            }
            catch (error)
            {
                this.message.textContent = this.labels.error;
            }
            this.busy = false;
            callbacks.render();
        });
        this.layerSelect.addEventListener('change', () =>
        {
            this.layer = Number(this.layerSelect.value);
            this.refreshSources();
            callbacks.render();
        });
        roll.addEventListener('click', (event) => this.click(event));
        roll.addEventListener('dblclick', (event) => this.doubleClick(event));
        roll.addEventListener('pointerdown', (event) => this.pointerDown(event));
        roll.addEventListener('pointermove', (event) => this.pointerMove(event));
        roll.addEventListener('pointerup', (event) => this.pointerUp(event));
        roll.addEventListener('pointercancel', () =>
        {
            this.drag = null;
            callbacks.render();
        });
        roll.addEventListener('keydown', (event) =>
        {
            if (!this.draft || this.busy) return;
            const node = event.target.closest('[data-event-index]');
            if (!node) return;
            const index = Number(node.dataset.eventIndex);
            if (event.key === 'Delete' || event.key === 'Backspace')
            {
                event.preventDefault();
                this.change('delete', index);
            }
            else if (event.key === 'Enter' || event.key === ' ')
            {
                event.preventDefault();
                callbacks.stopPreview();
                callbacks.audition(this.draft, this.draft.events[index]);
            }
            else if (['ArrowLeft', 'ArrowRight'].includes(event.key))
            {
                event.preventDefault();
                this.change('move', index, {t: this.draft.events[index].t + (event.key === 'ArrowLeft' ? -10 : 10)});
            }
        });
        window.addEventListener('beforeunload', (event) =>
        {
            if (this.draft && JSON.stringify(this.draft) !== this.original)
            {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    }

    /** @brief Cancel delayed or already playing event audition before previewing a song. */
    cancelAudition()
    {
        clearTimeout(this.clickTimer);
        this.callbacks.stopAudition();
    }

    /** @brief Discard the draft and pending audition safely. */
    close()
    {
        if (this.busy) return;
        this.callbacks.stopPreview();
        this.cancelAudition();
        this.draft = null;
        this.history = [];
        this.message.textContent = '';
        this.callbacks.render();
    }

    /** @brief Populate layer and source choices from stored mappings, never the live board. */
    refreshSources()
    {
        const previousPad = this.padSelect.value;
        this.layerSelect.replaceChildren();
        const layers = [...new Set([0, ...Object.keys(this.draft.layerBoards || {}).map(Number), ...this.draft.events.map((event) => event.layer || 0)])].sort((a, b) => a - b);
        for (const layer of layers) this.layerSelect.add(new Option(`${this.labels.layer} ${layer + 1}`, String(layer)));
        this.layerSelect.value = String(this.layer);
        this.padSelect.replaceChildren();
        const board = this.draft.layerBoards?.[this.layer] || this.draft.board;
        board.forEach((entry, index) =>
        {
            if (entry) this.padSelect.add(new Option(`${index + 1}: ${entry.title}`, String(index)));
        });
        if ([...this.padSelect.options].some((option) => option.value === previousPad)) this.padSelect.value = previousPad;
    }

    /** @brief Reflect draft/transport state without losing inputs. */
    sync()
    {
        this.toggle.hidden = Boolean(this.draft);
        this.toggle.disabled = this.callbacks.blocked() || !this.callbacks.selected();
        this.panel.hidden = !this.draft;
        this.roll.classList.toggle('is-editing', Boolean(this.draft));
        this.panel.querySelectorAll('button, select').forEach((control) => { control.disabled = this.busy; });
        this.undo.disabled = this.busy || !this.history.length;
    }

    /** @brief Calculate coordinates inside the scrolled and zoomed roll. */
    point(event)
    {
        const bounds = this.roll.getBoundingClientRect();
        return {t: Math.max(0, Math.round((event.clientX - bounds.left - 64) * 10 / this.callbacks.zoom())), row: Math.floor((event.clientY - bounds.top - 22) / 19), x: event.clientX - bounds.left};
    }

    /** @brief Apply a reversible edit; keep failures inside the editor. */
    change(action, index, value)
    {
        if (!this.draft || this.busy) return;
        try
        {
            const next = editRecording(this.draft, action, index, value);
            this.callbacks.stopPreview();
            this.cancelAudition();
            this.history.push(this.draft);
            if (this.history.length > 100) this.history.shift();
            this.draft = next;
            this.message.textContent = '';
            this.callbacks.stopAudition();
            this.callbacks.render();
        }
        catch (error)
        {
            this.message.textContent = this.labels.invalid;
            this.callbacks.render();
        }
    }

    /** @brief Seek in normal mode; delay audition so a double click can delete silently. */
    click(event)
    {
        if (this.suppressClick)
        {
            this.suppressClick = false;
            return;
        }
        if (this.busy) return;
        const point = this.point(event);
        if (point.x < 64) return;
        if (!this.draft || point.row < 0)
        {
            this.callbacks.seek(point.t);
            return;
        }
        const node = event.target.closest('[data-event-index]');
        if (!node) return;
        clearTimeout(this.clickTimer);
        this.clickTimer = setTimeout(() =>
        {
            if (this.draft && !this.busy)
            {
                this.callbacks.stopPreview();
                this.callbacks.audition(this.draft, this.draft.events[Number(node.dataset.eventIndex)]);
            }
        }, 280);
    }

    /** @brief Delete a hit event or create one in the selected stored layer. */
    doubleClick(event)
    {
        clearTimeout(this.clickTimer);
        if (!this.draft || this.busy) return;
        const node = event.target.closest('[data-event-index]');
        if (node)
        {
            this.change('delete', Number(node.dataset.eventIndex));
            return;
        }
        const point = this.point(event);
        const row = this.rows?.[point.row];
        if (!row || point.x < 64) return;
        const pad = row.type === 'pad' ? row.value : Number(this.padSelect.value || -1);
        this.change('add', -1, {type: row.type, t: point.t, pad, layer: this.layer, ...(row.type === 'note' ? {note: row.value, duration: 250, velocity: 127} : {})});
    }

    /** @brief Capture a drag without mutating the recording until release. */
    pointerDown(event)
    {
        if (!this.draft || this.busy || event.button !== 0) return;
        let node = event.target.closest('[data-event-index]');
        if (!node) return;
        const index = node.dataset.eventIndex;
        this.callbacks.stopPreview();
        node = this.roll.querySelector?.(`[data-event-index="${index}"]`) || node;
        clearTimeout(this.clickTimer);
        this.drag = {node, index: Number(node.dataset.eventIndex), x: event.clientX, y: event.clientY, point: this.point(event), moved: false};
        node.setPointerCapture(event.pointerId);
    }

    /** @brief Preview horizontal and vertical movement with a small click threshold. */
    pointerMove(event)
    {
        if (!this.drag) return;
        const dx = event.clientX - this.drag.x;
        const dy = event.clientY - this.drag.y;
        if (Math.abs(dx) + Math.abs(dy) > 4) this.drag.moved = true;
        if (this.drag.moved) this.drag.node.style.transform = `translate(${dx}px, ${Math.round(dy / 19) * 19}px)`;
    }

    /** @brief Commit time/pitch or pad movement while keeping the event's layer. */
    pointerUp(event)
    {
        const drag = this.drag;
        this.drag = null;
        if (!drag?.moved || !this.draft) return;
        this.suppressClick = true;
        setTimeout(() => { this.suppressClick = false; }, 350);
        const original = this.draft.events[drag.index];
        const point = this.point(event);
        const row = this.rows?.[point.row];
        if (!row || row.type !== original.type)
        {
            this.callbacks.render();
            return;
        }
        this.change('move', drag.index, {t: original.t + point.t - drag.point.t, ...(row.type === 'note' ? {note: row.value} : {pad: row.value})});
    }
}
