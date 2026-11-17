/** @brief Private cover upload controls; uploads save independently from the editor form. */
export function mountCover(control, selected = () => control?.dataset.coverId || '')
{
    if (!control || control.dataset.coverMounted) return;
    control.dataset.coverMounted = '1';
    const file = control.querySelector('[data-cover-file]');
    const upload = control.querySelector('[data-cover-upload]');
    const remove = control.querySelector('[data-cover-remove]');
    const status = control.querySelector('[data-cover-status]');
    let busy = false;
    let generation = 0;
    let activeId = '';
    /** @brief Bind each request and result to the selection at request time. */
    const request = async (action) =>
    {
        if (busy) return;
        const id = selected();
        if (!id)
        {
            status.textContent = control.dataset.coverFirst;
            return;
        }
        if (action === 'upload' && !file.files[0])
        {
            file.reportValidity();
            file.click();
            return;
        }
        busy = true;
        const current = ++generation;
        upload.disabled = remove.disabled = file.disabled = true;
        const body = new FormData();
        body.set(control.dataset.coverToken, '1');
        body.set('kind', control.dataset.coverKind);
        body.set('id', id);
        body.set('action', action);
        if (action === 'upload') body.set('cover', file.files[0]);
        try
        {
            const response = await fetch(control.dataset.coverEndpoint, {method: 'POST', credentials: 'same-origin', body});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || control.dataset.coverError);
            if (current !== generation || id !== selected()) return;
            const cover = result.data.cover;
            status.textContent = cover ? `${control.dataset.coverSaved} (${cover.width} × ${cover.height}, ${Math.ceil(cover.file_size / 1024)} KB)` : control.dataset.coverEmpty;
            if (action !== 'status') file.value = '';
        }
        catch (error)
        {
            if (current === generation && id === selected()) status.textContent = error.message || control.dataset.coverError;
        }
        finally
        {
            busy = false;
            upload.disabled = remove.disabled = file.disabled = !selected();
            if (id !== selected())
            {
                activeId = '';
                refresh();
            }
        }
    };
    /** @brief Clear the previous selection and retrieve the selected entity's cover status. */
    const refresh = () =>
    {
        const id = selected();
        if (activeId === id && activeId) return;
        activeId = id;
        ++generation;
        file.value = '';
        status.textContent = id ? '' : control.dataset.coverFirst;
        upload.disabled = remove.disabled = file.disabled = busy || !id;
        if (id && !busy) void request('status');
    };
    upload.addEventListener('click', () => void request('upload'));
    remove.addEventListener('click', () => void request('remove'));
    control.addEventListener('audioarchive:cover-selection', () =>
    {
        refresh();
    });
    refresh();
}

for (const control of document.querySelectorAll('[data-audioarchive-cover][data-cover-kind="clip"]'))
{
    mountCover(control);
}
