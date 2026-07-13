/**
 * @brief Add local tag searching to the administrator batch dialog.
 */
const initialiseAudioArchiveBatch = () =>
{
    const normaliseSearchValue = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase();

    document.querySelectorAll('[data-audioarchive-batch-tag-options]').forEach((list) =>
    {
        const fieldset = list.closest('.com-audioarchive-batch-tags');
        const search = fieldset?.querySelector('[data-audioarchive-batch-tag-search]');
        const searchWrapper = fieldset?.querySelector('[data-audioarchive-batch-tag-search-wrapper]');
        const noMatches = fieldset?.querySelector('[data-audioarchive-batch-tag-no-matches]');
        const options = Array.from(list.querySelectorAll('[data-audioarchive-batch-tag-option]'));

        if (!(search instanceof HTMLInputElement) || !(searchWrapper instanceof HTMLElement) || options.length === 0)
        {
            return;
        }

        searchWrapper.hidden = false;

        const filterOptions = () =>
        {
            const query = normaliseSearchValue(search.value.trim());
            let visibleCount = 0;

            options.forEach((option) =>
            {
                const visible = query === '' || normaliseSearchValue(option.textContent || '').includes(query);
                option.hidden = !visible;
                visibleCount += visible ? 1 : 0;
            });

            if (noMatches instanceof HTMLElement)
            {
                noMatches.hidden = visibleCount !== 0;
            }
        };

        search.addEventListener('input', filterOptions);
        search.addEventListener('search', filterOptions);
    });
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveBatch);
