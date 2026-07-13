/**
 * @brief Enhance the public Archive filters without changing server-side behavior.
 */
const initialiseAudioArchiveFilters = () =>
{
	const normaliseSearchValue = (value) => value
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLocaleLowerCase();

	document.querySelectorAll('[data-audioarchive-filter-panel]').forEach((panel, index) =>
	{
		const toggle = panel.querySelector('[data-audioarchive-filter-toggle]');
		const content = panel.querySelector('[data-audioarchive-filter-content]');

		if (!(toggle instanceof HTMLButtonElement) || !(content instanceof HTMLElement))
		{
			return;
		}

		const storageKey = `com_audioarchive.archive.filters.${index}`;
		const forceOpen = panel.dataset.forceOpen === 'true';
		let expanded = true;

		if (!forceOpen)
		{
			try
			{
				expanded = window.sessionStorage.getItem(storageKey) !== 'collapsed';
			}
			catch (error)
			{
				expanded = true;
			}
		}

		const applyExpandedState = (isExpanded) =>
		{
			const label = toggle.querySelector('[data-audioarchive-filter-toggle-label]');
			const icon = toggle.querySelector('.com-audioarchive-filter-toggle-icon');
			const showLabel = toggle.dataset.showLabel || '';
			const hideLabel = toggle.dataset.hideLabel || '';

			content.hidden = !isExpanded;
			toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
			panel.classList.toggle('is-collapsed', !isExpanded);

			if (label)
			{
				label.textContent = isExpanded ? hideLabel : showLabel;
			}

			if (icon)
			{
				icon.textContent = isExpanded ? '⌃' : '⌄';
			}
		};

		toggle.hidden = false;
		applyExpandedState(expanded);
		toggle.addEventListener('click', () =>
		{
			expanded = toggle.getAttribute('aria-expanded') !== 'true';
			applyExpandedState(expanded);

			try
			{
				window.sessionStorage.setItem(storageKey, expanded ? 'expanded' : 'collapsed');
			}
			catch (error)
			{
				// Storage is optional; the control still works for the current page.
			}
		});
	});

	document.querySelectorAll('[data-audioarchive-tag-options]').forEach((list) =>
	{
		const fieldset = list.closest('.com-audioarchive-filter-tags');
		const search = fieldset?.querySelector('[data-audioarchive-tag-search]');
		const searchWrapper = fieldset?.querySelector('[data-audioarchive-tag-search-wrapper]');
		const noMatches = fieldset?.querySelector('[data-audioarchive-tag-no-matches]');
		const options = Array.from(list.querySelectorAll('[data-audioarchive-tag-option]'));

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

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveFilters);
