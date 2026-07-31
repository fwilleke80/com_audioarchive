const PLAYLIST_STORAGE_KEY = 'com_audioarchive.playlists.v1';
const PLAYLIST_STORAGE_VERSION = 1;
const SOUNDBOARD_STORAGE_KEY = 'com_audioarchive.soundboard.v1';

/**
 * Read a JSON value from local storage.
 *
 * @param {string} key Storage key.
 * @param {*} fallback Fallback value.
 * @returns {*} Parsed value or fallback.
 */
function playlistReadStorage(key, fallback)
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
function playlistWriteStorage(key, value)
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
 * Create a browser-local identifier.
 *
 * @returns {string} Random identifier.
 */
function createLocalId()
{
	if (typeof window.crypto?.randomUUID === 'function')
	{
		return window.crypto.randomUUID();
	}

	const bytes = new Uint8Array(16);
	window.crypto.getRandomValues(bytes);
	return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/**
 * Validate a clip UUID.
 *
 * @param {*} value Candidate UUID.
 * @returns {string} Normalised UUID or an empty string.
 */
function normaliseClipUuid(value)
{
	const uuid = String(value || '').trim().toLowerCase();
	return /^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(uuid)
		? uuid
		: '';
}

/**
 * Normalise one playlist item.
 *
 * @param {*} value Candidate item.
 * @returns {{uuid:string,id:number,title:string}|null} Normalised item.
 */
function normalisePlaylistItem(value)
{
	if (!value || typeof value !== 'object')
	{
		return null;
	}

	const uuid = normaliseClipUuid(value.uuid);
	const id = Math.max(0, Number.parseInt(String(value.id || 0), 10) || 0);
	const title = String(value.title || '').trim().slice(0, 255);

	if (uuid === '')
	{
		return null;
	}

	return {uuid, id, title};
}

/**
 * Normalise one browser-local playlist.
 *
 * @param {*} value Candidate playlist.
 * @param {string} fallbackName Fallback playlist name.
 * @returns {{id:string,name:string,created:number,modified:number,items:Array<{uuid:string,id:number,title:string}>}|null} Normalised playlist.
 */
function normalisePlaylist(value, fallbackName = 'Playlist')
{
	if (!value || typeof value !== 'object')
	{
		return null;
	}

	const id = String(value.id || '').trim().slice(0, 80) || createLocalId();
	const name = String(value.name || fallbackName).trim().slice(0, 120) || fallbackName;
	const created = Number.isFinite(Number(value.created)) ? Number(value.created) : Date.now();
	const modified = Number.isFinite(Number(value.modified)) ? Number(value.modified) : created;
	const seen = new Set();
	const items = [];

	for (const candidate of Array.isArray(value.items) ? value.items : [])
	{
		const item = normalisePlaylistItem(candidate);

		if (!item || seen.has(item.uuid))
		{
			continue;
		}

		seen.add(item.uuid);
		items.push(item);

		if (items.length >= 500)
		{
			break;
		}
	}

	return {id, name, created, modified, items};
}

/**
 * Normalise the complete playlist store.
 *
 * @param {*} value Candidate store.
 * @param {string} fallbackName Fallback name for a newly created playlist.
 * @param {boolean} ensurePlaylist Whether to create one playlist when empty.
 * @returns {{version:number,selectedId:string,playlists:Array<object>}} Normalised store.
 */
function normalisePlaylistStore(value, fallbackName = 'Playlist', ensurePlaylist = false)
{
	const candidate = value && typeof value === 'object' ? value : {};
	const playlists = [];
	const seenIds = new Set();

	for (const entry of Array.isArray(candidate.playlists) ? candidate.playlists : [])
	{
		const playlist = normalisePlaylist(entry, fallbackName);

		if (!playlist)
		{
			continue;
		}

		if (seenIds.has(playlist.id))
		{
			playlist.id = createLocalId();
		}

		seenIds.add(playlist.id);
		playlists.push(playlist);
	}

	if (ensurePlaylist && playlists.length === 0)
	{
		const now = Date.now();
		playlists.push({id: createLocalId(), name: fallbackName, created: now, modified: now, items: []});
	}

	let selectedId = String(candidate.selectedId || '').trim();

	if (!playlists.some((playlist) => playlist.id === selectedId))
	{
		selectedId = playlists[0]?.id || '';
	}

	return {version: PLAYLIST_STORAGE_VERSION, selectedId, playlists};
}

/**
 * Read the playlist store.
 *
 * @param {string} fallbackName Fallback name.
 * @param {boolean} ensurePlaylist Whether to create a default playlist.
 * @returns {{version:number,selectedId:string,playlists:Array<object>}} Playlist store.
 */
function readPlaylistStore(fallbackName = 'Playlist', ensurePlaylist = false)
{
	return normalisePlaylistStore(
		playlistReadStorage(PLAYLIST_STORAGE_KEY, {}),
		fallbackName,
		ensurePlaylist
	);
}

/**
 * Persist the playlist store.
 *
 * @param {object} store Store to persist.
 * @returns {boolean} True on success.
 */
function writePlaylistStore(store)
{
	return playlistWriteStorage(
		PLAYLIST_STORAGE_KEY,
		normalisePlaylistStore(store, 'Playlist', false)
	);
}

/**
 * Return one playlist by identifier.
 *
 * @param {object} store Playlist store.
 * @param {string} playlistId Playlist identifier.
 * @returns {object|null} Playlist or null.
 */
function findPlaylist(store, playlistId)
{
	return store.playlists.find((playlist) => playlist.id === playlistId) || null;
}

/**
 * Return a unique playlist name.
 *
 * @param {object} store Playlist store.
 * @param {string} requestedName Requested name.
 * @returns {string} Unique name.
 */
function makeUniquePlaylistName(store, requestedName)
{
	const base = String(requestedName || 'Playlist').trim().slice(0, 120) || 'Playlist';
	const names = new Set(store.playlists.map((playlist) => playlist.name.toLocaleLowerCase()));

	if (!names.has(base.toLocaleLowerCase()))
	{
		return base;
	}

	let suffix = 2;

	while (names.has(`${base} (${suffix})`.toLocaleLowerCase()))
	{
		suffix++;
	}

	return `${base} (${suffix})`;
}

/**
 * Create a playlist in a store.
 *
 * @param {object} store Playlist store.
 * @param {string} name Requested name.
 * @returns {object} Created playlist.
 */
function createPlaylist(store, name)
{
	const now = Date.now();
	const playlist = {
		id: createLocalId(),
		name: makeUniquePlaylistName(store, name),
		created: now,
		modified: now,
		items: [],
	};
	store.playlists.push(playlist);
	store.selectedId = playlist.id;
	return playlist;
}

/**
 * Add a clip to one playlist without duplicates.
 *
 * @param {object} playlist Target playlist.
 * @param {{uuid:string,id:number,title:string}} clip Clip identity.
 * @returns {boolean} True when inserted.
 */
function addClipToPlaylist(playlist, clip)
{
	if (!playlist || playlist.items.some((item) => item.uuid === clip.uuid))
	{
		return false;
	}

	playlist.items.push(clip);
	playlist.modified = Date.now();
	return true;
}

/**
 * Post one optional analytics interaction.
 *
 * @param {string} url Endpoint URL.
 * @param {string} tokenName CSRF token field name.
 * @param {string} eventType Stable event type.
 * @param {object} data Optional clip context. Browser-local playlist names and identifiers are never transmitted.
 * @returns {void}
 */
function recordPlaylistInteraction(url, tokenName, eventType, data = {})
{
	if (url === '' || tokenName === '' || eventType === '')
	{
		return;
	}

	const body = new URLSearchParams();
	body.set('event_type', eventType);
	body.set(tokenName, '1');

	if (Number.isInteger(data.clipId) && data.clipId > 0)
	{
		body.set('clip_id', String(data.clipId));
	}

	fetch(url, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			'X-Requested-With': 'XMLHttpRequest',
		},
		body: body.toString(),
		credentials: 'same-origin',
		keepalive: true,
	}).catch(() =>
	{
		// Statistics are optional and must not interrupt playlist actions.
	});
}

/**
 * Copy text to the clipboard with a legacy fallback.
 *
 * @param {string} text Text to copy.
 * @returns {Promise<boolean>} Copy result.
 */
async function playlistCopyText(text)
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
			// Continue with the selection fallback.
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
 * Open the native share sheet.
 *
 * @param {string} title Share title.
 * @param {string} url URL to share.
 * @returns {Promise<boolean>} True when sharing completed.
 */
async function playlistNativeShare(title, url)
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
 * Encode a value as base64url JSON.
 *
 * @param {*} value JSON-compatible value.
 * @returns {string} Encoded payload.
 */
function encodePlaylistPayload(value)
{
	const bytes = new TextEncoder().encode(JSON.stringify(value));
	let binary = '';
	bytes.forEach((byte) =>
	{
		binary += String.fromCharCode(byte);
	});
	return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

/**
 * Decode a base64url JSON value.
 *
 * @param {string} encoded Encoded payload.
 * @returns {*|null} Decoded value or null.
 */
function decodePlaylistPayload(encoded)
{
	try
	{
		const padded = encoded.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - encoded.length % 4) % 4);
		const binary = window.atob(padded);
		const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));
		return JSON.parse(new TextDecoder().decode(bytes));
	}
	catch (error)
	{
		return null;
	}
}

/**
 * Position a fixed popover next to its toggle.
 *
 * @param {HTMLElement} toggle Toggle element.
 * @param {HTMLElement} popover Popover element.
 * @returns {void}
 */
function positionPlaylistPopover(toggle, popover)
{
	if (popover.hidden)
	{
		return;
	}

	const gap = 6;
	const viewportPadding = 8;
	const toggleRect = toggle.getBoundingClientRect();
	const popoverRect = popover.getBoundingClientRect();
	let left = toggleRect.right + gap;
	let top = toggleRect.top;

	if (left + popoverRect.width > window.innerWidth - viewportPadding)
	{
		left = toggleRect.left - popoverRect.width - gap;
	}

	if (left < viewportPadding)
	{
		left = Math.min(
			Math.max(viewportPadding, toggleRect.left),
			Math.max(viewportPadding, window.innerWidth - popoverRect.width - viewportPadding)
		);
		top = toggleRect.bottom + gap;
	}

	if (top + popoverRect.height > window.innerHeight - viewportPadding)
	{
		top = Math.max(viewportPadding, window.innerHeight - popoverRect.height - viewportPadding);
	}

	popover.style.left = `${Math.round(left)}px`;
	popover.style.top = `${Math.round(top)}px`;
}

/**
 * Close all playlist-managed popovers.
 *
 * @param {HTMLElement|null} retained Optional retained menu.
 * @returns {void}
 */
function closePlaylistPopovers(retained = null)
{
	document.querySelectorAll('[data-audioarchive-add-to-menu], [data-audioarchive-playlist-row-share-menu], [data-audioarchive-playlist-share-menu]').forEach((menu) =>
	{
		if (menu === retained)
		{
			return;
		}

		const toggle = menu.querySelector('[aria-expanded]');
		const popover = menu.querySelector('[role="menu"]');

		if (toggle)
		{
			toggle.setAttribute('aria-expanded', 'false');
		}

		if (popover instanceof HTMLElement)
		{
			popover.hidden = true;
		}
	});
}

/**
 * Add a clip to the browser-local Sound Board.
 *
 * @param {{id:number,title:string}} clip Clip identity.
 * @param {HTMLElement} origin Origin element.
 * @returns {boolean} True when present after the operation.
 */
function addClipToSoundboard(clip, origin)
{
	const configurationRoot = origin.closest('[data-audioarchive-soundboard-pad-count]')
		|| document.querySelector('[data-audioarchive-soundboard-pad-count]');
	const padCount = Math.max(4, Math.min(36, Number.parseInt(configurationRoot?.dataset.audioarchiveSoundboardPadCount || '12', 10)));
	const fullLabel = configurationRoot?.dataset.audioarchiveSoundboardFullLabel || 'The Sound Board is full.';
	const rawBoard = playlistReadStorage(SOUNDBOARD_STORAGE_KEY, []);
	const board = Array.isArray(rawBoard) ? rawBoard.slice(0, padCount) : [];
	const existing = board.findIndex((entry) => entry && Number.parseInt(entry.id, 10) === clip.id);

	if (existing >= 0)
	{
		return true;
	}

	let slot = board.findIndex((entry) => entry === null);

	if (slot < 0 && board.length < padCount)
	{
		slot = board.length;
	}

	if (slot < 0)
	{
		window.alert(fullLabel);
		return false;
	}

	board[slot] = {id: clip.id, title: clip.title};
	return playlistWriteStorage(SOUNDBOARD_STORAGE_KEY, board);
}

/**
 * Build playlist choices inside an Add to menu.
 *
 * @param {HTMLElement} container Choice container.
 * @param {HTMLElement} menu Menu root.
 * @returns {void}
 */
function renderAddToPlaylistChoices(container, menu)
{
	const fallbackName = menu.dataset.labelDefaultName || 'My playlist';
	const store = readPlaylistStore(fallbackName, false);
	const clip = {
		uuid: normaliseClipUuid(menu.dataset.clipUuid),
		id: Math.max(0, Number.parseInt(menu.dataset.clipId || '0', 10)),
		title: String(menu.dataset.clipTitle || '').trim().slice(0, 255),
	};
	container.replaceChildren();

	for (const playlist of store.playlists)
	{
		const alreadyAdded = playlist.items.some((item) => item.uuid === clip.uuid);
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'com-audioarchive-add-to-option';
		button.setAttribute('role', 'menuitem');
		button.disabled = alreadyAdded;
		button.textContent = `${alreadyAdded ? '✓ ' : ''}${playlist.name}`;
		button.addEventListener('click', () =>
		{
			if (addClipToPlaylist(playlist, clip) && writePlaylistStore(store))
			{
				recordPlaylistInteraction(
					menu.dataset.interactionUrl || '',
					menu.dataset.interactionToken || '',
					'audioarchive.playlist.clip_added',
					{clipId: clip.id, contextId: playlist.id, contextTitle: playlist.name}
				);
				menu.querySelector('[data-audioarchive-add-to-status]')?.replaceChildren(document.createTextNode(menu.dataset.labelAdded || 'Added.'));
			}

			closePlaylistPopovers();
		});
		container.appendChild(button);
	}

	if (store.playlists.length === 0)
	{
		const empty = document.createElement('span');
		empty.className = 'com-audioarchive-add-to-empty';
		empty.textContent = menu.dataset.labelEmpty || 'No playlists yet.';
		container.appendChild(empty);
	}

	const separator = document.createElement('div');
	separator.className = 'com-audioarchive-add-to-separator';
	separator.setAttribute('role', 'separator');
	container.appendChild(separator);
	const createButton = document.createElement('button');
	createButton.type = 'button';
	createButton.className = 'com-audioarchive-add-to-option';
	createButton.setAttribute('role', 'menuitem');
	createButton.innerHTML = `<span aria-hidden="true">＋</span><span>${menu.dataset.labelCreate || 'Create new playlist…'}</span>`;
	createButton.addEventListener('click', () =>
	{
		const requestedName = window.prompt(menu.dataset.labelNamePrompt || 'Playlist name:', fallbackName);

		if (requestedName === null || requestedName.trim() === '')
		{
			return;
		}

		const currentStore = readPlaylistStore(fallbackName, false);
		const playlist = createPlaylist(currentStore, requestedName);
		addClipToPlaylist(playlist, clip);

		if (writePlaylistStore(currentStore))
		{
			recordPlaylistInteraction(
				menu.dataset.interactionUrl || '',
				menu.dataset.interactionToken || '',
				'audioarchive.playlist.created',
				{contextId: playlist.id, contextTitle: playlist.name}
			);
			recordPlaylistInteraction(
				menu.dataset.interactionUrl || '',
				menu.dataset.interactionToken || '',
				'audioarchive.playlist.clip_added',
				{clipId: clip.id, contextId: playlist.id, contextTitle: playlist.name}
			);
		}

		closePlaylistPopovers();
	});
	container.appendChild(createButton);
}

/**
 * Initialise archive/detail Add to menus.
 *
 * @returns {void}
 */
function initialiseAddToMenus()
{
	document.querySelectorAll('[data-audioarchive-add-to-menu]').forEach((menu) =>
	{
		if (menu.dataset.audioarchiveInitialised === '1')
		{
			return;
		}

		menu.dataset.audioarchiveInitialised = '1';
		const toggle = menu.querySelector('[data-audioarchive-add-to-toggle]');
		const popover = menu.querySelector('[data-audioarchive-add-to-popover]');

		if (!(toggle instanceof HTMLButtonElement) || !(popover instanceof HTMLElement))
		{
			return;
		}

		const build = () =>
		{
			popover.replaceChildren();
			const playlistOnly = menu.dataset.playlistOnly === '1';
			const soundboardEnabled = menu.dataset.soundboardEnabled === '1';
			const playlistsEnabled = menu.dataset.playlistsEnabled !== '0';

			if (!playlistOnly && soundboardEnabled)
			{
				const soundboardButton = document.createElement('button');
				soundboardButton.type = 'button';
				soundboardButton.className = 'com-audioarchive-add-to-option';
				soundboardButton.setAttribute('role', 'menuitem');
				soundboardButton.innerHTML = `<span aria-hidden="true">▦</span><span>${menu.dataset.labelSoundboard || 'Sound Board'}</span>`;
				soundboardButton.addEventListener('click', () =>
				{
					addClipToSoundboard(
						{
							id: Math.max(0, Number.parseInt(menu.dataset.clipId || '0', 10)),
							title: String(menu.dataset.clipTitle || '').trim(),
						},
						menu
					);
					closePlaylistPopovers();
				});
				popover.appendChild(soundboardButton);
			}

			if (!playlistOnly && soundboardEnabled && playlistsEnabled)
			{
				const separator = document.createElement('div');
				separator.className = 'com-audioarchive-add-to-separator';
				separator.setAttribute('role', 'separator');
				popover.appendChild(separator);
			}

			if (playlistOnly && playlistsEnabled)
			{
				renderAddToPlaylistChoices(popover, menu);
			}
			else if (playlistsEnabled)
			{
				const submenuToggle = document.createElement('button');
				submenuToggle.type = 'button';
				submenuToggle.className = 'com-audioarchive-add-to-option';
				submenuToggle.setAttribute('role', 'menuitem');
				submenuToggle.setAttribute('aria-expanded', 'false');
				submenuToggle.innerHTML = `<span aria-hidden="true">☷</span><span>${menu.dataset.labelPlaylists || 'Playlist'}</span><span class="com-audioarchive-add-to-arrow" aria-hidden="true">›</span>`;
				const submenu = document.createElement('div');
				submenu.className = 'com-audioarchive-add-to-submenu';
				submenu.setAttribute('role', 'menu');
				submenu.hidden = true;
				submenuToggle.addEventListener('click', () =>
				{
					renderAddToPlaylistChoices(submenu, menu);
					const open = submenu.hidden;
					submenu.hidden = !open;
					submenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
					positionPlaylistPopover(toggle, popover);
				});
				popover.append(submenuToggle, submenu);
			}

			const status = document.createElement('span');
			status.className = 'visually-hidden';
			status.setAttribute('aria-live', 'polite');
			status.dataset.audioarchiveAddToStatus = '';
			popover.appendChild(status);
		};

		toggle.addEventListener('click', () =>
		{
			const open = toggle.getAttribute('aria-expanded') !== 'true';
			closePlaylistPopovers(open ? menu : null);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			popover.hidden = !open;

			if (open)
			{
				build();
				positionPlaylistPopover(toggle, popover);
				popover.querySelector('[role="menuitem"]:not(:disabled)')?.focus();
			}
		});

		menu.addEventListener('keydown', (event) =>
		{
			if (event.key === 'Escape')
			{
				event.preventDefault();
				closePlaylistPopovers();
				toggle.focus();
			}
		});
	});
}

/**
 * Format milliseconds for display.
 *
 * @param {number} milliseconds Duration.
 * @returns {string} Formatted duration.
 */
function formatPlaylistDuration(milliseconds)
{
	const seconds = Math.max(0, Math.floor(Number(milliseconds || 0) / 1000));
	const hours = Math.floor(seconds / 3600);
	const minutes = Math.floor((seconds % 3600) / 60);
	const remaining = seconds % 60;

	if (hours > 0)
	{
		return `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`;
	}

	return `${minutes}:${String(remaining).padStart(2, '0')}`;
}

/**
 * Create a row share menu.
 *
 * @param {HTMLElement} root Playlist page root.
 * @param {object} item Resolved clip item.
 * @returns {HTMLElement} Share menu.
 */
function createPlaylistRowShareMenu(root, item)
{
	const menu = document.createElement('div');
	menu.className = 'com-audioarchive-share-menu';
	menu.dataset.audioarchivePlaylistRowShareMenu = '';
	const toggle = document.createElement('button');
	toggle.type = 'button';
	toggle.className = 'btn btn-sm btn-outline-secondary com-audioarchive-share-toggle';
	toggle.setAttribute('aria-haspopup', 'menu');
	toggle.setAttribute('aria-expanded', 'false');
	toggle.innerHTML = `<span class="icon-share-alt" aria-hidden="true"></span><span>${root.dataset.audioarchiveLabelShare || 'Share'}</span>`;
	const popover = document.createElement('div');
	popover.className = 'com-audioarchive-share-popover';
	popover.setAttribute('role', 'menu');
	popover.hidden = true;
	const copy = document.createElement('button');
	copy.type = 'button';
	copy.className = 'com-audioarchive-share-option';
	copy.setAttribute('role', 'menuitem');
	copy.innerHTML = `<span class="icon-link" aria-hidden="true"></span><span>${root.dataset.audioarchiveLabelCopyLink || 'Copy link'}</span>`;
	const native = document.createElement('button');
	native.type = 'button';
	native.className = 'com-audioarchive-share-option';
	native.setAttribute('role', 'menuitem');
	native.disabled = typeof navigator.share !== 'function';
	if (native.disabled)
	{
		native.title = root.dataset.audioarchiveLabelBrowserUnavailable || '';
	}
	native.innerHTML = `<span class="icon-share-alt" aria-hidden="true"></span><span>${root.dataset.audioarchiveLabelBrowserShare || 'Share with browser'}</span>`;
	toggle.addEventListener('click', () =>
	{
		const open = toggle.getAttribute('aria-expanded') !== 'true';
		closePlaylistPopovers(open ? menu : null);
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		popover.hidden = !open;

		if (open)
		{
			positionPlaylistPopover(toggle, popover);
		}
	});
	copy.addEventListener('click', async () =>
	{
		if (await playlistCopyText(item.share_url))
		{
			const status = root.querySelector('[data-audioarchive-playlist-status]');
			if (status)
			{
				status.textContent = root.dataset.audioarchiveLabelCopied || 'Link copied.';
			}
		}
		closePlaylistPopovers();
	});
	native.addEventListener('click', async () =>
	{
		await playlistNativeShare(item.title, item.share_url);
		closePlaylistPopovers();
	});
	popover.append(copy, native);
	menu.append(toggle, popover);
	return menu;
}

/**
 * Initialise the full playlist manager page.
 *
 * @returns {void}
 */
function initialisePlaylistPage()
{
	const root = document.querySelector('[data-audioarchive-playlists]');

	if (!root)
	{
		return;
	}

	const fallbackName = root.dataset.audioarchiveLabelDefaultName || 'My playlist';
	let store = readPlaylistStore(fallbackName, true);
	writePlaylistStore(store);
	let temporaryPlaylist = null;
	let resolvedItems = new Map();
	let currentIndex = -1;
	let currentUuid = '';
	let startedUuid = '';
	let metadataRequest = 0;
	const select = root.querySelector('[data-audioarchive-playlist-select]');
	const tbody = root.querySelector('[data-audioarchive-playlist-items]');
	const empty = root.querySelector('[data-audioarchive-playlist-empty]');
	const tableWrapper = root.querySelector('[data-audioarchive-playlist-table-wrapper]');
	const sharedPanel = root.querySelector('[data-audioarchive-playlist-shared]');
	const status = root.querySelector('[data-audioarchive-playlist-status]');
	const player = root.querySelector('[data-audioarchive-custom-player]');
	const audio = player?.querySelector('[data-audioarchive-custom-audio]');
	const playerTitle = player?.querySelector('[data-audioarchive-playlist-player-title]');
	const playerPosition = player?.querySelector('[data-audioarchive-playlist-player-position]');
	const previousButton = player?.querySelector('[data-audioarchive-playlist-previous]');
	const nextButton = player?.querySelector('[data-audioarchive-playlist-next]');
	const rowPlayerTemplate = root.querySelector('[data-audioarchive-playlist-row-player-template]');

	const fragment = new URLSearchParams(window.location.hash.replace(/^#/, '')).get('playlist');

	if (fragment)
	{
		const decoded = decodePlaylistPayload(fragment);
		const candidate = normalisePlaylist(
			decoded && typeof decoded === 'object'
				? {
					id: createLocalId(),
					name: decoded.name,
					items: decoded.items,
					created: Date.now(),
					modified: Date.now(),
				}
				: null,
			fallbackName
		);

		if (candidate)
		{
			temporaryPlaylist = candidate;
			root.classList.add('is-shared-playlist');
			if (sharedPanel instanceof HTMLElement)
			{
				sharedPanel.hidden = false;
			}
		}
		else if (status)
		{
			status.textContent = root.dataset.audioarchiveLabelInvalid || '';
		}
	}

	const getCurrentPlaylist = () => temporaryPlaylist || findPlaylist(store, store.selectedId);

	const leaveSharedUrl = () =>
	{
		const url = new URL(window.location.href);
		url.hash = '';
		window.history.replaceState(window.history.state, '', url.toString());
	};

	const saveStore = () =>
	{
		if (!temporaryPlaylist)
		{
			writePlaylistStore(store);
		}
	};

	const setStatus = (message) =>
	{
		if (status)
		{
			status.textContent = message;
		}
	};

	const updateManager = () =>
	{
		if (select instanceof HTMLSelectElement)
		{
			select.replaceChildren();

			if (temporaryPlaylist)
			{
				const option = document.createElement('option');
				option.value = temporaryPlaylist.id;
				option.textContent = temporaryPlaylist.name;
				select.appendChild(option);
				select.disabled = true;
			}
			else
			{
				for (const playlist of store.playlists)
				{
					const option = document.createElement('option');
					option.value = playlist.id;
					option.textContent = `${playlist.name} (${playlist.items.length})`;
					option.selected = playlist.id === store.selectedId;
					select.appendChild(option);
				}
				select.disabled = false;
			}
		}

		root.querySelectorAll('[data-audioarchive-playlist-rename], [data-audioarchive-playlist-delete]').forEach((button) =>
		{
			button.disabled = temporaryPlaylist !== null;
		});
	};

	const setPlayerMetadata = (item, index, total) =>
	{
		if (!(audio instanceof HTMLAudioElement) || !(player instanceof HTMLElement))
		{
			return;
		}

		if (!item)
		{
			audio.pause();
			audio.removeAttribute('src');
			audio.dataset.clipId = '0';
			audio.dataset.clipTitle = '';
			audio.load();
			currentIndex = -1;
			currentUuid = '';
			startedUuid = '';
			if (playerTitle)
			{
				playerTitle.textContent = root.dataset.audioarchiveLabelEmpty || '';
			}
			if (playerPosition)
			{
				playerPosition.textContent = `0 / ${Math.max(0, total)}`;
			}
			if (previousButton instanceof HTMLButtonElement)
			{
				previousButton.disabled = true;
			}
			if (nextButton instanceof HTMLButtonElement)
			{
				nextButton.disabled = true;
			}
			player.dispatchEvent(new CustomEvent('audioarchive:sourcechanged', {detail: {item: null}}));
			return;
		}

		audio.pause();
		audio.src = item.stream_url;
		audio.dataset.clipId = String(item.id);
		audio.dataset.clipTitle = item.title;
		audio.load();
		player.classList.remove('has-error');
		currentIndex = index;
		currentUuid = item.uuid;
		startedUuid = '';
		if (playerTitle)
		{
			playerTitle.textContent = item.title;
		}
		if (playerPosition)
		{
			playerPosition.textContent = `${index + 1} / ${total}`;
		}
		player.dispatchEvent(new CustomEvent('audioarchive:sourcechanged', {detail: {item}}));
	};

	const findPlayableIndex = (startIndex, direction) =>
	{
		const playlist = getCurrentPlaylist();

		if (!playlist)
		{
			return -1;
		}

		for (let index = startIndex; index >= 0 && index < playlist.items.length; index += direction)
		{
			if (resolvedItems.has(playlist.items[index].uuid))
			{
				return index;
			}
		}

		return -1;
	};

	const loadIndex = async (index, autoplay = false) =>
	{
		const playlist = getCurrentPlaylist();

		if (!playlist || !(audio instanceof HTMLAudioElement))
		{
			return;
		}

		const playableIndex = findPlayableIndex(index, index < currentIndex ? -1 : 1);

		if (playableIndex < 0)
		{
			return;
		}

		const entry = playlist.items[playableIndex];
		const item = resolvedItems.get(entry.uuid);
		setPlayerMetadata(item, playableIndex, playlist.items.length);
		renderRows();

		if (autoplay)
		{
			try
			{
				await audio.play();
			}
			catch (error)
			{
				player?.classList.add('has-error');
			}
		}
	};

	const moveItem = (index, direction) =>
	{
		const playlist = getCurrentPlaylist();
		const target = index + direction;

		if (!playlist || temporaryPlaylist || target < 0 || target >= playlist.items.length)
		{
			return;
		}

		const [item] = playlist.items.splice(index, 1);
		playlist.items.splice(target, 0, item);
		playlist.modified = Date.now();
		if (currentUuid !== '')
		{
			currentIndex = playlist.items.findIndex((entry) => entry.uuid === currentUuid);
		}
		saveStore();
		renderRows();
		updateManager();
	};

	const removeItem = (index) =>
	{
		const playlist = getCurrentPlaylist();

		if (!playlist || temporaryPlaylist || index < 0 || index >= playlist.items.length)
		{
			return;
		}

		const [removed] = playlist.items.splice(index, 1);
		playlist.modified = Date.now();
		saveStore();
		const resolved = resolvedItems.get(removed.uuid);
		recordPlaylistInteraction(
			root.dataset.audioarchiveInteractionUrl || '',
			root.dataset.audioarchiveInteractionToken || '',
			'audioarchive.playlist.clip_removed',
			{clipId: resolved?.id || removed.id, contextId: playlist.id, contextTitle: playlist.name}
		);

		if (removed.uuid === currentUuid)
		{
			setPlayerMetadata(null, -1, playlist.items.length);
		}
		else if (currentIndex > index)
		{
			currentIndex--;
		}

		renderRows();
		updateManager();
	};

	/**
	 * Create the playlist-row control from the same unified Minimal player markup
	 * used by Archive and Related Clips. Playback remains routed through the
	 * queue player above the list so sequential playback and analytics retain
	 * their existing behaviour.
	 *
	 * @param {object|null} item Resolved clip metadata.
	 * @param {object} entry Stored playlist entry.
	 * @param {number} index Playlist position.
	 * @returns {HTMLElement|HTMLButtonElement} Row playback control.
	 */
	function createRowPlayerControl(item, entry, index)
	{
		const title = item?.title || entry.title || root.dataset.audioarchiveLabelUnavailable || 'Unavailable clip';
		const playLabel = root.dataset.audioarchiveLabelPlay || 'Play';
		const pauseLabel = root.dataset.audioarchiveLabelPause || 'Pause';
		const isPlaying = entry.uuid === currentUuid && audio instanceof HTMLAudioElement && !audio.paused;

		if (rowPlayerTemplate instanceof HTMLTemplateElement)
		{
			const fragment = rowPlayerTemplate.content.cloneNode(true);
			const rowPlayer = fragment.querySelector('[data-audioarchive-custom-player]');
			const nativeAudio = rowPlayer?.querySelector('[data-audioarchive-custom-audio]');
			const ui = rowPlayer?.querySelector('[data-audioarchive-custom-ui]');
			const toggle = rowPlayer?.querySelector('[data-audioarchive-custom-toggle]');
			const playIcon = rowPlayer?.querySelector('[data-audioarchive-icon-play]');
			const pauseIcon = rowPlayer?.querySelector('[data-audioarchive-icon-pause]');

			if (rowPlayer instanceof HTMLElement && ui instanceof HTMLElement && toggle instanceof HTMLButtonElement)
			{
				nativeAudio?.remove();
				rowPlayer.removeAttribute('data-audioarchive-custom-player');
				rowPlayer.dataset.audioarchivePlaylistRowPlayer = '';
				rowPlayer.classList.add('is-enhanced');
				rowPlayer.classList.toggle('is-playing', isPlaying);
				ui.hidden = false;
				toggle.removeAttribute('data-audioarchive-custom-toggle');
				toggle.dataset.action = 'play';
				toggle.dataset.index = String(index);
				toggle.disabled = item === null;
				toggle.setAttribute('aria-controls', audio instanceof HTMLAudioElement ? audio.id : '');
				toggle.setAttribute('aria-label', `${isPlaying ? pauseLabel : playLabel}: ${title}`);
				toggle.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
				toggle.title = `${isPlaying ? pauseLabel : playLabel}: ${title}`;
				toggle.dataset.playLabel = `${playLabel}: ${title}`;
				toggle.dataset.pauseLabel = `${pauseLabel}: ${title}`;

				if (playIcon instanceof HTMLElement)
				{
					playIcon.hidden = isPlaying;
				}

				if (pauseIcon instanceof HTMLElement)
				{
					pauseIcon.hidden = !isPlaying;
				}

				return rowPlayer;
			}
		}

		const fallback = document.createElement('button');
		fallback.type = 'button';
		fallback.className = 'audioarchive-custom-player-toggle';
		fallback.dataset.action = 'play';
		fallback.dataset.index = String(index);
		fallback.disabled = item === null;
		fallback.setAttribute('aria-controls', audio instanceof HTMLAudioElement ? audio.id : '');
		fallback.setAttribute('aria-label', `${isPlaying ? pauseLabel : playLabel}: ${title}`);
		fallback.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
		fallback.title = `${isPlaying ? pauseLabel : playLabel}: ${title}`;
		fallback.innerHTML = isPlaying
			? '<span aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6.5 5h4v14h-4zm7 0h4v14h-4z"/></svg></span>'
			: '<span aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 5.5v13l10-6.5z"/></svg></span>';
		return fallback;
	}

	function renderRows()
	{
		const playlist = getCurrentPlaylist();

		if (!(tbody instanceof HTMLElement) || !playlist)
		{
			return;
		}

		tbody.replaceChildren();
		const hasItems = playlist.items.length > 0;
		if (empty instanceof HTMLElement)
		{
			empty.hidden = hasItems;
		}
		if (tableWrapper instanceof HTMLElement)
		{
			tableWrapper.hidden = !hasItems;
		}

		playlist.items.forEach((entry, index) =>
		{
			const item = resolvedItems.get(entry.uuid) || null;
			const row = document.createElement('tr');
			row.dataset.audioarchivePlaylistItem = entry.uuid;
			row.classList.toggle('is-current', entry.uuid === currentUuid);
			row.classList.toggle('is-unavailable', item === null);

			const orderCell = document.createElement('td');
			orderCell.className = 'com-audioarchive-playlist-order-cell';
			orderCell.dataset.label = root.dataset.audioarchiveLabelPosition || 'Order';
			const orderControls = document.createElement('div');
			orderControls.className = 'com-audioarchive-playlist-order-controls';
			const up = document.createElement('button');
			up.type = 'button';
			up.className = 'btn btn-sm btn-outline-secondary';
			up.dataset.action = 'up';
			up.dataset.index = String(index);
			up.disabled = temporaryPlaylist !== null || index === 0;
			up.title = root.dataset.audioarchiveLabelMoveUp || 'Move up';
			up.setAttribute('aria-label', up.title);
			up.textContent = '↑';
			const down = document.createElement('button');
			down.type = 'button';
			down.className = 'btn btn-sm btn-outline-secondary';
			down.dataset.action = 'down';
			down.dataset.index = String(index);
			down.disabled = temporaryPlaylist !== null || index === playlist.items.length - 1;
			down.title = root.dataset.audioarchiveLabelMoveDown || 'Move down';
			down.setAttribute('aria-label', down.title);
			down.textContent = '↓';
			const number = document.createElement('span');
			number.textContent = String(index + 1);
			orderControls.append(up, down, number);
			orderCell.appendChild(orderControls);

			const playCell = document.createElement('td');
			playCell.className = 'com-audioarchive-play-cell';
			playCell.dataset.label = root.dataset.audioarchiveLabelPlay || 'Play';
			playCell.appendChild(createRowPlayerControl(item, entry, index));

			const titleCell = document.createElement('th');
			titleCell.scope = 'row';
			titleCell.className = 'com-audioarchive-title-cell';
			titleCell.dataset.label = root.dataset.audioarchiveLabelTitle || 'Title';
			if (item)
			{
				const link = document.createElement('a');
				link.className = 'com-audioarchive-title-link';
				link.dataset.audioarchiveDetailLink = '';
				link.href = item.detail_url;
				link.textContent = item.title;
				titleCell.appendChild(link);
			}
			else
			{
				const title = document.createElement('span');
				title.textContent = entry.title || root.dataset.audioarchiveLabelUnavailable || 'Unavailable clip';
				const unavailable = document.createElement('small');
				unavailable.className = 'com-audioarchive-playlist-unavailable';
				unavailable.textContent = root.dataset.audioarchiveLabelUnavailable || 'Unavailable clip';
				titleCell.append(title, unavailable);
			}

			const durationCell = document.createElement('td');
			durationCell.className = 'com-audioarchive-duration-cell';
			durationCell.textContent = item ? formatPlaylistDuration(item.duration_ms) : '—';

			const tagsCell = document.createElement('td');
			tagsCell.className = 'com-audioarchive-tags-cell';
			if (item && Array.isArray(item.tags) && item.tags.length > 0)
			{
				const list = document.createElement('ul');
				list.className = 'com-audioarchive-tag-list';
				for (const tag of item.tags)
				{
					const listItem = document.createElement('li');
					listItem.textContent = tag.title;
					list.appendChild(listItem);
				}
				tagsCell.appendChild(list);
			}
			else
			{
				tagsCell.textContent = '—';
			}

			const actionsCell = document.createElement('td');
			actionsCell.className = 'com-audioarchive-actions-cell';
			const actions = document.createElement('div');
			actions.className = 'com-audioarchive-inline-actions';
			if (item)
			{
				actions.appendChild(createPlaylistRowShareMenu(root, item));

				if (root.dataset.audioarchiveSoundboardEnabled === '1')
				{
					const soundboard = document.createElement('button');
					soundboard.type = 'button';
					soundboard.className = 'btn btn-sm btn-outline-secondary';
					soundboard.title = root.dataset.audioarchiveLabelAddSoundboard || 'Add to Sound Board';
					soundboard.setAttribute('aria-label', `${soundboard.title}: ${item.title}`);
					soundboard.innerHTML = `<span aria-hidden="true">▦</span><span>${root.dataset.audioarchiveLabelAddSoundboard || 'Add to Sound Board'}</span>`;
					soundboard.addEventListener('click', () => addClipToSoundboard({id: item.id, title: item.title}, root));
					actions.appendChild(soundboard);
				}
			}

			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'btn btn-sm btn-outline-danger';
			remove.dataset.action = 'remove';
			remove.dataset.index = String(index);
			remove.disabled = temporaryPlaylist !== null;
			remove.title = root.dataset.audioarchiveLabelRemove || 'Remove';
			remove.setAttribute('aria-label', `${remove.title}: ${item?.title || entry.title}`);
			remove.innerHTML = '<span class="icon-trash" aria-hidden="true"></span>';
			actions.appendChild(remove);
			actionsCell.appendChild(actions);
			row.append(orderCell, playCell, titleCell, durationCell, tagsCell, actionsCell);
			tbody.appendChild(row);
		});

		if (previousButton instanceof HTMLButtonElement)
		{
			previousButton.disabled = findPlayableIndex(currentIndex - 1, -1) < 0;
		}
		if (nextButton instanceof HTMLButtonElement)
		{
			nextButton.disabled = findPlayableIndex(currentIndex + 1, 1) < 0;
		}

		if (playerPosition)
		{
			playerPosition.textContent = currentIndex >= 0
				? `${currentIndex + 1} / ${playlist.items.length}`
				: `0 / ${playlist.items.length}`;
		}
	}

	const refreshMetadata = async (preserveCurrent = true) =>
	{
		const playlist = getCurrentPlaylist();
		const requestId = ++metadataRequest;
		resolvedItems = new Map();

		if (!playlist || playlist.items.length === 0)
		{
			setPlayerMetadata(null, -1, 0);
			renderRows();
			updateManager();
			return;
		}

		try
		{
			const body = new URLSearchParams();
			body.set('uuids', playlist.items.map((item) => item.uuid).join(','));
			const response = await fetch(root.dataset.audioarchiveItemsUrl || '', {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
				credentials: 'same-origin',
			});
			const payload = response.ok ? await response.json() : null;

			if (requestId !== metadataRequest)
			{
				return;
			}

			const items = payload?.success === true && payload.items && typeof payload.items === 'object'
				? payload.items
				: {};

			for (const [uuid, item] of Object.entries(items))
			{
				if (item && typeof item === 'object')
				{
					resolvedItems.set(uuid, item);
					const stored = playlist.items.find((entry) => entry.uuid === uuid);
					if (stored)
					{
						stored.id = Number.parseInt(item.id, 10) || stored.id;
						stored.title = String(item.title || stored.title).slice(0, 255);
					}
				}
			}

			if (!temporaryPlaylist)
			{
				saveStore();
			}
		}
		catch (error)
		{
			// Unresolved entries remain visible as unavailable clips.
		}

		updateManager();
		renderRows();
		const preservedIndex = preserveCurrent && currentUuid !== ''
			? playlist.items.findIndex((entry) => entry.uuid === currentUuid)
			: -1;
		const initialIndex = preservedIndex >= 0 && resolvedItems.has(currentUuid)
			? preservedIndex
			: findPlayableIndex(0, 1);

		if (initialIndex >= 0)
		{
			await loadIndex(initialIndex, false);
		}
		else
		{
			setPlayerMetadata(null, -1, playlist.items.length);
			renderRows();
		}
	};

	select?.addEventListener('change', () =>
	{
		if (!(select instanceof HTMLSelectElement) || temporaryPlaylist)
		{
			return;
		}

		store.selectedId = select.value;
		writePlaylistStore(store);
		currentIndex = -1;
		currentUuid = '';
		void refreshMetadata(false);
	});

	root.querySelector('[data-audioarchive-playlist-create]')?.addEventListener('click', () =>
	{
		const name = window.prompt(root.dataset.audioarchiveLabelNamePrompt || 'Playlist name:', fallbackName);

		if (name === null || name.trim() === '')
		{
			return;
		}

		if (temporaryPlaylist)
		{
			temporaryPlaylist = null;
			root.classList.remove('is-shared-playlist');
			if (sharedPanel instanceof HTMLElement)
			{
				sharedPanel.hidden = true;
			}
			leaveSharedUrl();
		}

		const playlist = createPlaylist(store, name);
		writePlaylistStore(store);
		recordPlaylistInteraction(
			root.dataset.audioarchiveInteractionUrl || '',
			root.dataset.audioarchiveInteractionToken || '',
			'audioarchive.playlist.created',
			{contextId: playlist.id, contextTitle: playlist.name}
		);
		currentIndex = -1;
		currentUuid = '';
		void refreshMetadata(false);
	});

	root.querySelector('[data-audioarchive-playlist-rename]')?.addEventListener('click', () =>
	{
		const playlist = getCurrentPlaylist();

		if (!playlist || temporaryPlaylist)
		{
			return;
		}

		const name = window.prompt(root.dataset.audioarchiveLabelRenamePrompt || 'Playlist name:', playlist.name);

		if (name === null || name.trim() === '')
		{
			return;
		}

		playlist.name = makeUniquePlaylistName(
			{playlists: store.playlists.filter((entry) => entry.id !== playlist.id)},
			name
		);
		playlist.modified = Date.now();
		writePlaylistStore(store);
		updateManager();
	});

	root.querySelector('[data-audioarchive-playlist-delete]')?.addEventListener('click', () =>
	{
		const playlist = getCurrentPlaylist();

		if (!playlist || temporaryPlaylist || !window.confirm(root.dataset.audioarchiveLabelDeleteConfirm || 'Delete this playlist?'))
		{
			return;
		}

		store.playlists = store.playlists.filter((entry) => entry.id !== playlist.id);
		if (store.playlists.length === 0)
		{
			createPlaylist(store, fallbackName);
		}
		store.selectedId = store.playlists[0].id;
		writePlaylistStore(store);
		recordPlaylistInteraction(
			root.dataset.audioarchiveInteractionUrl || '',
			root.dataset.audioarchiveInteractionToken || '',
			'audioarchive.playlist.deleted',
			{contextId: playlist.id, contextTitle: playlist.name}
		);
		currentIndex = -1;
		currentUuid = '';
		void refreshMetadata(false);
	});

	tbody?.addEventListener('click', (event) =>
	{
		if (!(event.target instanceof Element))
		{
			return;
		}

		const button = event.target.closest('[data-action]');

		if (!(button instanceof HTMLButtonElement))
		{
			return;
		}

		const index = Number.parseInt(button.dataset.index || '-1', 10);

		if (button.dataset.action === 'up')
		{
			moveItem(index, -1);
		}
		else if (button.dataset.action === 'down')
		{
			moveItem(index, 1);
		}
		else if (button.dataset.action === 'remove')
		{
			removeItem(index);
		}
		else if (button.dataset.action === 'play')
		{
			if (index === currentIndex && audio instanceof HTMLAudioElement)
			{
				if (!audio.paused)
				{
					audio.pause();
				}
				else
				{
					void audio.play();
				}
			}
			else
			{
				void loadIndex(index, true);
			}
		}
	});

	previousButton?.addEventListener('click', () =>
	{
		const index = findPlayableIndex(currentIndex - 1, -1);
		if (index >= 0)
		{
			void loadIndex(index, true);
		}
	});

	nextButton?.addEventListener('click', () =>
	{
		const index = findPlayableIndex(currentIndex + 1, 1);
		if (index >= 0)
		{
			void loadIndex(index, true);
		}
	});

	if (audio instanceof HTMLAudioElement)
	{
		audio.addEventListener('play', () =>
		{
			if (currentUuid !== '' && startedUuid !== currentUuid)
			{
				startedUuid = currentUuid;
				const playlist = getCurrentPlaylist();
				const item = resolvedItems.get(currentUuid);
				recordPlaylistInteraction(
					root.dataset.audioarchiveInteractionUrl || '',
					root.dataset.audioarchiveInteractionToken || '',
					'audioarchive.playlist.play',
					{clipId: item?.id || 0, contextId: playlist?.id || '', contextTitle: playlist?.name || ''}
				);
			}
			renderRows();
		});
		audio.addEventListener('pause', renderRows);
		audio.addEventListener('ended', () =>
		{
			const index = findPlayableIndex(currentIndex + 1, 1);
			if (index >= 0)
			{
				void loadIndex(index, true);
			}
			else
			{
				renderRows();
			}
		});
	}

	root.querySelector('[data-audioarchive-playlist-save-shared]')?.addEventListener('click', () =>
	{
		if (!temporaryPlaylist)
		{
			return;
		}

		const saved = normalisePlaylist(
			{
				...temporaryPlaylist,
				id: createLocalId(),
				name: makeUniquePlaylistName(store, temporaryPlaylist.name),
				created: Date.now(),
				modified: Date.now(),
			},
			fallbackName
		);

		if (!saved)
		{
			return;
		}

		store.playlists.push(saved);
		store.selectedId = saved.id;
		writePlaylistStore(store);
		recordPlaylistInteraction(
			root.dataset.audioarchiveInteractionUrl || '',
			root.dataset.audioarchiveInteractionToken || '',
			'audioarchive.playlist.saved_shared',
			{contextId: saved.id, contextTitle: saved.name}
		);
		temporaryPlaylist = null;
		root.classList.remove('is-shared-playlist');
		if (sharedPanel instanceof HTMLElement)
		{
			sharedPanel.hidden = true;
		}
		leaveSharedUrl();
		setStatus(root.dataset.audioarchiveLabelSavedShared || 'Shared playlist saved.');
		void refreshMetadata(true);
	});

	root.querySelector('[data-audioarchive-playlist-export]')?.addEventListener('click', () =>
	{
		const playlist = getCurrentPlaylist();

		if (!playlist)
		{
			return;
		}

		const payload = {
			version: PLAYLIST_STORAGE_VERSION,
			playlist: {
				name: playlist.name,
				items: playlist.items.map((item) => ({uuid: item.uuid, title: item.title})),
			},
		};
		const blob = new Blob([JSON.stringify(payload, null, 2)], {type: 'application/json'});
		const link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'audioarchive-playlist.json';
		link.click();
		window.setTimeout(() => URL.revokeObjectURL(link.href), 0);
	});

	const fileInput = root.querySelector('[data-audioarchive-playlist-file]');
	root.querySelector('[data-audioarchive-playlist-import]')?.addEventListener('click', () => fileInput?.click());
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
			const imported = normalisePlaylist(
				{
					id: createLocalId(),
					name: data?.playlist?.name ?? data?.name,
					items: data?.playlist?.items ?? data?.items,
					created: Date.now(),
					modified: Date.now(),
				},
				fallbackName
			);

			if (!imported || !Array.isArray(data?.playlist?.items ?? data?.items))
			{
				throw new Error('Invalid playlist');
			}

			imported.name = makeUniquePlaylistName(store, imported.name);
			store.playlists.push(imported);
			store.selectedId = imported.id;
			writePlaylistStore(store);
			setStatus(root.dataset.audioarchiveLabelImported || 'Playlist imported.');
			currentIndex = -1;
			currentUuid = '';
			await refreshMetadata(false);
		}
		catch (error)
		{
			setStatus(root.dataset.audioarchiveLabelInvalid || 'Invalid playlist file.');
		}
		finally
		{
			fileInput.value = '';
		}
	});

	const shareMenu = root.querySelector('[data-audioarchive-playlist-share-menu]');
	const shareToggle = shareMenu?.querySelector('[data-audioarchive-playlist-share-toggle]');
	const sharePopover = shareMenu?.querySelector('[data-audioarchive-playlist-share-popover]');
	const shareCopy = shareMenu?.querySelector('[data-audioarchive-playlist-share-copy]');
	const shareNative = shareMenu?.querySelector('[data-audioarchive-playlist-share-native]');

	if (shareNative instanceof HTMLButtonElement)
	{
		shareNative.disabled = typeof navigator.share !== 'function';
	}

	const getShareUrl = () =>
	{
		const playlist = getCurrentPlaylist();
		const payload = {
			version: PLAYLIST_STORAGE_VERSION,
			name: playlist?.name || fallbackName,
			items: (playlist?.items || []).map((item) => ({uuid: item.uuid, title: item.title})),
		};
		return `${root.dataset.audioarchiveCanonicalUrl || window.location.href.split('#')[0]}#playlist=${encodePlaylistPayload(payload)}`;
	};

	shareToggle?.addEventListener('click', () =>
	{
		if (!(shareToggle instanceof HTMLButtonElement) || !(sharePopover instanceof HTMLElement))
		{
			return;
		}

		const open = shareToggle.getAttribute('aria-expanded') !== 'true';
		closePlaylistPopovers(open ? shareMenu : null);
		shareToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		sharePopover.hidden = !open;
		if (open)
		{
			positionPlaylistPopover(shareToggle, sharePopover);
		}
	});

	shareCopy?.addEventListener('click', async () =>
	{
		const playlist = getCurrentPlaylist();
		if (await playlistCopyText(getShareUrl()))
		{
			setStatus(root.dataset.audioarchiveLabelCopied || 'Link copied.');
			recordPlaylistInteraction(
				root.dataset.audioarchiveInteractionUrl || '',
				root.dataset.audioarchiveInteractionToken || '',
				'audioarchive.playlist.shared',
				{contextId: playlist?.id || '', contextTitle: playlist?.name || ''}
			);
		}
		closePlaylistPopovers();
	});

	shareNative?.addEventListener('click', async () =>
	{
		const playlist = getCurrentPlaylist();
		if (await playlistNativeShare(playlist?.name || document.title, getShareUrl()))
		{
			recordPlaylistInteraction(
				root.dataset.audioarchiveInteractionUrl || '',
				root.dataset.audioarchiveInteractionToken || '',
				'audioarchive.playlist.shared',
				{contextId: playlist?.id || '', contextTitle: playlist?.name || ''}
			);
		}
		closePlaylistPopovers();
	});

	window.addEventListener('storage', (event) =>
	{
		if (event.key !== PLAYLIST_STORAGE_KEY || temporaryPlaylist)
		{
			return;
		}

		store = readPlaylistStore(fallbackName, true);
		void refreshMetadata(true);
	});

	updateManager();
	void refreshMetadata(false);
}

/**
 * Initialise playlist-related frontend features.
 *
 * @returns {void}
 */
function initialiseAudioArchivePlaylists()
{
	initialiseAddToMenus();
	initialisePlaylistPage();

	document.addEventListener('click', (event) =>
	{
		if (!(event.target instanceof Element))
		{
			return;
		}

		if (!event.target.closest('[data-audioarchive-add-to-menu], [data-audioarchive-playlist-row-share-menu], [data-audioarchive-playlist-share-menu]'))
		{
			closePlaylistPopovers();
		}
	});
	window.addEventListener('resize', () => closePlaylistPopovers());
	window.addEventListener('scroll', () => closePlaylistPopovers(), true);
}

document.addEventListener('DOMContentLoaded', initialiseAudioArchivePlaylists);
