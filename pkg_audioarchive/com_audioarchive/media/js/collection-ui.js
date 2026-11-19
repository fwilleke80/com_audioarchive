/** @brief Shared disclosure menus with viewport positioning and keyboard dismissal. */
export function initialiseCollectionMenus()
{
    if (document.documentElement.dataset.audioarchiveMenus) return;
    document.documentElement.dataset.audioarchiveMenus = '1';
    const selector = 'details.com-audioarchive-more, details.com-audioarchive-recording-actions';
    /** @brief Keep the popup inside the viewport and outside table overflow clipping. */
    const position = (details) =>
    {
        const summary = details.querySelector('summary');
        const menu = summary?.nextElementSibling;
        if (!menu) return;
        const bounds = summary.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.maxHeight = `${Math.max(120, window.innerHeight - 24)}px`;
        const width = menu.getBoundingClientRect().width;
        const height = menu.getBoundingClientRect().height;
        menu.style.left = `${Math.max(8, Math.min(bounds.right - width, window.innerWidth - width - 8))}px`;
        menu.style.top = `${Math.max(8, Math.min(bounds.bottom + 4, window.innerHeight - height - 8))}px`;
    };
    document.addEventListener('toggle', (event) =>
    {
        const menu = event.target;
        if (!menu.matches?.(selector) || !menu.open) return;
        document.querySelectorAll(selector).forEach(other =>
        {
            if (other !== menu) other.open = false;
        });
        position(menu);
    }, true);
    document.addEventListener('click', (event) =>
    {
        document.querySelectorAll(selector).forEach(menu =>
        {
            if (!menu.open) return;
            if (!menu.contains(event.target)) menu.open = false;
            else if (event.target.closest('button') && !event.target.closest('.com-audioarchive-share-toggle')) menu.open = false;
        });
    });
    document.addEventListener('keydown', (event) =>
    {
        if (event.key !== 'Escape') return;
        document.querySelectorAll(selector).forEach(menu =>
        {
            if (!menu.open) return;
            menu.open = false;
            menu.querySelector('summary')?.focus();
            event.preventDefault();
        });
    });
    window.addEventListener('resize', () => document.querySelectorAll(selector).forEach(menu =>
    {
        if (menu.open) position(menu);
    }));
    document.addEventListener('scroll', (event) => document.querySelectorAll(selector).forEach(menu =>
    {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    }), true);
}
