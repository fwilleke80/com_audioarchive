/**
 * @brief Manage all shared Audio Archive player presentations and legacy play buttons.
 */
const initialiseAudioArchivePlayers = () =>
{
	let activeAudio = null;
	let activeButton = null;
	let activeCustomPlayer = null;
	const countedClipIds = new Set();
	const animationFrames = new WeakMap();
	const waveformStates = new WeakMap();

	const getArchiveRoot = (element) => element.closest('.com-audioarchive');

	const announce = (element, type, title = '') =>
	{
		const root = getArchiveRoot(element);
		const status = root?.querySelector('[data-audioarchive-status]');

		if (!root || !status)
		{
			return;
		}

		const template = root.dataset[`audioarchiveStatus${type}`] || '';
		status.textContent = template.replace('%s', title);
	};

	const recordPlay = (element, clipId) =>
	{
		const id = Number.parseInt(String(clipId || ''), 10);
		const root = getArchiveRoot(element);
		const url = root?.dataset.audioarchivePlayCountUrl || '';
		const tokenName = root?.dataset.audioarchiveTokenName || '';

		if (!Number.isInteger(id) || id <= 0 || url === '' || tokenName === '' || countedClipIds.has(id))
		{
			return;
		}

		countedClipIds.add(id);
		const body = new URLSearchParams();
		body.set('id', String(id));
		body.set(tokenName, '1');

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
			// Counting is informational and must never interrupt playback.
		});
	};

	const formatTime = (seconds) =>
	{
		if (!Number.isFinite(seconds) || seconds < 0)
		{
			return '0:00';
		}

		const rounded = Math.floor(seconds);
		const hours = Math.floor(rounded / 3600);
		const minutes = Math.floor((rounded % 3600) / 60);
		const remainingSeconds = rounded % 60;

		if (hours > 0)
		{
			return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
		}

		return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
	};

	const getProgress = (audio) => Number.isFinite(audio.duration) && audio.duration > 0
		? Math.min(1, Math.max(0, audio.currentTime / audio.duration))
		: 0;

	const setButtonState = (button, playing) =>
	{
		const playLabel = button.dataset.playLabel || button.getAttribute('aria-label') || '';
		const pauseLabel = button.dataset.pauseLabel || playLabel;
		button.classList.toggle('is-playing', playing);
		button.setAttribute('aria-pressed', playing ? 'true' : 'false');
		button.setAttribute('aria-label', playing ? pauseLabel : playLabel);
		button.title = playing ? pauseLabel : playLabel;
		const icon = button.querySelector('[data-audioarchive-icon]');

		if (icon instanceof HTMLElement)
		{
			icon.textContent = playing ? '❚❚' : '▶';
		}
	};

	const setProgress = (button, audio) =>
	{
		button.style.setProperty('--audioarchive-progress', `${getProgress(audio) * 360}deg`);
	};

	const getCustomPlayerAudio = (player) => player.querySelector('[data-audioarchive-custom-audio]');

	const setCustomPlayerState = (player, playing) =>
	{
		const button = player.querySelector('[data-audioarchive-custom-toggle]');
		const playIcon = player.querySelector('[data-audioarchive-icon-play]');
		const pauseIcon = player.querySelector('[data-audioarchive-icon-pause]');

		if (!(button instanceof HTMLButtonElement))
		{
			return;
		}

		const playLabel = button.dataset.playLabel || button.getAttribute('aria-label') || '';
		const pauseLabel = button.dataset.pauseLabel || playLabel;
		player.classList.toggle('is-playing', playing);
		button.setAttribute('aria-pressed', playing ? 'true' : 'false');
		button.setAttribute('aria-label', playing ? pauseLabel : playLabel);
		button.title = playing ? pauseLabel : playLabel;

		if (playIcon instanceof HTMLElement)
		{
			playIcon.hidden = playing;
		}

		if (pauseIcon instanceof HTMLElement)
		{
			pauseIcon.hidden = !playing;
		}
	};

	const drawPeakLayer = (canvas, peaks, color, width, height, ratio) =>
	{
		canvas.width = Math.max(1, Math.round(width * ratio));
		canvas.height = Math.max(1, Math.round(height * ratio));
		const context = canvas.getContext('2d');

		if (!context)
		{
			return;
		}

		context.setTransform(ratio, 0, 0, ratio, 0, 0);
		context.clearRect(0, 0, width, height);
		const centre = height / 2;
		const amplitude = Math.max(1, centre - 3);
		context.strokeStyle = color;
		context.lineWidth = Math.max(1, width / peaks.length * 0.72);
		context.beginPath();

		peaks.forEach((pair, index) =>
		{
			const minimum = Number(pair[0]) / 32768;
			const maximum = Number(pair[1]) / 32768;
			const x = ((index + 0.5) / peaks.length) * width;
			context.moveTo(x, centre - maximum * amplitude);
			context.lineTo(x, centre - minimum * amplitude);
		});

		context.stroke();
	};

	const prepareWaveformLayers = (player, state) =>
	{
		const canvas = state.canvas;
		const bounds = canvas.getBoundingClientRect();
		const width = Math.max(1, Math.round(bounds.width));
		const height = Math.max(56, Math.round(bounds.height));
		const ratio = Math.max(1, window.devicePixelRatio || 1);

		if (state.width === width && state.height === height && state.ratio === ratio)
		{
			return;
		}

		state.width = width;
		state.height = height;
		state.ratio = ratio;
		canvas.width = Math.max(1, Math.round(width * ratio));
		canvas.height = Math.max(1, Math.round(height * ratio));
		canvas.style.width = `${width}px`;
		canvas.style.height = `${height}px`;
		const styles = getComputedStyle(player);
		const unplayed = styles.getPropertyValue('--audioarchive-waveform-unplayed').trim() || '#6c757d';
		const played = styles.getPropertyValue('--audioarchive-waveform-played').trim() || '#0d6efd';
		drawPeakLayer(state.unplayedLayer, state.peaks, unplayed, width, height, ratio);
		drawPeakLayer(state.playedLayer, state.peaks, played, width, height, ratio);
	};

	const drawPlayerWaveform = (player, audio) =>
	{
		const waveform = player.querySelector('[data-audioarchive-player-waveform]');
		const state = waveform instanceof HTMLElement ? waveformStates.get(waveform) : null;

		if (!state)
		{
			return;
		}

		prepareWaveformLayers(player, state);
		const context = state.canvas.getContext('2d');

		if (!context)
		{
			return;
		}

		const progress = getProgress(audio);
		const progressX = Math.round(progress * state.canvas.width);
		context.setTransform(1, 0, 0, 1, 0, 0);
		context.clearRect(0, 0, state.canvas.width, state.canvas.height);
		context.drawImage(state.unplayedLayer, 0, 0);

		if (progressX > 0)
		{
			context.save();
			context.beginPath();
			context.rect(0, 0, progressX, state.canvas.height);
			context.clip();
			context.drawImage(state.playedLayer, 0, 0);
			context.restore();
		}

		if (progress > 0 && progress < 1)
		{
			const styles = getComputedStyle(player);
			context.strokeStyle = styles.getPropertyValue('--audioarchive-waveform-played').trim() || '#0d6efd';
			context.lineWidth = Math.max(1, state.ratio);
			context.beginPath();
			context.moveTo(progressX + 0.5, 0);
			context.lineTo(progressX + 0.5, state.canvas.height);
			context.stroke();
		}
	};

	const initialisePlayerWaveform = (player, audio) =>
	{
		const waveform = player.querySelector('[data-audioarchive-player-waveform]');

		if (!(waveform instanceof HTMLElement))
		{
			return;
		}

		const canvas = waveform.querySelector('canvas');
		const status = waveform.querySelector('[data-audioarchive-waveform-status]');
		const url = waveform.dataset.waveformUrl || '';

		if (!(canvas instanceof HTMLCanvasElement) || url === '')
		{
			waveform.hidden = true;
			player.classList.remove('has-waveform');
			player.classList.add('no-waveform');
			return;
		}

		fetch(url, {
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
			},
		})
			.then((response) =>
			{
				if (!response.ok)
				{
					throw new Error(`HTTP ${response.status}`);
				}

				return response.json();
			})
			.then((data) =>
			{
				if (data?.dataFormat !== 'json-peaks-v1' || !Array.isArray(data.peaks))
				{
					throw new Error('Unsupported waveform data.');
				}

				const peaks = data.peaks.filter((pair) => Array.isArray(pair) && pair.length >= 2);

				if (peaks.length === 0)
				{
					throw new Error('Waveform data is empty.');
				}

				waveformStates.set(waveform, {
					canvas,
					peaks,
					unplayedLayer: document.createElement('canvas'),
					playedLayer: document.createElement('canvas'),
					width: 0,
					height: 0,
					ratio: 0,
				});

				if (status instanceof HTMLElement)
				{
					status.hidden = true;
				}

				drawPlayerWaveform(player, audio);

				if ('ResizeObserver' in window)
				{
					new ResizeObserver(() => drawPlayerWaveform(player, audio)).observe(waveform);
				}
				else
				{
					window.addEventListener('resize', () => drawPlayerWaveform(player, audio));
				}

				canvas.addEventListener('click', (event) =>
				{
					if (!Number.isFinite(audio.duration) || audio.duration <= 0)
					{
						return;
					}

					const bounds = canvas.getBoundingClientRect();
					const progress = bounds.width > 0
						? Math.min(1, Math.max(0, (event.clientX - bounds.left) / bounds.width))
						: 0;
					audio.currentTime = progress * audio.duration;
					drawPlayerWaveform(player, audio);
				});
			})
			.catch(() =>
			{
				waveform.hidden = true;
				player.classList.remove('has-waveform');
				player.classList.add('no-waveform');
			});
	};

	const updateCustomPlayerProgress = (player, audio) =>
	{
		const seek = player.querySelector('[data-audioarchive-custom-seek]');
		const currentTime = player.querySelector('[data-audioarchive-current-time]');
		const duration = player.querySelector('[data-audioarchive-duration]');
		const hasDuration = Number.isFinite(audio.duration) && audio.duration > 0;
		const progress = getProgress(audio);

		player.style.setProperty('--audioarchive-player-progress', `${progress * 100}%`);

		if (seek instanceof HTMLInputElement)
		{
			seek.disabled = !hasDuration;
			seek.value = String(Math.round(progress * 1000));
			seek.setAttribute('aria-valuetext', `${formatTime(audio.currentTime)} / ${hasDuration ? formatTime(audio.duration) : '0:00'}`);
		}

		if (currentTime instanceof HTMLElement)
		{
			currentTime.textContent = formatTime(audio.currentTime);
		}

		if (duration instanceof HTMLElement)
		{
			duration.textContent = hasDuration ? formatTime(audio.duration) : '0:00';
		}

		drawPlayerWaveform(player, audio);
	};

	const stopProgressAnimation = (player) =>
	{
		const frame = animationFrames.get(player);

		if (frame !== undefined)
		{
			cancelAnimationFrame(frame);
			animationFrames.delete(player);
		}
	};

	const startProgressAnimation = (player, audio) =>
	{
		stopProgressAnimation(player);
		const step = () =>
		{
			updateCustomPlayerProgress(player, audio);

			if (!audio.paused && !audio.ended)
			{
				animationFrames.set(player, requestAnimationFrame(step));
			}
		};
		animationFrames.set(player, requestAnimationFrame(step));
	};

	const updateCustomPlayerVolume = (player, audio) =>
	{
		const volume = player.querySelector('[data-audioarchive-custom-volume]');
		const mute = player.querySelector('[data-audioarchive-custom-mute]');
		const volumeIcon = player.querySelector('[data-audioarchive-icon-volume]');
		const mutedIcon = player.querySelector('[data-audioarchive-icon-muted]');
		const isMuted = audio.muted || audio.volume <= 0;

		if (volume instanceof HTMLInputElement && !audio.muted)
		{
			volume.value = String(audio.volume);
		}

		if (mute instanceof HTMLButtonElement)
		{
			const muteLabel = mute.dataset.muteLabel || mute.getAttribute('aria-label') || '';
			const unmuteLabel = mute.dataset.unmuteLabel || muteLabel;
			mute.setAttribute('aria-pressed', isMuted ? 'true' : 'false');
			mute.setAttribute('aria-label', isMuted ? unmuteLabel : muteLabel);
			mute.title = isMuted ? unmuteLabel : muteLabel;
		}

		if (volumeIcon instanceof HTMLElement)
		{
			volumeIcon.hidden = isMuted;
		}

		if (mutedIcon instanceof HTMLElement)
		{
			mutedIcon.hidden = !isMuted;
		}
	};

	const stopActive = (exceptAudio = null) =>
	{
		if (activeAudio && activeAudio !== exceptAudio)
		{
			activeAudio.pause();
			activeAudio.currentTime = 0;
		}

		if (activeButton && (!exceptAudio || activeButton.getAttribute('aria-controls') !== exceptAudio.id))
		{
			setButtonState(activeButton, false);
			activeButton.style.setProperty('--audioarchive-progress', '0deg');
		}

		if (activeCustomPlayer)
		{
			const customAudio = getCustomPlayerAudio(activeCustomPlayer);

			if (customAudio !== exceptAudio)
			{
				setCustomPlayerState(activeCustomPlayer, false);
				stopProgressAnimation(activeCustomPlayer);

				if (customAudio instanceof HTMLAudioElement)
				{
					updateCustomPlayerProgress(activeCustomPlayer, customAudio);
				}
			}
		}

		if (activeAudio !== exceptAudio)
		{
			activeAudio = null;
			activeButton = null;
			activeCustomPlayer = null;
		}
	};

	document.querySelectorAll('[data-audioarchive-play]').forEach((button) =>
	{
		const audioId = button.getAttribute('aria-controls');
		const audio = audioId ? document.getElementById(audioId) : null;
		const title = button.dataset.clipTitle || '';
		const clipId = button.dataset.clipId || '';

		if (!(audio instanceof HTMLAudioElement))
		{
			button.disabled = true;
			return;
		}

		button.addEventListener('click', async () =>
		{
			if (!audio.paused)
			{
				audio.pause();
				return;
			}

			stopActive(audio);
			activeAudio = audio;
			activeButton = button;
			activeCustomPlayer = null;

			try
			{
				await audio.play();
			}
			catch (error)
			{
				setButtonState(button, false);
				announce(button, 'Error', title);
				activeAudio = null;
				activeButton = null;
			}
		});

		audio.addEventListener('play', () =>
		{
			stopActive(audio);
			activeAudio = audio;
			activeButton = button;
			activeCustomPlayer = null;
			setButtonState(button, true);
			recordPlay(button, clipId);
			announce(button, 'Playing', title);
		});

		audio.addEventListener('pause', () =>
		{
			setButtonState(button, false);

			if (!audio.ended && audio.currentTime > 0)
			{
				announce(button, 'Paused', title);
			}
		});

		audio.addEventListener('timeupdate', () => setProgress(button, audio));
		audio.addEventListener('durationchange', () => setProgress(button, audio));

		audio.addEventListener('ended', () =>
		{
			setButtonState(button, false);
			button.style.setProperty('--audioarchive-progress', '0deg');
			activeAudio = null;
			activeButton = null;
		});

		audio.addEventListener('error', () =>
		{
			setButtonState(button, false);
			button.classList.add('has-error');
			button.setAttribute('aria-label', button.dataset.errorLabel || button.dataset.playLabel || button.getAttribute('aria-label') || '');
			announce(button, 'Error', title);
			activeAudio = null;
			activeButton = null;
		});
	});

	document.querySelectorAll('[data-audioarchive-custom-player]').forEach((player) =>
	{
		const audio = getCustomPlayerAudio(player);
		const ui = player.querySelector('[data-audioarchive-custom-ui]');
		const toggle = player.querySelector('[data-audioarchive-custom-toggle]');
		const seek = player.querySelector('[data-audioarchive-custom-seek]');
		const volume = player.querySelector('[data-audioarchive-custom-volume]');
		const mute = player.querySelector('[data-audioarchive-custom-mute]');

		if (!(audio instanceof HTMLAudioElement) || !(ui instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement))
		{
			player.classList.add('has-error');
			return;
		}

		audio.controls = false;
		audio.hidden = true;
		ui.hidden = false;
		player.classList.add('is-enhanced');
		const title = audio.dataset.clipTitle || '';
		const clipId = audio.dataset.clipId || '';
		updateCustomPlayerProgress(player, audio);
		updateCustomPlayerVolume(player, audio);
		initialisePlayerWaveform(player, audio);

		toggle.addEventListener('click', async () =>
		{
			if (!audio.paused)
			{
				audio.pause();
				return;
			}

			stopActive(audio);
			activeAudio = audio;
			activeButton = null;
			activeCustomPlayer = player;

			try
			{
				await audio.play();
			}
			catch (error)
			{
				setCustomPlayerState(player, false);
				player.classList.add('has-error');
				announce(player, 'Error', title);
				activeAudio = null;
				activeCustomPlayer = null;
			}
		});

		if (seek instanceof HTMLInputElement)
		{
			seek.addEventListener('input', () =>
			{
				if (!Number.isFinite(audio.duration) || audio.duration <= 0)
				{
					return;
				}

				audio.currentTime = (Number.parseInt(seek.value, 10) / 1000) * audio.duration;
				updateCustomPlayerProgress(player, audio);
			});
		}

		if (volume instanceof HTMLInputElement)
		{
			volume.addEventListener('input', () =>
			{
				const nextVolume = Math.min(1, Math.max(0, Number.parseFloat(volume.value)));
				audio.volume = Number.isFinite(nextVolume) ? nextVolume : 1;
				audio.muted = false;
				player.dataset.audioarchiveLastVolume = String(audio.volume || 1);
			});
		}

		if (mute instanceof HTMLButtonElement)
		{
			mute.addEventListener('click', () =>
			{
				if (audio.muted || audio.volume <= 0)
				{
					const lastVolume = Number.parseFloat(player.dataset.audioarchiveLastVolume || '1');
					audio.volume = Number.isFinite(lastVolume) && lastVolume > 0 ? Math.min(1, lastVolume) : 1;
					audio.muted = false;
				}
				else
				{
					player.dataset.audioarchiveLastVolume = String(audio.volume);
					audio.muted = true;
				}
			});
		}

		audio.addEventListener('loadedmetadata', () => updateCustomPlayerProgress(player, audio));
		audio.addEventListener('durationchange', () => updateCustomPlayerProgress(player, audio));
		audio.addEventListener('timeupdate', () => updateCustomPlayerProgress(player, audio));
		audio.addEventListener('volumechange', () => updateCustomPlayerVolume(player, audio));

		audio.addEventListener('play', () =>
		{
			stopActive(audio);
			activeAudio = audio;
			activeButton = null;
			activeCustomPlayer = player;
			player.classList.remove('has-error');
			setCustomPlayerState(player, true);
			startProgressAnimation(player, audio);
			recordPlay(player, clipId);
			announce(player, 'Playing', title);
		});

		audio.addEventListener('pause', () =>
		{
			stopProgressAnimation(player);
			setCustomPlayerState(player, false);
			updateCustomPlayerProgress(player, audio);

			if (!audio.ended && audio.currentTime > 0)
			{
				announce(player, 'Paused', title);
			}
		});

		audio.addEventListener('ended', () =>
		{
			stopProgressAnimation(player);
			audio.currentTime = 0;
			setCustomPlayerState(player, false);
			updateCustomPlayerProgress(player, audio);
			activeAudio = null;
			activeCustomPlayer = null;
		});

		audio.addEventListener('error', () =>
		{
			stopProgressAnimation(player);
			setCustomPlayerState(player, false);
			player.classList.add('has-error');
			announce(player, 'Error', title);
			activeAudio = null;
			activeCustomPlayer = null;
		});
	});

	document.querySelectorAll('[data-audioarchive-native-player]').forEach((audio) =>
	{
		if (!(audio instanceof HTMLAudioElement))
		{
			return;
		}

		const title = audio.dataset.clipTitle || '';
		const clipId = audio.dataset.clipId || '';

		audio.addEventListener('play', () =>
		{
			stopActive(audio);
			activeAudio = audio;
			activeButton = null;
			activeCustomPlayer = null;
			recordPlay(audio, clipId);
			announce(audio, 'Playing', title);
		});

		audio.addEventListener('pause', () =>
		{
			if (!audio.ended && audio.currentTime > 0)
			{
				announce(audio, 'Paused', title);
			}
		});

		audio.addEventListener('error', () => announce(audio, 'Error', title));
	});
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchivePlayers);
