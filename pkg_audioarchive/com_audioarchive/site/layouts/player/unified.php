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
$presentation = in_array($presentation, ['minimal', 'compact', 'default', 'featured'], true)
	? $presentation
	: 'default';
$showSeek = $presentation !== 'minimal';
$showVolume = in_array($presentation, ['default', 'featured'], true);
$showWaveform = $presentation === 'featured';
$audioId = trim((string) ($data['audioId'] ?? 'audioarchive-player'));
$seekId = $audioId . '-seek';
$volumeId = $audioId . '-volume';
$title = trim((string) ($data['title'] ?? ''));
$streamUrl = trim((string) ($data['streamUrl'] ?? ''));
$waveformUrl = $showWaveform ? trim((string) ($data['waveformUrl'] ?? '')) : '';
$mime = trim((string) ($data['mime'] ?? '')) ?: 'application/octet-stream';
$clipId = max(0, (int) ($data['clipId'] ?? 0));
$labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
$playLabel = (string) ($labels['play'] ?? 'Play');
$pauseLabel = (string) ($labels['pause'] ?? 'Pause');
$seekLabel = (string) ($labels['seek'] ?? 'Seek');
$muteLabel = (string) ($labels['mute'] ?? 'Mute');
$unmuteLabel = (string) ($labels['unmute'] ?? 'Unmute');
$fallbackLabel = (string) ($labels['fallback'] ?? 'Your browser cannot play this audio.');
$waveformLoadingLabel = (string) ($labels['waveformLoading'] ?? 'Loading waveform…');
$buttonSizeParameter = match ($presentation)
{
	'minimal' => 'player_minimal_button_size',
	'compact' => 'player_compact_button_size',
	'featured' => 'player_featured_button_size',
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
$className .= $waveformUrl !== '' ? ' has-waveform' : ' no-waveform';
$style = implode(';', [
	'--audioarchive-player-background:' . $normaliseColor($params->get('player_background_color'), '#f8f9fa'),
	'--audioarchive-player-text:' . $normaliseColor($params->get('player_text_color'), '#212529'),
	'--audioarchive-player-accent:' . $normaliseColor($params->get('player_control_color'), '#0d6efd'),
	'--audioarchive-waveform-unplayed:' . $normaliseColor($params->get('player_waveform_unplayed_color'), '#6c757d'),
	'--audioarchive-waveform-played:' . $normaliseColor($params->get('player_waveform_played_color'), '#0d6efd'),
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
		<source src="<?php echo $escape($streamUrl); ?>" type="<?php echo $escape($mime); ?>">
		<?php echo $escape($fallbackLabel); ?>
	</audio>

	<div class="audioarchive-custom-player-ui" data-audioarchive-custom-ui hidden>
		<div class="audioarchive-custom-player-controls">
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

			<?php if ($showVolume) : ?>
				<div class="audioarchive-custom-player-volume-controls">
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
					<label class="visually-hidden" for="<?php echo $escape($volumeId); ?>"><?php echo $escape((string) ($labels['volume'] ?? 'Volume')); ?></label>
					<input
						id="<?php echo $escape($volumeId); ?>"
						class="audioarchive-custom-player-volume"
						type="range"
						min="0"
						max="1"
						step="0.05"
						value="1"
						data-audioarchive-custom-volume
					>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($waveformUrl !== '') : ?>
			<div
				class="audioarchive-custom-player-waveform"
				data-audioarchive-player-waveform
				data-waveform-url="<?php echo $escape($waveformUrl); ?>"
			>
				<canvas aria-hidden="true"></canvas>
				<p class="audioarchive-custom-player-waveform-status" data-audioarchive-waveform-status>
					<?php echo $escape($waveformLoadingLabel); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>
