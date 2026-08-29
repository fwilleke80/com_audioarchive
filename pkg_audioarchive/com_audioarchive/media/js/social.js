const BOARD_STORAGE_KEY = 'com_audioarchive.soundboard.v1';
const SAMPLER_POLYPHONY_STORAGE_KEY = 'com_audioarchive.soundboard.sampler_polyphony.v1';
const RATING_CLIENT_KEY = 'com_audioarchive.rating.client.v1';
const RATING_VOTES_KEY = 'com_audioarchive.rating.votes.v1';
const RETURN_STORAGE_KEY = 'com_audioarchive.return.v1';
const RETURN_MAX_AGE_MS = 24 * 60 * 60 * 1000;
const SAMPLER_ROOT_MIDI_NOTE = 60;
const SAMPLER_MIN_BASE_NOTE = 24;
const SAMPLER_MAX_BASE_NOTE = 96;
const SAMPLER_NOTE_NAMES = ['C', 'C♯', 'D', 'D♯', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'A♯', 'B'];

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
 * Read a JSON value from this tab's session storage.
 *
 * @param {string} key Storage key.
 * @param {*} fallback Fallback value.
 * @returns {*} Parsed value or fallback.
 */
function readSessionStorage(key, fallback)
{
	try
	{
		const value = window.sessionStorage.getItem(key);
		return value === null ? fallback : JSON.parse(value);
	}
	catch (error)
	{
		return fallback;
	}
}

/**
 * Write a JSON value to this tab's session storage.
 *
 * @param {string} key Storage key.
 * @param {*} value Value to store.
 * @returns {boolean} True on success.
 */
function writeSessionStorage(key, value)
{
	try
	{
		window.sessionStorage.setItem(key, JSON.stringify(value));
		return true;
	}
	catch (error)
	{
		return false;
	}
}

/**
 * Remove a value from this tab's session storage.
 *
 * @param {string} key Storage key.
 * @returns {void}
 */
function removeSessionStorage(key)
{
	try
	{
		window.sessionStorage.removeItem(key);
	}
	catch (error)
	{
		// Storage can be unavailable in restricted browser contexts.
	}
}

/**
 * Convert a same-origin URL into a compact relative return target.
 *
 * @param {string} value Candidate URL.
 * @returns {string} Relative same-origin URL or an empty string.
 */
function normaliseReturnUrl(value)
{
	try
	{
		const url = new URL(value, window.location.href);

		if (url.origin !== window.location.origin)
		{
			return '';
		}

		return `${url.pathname}${url.search}${url.hash}`;
	}
	catch (error)
	{
		return '';
	}
}

/**
 * Resolve a human-readable title for the current return page.
 *
 * Archive and Sound Board views provide the active menu-item title explicitly.
 * Other pages fall back to their main heading and then the document title.
 *
 * @param {HTMLElement|null} root Nearest Audio Archive origin root.
 * @returns {string} Return-page title or an empty string.
 */
function resolveReturnTitle(root)
{
	const explicitTitle = String(root?.dataset.audioarchiveReturnTitle || '').trim();

	if (explicitTitle !== '')
	{
		return explicitTitle;
	}

	const clipRoot = document.querySelector('[data-audioarchive-clip-return]');
	const clipTitle = String(clipRoot?.querySelector('h1')?.textContent || '').trim();

	if (clipTitle !== '')
	{
		return clipTitle;
	}

	const mainTitle = String(
		document.querySelector('main h1, [role="main"] h1, h1')?.textContent || ''
	).trim();

	if (mainTitle !== '')
	{
		return mainTitle;
	}

	return String(document.title || '').trim();
}

/**
 * Store the current page as the detail-page return origin.
 *
 * @param {HTMLElement|null} root Nearest Audio Archive origin root.
 * @returns {void}
 */
function storeReturnOrigin(root)
{
	const title = resolveReturnTitle(root);
	const url = normaliseReturnUrl(window.location.href);

	if (title === '' || url === '')
	{
		return;
	}

	writeSessionStorage(
		RETURN_STORAGE_KEY,
		{
			title,
			url,
			timestamp: Date.now(),
		}
	);
}

/**
 * Return a valid, recent detail-page origin from sessionStorage.
 *
 * @returns {{title: string, url: string, timestamp: number}|null} Valid origin.
 */
function readReturnOrigin()
{
	const origin = readSessionStorage(RETURN_STORAGE_KEY, null);

	if (!origin || typeof origin !== 'object')
	{
		return null;
	}

	const title = String(origin.title || '').trim();
	const url = normaliseReturnUrl(String(origin.url || ''));
	const timestamp = Number(origin.timestamp || 0);
	const age = Date.now() - timestamp;

	if (title === ''
		|| url === ''
		|| !Number.isFinite(timestamp)
		|| age < 0
		|| age > RETURN_MAX_AGE_MS)
	{
		removeSessionStorage(RETURN_STORAGE_KEY);
		return null;
	}

	return {title, url, timestamp};
}

/**
 * Preserve clean clip URLs while restoring the exact same-tab origin link.
 *
 * Every built-in clip-detail link uses data-audioarchive-detail-link. This
 * allows Archive views, Sound Boards, modules, content-plugin embeds, and
 * future layouts to use the same navigation mechanism.
 *
 * @returns {void}
 */
function initialiseReturnNavigation()
{
	const captureOrigin = (event) =>
	{
		if (!(event.target instanceof Element))
		{
			return;
		}

		const link = event.target.closest('[data-audioarchive-detail-link]');

		if (!link || link.hidden)
		{
			return;
		}

		const target = normaliseReturnUrl(link.href);

		if (target === '')
		{
			return;
		}

		const clipRoot = document.querySelector('[data-audioarchive-clip-return]');

		// Clip-to-clip navigation retains an existing external origin. When a
		// directly opened clip has no origin, the current clip becomes one.
		if (clipRoot && readReturnOrigin())
		{
			return;
		}

		storeReturnOrigin(link.closest('[data-audioarchive-return-origin]') || clipRoot);
	};

	document.addEventListener('click', captureOrigin, true);
	document.addEventListener('auxclick', captureOrigin, true);

	const clipRoot = document.querySelector('[data-audioarchive-clip-return]');
	const backLink = clipRoot?.querySelector('[data-audioarchive-return-link]');
	const backLabel = backLink?.querySelector('[data-audioarchive-return-label]');
	const origin = readReturnOrigin();

	if (!clipRoot || !backLink || !backLabel || !origin)
	{
		return;
	}

	const labelTemplate = String(clipRoot.dataset.audioarchiveReturnLabelTemplate || '').trim();
	backLink.href = origin.url;
	backLabel.textContent = labelTemplate.includes('%s')
		? labelTemplate.replace('%s', origin.title)
		: origin.title;
	backLink.addEventListener('click', (event) =>
	{
		if (event.button === 0
			&& !event.metaKey
			&& !event.ctrlKey
			&& !event.shiftKey
			&& !event.altKey
			&& backLink.target !== '_blank')
		{
			removeSessionStorage(RETURN_STORAGE_KEY);
		}
	});
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
		const uuid = String(entry.uuid || '').trim().toLowerCase();
		const title = String(entry.title || '').trim().slice(0, 255);
		return Number.isInteger(id) && id > 0 && title !== '' ? {id, uuid, title} : null;
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
 * Show a visible Sound Board warning using Joomla's message area when available.
 *
 * @param {string} message Warning message.
 * @returns {void}
 */
function showSoundboardWarning(message)
{
	const text = String(message || '').trim();

	if (text === '')
	{
		return;
	}

	window.alert(text);
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
			const uuid = String(button.dataset.clipUuid || '').trim().toLowerCase();
			const title = String(button.dataset.clipTitle || '').trim();
			const root = button.closest('[data-audioarchive-soundboard-pad-count]') || document.querySelector('[data-audioarchive-soundboard-pad-count]');
			const padCount = Math.max(4, Number.parseInt(root?.dataset.audioarchiveSoundboardPadCount || '12', 10));
			const board = readBoard().slice(0, padCount);
			const existing = board.findIndex((entry) =>
			{
				if (!entry || entry.id !== id)
				{
					return false;
				}

				if (uuid !== '' && entry.uuid !== '')
				{
					return entry.uuid === uuid;
				}

				return entry.title === title;
			});

			if (existing >= 0)
			{
				if (uuid !== '' && board[existing] && board[existing].uuid === '')
				{
					board[existing].uuid = uuid;
					writeStorage(BOARD_STORAGE_KEY, board);
				}

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
				showSoundboardWarning(fullLabel);
				return;
			}

			board[slot] = {id, uuid, title};

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
 * Insert a value into a translated single-placeholder label.
 *
 * @param {string} template Label containing %s or %d.
 * @param {string|number} value Replacement value.
 * @returns {string} Formatted label.
 */
function formatSoundboardLabel(template, value)
{
	return String(template || '').replace(/%[sd]/, String(value));
}

/**
 * Return a display name for one MIDI note number.
 *
 * @param {number} midiNote MIDI note number.
 * @returns {string} Note name such as C4.
 */
function getMidiNoteName(midiNote)
{
	const note = Math.max(0, Math.min(127, Math.round(midiNote)));
	const octave = Math.floor(note / 12) - 1;
	return `${SAMPLER_NOTE_NAMES[note % 12]}${octave}`;
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
	const polyphonic = root.dataset.audioarchivePolyphonic !== '0';
	const samplerEnabled = root.dataset.audioarchiveSamplerEnabled !== '0';
	const recordSoundboardPlays = root.dataset.audioarchiveRecordSoundboardPlays !== '0';
	const streamTemplate = root.dataset.audioarchiveStreamTemplate || '';
	const routesUrl = root.dataset.audioarchiveRoutesUrl || '';
	const canonicalUrl = root.dataset.audioarchiveCanonicalUrl || window.location.href.split('#')[0];
	const status = root.querySelector('[data-audioarchive-soundboard-status]');
	const playCountUrl = root.dataset.audioarchivePlayCountUrl || '';
	const tokenName = root.dataset.audioarchiveTokenName || '';
	const interactionUrl = root.dataset.audioarchiveInteractionUrl || '';
	const interactionToken = root.dataset.audioarchiveInteractionToken || '';
	const pads = Array.from(root.querySelectorAll('[data-audioarchive-soundboard-pad]'));
	const sharedPanel = root.querySelector('[data-audioarchive-soundboard-shared]');
	const padModeButton = root.querySelector('[data-audioarchive-soundboard-mode-pad]');
	const samplerModeButton = root.querySelector('[data-audioarchive-soundboard-mode-sampler]');
	const samplerPanel = root.querySelector('[data-audioarchive-soundboard-sampler]');
	const samplerDescription = root.querySelector('[data-audioarchive-soundboard-sampler-description]');
	const midiEnableButton = root.querySelector('[data-audioarchive-soundboard-midi-enable]');
	const midiEnableLabel = root.querySelector('[data-audioarchive-soundboard-midi-enable-label]');
	const midiStatus = root.querySelector('[data-audioarchive-soundboard-midi-status]');
	const keyboardToggle = root.querySelector('[data-audioarchive-soundboard-keyboard-toggle]');
	const keyboardToggleLabel = root.querySelector('[data-audioarchive-soundboard-keyboard-toggle-label]');
	const samplerPolyphonyToggle = root.querySelector('[data-audioarchive-soundboard-sampler-polyphony]');
	const keyboard = root.querySelector('[data-audioarchive-soundboard-keyboard]');
	const octaveLabel = root.querySelector('[data-audioarchive-soundboard-octave-label]');
	const pianoKeys = Array.from(root.querySelectorAll('[data-audioarchive-soundboard-piano-key]'));
	const storedBoard = readBoard();
	let board = storedBoard.slice(0, padCount);

	if (storedBoard.length > padCount)
	{
		writeStorage(BOARD_STORAGE_KEY, board);
	}
	let temporarySharedBoard = false;
	const detailRoutes = new Map();
	const unavailableDetailIds = new Set();
	const pendingDetailIds = new Set();
	const countedClipIds = new Set();
	const activeVoices = new Set();
	const voicesByPad = new Map();
	const activeSamplerVoices = new Set();
	const samplerVoicesByPad = new Map();
	const samplerBuffers = new Map();
	const decodedSamplerBuffers = new Map();
	let audioContext = null;
	let midiAccess = null;
	let selectedSamplerIndex = -1;
	let selectedSamplerClipId = 0;
	let samplerBaseNote = SAMPLER_ROOT_MIDI_NOTE;
	let keyboardVisible = false;
	let samplerPolyphonic = polyphonic && readStorage(SAMPLER_POLYPHONY_STORAGE_KEY, true) !== false;
	let samplerSelectionGeneration = 0;
	let samplerMode = false;
	let previousAudioSessionType = null;

	const fragmentParameters = new URLSearchParams(window.location.hash.replace(/^#/, ''));
	const fragment = fragmentParameters.get('board');

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

	const queryParameters = new URLSearchParams(window.location.search);
	const requestedMode = Number.parseInt(queryParameters.get('mode') || fragmentParameters.get('mode') || '0', 10);
	const requestedOctave = Number.parseInt(queryParameters.get('octave') || fragmentParameters.get('octave') || '', 10);
	const requestedPad = Number.parseInt(queryParameters.get('pad') || fragmentParameters.get('pad') || '', 10);

	if (Number.isInteger(requestedOctave))
	{
		const requestedBaseNote = (requestedOctave + 1) * 12;

		if (requestedBaseNote >= SAMPLER_MIN_BASE_NOTE && requestedBaseNote <= SAMPLER_MAX_BASE_NOTE)
		{
			samplerBaseNote = requestedBaseNote;
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

	const applyDetailRoutes = () =>
	{
		pads.forEach((pad, index) =>
		{
			const entry = board[index] || null;
			const detail = pad.querySelector('[data-audioarchive-soundboard-detail]');
			const route = entry ? detailRoutes.get(entry.id) || '' : '';

			if (detail)
			{
				detail.hidden = route === '';
				detail.href = route !== '' ? route : '#';
			}
		});
	};

	const resolveDetailRoutes = async () =>
	{
		if (routesUrl === '')
		{
			applyDetailRoutes();
			return;
		}

		const ids = Array.from(new Set(board
			.filter((entry) => entry && Number.isInteger(entry.id) && entry.id > 0)
			.map((entry) => entry.id)))
			.filter((id) => !detailRoutes.has(id) && !unavailableDetailIds.has(id) && !pendingDetailIds.has(id));

		if (ids.length === 0)
		{
			applyDetailRoutes();
			return;
		}

		ids.forEach((id) => pendingDetailIds.add(id));

		try
		{
			const requestUrl = new URL(routesUrl, window.location.href);
			const requestBody = new URLSearchParams();
			requestBody.set('ids', ids.join(','));
			const response = await fetch(
				requestUrl.toString(),
				{
					method: 'POST',
					credentials: 'same-origin',
					headers:
					{
						'Accept': 'application/json',
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					},
					body: requestBody.toString(),
				}
			);
			const payload = response.ok ? await response.json() : null;
			const routes = payload && payload.success === true && payload.routes && typeof payload.routes === 'object'
				? payload.routes
				: Object.create(null);

			ids.forEach((id) =>
			{
				const route = typeof routes[String(id)] === 'string' ? routes[String(id)].trim() : '';

				if (route !== '')
				{
					detailRoutes.set(id, route);
				}
				else
				{
					unavailableDetailIds.add(id);
				}
			});
		}
		catch (error)
		{
			// Keep detail icons hidden when route resolution is temporarily unavailable.
		}
		finally
		{
			ids.forEach((id) => pendingDetailIds.delete(id));
			applyDetailRoutes();
		}
	};

	const recordInteraction = (eventType, clipId = 0, playDetails = {}) =>
	{
		if (interactionUrl === '' || interactionToken === '')
		{
			return;
		}

		const body = new URLSearchParams();
		body.set('event_type', eventType);
		body.set(interactionToken, '1');

		if (clipId > 0)
		{
			body.set('clip_id', String(clipId));
		}

		if (eventType === 'audioarchive.soundboard.play')
		{
			const playSource = String(playDetails.source || '').trim();
			const midiNote = Number.parseInt(String(playDetails.midiNote ?? ''), 10);
			const midiVelocity = Number.parseInt(String(playDetails.midiVelocity ?? ''), 10);

			if (playSource !== '')
			{
				body.set('play_source', playSource);
			}

			if (Number.isInteger(midiNote))
			{
				body.set('midi_note', String(midiNote));
			}

			if (Number.isInteger(midiVelocity))
			{
				body.set('midi_velocity', String(midiVelocity));
			}
		}

		fetch(interactionUrl,
			{
				method: 'POST',
				headers:
				{
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: body.toString(),
				credentials: 'same-origin',
				keepalive: true,
			}
		).catch(() =>
		{
			// Optional analytics must never interrupt the Sound Board.
		});
	};

	const render = () =>
	{
		pads.forEach((pad, index) =>
		{
			const entry = board[index] || null;
			const title = pad.querySelector('[data-audioarchive-soundboard-title]');
			pad.classList.toggle('is-empty', !entry);
			title.textContent = entry ? entry.title : root.dataset.audioarchiveLabelEmpty;
			updatePadPlayingState(index);
		});

		syncSamplerSelection();

		if (samplerMode && selectedSamplerIndex < 0)
		{
			const firstOccupiedPad = board.findIndex((entry) => Boolean(entry));

			if (firstOccupiedPad >= 0)
			{
				void selectSamplerPad(firstOccupiedPad, false);
			}
		}

		applyDetailRoutes();
		void resolveDetailRoutes();
	};

	const recordPlay = (clipId) =>
	{
		const id = Number.parseInt(String(clipId || ''), 10);

		if (!Number.isInteger(id) || id <= 0 || playCountUrl === '' || tokenName === '' || countedClipIds.has(id))
		{
			return;
		}

		countedClipIds.add(id);
		const body = new URLSearchParams();
		body.set('id', String(id));
		body.set(tokenName, '1');

		fetch(playCountUrl,
			{
				method: 'POST',
				headers:
				{
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: body.toString(),
				credentials: 'same-origin',
				keepalive: true,
			}
		).catch(() =>
		{
			// Counting is informational and must never interrupt playback.
		});
	};

	const updatePadPlayingState = (index) =>
	{
		const hasAudioVoices = (voicesByPad.get(index)?.size || 0) > 0;
		const hasSamplerVoices = (samplerVoicesByPad.get(index)?.size || 0) > 0;
		pads[index]?.classList.toggle('is-playing', hasAudioVoices || hasSamplerVoices);
	};

	const cleanupVoice = (voice, index) =>
	{
		activeVoices.delete(voice);
		const padVoices = voicesByPad.get(index);

		if (padVoices)
		{
			padVoices.delete(voice);

			if (padVoices.size === 0)
			{
				voicesByPad.delete(index);
			}
		}

		voice.removeAttribute('src');
		voice.load();
		updatePadPlayingState(index);
	};

	const cleanupSamplerVoice = (voice) =>
	{
		activeSamplerVoices.delete(voice);
		const padVoices = samplerVoicesByPad.get(voice.index);

		if (padVoices)
		{
			padVoices.delete(voice);

			if (padVoices.size === 0)
			{
				samplerVoicesByPad.delete(voice.index);
			}
		}

		try
		{
			voice.sourceNode.disconnect();
			voice.gainNode.disconnect();
		}
		catch (error)
		{
			// A voice that already ended may already be disconnected.
		}

		updatePadPlayingState(voice.index);
	};

	const stopSamplerVoice = (voice) =>
	{
		try
		{
			voice.sourceNode.stop();
		}
		catch (error)
		{
			// Stopping an already-ended source is harmless.
		}

		cleanupSamplerVoice(voice);
	};

	const stopAllSamplerVoices = () =>
	{
		Array.from(activeSamplerVoices).forEach((voice) => stopSamplerVoice(voice));
		activeSamplerVoices.clear();
		samplerVoicesByPad.clear();
	};

	const stopPadVoices = (index) =>
	{
		Array.from(voicesByPad.get(index) || []).forEach((voice) =>
		{
			voice.pause();
			cleanupVoice(voice, index);
		});

		Array.from(samplerVoicesByPad.get(index) || []).forEach((voice) => stopSamplerVoice(voice));
	};

	const stopAllVoices = () =>
	{
		Array.from(activeVoices).forEach((voice) =>
		{
			voice.pause();
			const index = Number.parseInt(voice.dataset.audioarchivePadIndex || '-1', 10);

			if (index >= 0)
			{
				cleanupVoice(voice, index);
			}
		});

		Array.from(activeSamplerVoices).forEach((voice) => stopSamplerVoice(voice));
		activeVoices.clear();
		voicesByPad.clear();
		activeSamplerVoices.clear();
		samplerVoicesByPad.clear();
		pads.forEach((pad) => pad.classList.remove('is-playing'));
	};

	const setSamplerDescription = (template, title) =>
	{
		if (samplerDescription)
		{
			samplerDescription.textContent = formatSoundboardLabel(template, title);
		}
	};

	const updatePianoNotes = () =>
	{
		pianoKeys.forEach((key) =>
		{
			const offset = Number.parseInt(key.dataset.noteOffset || '0', 10);
			const midiNote = samplerBaseNote + offset;
			const noteName = getMidiNoteName(midiNote);
			const computerKey = key.dataset.computerKey || '';
			const noteLabel = key.querySelector('[data-audioarchive-soundboard-piano-note]');
			key.dataset.midiNote = String(midiNote);
			key.setAttribute('aria-label', computerKey === '' ? noteName : `${noteName} (${computerKey})`);

			if (noteLabel)
			{
				noteLabel.textContent = noteName;
			}
		});

		if (octaveLabel)
		{
			const octave = Math.floor(samplerBaseNote / 12) - 1;
			octaveLabel.textContent = formatSoundboardLabel(root.dataset.audioarchiveLabelKeyboardOctave, octave);
		}
	};

	const setKeyboardVisible = (visible) =>
	{
		keyboardVisible = visible && samplerMode && selectedSamplerIndex >= 0;

		if (keyboard)
		{
			keyboard.hidden = !keyboardVisible;
		}

		if (keyboardToggle)
		{
			keyboardToggle.setAttribute('aria-expanded', keyboardVisible ? 'true' : 'false');
		}

		if (keyboardToggleLabel)
		{
			keyboardToggleLabel.textContent = keyboardVisible
				? root.dataset.audioarchiveLabelKeyboardHide
				: root.dataset.audioarchiveLabelKeyboardShow;
		}

		if (!keyboardVisible)
		{
			pianoKeys.forEach((key) => key.classList.remove('is-pressed'));
		}
	};

	const syncSamplerSelection = () =>
	{
		const entry = samplerMode && selectedSamplerIndex >= 0 ? board[selectedSamplerIndex] || null : null;

		if (!entry || entry.id !== selectedSamplerClipId)
		{
			selectedSamplerIndex = -1;
			selectedSamplerClipId = 0;
			samplerSelectionGeneration++;
			setKeyboardVisible(false);

			if (samplerPanel)
			{
				samplerPanel.hidden = true;
			}

			setSamplerDescription(root.dataset.audioarchiveLabelSamplerPrompt, '');
		}
		else if (samplerPanel)
		{
			samplerPanel.hidden = false;
		}

		pads.forEach((pad, index) =>
		{
			const selected = samplerMode && index === selectedSamplerIndex;
			const trigger = pad.querySelector('[data-audioarchive-soundboard-trigger]');
			pad.classList.toggle('is-sampler-selected', selected);

			if (trigger)
			{
				trigger.setAttribute(
					'aria-label',
					formatSoundboardLabel(
						samplerMode
							? root.dataset.audioarchiveLabelSamplerSelectPad
							: root.dataset.audioarchiveLabelPlayPad,
						index + 1
					)
				);

				if (samplerMode)
				{
					trigger.setAttribute('aria-pressed', selected ? 'true' : 'false');
				}
				else
				{
					trigger.removeAttribute('aria-pressed');
				}
			}
		});

		root.classList.toggle('is-sampler-mode', samplerMode);
		padModeButton?.classList.toggle('is-active', !samplerMode);
		padModeButton?.setAttribute('aria-pressed', samplerMode ? 'false' : 'true');
		samplerModeButton?.classList.toggle('is-active', samplerMode);
		samplerModeButton?.setAttribute('aria-pressed', samplerMode ? 'true' : 'false');
	};

	const createAudioContext = () =>
	{
		const AudioContextClass = window.AudioContext || window.webkitAudioContext;

		if (!AudioContextClass)
		{
			throw new Error('Web Audio is unavailable');
		}

		if (!audioContext)
		{
			audioContext = new AudioContextClass();
		}

		return audioContext;
	};

	const unlockSamplerAudio = () =>
	{
		try
		{
			const context = createAudioContext();

			if (context.state !== 'running')
			{
				void context.resume().catch(() => {});
			}

			const unlockBuffer = context.createBuffer(1, 1, context.sampleRate);
			const unlockSource = context.createBufferSource();
			unlockSource.buffer = unlockBuffer;
			unlockSource.connect(context.destination);
			unlockSource.addEventListener('ended', () => unlockSource.disconnect(), {once: true});
			unlockSource.start(0);
		}
		catch (error)
		{
			// The normal sampler error message handles browsers without usable Web Audio.
		}
	};

	const getAudioContext = async () =>
	{
		const context = createAudioContext();

		if (context.state !== 'running')
		{
			await context.resume();
		}

		return context;
	};

	const loadSamplerBuffer = (entry) =>
	{
		if (samplerBuffers.has(entry.id))
		{
			return samplerBuffers.get(entry.id);
		}

		const promise = (async () =>
		{
			const context = await getAudioContext();
			const source = streamTemplate.replace('987654321', String(entry.id));
			const response = await fetch(source, {credentials: 'same-origin'});

			if (!response.ok)
			{
				throw new Error(`Unable to load sampler audio (${response.status})`);
			}

			const encodedAudio = await response.arrayBuffer();
			return new Promise((resolve, reject) =>
			{
				context.decodeAudioData(encodedAudio, resolve, reject);
			});
		})();

		samplerBuffers.set(entry.id, promise);
		promise
			.then((buffer) => decodedSamplerBuffers.set(entry.id, buffer))
			.catch(() =>
			{
				samplerBuffers.delete(entry.id);
				decodedSamplerBuffers.delete(entry.id);
			});
		return promise;
	};

	const setSamplerAudioSessionActive = (active) =>
	{
		const audioSession = navigator.audioSession;

		if (!audioSession || typeof audioSession.type !== 'string')
		{
			return;
		}

		if (active)
		{
			try
			{
				const currentType = audioSession.type;
				audioSession.type = 'playback';

				if (previousAudioSessionType === null)
				{
					previousAudioSessionType = currentType;
				}
			}
			catch (error)
			{
				// Browsers without a writable AudioSession type keep their default behaviour.
			}

			return;
		}

		if (previousAudioSessionType !== null)
		{
			try
			{
				audioSession.type = previousAudioSessionType;
			}
			catch (error)
			{
				// The session may no longer be writable while the page is being hidden.
			}
			finally
			{
				previousAudioSessionType = null;
			}
		}
	};

	const selectSamplerPad = async (index, allowDeselect = true) =>
	{
		if (!samplerEnabled || !samplerMode)
		{
			return;
		}

		unlockSamplerAudio();

		const entry = board[index] || null;

		if (!entry)
		{
			return;
		}

		if (selectedSamplerIndex === index && selectedSamplerClipId === entry.id)
		{
			if (allowDeselect)
			{
				selectedSamplerIndex = -1;
				selectedSamplerClipId = 0;
				samplerSelectionGeneration++;
				syncSamplerSelection();
			}
			else
			{
				setKeyboardVisible(true);
			}

			return;
		}

		selectedSamplerIndex = index;
		selectedSamplerClipId = entry.id;
		const generation = ++samplerSelectionGeneration;
		syncSamplerSelection();
		setKeyboardVisible(true);
		setSamplerDescription(root.dataset.audioarchiveLabelSamplerLoading, entry.title);

		try
		{
			await loadSamplerBuffer(entry);

			if (generation === samplerSelectionGeneration && selectedSamplerClipId === entry.id)
			{
				setSamplerDescription(root.dataset.audioarchiveLabelSamplerReady, entry.title);
			}
		}
		catch (error)
		{
			if (generation === samplerSelectionGeneration && selectedSamplerClipId === entry.id)
			{
				setSamplerDescription(root.dataset.audioarchiveLabelSamplerError, entry.title);
			}
		}
	};

	const setSamplerMode = (enabled) =>
	{
		const nextMode = samplerEnabled && Boolean(enabled);
		setSamplerAudioSessionActive(nextMode);

		if (nextMode)
		{
			unlockSamplerAudio();
		}

		if (samplerMode === nextMode)
		{
			syncSamplerSelection();
		}
		else
		{
			samplerMode = nextMode;
			selectedSamplerIndex = -1;
			selectedSamplerClipId = 0;
			samplerSelectionGeneration++;
			syncSamplerSelection();
		}

		if (samplerMode && selectedSamplerIndex < 0)
		{
			const firstOccupiedPad = board.findIndex((entry) => Boolean(entry));

			if (firstOccupiedPad >= 0)
			{
				void selectSamplerPad(firstOccupiedPad, false);
			}
		}
	};

	const setPianoKeyPressed = (midiNote, pressed) =>
	{
		pianoKeys.forEach((key) =>
		{
			if (Number.parseInt(key.dataset.midiNote || '-1', 10) === midiNote)
			{
				key.classList.toggle('is-pressed', pressed);
			}
		});
	};

	const startSamplerVoice = (context, buffer, entry, index, midiNote, velocity, playSource) =>
	{
		if (selectedSamplerIndex !== index || selectedSamplerClipId !== entry.id)
		{
			return;
		}

		if (!polyphonic)
		{
			stopAllVoices();
		}
		else if (!samplerPolyphonic)
		{
			stopAllSamplerVoices();
		}

		const sourceNode = context.createBufferSource();
		const gainNode = context.createGain();
		const safeNote = Math.max(0, Math.min(127, Math.round(midiNote)));
		const safeVelocity = Math.max(1, Math.min(127, Math.round(velocity)));
		sourceNode.buffer = buffer;
		sourceNode.playbackRate.value = 2 ** ((safeNote - SAMPLER_ROOT_MIDI_NOTE) / 12);
		gainNode.gain.value = safeVelocity / 127;
		sourceNode.connect(gainNode);
		gainNode.connect(context.destination);

		const voice = {sourceNode, gainNode, index};
		activeSamplerVoices.add(voice);

		if (!samplerVoicesByPad.has(index))
		{
			samplerVoicesByPad.set(index, new Set());
		}

		samplerVoicesByPad.get(index).add(voice);
		sourceNode.addEventListener('ended', () => cleanupSamplerVoice(voice), {once: true});
		sourceNode.start();
		updatePadPlayingState(index);
		recordPlay(entry.id);

		if (recordSoundboardPlays)
		{
			recordInteraction(
				'audioarchive.soundboard.play',
				entry.id,
				{source: playSource, midiNote: safeNote, midiVelocity: safeVelocity}
			);
		}

		if (status)
		{
			status.textContent = `${root.dataset.audioarchiveLabelPlaying}: ${entry.title} (${getMidiNoteName(safeNote)})`;
		}
	};

	const playSamplerNote = (midiNote, velocity, playSource) =>
	{
		if (!samplerEnabled || !samplerMode)
		{
			return;
		}

		setSamplerAudioSessionActive(true);
		unlockSamplerAudio();

		const index = selectedSamplerIndex;
		const entry = index >= 0 ? board[index] || null : null;

		if (!entry || entry.id !== selectedSamplerClipId || streamTemplate === '')
		{
			return;
		}

		const readyBuffer = decodedSamplerBuffers.get(entry.id) || null;

		if (readyBuffer)
		{
			try
			{
				startSamplerVoice(createAudioContext(), readyBuffer, entry, index, midiNote, velocity, playSource);
			}
			catch (error)
			{
				setSamplerDescription(root.dataset.audioarchiveLabelSamplerError, entry.title);
			}

			return;
		}

		void (async () =>
		{
			const context = await getAudioContext();
			const buffer = await loadSamplerBuffer(entry);
			startSamplerVoice(context, buffer, entry, index, midiNote, velocity, playSource);
		})().catch(() =>
		{
			setSamplerDescription(root.dataset.audioarchiveLabelSamplerError, entry.title);
		});
	};

	const shiftSamplerOctave = (direction) =>
	{
		const nextBaseNote = Math.max(
			SAMPLER_MIN_BASE_NOTE,
			Math.min(SAMPLER_MAX_BASE_NOTE, samplerBaseNote + direction * 12)
		);

		if (nextBaseNote !== samplerBaseNote)
		{
			samplerBaseNote = nextBaseNote;
			pianoKeys.forEach((key) => key.classList.remove('is-pressed'));
			updatePianoNotes();
		}
	};

	const updateMidiStatus = () =>
	{
		if (!midiStatus || !midiEnableButton)
		{
			return;
		}

		if (window.isSecureContext === false)
		{
			midiEnableButton.disabled = true;
			midiStatus.textContent = root.dataset.audioarchiveLabelMidiInsecure;
			return;
		}

		if (typeof navigator.requestMIDIAccess !== 'function')
		{
			midiEnableButton.disabled = true;
			midiStatus.textContent = root.dataset.audioarchiveLabelMidiUnavailable;
			return;
		}

		if (!midiAccess)
		{
			midiEnableButton.disabled = false;
			midiStatus.textContent = '';
			return;
		}

		const inputCount = Array.from(midiAccess.inputs.values()).filter((input) => input.state === 'connected').length;
		midiEnableButton.disabled = true;

		if (midiEnableLabel)
		{
			midiEnableLabel.textContent = root.dataset.audioarchiveLabelMidiEnabled;
		}

		midiStatus.textContent = inputCount === 0
			? root.dataset.audioarchiveLabelMidiNoInputs
			: formatSoundboardLabel(root.dataset.audioarchiveLabelMidiInputs, inputCount);
	};

	const handleMidiMessage = (event) =>
	{
		const data = event.data || [];
		const command = Number(data[0] || 0) & 0xf0;
		const midiNote = Number(data[1] || 0);
		const velocity = Number(data[2] || 0);

		if (command === 0x90 && velocity > 0)
		{
			setPianoKeyPressed(midiNote, true);
			void playSamplerNote(midiNote, velocity, 'midi');
		}
		else if (command === 0x80 || (command === 0x90 && velocity === 0))
		{
			setPianoKeyPressed(midiNote, false);
		}
	};

	const bindMidiInputs = () =>
	{
		if (!midiAccess)
		{
			return;
		}

		midiAccess.inputs.forEach((input) =>
		{
			input.onmidimessage = handleMidiMessage;
		});
		updateMidiStatus();
	};

	const enableMidi = async () =>
	{
		if (window.isSecureContext === false || typeof navigator.requestMIDIAccess !== 'function')
		{
			updateMidiStatus();
			return;
		}

		try
		{
			midiAccess = await navigator.requestMIDIAccess({sysex: false});
			midiAccess.addEventListener('statechange', bindMidiInputs);
			bindMidiInputs();
		}
		catch (error)
		{
			if (midiStatus)
			{
				midiStatus.textContent = root.dataset.audioarchiveLabelMidiDenied;
			}
		}
	};

	const play = (index) =>
	{
		const entry = board[index] || null;

		if (!entry || streamTemplate === '')
		{
			return;
		}

		if (!polyphonic)
		{
			stopAllVoices();
		}

		const source = streamTemplate.replace('987654321', String(entry.id));
		const voice = new Audio(source);
		voice.preload = 'auto';
		voice.playsInline = true;
		voice.dataset.audioarchivePadIndex = String(index);
		activeVoices.add(voice);

		if (!voicesByPad.has(index))
		{
			voicesByPad.set(index, new Set());
		}

		voicesByPad.get(index).add(voice);
		voice.addEventListener('play', () =>
		{
			pads[index]?.classList.add('is-playing');
			recordPlay(entry.id);
			if (recordSoundboardPlays)
			{
				recordInteraction('audioarchive.soundboard.play', entry.id, {source: 'pad'});
			}

			if (status)
			{
				status.textContent = `${root.dataset.audioarchiveLabelPlaying}: ${entry.title}`;
			}
		}, {once: true});
		voice.addEventListener('ended', () => cleanupVoice(voice, index), {once: true});
		voice.addEventListener('error', () => cleanupVoice(voice, index), {once: true});

		voice.play().catch(() =>
		{
			cleanupVoice(voice, index);
		});
	};

	pads.forEach((pad, index) =>
	{
		pad.querySelector('[data-audioarchive-soundboard-trigger]')?.addEventListener('click', () =>
		{
			if (samplerMode)
			{
				void selectSamplerPad(index, false);
				return;
			}

			play(index);
		});
		pad.querySelector('[data-audioarchive-soundboard-remove]')?.addEventListener('click', () =>
		{
			stopPadVoices(index);
			board[index] = null;
			saveCurrentBoard();
			render();
		});
	});

	midiEnableButton?.addEventListener('click', () =>
	{
		void enableMidi();
	});

	if (samplerPolyphonyToggle instanceof HTMLInputElement)
	{
		samplerPolyphonyToggle.checked = samplerPolyphonic;
		samplerPolyphonyToggle.addEventListener('change', () =>
		{
			samplerPolyphonic = polyphonic && samplerPolyphonyToggle.checked;
			writeStorage(SAMPLER_POLYPHONY_STORAGE_KEY, samplerPolyphonic);

			if (!samplerPolyphonic)
			{
				stopAllSamplerVoices();
			}
		});
	}

	padModeButton?.addEventListener('click', () => setSamplerMode(false));
	samplerModeButton?.addEventListener('click', () => setSamplerMode(true));
	keyboardToggle?.addEventListener('click', () => setKeyboardVisible(!keyboardVisible));
	root.querySelector('[data-audioarchive-soundboard-octave-down]')?.addEventListener('click', () => shiftSamplerOctave(-1));
	root.querySelector('[data-audioarchive-soundboard-octave-up]')?.addEventListener('click', () => shiftSamplerOctave(1));

	pianoKeys.forEach((key) =>
	{
		const release = () => key.classList.remove('is-pressed');

		key.addEventListener('pointerdown', (event) =>
		{
			if (event.button !== 0)
			{
				return;
			}

			event.preventDefault();
			const midiNote = Number.parseInt(key.dataset.midiNote || '-1', 10);

			if (midiNote >= 0)
			{
				key.classList.add('is-pressed');
				void playSamplerNote(midiNote, 112, 'onscreen_keyboard');
			}
		});
		key.addEventListener('pointerup', release);
		key.addEventListener('pointercancel', release);
		key.addEventListener('pointerleave', release);
		key.addEventListener('click', (event) =>
		{
			if (event.detail !== 0)
			{
				return;
			}

			const midiNote = Number.parseInt(key.dataset.midiNote || '-1', 10);

			if (midiNote >= 0)
			{
				void playSamplerNote(midiNote, 112, 'onscreen_keyboard');
			}
		});
	});

	root.querySelector('[data-audioarchive-soundboard-clear]')?.addEventListener('click', () =>
	{
		stopAllVoices();
		board = [];
		saveCurrentBoard();
		render();
	});

	root.querySelector('[data-audioarchive-soundboard-shared-add]')?.addEventListener('click', () =>
	{
		const result = mergeBoards(readBoard(), board, padCount);

		if (!writeStorage(BOARD_STORAGE_KEY, result.board))
		{
			return;
		}

		stopAllVoices();
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

		stopAllVoices();
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

			stopAllVoices();
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

	const getSharedSoundboardUrl = () =>
	{
		let url = `${canonicalUrl}#board=${encodeBoard(board)}`;

		if (samplerMode)
		{
			const octave = Math.floor(samplerBaseNote / 12) - 1;
			url += `&mode=2&octave=${octave}`;

			if (selectedSamplerIndex >= 0 && selectedSamplerIndex < padCount && board[selectedSamplerIndex])
			{
				url += `&pad=${selectedSamplerIndex}`;
			}
		}

		return url;
	};

	shareCopy?.addEventListener('click', async () =>
	{
		const url = getSharedSoundboardUrl();

		if (await copyText(url))
		{
			status.textContent = root.dataset.audioarchiveLabelCopied;
			recordInteraction('audioarchive.soundboard.shared');
		}

		setShareMenuOpen(shareMenu, false);
	});

	shareNative?.addEventListener('click', async () =>
	{
		const url = getSharedSoundboardUrl();
		if (await openNativeShare(document.title, url))
		{
			recordInteraction('audioarchive.soundboard.shared');
		}

		setShareMenuOpen(shareMenu, false);
	});

	if (shareMenu)
	{
		initialiseShareMenuKeyboard(shareMenu);
	}

	const keyboardKeys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'];
	const samplerPadKeys = keyboardKeys.slice(0, 10);
	const samplerKeyboardOffsets = new Map(
		[
			['A', 0], ['W', 1], ['S', 2], ['E', 3], ['D', 4], ['F', 5], ['T', 6], ['G', 7],
			['Z', 8], ['H', 9], ['U', 10], ['J', 11], ['K', 12], ['O', 13], ['L', 14], ['P', 15],
		]
	);
	window.addEventListener('keydown', (event) =>
	{
		if (event.repeat || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey)
		{
			return;
		}

		if (
			event.target instanceof HTMLInputElement
			|| event.target instanceof HTMLTextAreaElement
			|| event.target instanceof HTMLSelectElement
			|| (event.target instanceof HTMLElement && event.target.isContentEditable)
		)
		{
			return;
		}

		const pressedKey = event.key.toUpperCase();

		if (samplerEnabled && samplerMode)
		{
			const samplerPadIndex = samplerPadKeys.indexOf(pressedKey);

			if (samplerPadIndex >= 0)
			{
				event.preventDefault();

				if (samplerPadIndex < padCount && board[samplerPadIndex])
				{
					void selectSamplerPad(samplerPadIndex, false);
				}

				return;
			}

			if (pressedKey === 'Y' || pressedKey === 'X')
			{
				event.preventDefault();
				shiftSamplerOctave(pressedKey === 'Y' ? -1 : 1);
				return;
			}

			if (samplerKeyboardOffsets.has(pressedKey))
			{
				event.preventDefault();
				const midiNote = samplerBaseNote + samplerKeyboardOffsets.get(pressedKey);
				setPianoKeyPressed(midiNote, true);
				void playSamplerNote(midiNote, 112, 'computer_keyboard');
				return;
			}

			if (/^[A-Z]$/.test(pressedKey))
			{
				event.preventDefault();
				return;
			}
		}

		const index = keyboardKeys.indexOf(pressedKey);

		if (index >= 0 && index < padCount)
		{
			event.preventDefault();
			play(index);
		}
	});
	window.addEventListener('keyup', (event) =>
	{
		if (!samplerEnabled || !samplerMode)
		{
			return;
		}

		const pressedKey = event.key.toUpperCase();

		if (samplerKeyboardOffsets.has(pressedKey))
		{
			setPianoKeyPressed(samplerBaseNote + samplerKeyboardOffsets.get(pressedKey), false);
		}
	});

	const shutdownSoundboard = () =>
	{
		setSamplerAudioSessionActive(false);
		stopAllVoices();

		if (midiAccess)
		{
			midiAccess.removeEventListener('statechange', bindMidiInputs);
			midiAccess.inputs.forEach((input) =>
			{
				input.onmidimessage = null;
			});
		}

		if (audioContext && audioContext.state !== 'closed')
		{
			void audioContext.close();
		}
	};

	window.addEventListener('pagehide', shutdownSoundboard);
	updatePianoNotes();
	updateMidiStatus();
	setTemporarySharedBoard(temporarySharedBoard);
	render();

	if (requestedMode === 2)
	{
		setSamplerMode(true);

		if (Number.isInteger(requestedPad) && requestedPad >= 0 && requestedPad < padCount && board[requestedPad])
		{
			void selectSamplerPad(requestedPad, false);
		}
	}
	else if (requestedMode === 1)
	{
		setSamplerMode(false);
	}
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

initialiseReturnNavigation();
initialiseShareButtons();
initialiseSoundboardAddButtons();
initialiseSoundboard();
initialiseRatings();
