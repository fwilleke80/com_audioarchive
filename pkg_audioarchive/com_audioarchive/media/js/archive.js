/**
 * @brief Enhance the public Archive filters without changing server-side behavior.
 */
const initialiseAudioArchiveFilters = () =>
{
	const normaliseSearchValue = (value) => value
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLocaleLowerCase();

	const parseDuration = (value) =>
	{
		const trimmed = value.trim();

		if (trimmed === '')
		{
			return null;
		}

		if (/^\d+$/.test(trimmed))
		{
			return Number.parseInt(trimmed, 10);
		}

		const parts = trimmed.split(':');

		if (parts.length < 2 || parts.length > 3 || parts.some((part) => !/^\d+$/.test(part)))
		{
			return Number.NaN;
		}

		const numbers = parts.map((part) => Number.parseInt(part, 10));

		if (numbers.slice(1).some((part) => part > 59))
		{
			return Number.NaN;
		}

		return parts.length === 2
			? (numbers[0] * 60) + numbers[1]
			: (numbers[0] * 3600) + (numbers[1] * 60) + numbers[2];
	};

	const formatDuration = (value) =>
	{
		const seconds = Math.max(0, Math.round(value));
		const hours = Math.floor(seconds / 3600);
		const minutes = Math.floor((seconds % 3600) / 60);
		const remainder = seconds % 60;

		if (hours > 0)
		{
			return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
		}

		return `${minutes}:${String(remainder).padStart(2, '0')}`;
	};

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
		const defaultExpanded = panel.dataset.defaultExpanded !== 'false';
		let expanded = forceOpen ? true : defaultExpanded;

		if (!forceOpen)
		{
			try
			{
				const storedState = window.sessionStorage.getItem(storageKey);

				if (storedState === 'expanded' || storedState === 'collapsed')
				{
					expanded = storedState === 'expanded';
				}
			}
			catch (error)
			{
				expanded = defaultExpanded;
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

	document.querySelectorAll('[data-audioarchive-duration-slider]').forEach((slider) =>
	{
		const fieldset = slider.closest('.com-audioarchive-filter-duration');
		const track = slider.querySelector('[data-audioarchive-duration-track]');
		const minimumRange = slider.querySelector('[data-audioarchive-duration-min-range]');
		const maximumRange = slider.querySelector('[data-audioarchive-duration-max-range]');
		const maximumLabel = slider.querySelector('[data-audioarchive-duration-maximum-label]');
		const minimumField = fieldset?.querySelector('[data-audioarchive-duration-min-field]');
		const maximumField = fieldset?.querySelector('[data-audioarchive-duration-max-field]');
		const maximum = Number.parseInt(slider.dataset.maximum || '0', 10);

		if (
			!(track instanceof HTMLElement)
			|| !(minimumRange instanceof HTMLInputElement)
			|| !(maximumRange instanceof HTMLInputElement)
			|| !(minimumField instanceof HTMLInputElement)
			|| !(maximumField instanceof HTMLInputElement)
			|| !Number.isFinite(maximum)
			|| maximum <= 0
		)
		{
			return;
		}

		const clamp = (value) => Math.max(0, Math.min(maximum, value));

		const updatePresentation = () =>
		{
			const minimum = clamp(Number.parseInt(minimumRange.value, 10));
			const maximumValue = clamp(Number.parseInt(maximumRange.value, 10));
			const start = (minimum / maximum) * 100;
			const end = (maximumValue / maximum) * 100;

			track.style.setProperty('--audioarchive-duration-start', `${start}%`);
			track.style.setProperty('--audioarchive-duration-end', `${end}%`);
			minimumRange.setAttribute('aria-valuetext', formatDuration(minimum));
			maximumRange.setAttribute('aria-valuetext', formatDuration(maximumValue));
			minimumRange.style.zIndex = minimum > maximum - Math.max(1, maximum * 0.05) ? '5' : '3';

			if (maximumLabel instanceof HTMLElement)
			{
				maximumLabel.textContent = formatDuration(maximum);
			}
		};

		const applyRangeChange = (changedRange) =>
		{
			let minimum = clamp(Number.parseInt(minimumRange.value, 10));
			let maximumValue = clamp(Number.parseInt(maximumRange.value, 10));

			if (minimum > maximumValue)
			{
				if (changedRange === minimumRange)
				{
					minimum = maximumValue;
				}
				else
				{
					maximumValue = minimum;
				}
			}

			minimumRange.value = String(minimum);
			maximumRange.value = String(maximumValue);
			minimumField.value = formatDuration(minimum);
			maximumField.value = formatDuration(maximumValue);
			updatePresentation();
		};

		const applyFieldChange = (changedField) =>
		{
			const parsedMinimum = parseDuration(minimumField.value);
			const parsedMaximum = parseDuration(maximumField.value);

			if (Number.isNaN(parsedMinimum) || Number.isNaN(parsedMaximum))
			{
				return;
			}

			let minimum = parsedMinimum === null ? 0 : clamp(parsedMinimum);
			let maximumValue = parsedMaximum === null ? maximum : clamp(parsedMaximum);

			if (minimum > maximumValue)
			{
				if (changedField === minimumField)
				{
					maximumValue = minimum;
				}
				else
				{
					minimum = maximumValue;
				}
			}

			minimumRange.value = String(minimum);
			maximumRange.value = String(maximumValue);
			updatePresentation();
		};

		minimumRange.addEventListener('input', () => applyRangeChange(minimumRange));
		maximumRange.addEventListener('input', () => applyRangeChange(maximumRange));
		minimumField.addEventListener('change', () => applyFieldChange(minimumField));
		maximumField.addEventListener('change', () => applyFieldChange(maximumField));

		slider.hidden = false;
		applyFieldChange(null);
	});
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveFilters);
