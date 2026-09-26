import {Collections} from './collections.js?v=0.13.4.6';

/** @brief Present explicit account destinations using native keyboard-accessible buttons. */
export function openSoundboardChooser(button, clip, capacity)
{
	document.querySelector('[data-audioarchive-board-chooser]')?.dispatchEvent(new Event('dismiss'));
	const menu = document.createElement('div');
	menu.dataset.audioarchiveBoardChooser = '';
	menu.className = 'dropdown-menu show p-2 shadow';
	menu.style.position = 'fixed';
	menu.style.zIndex = '1080';
	menu.style.maxHeight = '60vh';
	menu.style.overflowY = 'auto';
	menu.setAttribute('role', 'menu');
	menu.tabIndex = -1;
	menu.setAttribute('aria-label', button.getAttribute('aria-label') || button.textContent.trim());
	button.setAttribute('aria-haspopup', 'menu');
	button.setAttribute('aria-expanded', 'true');
	const close = () =>
	{
		menu.remove();
		button.setAttribute('aria-expanded', 'false');
		document.removeEventListener('click', outside, true);
		window.removeEventListener('resize', close);
	};
	const outside = (event) =>
	{
		if (!menu.contains(event.target) && !button.contains(event.target)) close();
	};
	menu.addEventListener('dismiss', close);
	menu.addEventListener('keydown', (event) =>
	{
		if (event.key === 'Escape')
		{
			event.preventDefault(); close(); button.focus();
		}
		if (event.key === 'ArrowDown' || event.key === 'ArrowUp')
		{
			event.preventDefault();
			const choices = [...menu.querySelectorAll('button:not(:disabled)')];
			const index = choices.indexOf(document.activeElement);
			choices[(index + (event.key === 'ArrowDown' ? 1 : choices.length - 1)) % choices.length]?.focus();
		}
	});
	renderSoundboardChoices(menu, clip, capacity, () =>
	{
		close(); button.focus();
	});
	document.body.append(menu);
	const rect = button.getBoundingClientRect();
	menu.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8)) + 'px';
	menu.style.top = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menu.offsetHeight - 8)) + 'px';
	(menu.querySelector('button:not(:disabled)') || menu).focus();
	// Capture has already passed for the opening click; only subsequent clicks dismiss.
	document.addEventListener('click', outside, true);
	window.addEventListener('resize', close);
}

/**
 * @brief Render account board destinations for standalone and nested menus.
 * @param {HTMLElement} menu Destination container.
 * @param {Object} clip Clip identity.
 * @param {number} capacity Pad capacity.
 * @param {Function} onComplete Called after a successful addition.
 * @param {string} className Shared menu option styling.
 */
export function renderSoundboardChoices(menu, clip, capacity, onComplete, className = 'dropdown-item')
{
	menu.replaceChildren();
	const labels = Collections.boardChoiceLabels;
	const boards = Collections.boardChoices;
	if (!boards.length) boards.push({id:'', name:labels.empty, items:[], isDefault:true});
	boards.forEach((board) =>
	{
		const added = board.items.some((item) => item && (clip.uuid ? item.uuid === clip.uuid : item.id === clip.id));
		const full = board.items.length >= capacity && board.items.every(Boolean);
		const choice = document.createElement('button');
		choice.type = 'button';
		choice.className = className;
		choice.setAttribute('role', 'menuitem');
		choice.textContent = board.name + (board.isDefault ? ' ★' : '') + (added ? ' — ' + labels.added : full ? ' — ' + labels.full : '');
		choice.disabled = added || full;
		choice.addEventListener('click', async () =>
		{
			menu.querySelectorAll('button').forEach((item) => { item.disabled = true; });
			try
			{
				await Collections.addToBoard(board.id, clip, capacity);
				onComplete();
			}
			catch (error)
			{
				window.alert(error.message);
				renderSoundboardChoices(menu, clip, capacity, onComplete, className);
			}
		});
		menu.append(choice);
	});
}
