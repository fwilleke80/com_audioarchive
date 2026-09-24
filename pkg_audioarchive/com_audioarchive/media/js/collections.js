/** @brief Shared asynchronous account adapter; browser copies are never silently imported or overwritten. */
const PLAYLIST_KEY = 'com_audioarchive.playlists.v1';
const RECORDINGS_KEY = 'com_audioarchive.soundboard.recordings.v1';
const BOARD_KEY = 'com_audioarchive.soundboard.v1';
const clone = (value) => JSON.parse(JSON.stringify(value));
let state = {backend: 'disabled', playlists: [], boards: [], revision: 0, userId: 0};
let token = '';
let labels = {};
let readyPromise;
let pending = 0;
let unsavedRecordings = false;
let tail = Promise.resolve();
let shared = null;
let statusNode;
let activeBoard = '';
let selectedPlaylist = '';
const observers = new Set();

/** @brief Refresh mounted controls after acknowledged state and caller selections settle. */
function refreshControls()
{
	observers.forEach((refresh) => window.setTimeout(refresh, 0));
}

/** @brief Read optional browser/session data without making storage availability mandatory. */
function read(key, fallback, session = false)
{
	try
	{
		return JSON.parse((session ? sessionStorage : localStorage).getItem(key)) ?? fallback;
	}
	catch
	{
		return fallback;
	}
}

/** @brief Store optional session navigation state. */
function session(key, value)
{
	try
	{
		sessionStorage.setItem(key, JSON.stringify(value));
	}
	catch
	{
		// Navigation still works when browser persistence is blocked.
	}
}

/** @brief Build a same-origin Joomla endpoint, including installations below the domain root. */
function endpoint(action)
{
	const base = window.Joomla?.getOptions('system.paths')?.root || '';
	const url = new URL(`${base}/index.php`, window.location.origin);
	url.searchParams.set('option', 'com_audioarchive');
	url.searchParams.set('task', `collection.${action}`);
	url.searchParams.set('format', 'json');
	return url;
}

/** @brief Surface saving/conflict/errors instead of silently falling back to browser storage. */
function notice(message, error = false)
{
	if (!statusNode)
	{
		statusNode = document.createElement('p');
		statusNode.className = 'alert alert-info my-2';
		statusNode.setAttribute('role', 'status');
		(document.querySelector('.com-audioarchive') || document.querySelector('main') || document.body).prepend(statusNode);
	}
	statusNode.textContent = message;
	statusNode.classList.toggle('alert-danger', error);
	statusNode.hidden = !message;
}

/** @brief Perform a typed request; HTTP failures and expired login are always errors. */
async function request(action, payload = null, shareToken = '')
{
	const url = endpoint(action);
	if (shareToken)
	{
		url.searchParams.set('share', shareToken);
	}
	const options = {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}};
	if (payload !== null)
	{
		options.method = 'POST';
		options.body = new URLSearchParams({payload: JSON.stringify(payload), [token]: '1'});
	}
	const response = await fetch(url, options);
	let json;
	try
	{
		json = await response.json();
	}
	catch
	{
		throw new Error(labels.error || 'Unable to load collections. Sign in again or reload the page.');
	}
	if (!response.ok || json.success !== true)
	{
		throw new Error(json.message || labels.error || 'Collection request failed.');
	}
	return json.data;
}

/** @brief Apply an acknowledged server snapshot and keep per-tab selections account-scoped. */
function accept(data)
{
	state = {...state, ...data};
	refreshControls();
	if (!state.boards.some((board) => board.id === activeBoard))
	{
		activeBoard = state.defaultBoard || state.boards[0]?.id || '';
	}
	if (!state.playlists.some((playlist) => playlist.id === selectedPlaylist))
	{
		selectedPlaylist = state.playlists[0]?.id || '';
	}
}

/** @brief Queue mutations with the latest acknowledged revision; another tab causes a conflict, never an overwrite. */
function mutate(action, payload)
{
	pending++;
	const run = async () =>
	{
		notice(labels.saving || 'Saving…');
		try
		{
			const result = await request(action, {...payload, revision: state.revision});
			accept(result.state);
			notice('');
			return result;
		}
		catch (error)
		{
			notice(error.message, true);
			throw error;
		}
		finally
		{
			pending--;
		}
	};
	const operation = tail.then(run);
	tail = operation.catch(() => {});
	return operation;
}

/** @brief Load collections once before installing their controls. No browser data is written here. */
async function initialise()
{
	try
	{
		const data = await request('state');
		token = data.token;
		labels = data.labels || {};
		activeBoard = read(`com_audioarchive.activeBoard.${data.userId}`, '', true);
		selectedPlaylist = read(`com_audioarchive.activePlaylist.${data.userId}`, '', true);
		accept(data);
		const shareToken = new URL(window.location.href).searchParams.get('aa_share');
		if (shareToken)
		{
			shared = await request('shared', null, shareToken);
			if (shared.unavailable)
			{
				notice(`${labels.unavailable} (${shared.unavailable})`);
			}
		}
	}
	catch (error)
	{
		notice(error.message, true);
		// Fail closed, including a revoked share link: never open the owner's board in its place.
		if (new URL(window.location.href).searchParams.has('aa_share'))
		{
			shared = {kind: document.querySelector('[data-audioarchive-playlists]') ? 'playlist' : 'soundboard', id: '', name: labels.unavailable || 'Unavailable', items: []};
		}
	}
	window.addEventListener('beforeunload', (event) =>
	{
		if (pending || unsavedRecordings)
		{
			event.preventDefault();
			event.returnValue = '';
		}
	});
	// Prevent overlapping user mutations while allowing an in-flight operation to finish.
	document.addEventListener('click', (event) =>
	{
		if (pending && !event.target.closest('[data-audioarchive-custom-toggle], [data-audioarchive-recording-record], [data-audioarchive-recording-play], [data-audioarchive-soundboard-trigger], [data-action="play"]') && event.target.closest('[data-audioarchive-playlists], [data-audioarchive-soundboard], [data-audioarchive-add-to-menu], [data-audioarchive-add-all-menu], [data-audioarchive-soundboard-add]'))
		{
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true);
}

/** @brief Public adapter shared by playlist, Sound Board and archive add-to controls. */
export const Collections = {
	ready()
	{
		return readyPromise ||= initialise();
	},
	get backend()
	{
		return state.backend;
	},
	get shared()
	{
		return shared;
	},
	get boardId()
	{
		return activeBoard;
	},
	get sharing()
	{
		return state.sharing !== false;
	},
	get userId()
	{
		return state.userId;
	},
	/** @brief Save an explicitly loaded recording board as a separate named account board. */
	async newBoard(name, items)
	{
		const result = await mutate('board', {board: {id: crypto.randomUUID(), name, items: clone(items)}});
		activeBoard = result.id;
		return result.id;
	},

	/** @brief Read a copy of already-loaded server data; recording/rating keys remain browser-local. */
	read(key, fallback)
	{
		if (state.backend === 'disabled' && [PLAYLIST_KEY, BOARD_KEY, RECORDINGS_KEY].includes(key))
		{
			return clone(fallback);
		}
		if (state.backend !== 'server')
		{
			return read(key, fallback);
		}
		if (key === RECORDINGS_KEY)
		{
			return clone(state.recordings || []);
		}
		if (key === PLAYLIST_KEY)
		{
			return clone({version: 1, selectedId: selectedPlaylist, playlists: state.playlists});
		}
		if (key === BOARD_KEY)
		{
			return clone(state.boards.find((board) => board.id === activeBoard)?.items || []);
		}
		return read(key, fallback);
	},
	/** @brief Persist explicitly and roll the caller's working copy back on rejection. */
	async write(key, value)
	{
		const previous = this.read(key, key === BOARD_KEY ? [] : {});
		try
		{
			if (state.backend === 'disabled')
			{
				throw new Error(labels.disabled || 'Personal collections are disabled.');
			}
			if (state.backend !== 'server')
			{
				localStorage.setItem(key, JSON.stringify(value));
				return true;
			}
			if (key === RECORDINGS_KEY)
			{
				await mutate('recordings', {recordings: clone(value)});
				unsavedRecordings = false;
			}
			else if (key === PLAYLIST_KEY)
			{
				selectedPlaylist = value.selectedId;
				// Selecting a playlist or refreshing its titles does not change server membership.
				const signature = (lists) => JSON.stringify(lists.map((item) => [item.id, item.name, item.items.map((clip) => clip.uuid)]));
				if (signature(value.playlists) !== signature(state.playlists))
				{
					await mutate('playlists', {playlists: clone(value.playlists)});
				}
				session(`com_audioarchive.activePlaylist.${state.userId}`, selectedPlaylist);
			}
			else if (key === BOARD_KEY)
			{
				const board = state.boards.find((item) => item.id === activeBoard);
				const result = await mutate('board', {board: {id: board?.id || crypto.randomUUID(), name: board?.name || labels.default_name || 'Default', items: clone(value)}});
				activeBoard = result.id;
				session(`com_audioarchive.activeBoard.${state.userId}`, activeBoard);
			}
			const acknowledged = this.read(key, previous);
			if (Array.isArray(value))
			{
				value.splice(0, value.length, ...acknowledged);
			}
			else
			{
				Object.assign(value, acknowledged);
			}
			refreshControls();
			return true;
		}
		catch (error)
		{
			if (key === RECORDINGS_KEY) unsavedRecordings = true;
			if (Array.isArray(value) && key !== RECORDINGS_KEY)
			{
				value.splice(0, value.length, ...previous);
			}
			else if (key !== RECORDINGS_KEY)
			{
				Object.keys(value).forEach((key) => delete value[key]);
				Object.assign(value, previous);
			}
			notice(error.message || labels.error, true);
			return false;
		}
	},
	/** @brief Create/reuse a short account share link; opening it is always temporary. */
	async shareUrl(id, canonical)
	{
		try
		{
			const result = await mutate('share', {id});
			const url = new URL(canonical, window.location.href);
			url.hash = '';
			url.searchParams.set('aa_share', result.token);
			return url.toString();
		}
		catch
		{
			return '';
		}
	},
	/** @brief Mount backend feedback, revocation and explicit browser import beside existing controls. */
	mount(root, kind, selected, changed, canChange = () => true)
	{
		if (!root)
		{
			return;
		}
		const panel = document.createElement('div');
		panel.className = 'border rounded p-3 mb-3 d-flex flex-wrap gap-2 align-items-center';
		root.prepend(panel);
		root.querySelectorAll('.com-audioarchive-soundboard-note, .com-audioarchive-playlists-note').forEach((node) =>
		{
			node.hidden = state.backend === 'server';
			node.textContent = state.backend === 'disabled' ? labels.disabled : labels[kind === 'playlist' ? 'browser_playlists' : 'browser_board'];
		});
		const button = (key, operation) =>
		{
			const control = document.createElement('button');
			control.type = 'button';
			control.className = 'btn btn-sm btn-outline-secondary';
			control.textContent = labels[key] || key;
			control.addEventListener('click', async () =>
			{
				if (!canChange())
				{
					return;
				}
				control.disabled = true;
				try
				{
					await operation();
				}
				catch (error)
				{
					notice(error.message, true);
				}
				finally
				{
					control.disabled = false;
					refreshControls();
				}
			});
			panel.append(control);
			return control;
		};
		if (state.backend !== 'server')
		{
			panel.remove();
			return;
		}
		let selector;
		let temporaryBoardLabel = '';
		const render = () =>
		{
			session(`com_audioarchive.activeBoard.${state.userId}`, activeBoard);
			if (selector)
			{
				selector.replaceChildren(...state.boards.map((board) => new Option(board.name + (board.id === state.defaultBoard ? ' ★' : ''), board.id)));
				selector.value = activeBoard;
				if (temporaryBoardLabel)
				{
					selector.add(new Option(temporaryBoardLabel, '__temporary__'));
					selector.value = '__temporary__';
				}
			}
		};
		root.addEventListener('audioarchive:temporary-board', (event) =>
		{
			temporaryBoardLabel = String(event.detail?.label || '');
			render();
		});
		if (kind === 'soundboard')
		{
			selector = document.createElement('select');
			selector.className = 'form-select w-auto';
			selector.setAttribute('aria-label', labels.name || 'Sound Board');
			selector.addEventListener('change', () =>
			{
				if (!canChange() || pending)
				{
					selector.value = activeBoard;
					return;
				}
				if (selector.value === '__temporary__') return;
				temporaryBoardLabel = '';
				activeBoard = selector.value;
				session(`com_audioarchive.activeBoard.${state.userId}`, activeBoard);
				changed();
			});
			panel.append(selector);
			button('new', async () =>
			{
				const name = prompt(labels.name, labels.default_name);
				if (!name?.trim()) return;
				const result = await mutate('board', {board: {id: crypto.randomUUID(), name, items: []}});
				activeBoard = result.id;
				render();
				changed();
			});
			button('rename', async () =>
			{
				const board = state.boards.find((item) => item.id === activeBoard);
				if (!board) return;
				const name = prompt(labels.name, board.name);
				if (!name?.trim()) return;
				await mutate('board', {board: {...board, name}});
				render();
			});
			button('delete', async () =>
			{
				if (!activeBoard || !confirm(labels.confirm)) return;
				await mutate('deleteBoard', {id: activeBoard});
				render();
				changed();
			});
			button('default', async () =>
			{
				if (!activeBoard) return;
				await mutate('defaultBoard', {id: activeBoard});
				render();
			});
			render();
		}
		let revoke;
		if (state.sharing)
		{
			revoke = button('revoke', async () =>
			{
				const id = selected();
				if (!id || !confirm(labels.confirm)) return;
				await mutate('revoke', {id});
				notice(labels.revoked);
			});
		}
		/** @brief Only the selected owned collection can expose its active share link. */
		const refresh = () =>
		{
			render();
			const collection = (kind === 'playlist' ? state.playlists : state.boards).find((item) => item.id === selected());
			if (revoke)
			{
				revoke.hidden = !collection?.shared;
			}
			panel.classList.toggle('d-none', !Array.from(panel.children).some((child) => !child.hidden));
		};
		observers.add(refresh);
		root.addEventListener('change', refreshControls);
		root.addEventListener('click', refreshControls);
		const browser = read(kind === 'playlist' ? PLAYLIST_KEY : BOARD_KEY, null);
		const candidates = kind === 'playlist' ? (browser?.playlists || []) : (Array.isArray(browser) && browser.some(Boolean) ? [{name: labels.default_name, items: browser}] : []);
		if (candidates.length)
		{
			const message = document.createElement('span');
			message.textContent = labels.import_found;
			const importPanel = document.createElement('div');
			importPanel.className = 'w-100 d-flex flex-wrap align-items-center gap-2 mt-2';
			importPanel.append(message);
			const importButton = button('import', async () =>
			{
				let retained = false;
				try
				{
					for (const candidate of [...candidates])
					{
						const result = await mutate(kind === 'playlist' ? 'importPlaylist' : 'importBoard', {collection: candidate});
						if (result.skipped)
						{
							retained = true;
							continue;
						}
						// Compare against fresh storage so another tab's edits are never deleted.
						const key = kind === 'playlist' ? PLAYLIST_KEY : BOARD_KEY;
						const current = read(key, null);
						if (kind === 'playlist' && Array.isArray(current?.playlists))
						{
							const index = current.playlists.findIndex((item) => JSON.stringify(item) === JSON.stringify(candidate));
							if (index < 0)
							{
								retained = true;
								continue;
							}
							current.playlists.splice(index, 1);
							if (!current.playlists.some((item) => item.id === current.selectedId))
							{
								current.selectedId = current.playlists[0]?.id || '';
							}
							localStorage.setItem(key, JSON.stringify(current));
						}
						else if (kind === 'soundboard' && JSON.stringify(current) === JSON.stringify(candidate.items))
						{
							localStorage.removeItem(key);
							activeBoard = result.id;
						}
						else
						{
							retained = true;
							continue;
						}
						candidates.splice(candidates.indexOf(candidate), 1);
					}
					notice(retained ? labels.import_retained : labels.import_done, retained);
				}
				finally
				{
					if (!candidates.length)
					{
						importPanel.remove();
					}
					render();
					changed();
					refresh();
				}
			});
			importPanel.append(importButton);
			panel.append(importPanel);
		}
		refresh();
	},
};
