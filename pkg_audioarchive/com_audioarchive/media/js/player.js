/**
 * @brief Manage Audio Archive players and compact play buttons.
 */
const initialiseAudioArchivePlayers = () =>
{
	let activeAudio = null;
	let activeButton = null;

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

	const setButtonState = (button, playing) =>
	{
		const playLabel = button.dataset.playLabel || button.getAttribute('aria-label') || '';
		const pauseLabel = button.dataset.pauseLabel || playLabel;
		button.classList.toggle('is-playing', playing);
		button.setAttribute('aria-pressed', playing ? 'true' : 'false');
		button.setAttribute('aria-label', playing ? pauseLabel : playLabel);
		button.title = playing ? pauseLabel : playLabel;
		button.querySelector('[data-audioarchive-icon]').textContent = playing ? '❚❚' : '▶';
	};

	const setProgress = (button, audio) =>
	{
		const progress = Number.isFinite(audio.duration) && audio.duration > 0
			? Math.min(1, Math.max(0, audio.currentTime / audio.duration))
			: 0;
		button.style.setProperty('--audioarchive-progress', `${progress * 360}deg`);
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

		if (activeAudio !== exceptAudio)
		{
			activeAudio = null;
			activeButton = null;
		}
	};

	document.querySelectorAll('[data-audioarchive-play]').forEach((button) =>
	{
		const audioId = button.getAttribute('aria-controls');
		const audio = audioId ? document.getElementById(audioId) : null;
		const title = button.dataset.clipTitle || '';

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
			setButtonState(button, true);
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

	document.querySelectorAll('[data-audioarchive-native-player]').forEach((audio) =>
	{
		if (!(audio instanceof HTMLAudioElement))
		{
			return;
		}

		const title = audio.dataset.clipTitle || '';

		audio.addEventListener('play', () =>
		{
			stopActive(audio);
			activeAudio = audio;
			activeButton = null;
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
