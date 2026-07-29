<?php

namespace Punga\Component\Audioarchive\Site\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * @brief Builds safe optional frontend colour overrides.
 */
final class StyleHelper
{
	/**
	 * @brief Build classes that activate optional Archive field overrides.
	 *
	 * @param Registry $params Resolved component and menu parameters.
	 *
	 * @return string Space-separated CSS class names.
	 */
	public static function buildArchiveFilterClasses(Registry $params): string
	{
		return self::buildColorClasses($params, [
			'archive_filter_field_text_color' => 'has-audioarchive-filter-field-text',
			'archive_filter_text_field_background_color' => 'has-audioarchive-filter-text-field-background',
			'archive_filter_date_field_background_color' => 'has-audioarchive-filter-date-field-background',
			'archive_filter_select_field_background_color' => 'has-audioarchive-filter-select-field-background',
			'archive_filter_field_border_color' => 'has-audioarchive-filter-field-border',
		]);
	}

	/**
	 * @brief Build Archive filter CSS custom properties.
	 *
	 * Empty or invalid settings are omitted so the active Joomla template and
	 * the component's adaptive defaults remain in control.
	 *
	 * @param Registry $params Resolved component and menu parameters.
	 *
	 * @return string Inline CSS custom-property declarations.
	 */
	public static function buildArchiveFilterVariables(Registry $params): string
	{
		return self::buildColorVariables($params, [
			'archive_filter_background_color' => '--audioarchive-filter-background',
			'archive_filter_header_background_color' => '--audioarchive-filter-header-background',
			'archive_filter_header_text_color' => '--audioarchive-filter-header-text',
			'archive_filter_text_color' => '--audioarchive-filter-text',
			'archive_filter_field_text_color' => '--audioarchive-filter-field-text',
			'archive_filter_text_field_background_color' => '--audioarchive-filter-text-field-background',
			'archive_filter_date_field_background_color' => '--audioarchive-filter-date-field-background',
			'archive_filter_select_field_background_color' => '--audioarchive-filter-select-field-background',
			'archive_filter_tag_list_background_color' => '--audioarchive-filter-tag-list-background',
			'archive_filter_border_color' => '--audioarchive-filter-border',
			'archive_filter_field_border_color' => '--audioarchive-filter-field-border',
			'archive_filter_accent_color' => '--audioarchive-filter-accent',
		]);
	}

	/**
	 * @brief Build Archive list CSS custom properties.
	 *
	 * Empty or invalid settings are omitted so desktop tables and responsive
	 * cards continue to inherit the active Joomla template when unconfigured.
	 *
	 * @param Registry $params Resolved component and menu parameters.
	 *
	 * @return string Inline CSS custom-property declarations.
	 */
	public static function buildArchiveListVariables(Registry $params): string
	{
		return self::buildColorVariables($params, [
			'archive_list_background_color' => '--audioarchive-list-background',
			'archive_list_header_background_color' => '--audioarchive-list-header-background',
			'archive_list_header_text_color' => '--audioarchive-list-header-text',
			'archive_list_text_color' => '--audioarchive-list-text',
			'archive_list_link_color' => '--audioarchive-list-link',
			'archive_list_border_color' => '--audioarchive-list-border',
			'archive_list_alternate_row_background_color' => '--audioarchive-list-alternate-row-background',
			'archive_list_hover_background_color' => '--audioarchive-list-hover-background',
			'archive_list_tag_background_color' => '--audioarchive-list-tag-background',
			'archive_list_tag_text_color' => '--audioarchive-list-tag-text',
		]);
	}

	/**
	 * @brief Build Sound Board pad CSS custom properties.
	 *
	 * @param Registry $params Resolved component and menu parameters.
	 *
	 * @return string Inline CSS custom-property declarations.
	 */
	public static function buildSoundboardVariables(Registry $params): string
	{
		return self::buildColorVariables($params, [
			'soundboard_button_background_color' => '--audioarchive-soundboard-button-background',
			'soundboard_button_text_color' => '--audioarchive-soundboard-button-text',
			'soundboard_button_border_color' => '--audioarchive-soundboard-button-border',
		]);
	}

	/**
	 * @brief Build CSS classes for configured colour options.
	 *
	 * @param Registry $params Resolved component and menu parameters.
	 * @param array<string, string> $mapping Parameter names mapped to CSS class names.
	 *
	 * @return string Space-separated CSS class names.
	 */
	private static function buildColorClasses(Registry $params, array $mapping): string
	{
		$classes = [];

		foreach ($mapping as $parameterName => $className)
		{
			if (self::normaliseColor($params->get($parameterName)) !== '')
			{
				$classes[] = $className;
			}
		}

		return implode(' ', $classes);
	}

	/**
	 * @brief Convert configured colours into safe CSS custom properties.
	 *
	 * @param Registry $params Resolved component and menu parameters.
	 * @param array<string, string> $mapping Parameter names mapped to CSS custom-property names.
	 *
	 * @return string Inline CSS custom-property declarations.
	 */
	private static function buildColorVariables(Registry $params, array $mapping): string
	{
		$variables = [];

		foreach ($mapping as $parameterName => $variableName)
		{
			$color = self::normaliseColor($params->get($parameterName));

			if ($color === '')
			{
				continue;
			}

			$variables[] = $variableName . ':' . $color;
		}

		return implode(';', $variables);
	}

	/**
	 * @brief Validate a hexadecimal CSS colour.
	 *
	 * @param mixed $value Candidate setting value.
	 *
	 * @return string Normalised colour or an empty string when unset or invalid.
	 */
	private static function normaliseColor(mixed $value): string
	{
		$color = trim((string) $value);

		if ($color === '')
		{
			return '';
		}

		if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color) !== 1)
		{
			return '';
		}

		return strtolower($color);
	}
}
