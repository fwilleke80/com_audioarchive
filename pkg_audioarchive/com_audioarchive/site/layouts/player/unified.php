<?php

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

$data = is_array($displayData ?? null) ? $displayData : [];
$params = ($data['params'] ?? null) instanceof Registry ? $data['params'] : new Registry();
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$normaliseColor = static function (mixed $value, string $fallback): string
{
	$color = trim((string) $value);

	return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? $color : $fallback;
};
$normaliseInteger = static function (mixed $value, int $fallback, int $minimum, int $maximum): int
{
	$integer = filter_var($value, FILTER_VALIDATE_INT);

	if ($integer === false)
	{
		return $fallback;
	}

	return max($minimum, min($maximum, (int) $integer));
};
$presentation = strtolower(trim((string) ($data['presentation'] ?? 'default')));
$presentation = in_array($presentation, ['minimal', 'compact', 'default', 'featured', 'playlist'], true)
	? $presentation
	: 'default';
$showSeek = $presentation !== 'minimal';
$isPlaylist = $presentation === 'playlist';
$showMute = in_array($presentation, ['default', 'featured', 'playlist'], true);
$showAnalysis = $presentation === 'featured';
$showVarispeed = $presentation === 'featured' && (int) $params->get('player_featured_varispeed', 0) === 1;
$audioId = trim((string) ($data['audioId'] ?? 'audioarchive-player'));
$seekId = $audioId . '-seek';
$varispeedId = $audioId . '-varispeed';
$title = trim((string) ($data['title'] ?? ''));
$streamUrl = trim((string) ($data['streamUrl'] ?? ''));
$waveformUrl = $showAnalysis ? trim((string) ($data['waveformUrl'] ?? '')) : '';
$spectrogramUrl = $showAnalysis ? trim((string) ($data['spectrogramUrl'] ?? '')) : '';
$frequencyProfileUrl = $showAnalysis ? trim((string) ($data['frequencyProfileUrl'] ?? '')) : '';
$hasWaveform = $waveformUrl !== '';
$hasSpectrogram = $spectrogramUrl !== '';
$hasFrequencyProfile = $frequencyProfileUrl !== '';
$hasAnalysis = $hasWaveform || $hasSpectrogram || $hasFrequencyProfile;
$preferredAnalysisView = strtolower(trim((string) (
	$data['preferredAnalysisView']
	?? $params->get('player_preferred_data_view', 'waveform')
)));
$preferredAnalysisView = in_array($preferredAnalysisView, ['waveform', 'spectrogram', 'frequency_profile'], true)
	? $preferredAnalysisView
	: 'waveform';
$initialAnalysisView = match (true)
{
	$preferredAnalysisView === 'spectrogram' && $hasSpectrogram => 'spectrogram',
	$preferredAnalysisView === 'frequency_profile' && $hasFrequencyProfile => 'frequency_profile',
	$hasWaveform => 'waveform',
	$hasSpectrogram => 'spectrogram',
	$hasFrequencyProfile => 'frequency_profile',
	default => '',
};
$mime = trim((string) ($data['mime'] ?? '')) ?: 'application/octet-stream';
$clipId = max(0, (int) ($data['clipId'] ?? 0));
$labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
$playLabel = (string) ($labels['play'] ?? 'Play');
$pauseLabel = (string) ($labels['pause'] ?? 'Pause');
$seekLabel = (string) ($labels['seek'] ?? 'Seek');
$muteLabel = (string) ($labels['mute'] ?? 'Mute');
$unmuteLabel = (string) ($labels['unmute'] ?? 'Unmute');
$fallbackLabel = (string) ($labels['fallback'] ?? 'Your browser cannot play this audio.');
$previousLabel = (string) ($labels['previous'] ?? 'Previous');
$nextLabel = (string) ($labels['next'] ?? 'Next');
$waveformLoadingLabel = (string) ($labels['waveformLoading'] ?? 'Loading waveform…');
$spectrogramLoadingLabel = (string) ($labels['spectrogramLoading'] ?? 'Loading spectrum…');
$frequencyProfileLoadingLabel = (string) ($labels['frequencyProfileLoading'] ?? 'Loading frequency profile…');
$waveformLabel = (string) ($labels['waveform'] ?? 'Waveform');
$spectrumLabel = (string) ($labels['spectrum'] ?? 'Spectrum');
$frequencyProfileLabel = (string) ($labels['frequencyProfile'] ?? 'Frequency Profile');
$frequencyProfileSummary = (string) ($labels['frequencyProfileSummary'] ?? 'Peak: %1$s Hz · Centroid: %2$s Hz');
$varispeedLabel = (string) ($labels['varispeed'] ?? 'Pitch / speed');
$varispeedResetLabel = (string) ($labels['varispeedReset'] ?? 'Reset pitch and speed to normal');
$varispeedNormalLabel = (string) ($labels['varispeedNormal'] ?? 'Normal');
$varispeedOctaveLabel = (string) ($labels['varispeedOctave'] ?? 'oct');
$buttonSizeParameter = match ($presentation)
{
	'minimal' => 'player_minimal_button_size',
	'compact' => 'player_compact_button_size',
	'featured' => 'player_featured_button_size',
	'playlist' => 'player_default_button_size',
	default => 'player_default_button_size',
};
$buttonSizeFallback = match ($presentation)
{
	'minimal' => 42,
	'compact' => 48,
	default => 54,
};
$className = trim(
	'audioarchive-custom-player audioarchive-custom-player--' . $presentation . ' '
	. (string) ($data['class'] ?? '')
);
$className .= $hasAnalysis ? ' has-analysis' : ' no-analysis';
$className .= $hasWaveform ? ' has-waveform' : ' no-waveform';
$className .= $hasSpectrogram ? ' has-spectrogram' : ' no-spectrogram';
$className .= $hasFrequencyProfile ? ' has-frequency-profile' : ' no-frequency-profile';
$style = implode(';', [
	'--audioarchive-player-background:' . $normaliseColor($params->get('player_background_color'), '#f8f9fa'),
	'--audioarchive-player-text:' . $normaliseColor($params->get('player_text_color'), '#212529'),
	'--audioarchive-player-accent:' . $normaliseColor($params->get('player_control_color'), '#0d6efd'),
	'--audioarchive-waveform-unplayed:' . $normaliseColor($params->get('player_waveform_unplayed_color'), '#6c757d'),
	'--audioarchive-waveform-played:' . $normaliseColor($params->get('player_waveform_played_color'), '#0d6efd'),
	'--audioarchive-frequency-profile-background:' . $normaliseColor($params->get('player_frequency_profile_background_color'), '#111827'),
	'--audioarchive-frequency-profile-fill:' . $normaliseColor($params->get('player_frequency_profile_fill_color'), '#0d6efd'),
	'--audioarchive-frequency-profile-line:' . $normaliseColor($params->get('player_frequency_profile_line_color'), '#8bb9fe'),
	'--audioarchive-frequency-profile-grid:' . $normaliseColor($params->get('player_frequency_profile_grid_color'), '#94a3b8'),
	'--audioarchive-player-radius:' . $normaliseInteger($params->get('player_border_radius'), 14, 0, 40) . 'px',
	'--audioarchive-player-button-size:' . $normaliseInteger($params->get($buttonSizeParameter), $buttonSizeFallback, 32, 88) . 'px',
	'--audioarchive-waveform-height:' . $normaliseInteger($params->get('player_featured_waveform_height'), 100, 48, 240) . 'px',
]);
?>
<div
	class="<?php echo $escape($className); ?>"
	style="<?php echo $escape($style); ?>"
	data-audioarchive-custom-player
	data-player-presentation="<?php echo $escape($presentation); ?>"
	data-preferred-analysis-view="<?php echo $escape($initialAnalysisView); ?>"
>
	<audio
		id="<?php echo $escape($audioId); ?>"
		class="audioarchive-custom-player-native"
		controls
		preload="<?php echo $presentation === 'minimal' ? 'none' : 'metadata'; ?>"
		data-audioarchive-custom-audio
		data-clip-id="<?php echo $clipId; ?>"
		data-clip-title="<?php echo $escape($title); ?>"
	>
		<?php if ($streamUrl !== '') : ?>
			<source src="<?php echo $escape($streamUrl); ?>" type="<?php echo $escape($mime); ?>">
		<?php endif; ?>
		<?php echo $escape($fallbackLabel); ?>
	</audio>

	<div class="audioarchive-custom-player-ui" data-audioarchive-custom-ui hidden>
		<?php if ($isPlaylist) : ?>
			<div class="audioarchive-custom-player-playlist-meta">
				<strong data-audioarchive-playlist-player-title><?php echo $escape($title); ?></strong>
				<span data-audioarchive-playlist-player-position>0 / 0</span>
			</div>
		<?php endif; ?>

		<div class="audioarchive-custom-player-controls">
			<?php if ($isPlaylist) : ?>
				<button
					type="button"
					class="audioarchive-custom-player-skip"
					aria-label="<?php echo $escape($previousLabel); ?>"
					title="<?php echo $escape($previousLabel); ?>"
					data-audioarchive-playlist-previous
					disabled
				>
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M6 5h2v14H6zm3.5 7 8.5 7V5z"/></svg>
				</button>
			<?php endif; ?>
			<button
				type="button"
				class="audioarchive-custom-player-toggle"
				aria-controls="<?php echo $escape($audioId); ?>"
				aria-label="<?php echo $escape($playLabel); ?>"
				aria-pressed="false"
				title="<?php echo $escape($playLabel); ?>"
				data-audioarchive-custom-toggle
				data-play-label="<?php echo $escape($playLabel); ?>"
				data-pause-label="<?php echo $escape($pauseLabel); ?>"
			>
				<span data-audioarchive-icon-play aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg>
				</span>
				<span data-audioarchive-icon-pause aria-hidden="true" hidden>
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M6.5 5h4v14h-4zm7 0h4v14h-4z"/></svg>
				</span>
			</button>

			<?php if ($isPlaylist) : ?>
				<button
					type="button"
					class="audioarchive-custom-player-skip"
					aria-label="<?php echo $escape($nextLabel); ?>"
					title="<?php echo $escape($nextLabel); ?>"
					data-audioarchive-playlist-next
					disabled
				>
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M16 5h2v14h-2zM6 5v14l8.5-7z"/></svg>
				</button>
			<?php endif; ?>

			<?php if ($showSeek) : ?>
				<div class="audioarchive-custom-player-main">
					<label class="visually-hidden" for="<?php echo $escape($seekId); ?>"><?php echo $escape($seekLabel); ?></label>
					<input
						id="<?php echo $escape($seekId); ?>"
						class="audioarchive-custom-player-seek"
						type="range"
						min="0"
						max="1000"
						step="1"
						value="0"
						disabled
						data-audioarchive-custom-seek
					>
					<div class="audioarchive-custom-player-times" aria-hidden="true">
						<span data-audioarchive-current-time>0:00</span>
						<span data-audioarchive-duration>0:00</span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($showMute) : ?>
				<div class="audioarchive-custom-player-mute-controls">
					<button
						type="button"
						class="audioarchive-custom-player-mute"
						aria-label="<?php echo $escape($muteLabel); ?>"
						aria-pressed="false"
						title="<?php echo $escape($muteLabel); ?>"
						data-audioarchive-custom-mute
						data-mute-label="<?php echo $escape($muteLabel); ?>"
						data-unmute-label="<?php echo $escape($unmuteLabel); ?>"
					>
						<span data-audioarchive-icon-volume aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.5-.8v2.1c1.2.6 2 1.8 2 3.2s-.8 2.6-2 3.2v2.1c2.3-.7 4-2.8 4-5.3s-1.7-4.6-4-5.3z"/></svg>
						</span>
						<span data-audioarchive-icon-muted aria-hidden="true" hidden>
							<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.7 1.3-1.4 1.4 1.8 1.8-1.8 1.8 1.4 1.4 1.8-1.8 1.8 1.8 1.4-1.4-1.8-1.8 1.8-1.8-1.4-1.4-1.8 1.8z"/></svg>
						</span>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($showVarispeed) : ?>
			<div class="audioarchive-custom-player-varispeed" data-audioarchive-varispeed>
				<div class="audioarchive-custom-player-varispeed-heading">
					<label for="<?php echo $escape($varispeedId); ?>"><?php echo $escape($varispeedLabel); ?></label>
					<button
						type="button"
						class="audioarchive-custom-player-varispeed-reset"
						title="<?php echo $escape($varispeedResetLabel); ?>"
						aria-label="<?php echo $escape($varispeedResetLabel); ?>"
						data-audioarchive-varispeed-reset
					>
						<span data-audioarchive-varispeed-value><?php echo $escape($varispeedNormalLabel); ?> · 1.00×</span>
					</button>
				</div>
				<div class="audioarchive-custom-player-varispeed-slider">
					<span aria-hidden="true">−2 <?php echo $escape($varispeedOctaveLabel); ?></span>
					<input
						id="<?php echo $escape($varispeedId); ?>"
						type="range"
						min="-2"
						max="2"
						step="0.001"
						value="0"
						data-audioarchive-varispeed-range
						data-normal-label="<?php echo $escape($varispeedNormalLabel); ?>"
						data-octave-label="<?php echo $escape($varispeedOctaveLabel); ?>"
					>
					<span aria-hidden="true">+2 <?php echo $escape($varispeedOctaveLabel); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<?php if ($hasAnalysis) : ?>
			<div class="audioarchive-custom-player-analysis" data-audioarchive-player-analysis>
				<?php if (((int) $hasWaveform + (int) $hasSpectrogram + (int) $hasFrequencyProfile) > 1) : ?>
					<div class="audioarchive-custom-player-analysis-switch" role="group" aria-label="<?php echo $escape((string) ($labels['analysisView'] ?? 'Analysis view')); ?>">
						<button
							<?php echo !$hasWaveform ? 'hidden' : ''; ?>
							type="button"
							<?php echo $initialAnalysisView === 'waveform' ? 'class="is-active" aria-pressed="true"' : 'aria-pressed="false"'; ?>
							data-audioarchive-analysis-switch="waveform"
						>
							<?php echo $escape($waveformLabel); ?>
						</button>
						<button
							<?php echo !$hasSpectrogram ? 'hidden' : ''; ?>
							type="button"
							<?php echo $initialAnalysisView === 'spectrogram' ? 'class="is-active" aria-pressed="true"' : 'aria-pressed="false"'; ?>
							data-audioarchive-analysis-switch="spectrogram"
						>
							<?php echo $escape($spectrumLabel); ?>
						</button>
						<button
							<?php echo !$hasFrequencyProfile ? 'hidden' : ''; ?>
							type="button"
							<?php echo $initialAnalysisView === 'frequency_profile' ? 'class="is-active" aria-pressed="true"' : 'aria-pressed="false"'; ?>
							data-audioarchive-analysis-switch="frequency_profile"
						>
							<?php echo $escape($frequencyProfileLabel); ?>
						</button>
					</div>
				<?php endif; ?>

				<?php if ($hasWaveform) : ?>
					<div
						class="audioarchive-custom-player-analysis-panel audioarchive-custom-player-waveform"
						data-audioarchive-analysis-panel="waveform"
						data-audioarchive-player-waveform
						data-waveform-url="<?php echo $escape($waveformUrl); ?>"
						<?php echo $initialAnalysisView !== 'waveform' ? 'hidden' : ''; ?>
					>
						<canvas aria-hidden="true"></canvas>
						<p class="audioarchive-custom-player-analysis-status" data-audioarchive-waveform-status>
							<?php echo $escape($waveformLoadingLabel); ?>
						</p>
					</div>
				<?php endif; ?>

				<?php if ($hasSpectrogram) : ?>
					<div
						class="audioarchive-custom-player-analysis-panel audioarchive-custom-player-spectrogram"
						data-audioarchive-analysis-panel="spectrogram"
						data-audioarchive-player-spectrogram
						data-spectrogram-url="<?php echo $escape($spectrogramUrl); ?>"
						<?php echo $initialAnalysisView !== 'spectrogram' ? 'hidden' : ''; ?>
					>
						<img alt="" aria-hidden="true" decoding="async" fetchpriority="low" data-audioarchive-spectrogram-image>
						<span class="audioarchive-custom-player-spectrogram-playhead" aria-hidden="true" data-audioarchive-spectrogram-playhead></span>
						<p class="audioarchive-custom-player-analysis-status" data-audioarchive-spectrogram-status>
							<?php echo $escape($spectrogramLoadingLabel); ?>
						</p>
					</div>
				<?php endif; ?>

				<?php if ($hasFrequencyProfile) : ?>
					<div
						class="audioarchive-custom-player-analysis-panel audioarchive-custom-player-frequency-profile"
						data-audioarchive-analysis-panel="frequency_profile"
						data-audioarchive-player-frequency-profile
						data-frequency-profile-url="<?php echo $escape($frequencyProfileUrl); ?>"
						data-summary-template="<?php echo $escape($frequencyProfileSummary); ?>"
						<?php echo $initialAnalysisView !== 'frequency_profile' ? 'hidden' : ''; ?>
					>
						<canvas aria-hidden="true"></canvas>
						<p class="audioarchive-custom-player-analysis-status" data-audioarchive-frequency-profile-status>
							<?php echo $escape($frequencyProfileLoadingLabel); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
