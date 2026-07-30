/**
 * @brief Add local tag searching to the dynamically created administrator batch dialog.
 */
const normaliseAudioArchiveBatchSearchValue = (value) => value
	.normalize('NFD')
	.replace(/[\u0300-\u036f]/g, '')
	.toLocaleLowerCase();

const filterAudioArchiveBatchTags = (search) =>
{
	if (!(search instanceof HTMLInputElement))
	{
		return;
	}

	const fieldset = search.closest('.com-audioarchive-batch-tags');
	const list = fieldset?.querySelector('[data-audioarchive-batch-tag-options]');
	const noMatches = fieldset?.querySelector('[data-audioarchive-batch-tag-no-matches]');

	if (!(list instanceof HTMLElement))
	{
		return;
	}

	const query = normaliseAudioArchiveBatchSearchValue(search.value.trim());
	const options = Array.from(list.querySelectorAll('[data-audioarchive-batch-tag-option]'));
	let visibleCount = 0;

	options.forEach((option) =>
	{
		const visible = query === ''
			|| normaliseAudioArchiveBatchSearchValue(option.textContent || '').includes(query);
		option.hidden = !visible;
		visibleCount += visible ? 1 : 0;
	});

	if (noMatches instanceof HTMLElement)
	{
		noMatches.hidden = visibleCount !== 0;
	}
};

document.addEventListener('input', (event) =>
{
	if (event.target instanceof HTMLInputElement && event.target.matches('[data-audioarchive-batch-tag-search]'))
	{
		filterAudioArchiveBatchTags(event.target);
	}
});

document.addEventListener('search', (event) =>
{
	if (event.target instanceof HTMLInputElement && event.target.matches('[data-audioarchive-batch-tag-search]'))
	{
		filterAudioArchiveBatchTags(event.target);
	}
});


/**
 * @brief Close the Joomla inline batch dialog reliably.
 *
 * Joomla clones the template into its dialog custom element. The declarative
 * close attribute is not handled consistently for this inline popup, so the
 * component calls the dialog API explicitly and retains safe fallbacks.
 *
 * @param {HTMLElement} button Clicked Cancel button.
 *
 * @return {void}
 */
const closeAudioArchiveBatchDialog = (button) =>
{
	const root = button.getRootNode();
	const dialog = button.closest('joomla-dialog')
		|| (typeof ShadowRoot !== 'undefined'
			&& root instanceof ShadowRoot
			&& root.host instanceof HTMLElement
			&& root.host.matches('joomla-dialog')
			? root.host
			: null);

	if (!(dialog instanceof HTMLElement))
	{
		return;
	}

	if (typeof dialog.close === 'function')
	{
		dialog.close();
		return;
	}

	if (typeof dialog.hide === 'function')
	{
		dialog.hide();
		return;
	}

	dialog.removeAttribute('open');
	dialog.hidden = true;
};

document.addEventListener('click', (event) =>
{
	const target = event.composedPath().find((node) =>
		node instanceof HTMLElement && node.matches('[data-audioarchive-batch-cancel]')
	) || null;

	if (!(target instanceof HTMLElement))
	{
		return;
	}

	event.preventDefault();
	closeAudioArchiveBatchDialog(target);
});
