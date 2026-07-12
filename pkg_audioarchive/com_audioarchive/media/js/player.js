/**
 * @brief Manage compact Audio Archive play buttons.
 */
const initialiseAudioArchivePlayers = () =>
{
	let activeAudio = null;
	let activeButton = null;

	const setButtonState = (button, playing) =>
	{
		const playLabel = button.dataset.playLabel || 'Play';
		const pauseLabel = button.dataset.pauseLabel || 'Pause';
		button.classList.toggle('is-playing', playing);
		button.setAttribute('aria-pressed', playing ? 'true' : 'false');
		button.setAttribute('aria-label', playing ? pauseLabel : playLabel);
		button.title = playing ? pauseLabel : playLabel;
		button.querySelector('[data-audioarchive-icon]').textContent = playing ? '❚❚' : '▶';
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
		});

		audio.addEventListener('pause', () =>
		{
			setButtonState(button, false);
		});

		audio.addEventListener('ended', () =>
		{
			setButtonState(button, false);
			activeAudio = null;
			activeButton = null;
		});

		audio.addEventListener('error', () =>
		{
			setButtonState(button, false);
			button.classList.add('has-error');
			button.setAttribute('aria-label', button.dataset.errorLabel || button.dataset.playLabel || 'Playback unavailable');
			activeAudio = null;
			activeButton = null;
		});
	});
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchivePlayers);
