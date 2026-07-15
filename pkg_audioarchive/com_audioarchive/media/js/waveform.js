/**
 * @brief Load and render protected Audio Archive waveform peak data.
 */
const initialiseAudioArchiveWaveforms = () =>
{
	document.querySelectorAll('[data-audioarchive-waveform]').forEach((root) =>
	{
		if (!(root instanceof HTMLElement))
		{
			return;
		}

		const canvas = root.querySelector('canvas');
		const status = root.querySelector('[data-audioarchive-waveform-status]');
		const url = root.dataset.waveformUrl || '';

		if (!(canvas instanceof HTMLCanvasElement) || url === '')
		{
			return;
		}

		let peaks = [];

		const draw = () =>
		{
			if (peaks.length === 0)
			{
				return;
			}

			const bounds = canvas.getBoundingClientRect();
			const width = Math.max(1, Math.round(bounds.width));
			const height = Math.max(48, Math.round(bounds.height));
			const ratio = Math.max(1, window.devicePixelRatio || 1);
			canvas.width = Math.round(width * ratio);
			canvas.height = Math.round(height * ratio);
			canvas.style.width = `${width}px`;
			canvas.style.height = `${height}px`;
			const context = canvas.getContext('2d');

			if (!context)
			{
				return;
			}

			context.setTransform(ratio, 0, 0, ratio, 0, 0);
			context.clearRect(0, 0, width, height);
			const styles = getComputedStyle(root);
			const waveformColor = styles.getPropertyValue('--audioarchive-waveform-color').trim() || styles.color;
			const centreColor = styles.getPropertyValue('--audioarchive-waveform-centre-color').trim() || waveformColor;
			const centre = height / 2;
			const amplitude = Math.max(1, centre - 4);
			context.strokeStyle = centreColor;
			context.globalAlpha = 0.2;
			context.beginPath();
			context.moveTo(0, centre + 0.5);
			context.lineTo(width, centre + 0.5);
			context.stroke();
			context.globalAlpha = 1;
			context.strokeStyle = waveformColor;
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

				peaks = data.peaks.filter((pair) => Array.isArray(pair) && pair.length >= 2);

				if (peaks.length === 0)
				{
					throw new Error('Waveform data is empty.');
				}

				if (status instanceof HTMLElement)
				{
					status.hidden = true;
				}

				draw();
				new ResizeObserver(draw).observe(root);
			})
			.catch(() =>
			{
				root.hidden = true;
			});
	});
};

document.addEventListener('DOMContentLoaded', initialiseAudioArchiveWaveforms);
