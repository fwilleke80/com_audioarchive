const BOARD_STORAGE_KEY = 'com_audioarchive.soundboard.v1';
const RATING_CLIENT_KEY = 'com_audioarchive.rating.client.v1';
const RATING_VOTES_KEY = 'com_audioarchive.rating.votes.v1';

/**
 * Read a JSON value from local storage.
 *
 * @param {string} key Storage key.
 * @param {*} fallback Fallback value.
 * @returns {*} Parsed value or fallback.
 */
function readStorage(key, fallback)
{
	try
	{
		const value = window.localStorage.getItem(key);
		return value === null ? fallback : JSON.parse(value);
	}
	catch (error)
	{
		return fallback;
	}
}

/**
 * Write a JSON value to local storage.
 *
 * @param {string} key Storage key.
 * @param {*} value Value to store.
 * @returns {boolean} True on success.
 */
function writeStorage(key, value)
{
	try
	{
		window.localStorage.setItem(key, JSON.stringify(value));
		return true;
	}
	catch (error)
	{
		return false;
	}
}

/**
 * Copy text to the clipboard with a legacy fallback.
 *
 * @param {string} text Text to copy.
 * @returns {Promise<boolean>} Copy result.
 */
async function copyText(text)
{
	if (navigator.clipboard && window.isSecureContext)
	{
		try
		{
			await navigator.clipboard.writeText(text);
			return true;
		}
		catch (error)
		{
			// Continue with the selection-based fallback.
		}
	}

	const textarea = document.createElement('textarea');
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild(textarea);
	textarea.select();
	const result = document.execCommand('copy');
	textarea.remove();
	return result;
}

/**
 * Open the native browser share sheet.
 *
 * @param {string} title Share title.
 * @param {string} url URL to share.
 * @returns {Promise<boolean>} True when the native share completed.
 */
async function openNativeShare(title, url)
{
	if (typeof navigator.share !== 'function')
	{
		return false;
	}

	try
	{
		await navigator.share({title, url});
		return true;
	}
	catch (error)
	{
		return false;
	}
}

/**
 * Position a share popover inside the current viewport.
 *
 * @param {HTMLElement} menu Share menu root.
 * @returns {void}
 */
function positionSharePopover(menu)
{
	const toggle = menu.querySelector('[data-audioarchive-share-toggle], [data-audioarchive-soundboard-share-toggle]');
	const popover = menu.querySelector('[data-audioarchive-share-popover], [data-audioarchive-soundboard-share-popover]');

	if (!toggle || !popover || popover.hidden)
	{
		return;
	}

	const gap = 6;
	const viewportPadding = 8;
	const toggleRect = toggle.getBoundingClientRect();
	const popoverRect = popover.getBoundingClientRect();
	const spaceRight = window.innerWidth - toggleRect.right - viewportPadding;
	const spaceLeft = toggleRect.left - viewportPadding;
	let left;
	let top;

	if (spaceRight >= popoverRect.width + gap)
	{
		left = toggleRect.right + gap;
		top = toggleRect.top;
	}
	else if (spaceLeft >= popoverRect.width + gap)
	{
		left = toggleRect.left - popoverRect.width - gap;
		top = toggleRect.top;
	}
	else
	{
		const maxLeft = Math.max(viewportPadding, window.innerWidth - popoverRect.width - viewportPadding);
		left = Math.min(Math.max(viewportPadding, toggleRect.right - popoverRect.width), maxLeft);
		top = toggleRect.bottom + gap;

		if (top + popoverRect.height > window.innerHeight - viewportPadding && toggleRect.top >= popoverRect.height + gap + viewportPadding)
		{
			top = toggleRect.top - popoverRect.height - gap;
		}
	}

	const maxTop = Math.max(viewportPadding, window.innerHeight - popoverRect.height - viewportPadding);
	popover.style.left = `${Math.round(left)}px`;
	popover.style.top = `${Math.round(Math.min(Math.max(viewportPadding, top), maxTop))}px`;
}

/**
 * Set the open state of a share menu.
 *
 * @param {HTMLElement} menu Share menu root.
 * @param {boolean} open Requested state.
 * @param {boolean} focusFirst Whether to focus the first item.
 * @returns {void}
 */
function setShareMenuOpen(menu, open, focusFirst = false)
{
	const toggle = menu.querySelector('[data-audioarchive-share-toggle], [data-audioarchive-soundboard-share-toggle]');
	const popover = menu.querySelector('[data-audioarchive-share-popover], [data-audioarchive-soundboard-share-popover]');

	if (!toggle || !popover)
	{
		return;
	}

	toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	popover.hidden = !open;

	if (open)
	{
		positionSharePopover(menu);
	}

	if (open && focusFirst)
	{
		popover.querySelector('[role="menuitem"]:not(:disabled)')?.focus();
	}
}

/**
 * Close every share menu except an optional retained menu.
 *
 * @param {HTMLElement|null} retainedMenu Menu that should remain open.
 * @returns {void}
 */
function closeShareMenus(retainedMenu = null)
{
	document.querySelectorAll('.com-audioarchive-share-menu').forEach((menu) =>
	{
		if (menu !== retainedMenu)
		{
			setShareMenuOpen(menu, false);
		}
	});
}

/**
 * Enable keyboard navigation inside a share menu.
 *
 * @param {HTMLElement} menu Share menu root.
 * @returns {void}
 */
function initialiseShareMenuKeyboard(menu)
{
	menu.addEventListener('keydown', (event) =>
	{
		const items = Array.from(menu.querySelectorAll('[role="menuitem"]:not(:disabled)'));

		if (event.key === 'Escape')
		{
			event.preventDefault();
			setShareMenuOpen(menu, false);
			menu.querySelector('[data-audioarchive-share-toggle], [data-audioarchive-soundboard-share-toggle]')?.focus();
			return;
		}

		if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) || items.length === 0)
		{
			return;
		}

		event.preventDefault();
		const currentIndex = items.indexOf(document.activeElement);
		let nextIndex = 0;

		if (event.key === 'End')
		{
			nextIndex = items.length - 1;
		}
		else if (event.key === 'ArrowUp')
		{
			nextIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
		}
		else if (event.key === 'ArrowDown')
		{
			nextIndex = currentIndex < 0 || currentIndex >= items.length - 1 ? 0 : currentIndex + 1;
		}

		items[nextIndex]?.focus();
	});
}

/**
 * Initialise ordinary clip share menus.
 *
 * @returns {void}
 */
function initialiseShareButtons()
{
	document.querySelectorAll('[data-audioarchive-share-menu]').forEach((menu) =>
	{
		const toggle = menu.querySelector('[data-audioarchive-share-toggle]');
		const copyButton = menu.querySelector('[data-audioarchive-share-copy]');
		const nativeButton = menu.querySelector('[data-audioarchive-share-native]');
		const copyTextElement = menu.querySelector('[data-audioarchive-share-copy-text]');
		const status = menu.querySelector('[data-audioarchive-share-status]');
		const url = menu.dataset.shareUrl || window.location.href;
		const title = menu.dataset.shareTitle || document.title;
		const copiedLabel = menu.dataset.shareCopiedLabel || 'Link copied.';
		const originalCopyLabel = copyTextElement?.textContent || '';

		if (nativeButton)
		{
			nativeButton.disabled = typeof navigator.share !== 'function';

			if (nativeButton.disabled)
			{
				nativeButton.title = nativeButton.dataset.shareUnavailableLabel || '';
			}
		}

		toggle?.addEventListener('click', () =>
		{
			const open = toggle.getAttribute('aria-expanded') !== 'true';
			closeShareMenus(menu);
			setShareMenuOpen(menu, open, open);
		});

		copyButton?.addEventListener('click', async () =>
		{
			const copied = await copyText(url);

			if (copied)
			{
				if (status)
				{
					status.textContent = copiedLabel;
				}

				if (copyTextElement)
				{
					copyTextElement.textContent = copiedLabel;
					window.setTimeout(() =>
					{
						copyTextElement.textContent = originalCopyLabel;
					}, 1600);
				}
			}

			setShareMenuOpen(menu, false);
		});

		nativeButton?.addEventListener('click', async () =>
		{
			await openNativeShare(title, url);
			setShareMenuOpen(menu, false);
		});

		initialiseShareMenuKeyboard(menu);
	});

	document.addEventListener('click', (event) =>
	{
		if (!event.target.closest('.com-audioarchive-share-menu'))
		{
			closeShareMenus();
		}
	});

	window.addEventListener('resize', () => closeShareMenus());
	window.addEventListener('scroll', () => closeShareMenus(), true);
}

/**
 * Validate and limit a soundboard configuration.
 *
 * @param {*} board Candidate board.
 * @param {number} padCount Maximum pad count.
 * @returns {Array<{id:number,title:string}|null>} Normalised board entries.
 */
function normaliseBoard(board, padCount = 36)
{
	if (!Array.isArray(board))
	{
		return [];
	}

	return board.slice(0, padCount).map((entry) =>
	{
		if (!entry || typeof entry !== 'object')
		{
			return null;
		}

		const id = Number.parseInt(entry.id, 10);
		const title = String(entry.title || '').trim().slice(0, 255);
		return Number.isInteger(id) && id > 0 && title !== '' ? {id, title} : null;
	});
}

/**
 * Return the soundboard configuration.
 *
 * @returns {Array<{id:number,title:string}|null>} Stored board entries.
 */
function readBoard()
{
	return normaliseBoard(readStorage(BOARD_STORAGE_KEY, []));
}

/**
 * Initialise add-to-soundboard buttons on archive and detail pages.
 *
 * @returns {void}
 */
function initialiseSoundboardAddButtons()
{
	const buttons = Array.from(document.querySelectorAll('[data-audioarchive-soundboard-add]'));
	const updateButtons = (clipId, added) =>
	{
		buttons.filter((button) => Number.parseInt(button.dataset.clipId || '0', 10) === clipId).forEach((button) =>
		{
			button.classList.toggle('is-added', added);
			button.setAttribute('aria-pressed', added ? 'true' : 'false');
		});
	};
	const storedIds = new Set(readBoard().filter(Boolean).map((entry) => entry.id));
	buttons.forEach((button) =>
	{
		const id = Number.parseInt(button.dataset.clipId || '0', 10);
		updateButtons(id, storedIds.has(id));
		button.addEventListener('click', () =>
		{
			const title = String(button.dataset.clipTitle || '').trim();
			const root = button.closest('[data-audioarchive-soundboard-pad-count]') || document.querySelector('[data-audioarchive-soundboard-pad-count]');
			const padCount = Math.max(4, Number.parseInt(root?.dataset.audioarchiveSoundboardPadCount || '12', 10));
			const board = readBoard().slice(0, padCount);
			const existing = board.findIndex((entry) => entry && entry.id === id);

			if (existing >= 0)
			{
				updateButtons(id, true);
				return;
			}

			let slot = board.findIndex((entry) => entry === null);

			if (slot < 0 && board.length < padCount)
			{
				slot = board.length;
			}

			if (slot < 0)
			{
				const fullLabel = button.closest('[data-audioarchive-soundboard-full-label]')?.dataset.audioarchiveSoundboardFullLabel || 'The sound board is full.';
				window.alert(fullLabel);
				return;
			}

			board[slot] = {id, title};

			if (writeStorage(BOARD_STORAGE_KEY, board))
			{
				updateButtons(id, true);
			}
		});
	});
}

/**
 * Encode a soundboard for a URL fragment.
 *
 * @param {Array<{id:number,title:string}|null>} board Board entries.
 * @returns {string} Base64url payload.
 */
function encodeBoard(board)
{
	const bytes = new TextEncoder().encode(JSON.stringify(board));
	let binary = '';
	bytes.forEach((byte) =>
	{
		binary += String.fromCharCode(byte);
	});
	return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

/**
 * Decode a URL-fragment soundboard.
 *
 * @param {string} encoded Base64url payload.
 * @returns {Array<{id:number,title:string}|null>|null} Decoded board or null.
 */
function decodeBoard(encoded)
{
	try
	{
		const padded = encoded.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - encoded.length % 4) % 4);
		const binary = window.atob(padded);
		const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));
		const value = JSON.parse(new TextDecoder().decode(bytes));
		return Array.isArray(value) ? value : null;
	}
	catch (error)
	{
		return null;
	}
}

/**
 * Merge shared soundboard entries into a personal board without duplicates.
 *
 * @param {Array<{id:number,title:string}|null>} personalBoard Personal board.
 * @param {Array<{id:number,title:string}|null>} sharedBoard Shared board.
 * @param {number} padCount Maximum pad count.
 * @returns {{board:Array<{id:number,title:string}|null>,added:number,full:boolean}} Merge result.
 */
function mergeBoards(personalBoard, sharedBoard, padCount)
{
	const merged = normaliseBoard(personalBoard, padCount);
	const existingIds = new Set(merged.filter(Boolean).map((entry) => entry.id));
	let added = 0;
	let full = false;

	for (const entry of normaliseBoard(sharedBoard, padCount))
	{
		if (!entry || existingIds.has(entry.id))
		{
			continue;
		}

		let slot = merged.findIndex((candidate) => candidate === null);

		if (slot < 0 && merged.length < padCount)
		{
			slot = merged.length;
		}

		if (slot < 0)
		{
			full = true;
			break;
		}

		merged[slot] = entry;
		existingIds.add(entry.id);
		added++;
	}

	return {board: merged, added, full};
}

/**
 * Initialise the soundboard page.
 *
 * @returns {void}
 */
function initialiseSoundboard()
{
	const root = document.querySelector('[data-audioarchive-soundboard]');

	if (!root)
	{
		return;
	}

	const padCount = Math.max(4, Number.parseInt(root.dataset.audioarchivePadCount || '12', 10));
	const streamTemplate = root.dataset.audioarchiveStreamTemplate || '';
	const clipTemplate = root.dataset.audioarchiveClipTemplate || '';
	const canonicalUrl = root.dataset.audioarchiveCanonicalUrl || window.location.href.split('#')[0];
	const status = root.querySelector('[data-audioarchive-soundboard-status]');
	const audio = root.querySelector('[data-audioarchive-soundboard-audio]');
	const pads = Array.from(root.querySelectorAll('[data-audioarchive-soundboard-pad]'));
	const sharedPanel = root.querySelector('[data-audioarchive-soundboard-shared]');
	let board = readBoard().slice(0, padCount);
	let temporarySharedBoard = false;

	const fragment = new URLSearchParams(window.location.hash.replace(/^#/, '')).get('board');

	if (fragment)
	{
		const imported = decodeBoard(fragment);

		if (imported)
		{
			board = normaliseBoard(imported, padCount);
			temporarySharedBoard = true;
		}
		else if (status)
		{
			status.textContent = root.dataset.audioarchiveLabelInvalid;
		}
	}

	const setTemporarySharedBoard = (enabled) =>
	{
		temporarySharedBoard = enabled;
		root.classList.toggle('is-shared-board', enabled);

		if (sharedPanel)
		{
			sharedPanel.hidden = !enabled;
		}
	};

	const leaveSharedUrl = () =>
	{
		const url = new URL(window.location.href);
		url.hash = '';
		window.history.replaceState(window.history.state, '', url.toString());
	};

	const saveCurrentBoard = () =>
	{
		return temporarySharedBoard || writeStorage(BOARD_STORAGE_KEY, board);
	};

	const render = () =>
	{
		pads.forEach((pad, index) =>
		{
			const entry = board[index] || null;
			const title = pad.querySelector('[data-audioarchive-soundboard-title]');
			const detail = pad.querySelector('[data-audioarchive-soundboard-detail]');
			pad.classList.toggle('is-empty', !entry);
			pad.classList.remove('is-playing');
			title.textContent = entry ? entry.title : root.dataset.audioarchiveLabelEmpty;

			if (detail)
			{
				detail.hidden = !entry || clipTemplate === '';
				detail.href = entry && clipTemplate !== ''
					? clipTemplate.replace('987654321', String(entry.id))
					: '#';
			}
		});
	};

	const play = (index) =>
	{
		const entry = board[index] || null;

		if (!entry || !audio)
		{
			return;
		}

		pads.forEach((pad) => pad.classList.remove('is-playing'));
		const source = streamTemplate.replace('987654321', String(entry.id));

		if (audio.src !== source)
		{
			audio.src = source;
		}

		audio.currentTime = 0;
		audio.play().then(() =>
		{
			pads[index]?.classList.add('is-playing');
			status.textContent = `${root.dataset.audioarchiveLabelPlaying}: ${entry.title}`;
		}).catch(() =>
		{
			pads[index]?.classList.remove('is-playing');
		});
	};

	pads.forEach((pad, index) =>
	{
		pad.querySelector('[data-audioarchive-soundboard-trigger]')?.addEventListener('click', () => play(index));
		pad.querySelector('[data-audioarchive-soundboard-remove]')?.addEventListener('click', () =>
		{
			board[index] = null;
			saveCurrentBoard();
			render();
		});
	});

	root.querySelector('[data-audioarchive-soundboard-clear]')?.addEventListener('click', () =>
	{
		board = [];
		saveCurrentBoard();
		if (audio)
		{
			audio.pause();
		}
		render();
	});

	root.querySelector('[data-audioarchive-soundboard-shared-add]')?.addEventListener('click', () =>
	{
		const result = mergeBoards(readBoard(), board, padCount);

		if (!writeStorage(BOARD_STORAGE_KEY, result.board))
		{
			return;
		}

		board = result.board;
		setTemporarySharedBoard(false);
		leaveSharedUrl();
		render();

		if (status)
		{
			status.textContent = String(root.dataset.audioarchiveLabelSharedAdded || '').replace('%d', String(result.added));

			if (result.full)
			{
				status.textContent += ` ${root.dataset.audioarchiveLabelSharedFull || ''}`;
			}
		}
	});

	root.querySelector('[data-audioarchive-soundboard-shared-replace]')?.addEventListener('click', () =>
	{
		if (!window.confirm(root.dataset.audioarchiveLabelReplaceConfirm || ''))
		{
			return;
		}

		board = normaliseBoard(board, padCount);

		if (!writeStorage(BOARD_STORAGE_KEY, board))
		{
			return;
		}

		setTemporarySharedBoard(false);
		leaveSharedUrl();
		render();

		if (status)
		{
			status.textContent = root.dataset.audioarchiveLabelSharedReplaced || '';
		}
	});

	root.querySelector('[data-audioarchive-soundboard-export]')?.addEventListener('click', () =>
	{
		const blob = new Blob([JSON.stringify({version: 1, pads: board}, null, 2)], {type: 'application/json'});
		const link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'audioarchive-soundboard.json';
		link.click();
		window.setTimeout(() => URL.revokeObjectURL(link.href), 0);
	});

	const fileInput = root.querySelector('[data-audioarchive-soundboard-file]');
	root.querySelector('[data-audioarchive-soundboard-import]')?.addEventListener('click', () => fileInput?.click());
	fileInput?.addEventListener('change', async () =>
	{
		const file = fileInput.files?.[0];

		if (!file)
		{
			return;
		}

		try
		{
			const data = JSON.parse(await file.text());
			const imported = Array.isArray(data) ? data : data.pads;

			if (!Array.isArray(imported))
			{
				throw new Error('Invalid soundboard');
			}

			const importedBoard = normaliseBoard(imported, padCount);

			if (!writeStorage(BOARD_STORAGE_KEY, importedBoard))
			{
				throw new Error('Unable to store soundboard');
			}

			board = importedBoard;
			setTemporarySharedBoard(false);
			leaveSharedUrl();
			status.textContent = root.dataset.audioarchiveLabelImported;
			render();
		}
		catch (error)
		{
			status.textContent = root.dataset.audioarchiveLabelInvalid;
		}
		finally
		{
			fileInput.value = '';
		}
	});

	const shareMenu = root.querySelector('[data-audioarchive-soundboard-share-menu]');
	const shareToggle = shareMenu?.querySelector('[data-audioarchive-soundboard-share-toggle]');
	const shareCopy = shareMenu?.querySelector('[data-audioarchive-soundboard-share-copy]');
	const shareNative = shareMenu?.querySelector('[data-audioarchive-soundboard-share-native]');

	if (shareNative)
	{
		shareNative.disabled = typeof navigator.share !== 'function';

		if (shareNative.disabled)
		{
			shareNative.title = shareNative.dataset.shareUnavailableLabel || '';
		}
	}

	shareToggle?.addEventListener('click', () =>
	{
		const open = shareToggle.getAttribute('aria-expanded') !== 'true';
		closeShareMenus(shareMenu);
		setShareMenuOpen(shareMenu, open, open);
	});

	shareCopy?.addEventListener('click', async () =>
	{
		const url = `${canonicalUrl}#board=${encodeBoard(board)}`;

		if (await copyText(url))
		{
			status.textContent = root.dataset.audioarchiveLabelCopied;
		}

		setShareMenuOpen(shareMenu, false);
	});

	shareNative?.addEventListener('click', async () =>
	{
		const url = `${canonicalUrl}#board=${encodeBoard(board)}`;
		await openNativeShare(document.title, url);
		setShareMenuOpen(shareMenu, false);
	});

	if (shareMenu)
	{
		initialiseShareMenuKeyboard(shareMenu);
	}

	const keyboardKeys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'];
	window.addEventListener('keydown', (event) =>
	{
		if (event.repeat || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey)
		{
			return;
		}

		if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement)
		{
			return;
		}

		const index = keyboardKeys.indexOf(event.key.toUpperCase());

		if (index >= 0 && index < padCount)
		{
			event.preventDefault();
			play(index);
		}
	});

	audio?.addEventListener('ended', () => pads.forEach((pad) => pad.classList.remove('is-playing')));
	setTemporarySharedBoard(temporarySharedBoard);
	render();
}

/**
 * Return or create a browser-local anonymous rating identifier.
 *
 * @returns {string} 64-character hexadecimal identifier.
 */
function getRatingClientId()
{
	let clientId = String(readStorage(RATING_CLIENT_KEY, '') || '');

	if (/^[a-f0-9]{64}$/.test(clientId))
	{
		return clientId;
	}

	const bytes = new Uint8Array(32);
	window.crypto.getRandomValues(bytes);
	clientId = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
	writeStorage(RATING_CLIENT_KEY, clientId);
	return clientId;
}

/**
 * Initialise rating controls.
 *
 * @returns {void}
 */
function initialiseRatings()
{
	const container = document.querySelector('[data-audioarchive-rating-endpoint]');

	if (!container)
	{
		return;
	}

	const endpoint = container.dataset.audioarchiveRatingEndpoint || '';
	const tokenName = container.dataset.audioarchiveRatingToken || '';
	const successLabel = container.dataset.audioarchiveRatingSuccess || 'Rating saved.';
	const errorLabel = container.dataset.audioarchiveRatingError || 'The rating could not be saved.';
	const votes = readStorage(RATING_VOTES_KEY, {});

	document.querySelectorAll('[data-audioarchive-rating]').forEach((rating) =>
	{
		const clipId = Number.parseInt(rating.dataset.clipId || '0', 10);
		const currentVote = Number.parseInt(votes[String(clipId)] || '0', 10);
		rating.querySelectorAll('[data-audioarchive-rating-vote]').forEach((button) =>
		{
			const value = Number.parseInt(button.dataset.audioarchiveRatingVote || '0', 10);
			button.setAttribute('aria-pressed', value === currentVote ? 'true' : 'false');
			button.classList.toggle('is-selected', value === currentVote);
		});
	});

	document.addEventListener('click', async (event) =>
	{
		const button = event.target.closest('[data-audioarchive-rating-vote]');

		if (!button || button.disabled)
		{
			return;
		}

		const rating = button.closest('[data-audioarchive-rating]');
		const clipId = Number.parseInt(rating.dataset.clipId || '0', 10);
		const ratingWidgets = Array.from(document.querySelectorAll('[data-audioarchive-rating]')).filter(
			(widget) => Number.parseInt(widget.dataset.clipId || '0', 10) === clipId
		);
		const requestedVote = Number.parseInt(button.dataset.audioarchiveRatingVote || '0', 10);
		const existingVote = Number.parseInt(votes[String(clipId)] || '0', 10);
		const vote = requestedVote === existingVote ? 0 : requestedVote;
		const form = new FormData();
		form.set('id', String(clipId));
		form.set('vote', String(vote));
		form.set('client_id', getRatingClientId());
		form.set(tokenName, '1');
		ratingWidgets.forEach((widget) => widget.querySelectorAll('[data-audioarchive-rating-vote]').forEach((choice) => { choice.disabled = true; }));

		try
		{
			const response = await fetch(endpoint, {
				method: 'POST',
				body: form,
				credentials: 'same-origin',
				headers: {'X-Requested-With': 'XMLHttpRequest'},
			});
			const data = await response.json();

			if (!response.ok || !data.success)
			{
				throw new Error('Rating failed');
			}

			votes[String(clipId)] = vote;
			writeStorage(RATING_VOTES_KEY, votes);
			ratingWidgets.forEach((widget) =>
			{
				widget.querySelector('[data-audioarchive-rating-up]').textContent = String(data.up);
				widget.querySelector('[data-audioarchive-rating-down]').textContent = String(data.down);
				widget.querySelectorAll('[data-audioarchive-rating-vote]').forEach((choice) =>
				{
					const value = Number.parseInt(choice.dataset.audioarchiveRatingVote || '0', 10);
					choice.setAttribute('aria-pressed', value === vote ? 'true' : 'false');
					choice.classList.toggle('is-selected', value === vote);
				});
				widget.querySelector('[data-audioarchive-rating-status]').textContent = successLabel;
			});
		}
		catch (error)
		{
			ratingWidgets.forEach((widget) => { widget.querySelector('[data-audioarchive-rating-status]').textContent = errorLabel; });
		}
		finally
		{
			ratingWidgets.forEach((widget) => widget.querySelectorAll('[data-audioarchive-rating-vote]').forEach((choice) => { choice.disabled = false; }));
		}
	});
}

initialiseShareButtons();
initialiseSoundboardAddButtons();
initialiseSoundboard();
initialiseRatings();
