/** @brief Preview pending clip artwork locally; upload only with the enclosing form. */
for (const root of document.querySelectorAll('[data-cover-form]'))
{
    const file = root.querySelector('[data-cover-pending-file]');
    const remove = root.querySelector('[data-cover-pending-remove]');
    const undo = root.querySelector('[data-cover-undo]');
    const preview = root.querySelector('[data-cover-local-preview]');
    const status = root.querySelector('[data-cover-status]');
    let objectUrl = null;
    /** @brief Release temporary browser resources when selection changes or the page closes. */
    const release = () =>
    {
        if (objectUrl)
        {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };
    /** @brief Reflect the pending choice without touching the stored cover. */
    const refresh = () =>
    {
        release();
        preview.removeAttribute('src');
        const selected = file.files?.[0];
        preview.hidden = !selected;
        if (selected)
        {
            objectUrl = URL.createObjectURL(selected);
            preview.src = objectUrl;
        }
        status.textContent = selected ? root.dataset.coverPending : remove.checked ? root.dataset.coverRemoving : root.dataset.coverInitial;
        undo.hidden = !selected && !remove.checked;
    };
    file.addEventListener('change', () =>
    {
        remove.checked = false;
        refresh();
    });
    remove.addEventListener('change', () =>
    {
        if (remove.checked)
        {
            file.value = '';
        }
        refresh();
    });
    undo.addEventListener('click', () =>
    {
        file.value = '';
        remove.checked = false;
        refresh();
    });
    root.closest('form')?.addEventListener('reset', () => setTimeout(refresh, 0));
    window.addEventListener('pagehide', release);
    window.addEventListener('pageshow', refresh);
    refresh();
}
