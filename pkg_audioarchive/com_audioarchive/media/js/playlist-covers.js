/** @brief Keep per-playlist cover selections in memory until the playlist editor saves. */
export function mountPlaylistCovers(control, selected, exists, changed)
{
    const pending = new Map();
    if (!control)
    {
        return {pending, refresh() {}, clear() {}};
    }
    const file = control.querySelector('[data-cover-file]');
    const remove = control.querySelector('[data-cover-remove]');
    const undo = control.querySelector('[data-cover-undo]');
    const preview = control.querySelector('[data-cover-preview]');
    const status = control.querySelector('[data-cover-status]');
    const stored = new Map();
    let active = '';
    let generation = 0;
    let objectUrl = '';
    /** @brief Release the current local thumbnail. */
    const release = () =>
    {
        if (objectUrl)
        {
            URL.revokeObjectURL(objectUrl);
            objectUrl = '';
        }
    };
    /** @brief Render a pending file or the last known stored-cover status. */
    const render = () =>
    {
        release();
        preview.removeAttribute('src');
        const image = pending.get(active);
        preview.hidden = !image;
        if (image)
        {
            objectUrl = URL.createObjectURL(image);
            preview.src = objectUrl;
        }
        undo.hidden = !pending.has(active);
        status.textContent = pending.has(active) ? image ? control.dataset.coverPending : control.dataset.coverRemoving : stored.get(active) || control.dataset.coverEmpty;
        file.disabled = remove.disabled = !active;
        control.hidden = !active;
    };
    /** @brief Read status only; stale responses never replace another playlist's pending selection. */
    const refresh = async () =>
    {
        const id = selected();
        if (active !== id)
        {
            file.value = '';
            active = id;
        }
        render();
        const current = ++generation;
        if (!id || !exists(id) || stored.has(id)) return;
        const body = new FormData();
        body.set(control.dataset.coverToken, '1');
        body.set('kind', 'playlist');
        body.set('id', id);
        body.set('action', 'status');
        try
        {
            const response = await fetch(control.dataset.coverEndpoint, {method: 'POST', credentials: 'same-origin', body});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || control.dataset.coverError);
            const cover = result.data.cover;
            stored.set(id, cover ? `${control.dataset.coverSaved} (${cover.width} × ${cover.height})` : control.dataset.coverEmpty);
            if (current === generation && selected() === id) render();
        }
        catch (error)
        {
            if (current === generation && !pending.has(id)) status.textContent = error.message;
        }
    };
    file.addEventListener('change', () =>
    {
        if (!active || !file.files?.[0]) return;
        pending.set(active, file.files[0]);
        render();
        changed();
    });
    remove.addEventListener('click', () =>
    {
        if (!active) return;
        pending.set(active, null);
        file.value = '';
        render();
        changed();
    });
    undo.addEventListener('click', () =>
    {
        pending.delete(active);
        file.value = '';
        render();
        changed();
    });
    window.addEventListener('pagehide', release);
    window.addEventListener('pageshow', render);
    return {pending, refresh, clear(id = null)
    {
        if (id === null)
        {
            pending.clear();
            stored.clear();
        }
        else
        {
            pending.delete(id);
            stored.delete(id);
        }
        file.value = '';
        void refresh();
    }};
}
