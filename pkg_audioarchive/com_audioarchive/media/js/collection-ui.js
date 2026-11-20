/** @brief Shared disclosure menus with viewport positioning and keyboard dismissal. */
export function initialiseCollectionMenus()
{
    if (document.documentElement.dataset.audioarchiveMenus) return;
    document.documentElement.dataset.audioarchiveMenus = '1';
    decorateCollectionMenus();
    new MutationObserver(() => decorateCollectionMenus()).observe(document.body, {childList: true, subtree: true});
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
    /** @brief Block native disclosure activation for mouse and keyboard while locked. */
    const blockDisabled = (event) =>
    {
        const summary = event.target.closest?.('summary[aria-disabled="true"]');
        if (!summary || !summary.parentElement.matches(selector)) return;
        if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
        event.preventDefault();
        event.stopImmediatePropagation();
    };
    document.addEventListener('click', blockDisabled, true);
    document.addEventListener('keydown', blockDisabled, true);
    document.addEventListener('toggle', (event) =>
    {
        const menu = event.target;
        if (!menu.matches?.(selector) || !menu.open) return;
        if (menu.querySelector('summary')?.getAttribute('aria-disabled') === 'true')
        {
            menu.open = false;
            return;
        }
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

/** @brief Give shared menus semantic icons without replacing labels or event listeners. */
export function decorateCollectionMenus()
{
    const menus = '.com-audioarchive-more-menu, .com-audioarchive-recording-actions-menu, .com-audioarchive-share-popover, .com-audioarchive-add-to-popover, .com-audioarchive-menu';
    document.querySelectorAll(menus).forEach(menu =>
    {
        menu.querySelectorAll('button, a[role="menuitem"]').forEach(control =>
        {
            const action = [control.dataset.collectionAction, control.dataset.recordingRowAction, control.dataset.action,
                ...control.getAttributeNames().filter(name => name.startsWith('data-'))].filter(Boolean).join(' ');
            const destructive = /delete|remove/.test(action) || control.classList.contains('btn-outline-danger');
            if (destructive) control.classList.add('text-danger');
            if (control.querySelector('[aria-hidden="true"]')) return;
            const icons = [['delete|remove', 'trash'], ['rename|edit', 'edit'], ['export', 'download'],
                ['import', 'upload'], ['revoke', 'unlink'], ['copy|link', 'link'], ['share|native', 'share'],
                ['default', 'star'], ['soundboard|board', 'grid-view'], ['playlist', 'list'], ['create|new', 'plus']];
            const match = icons.find(([pattern]) => new RegExp(pattern).test(action));
            const icon = document.createElement('span');
            icon.className = `icon-${match?.[1] || 'list'}`;
            icon.setAttribute('aria-hidden', 'true');
            control.prepend(icon);
        });
    });
}
