<?php
namespace Punga\Plugin\User\Audioarchive\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Model\PrepareDataEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Event\User\BeforeSaveEvent;
use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Event\User\AfterDeleteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
use Punga\Component\Audioarchive\Administrator\Service\UserProfileService;

\defined('_JEXEC') or die;

/** @brief Add private account preferences and administrator quota controls to Joomla user forms. */
final class Audioarchive extends CMSPlugin implements SubscriberInterface
{
	/** @var bool Automatically load translated profile labels. */
	protected $autoloadLanguage = true;
	/** @var array Validated request-local submissions awaiting a successful Joomla account save. */
	private array $pending = [];

	/** @brief Subscribe to concrete Joomla form and account lifecycle events. */
	public static function getSubscribedEvents(): array
	{
		return ['onContentPrepareForm' => 'prepareForm', 'onContentPrepareData' => 'prepareData',
			'onUserBeforeSave' => 'beforeSave', 'onUserAfterSave' => 'afterSave', 'onUserAfterDelete' => 'afterDelete'];
	}

	/** @brief Avoid interfering with unrelated forms, registration, API payloads or public user listings. */
	private function supported(string $context): bool
	{
		return in_array($context, ['com_users.profile', 'com_users.user', 'com_admin.profile'], true)
			&& ComponentHelper::isEnabled('com_audioarchive');
	}

	/** @brief Boot the component's namespaced services only when this plugin actually needs them. */
	private function service(): UserProfileService
	{
		$app = $this->getApplication();
		$app->bootComponent('com_audioarchive');
		return new UserProfileService(Factory::getContainer()->get(DatabaseInterface::class), ComponentHelper::getParams('com_audioarchive'), $app->getIdentity(), $app->isClient('administrator'));
	}

	/** @brief Build a field with XML-safe attributes and optional translated choices. */
	private function field(\SimpleXMLElement $fieldset, string $name, string $type, string $label, array $options = [], array $attributes = []): void
	{
		$field = $fieldset->addChild('field');
		foreach (['name' => $name, 'type' => $type, 'label' => $label] + $attributes as $key => $value)
		{
			$field->addAttribute($key, htmlspecialchars((string) $value, ENT_XML1, 'UTF-8'));
		}
		foreach ($options as $value => $text)
		{
			$field->addChild('option', htmlspecialchars((string) $text, ENT_XML1, 'UTF-8'))->addAttribute('value', (string) $value);
		}
		// Joomla's read-only profile renderer uses these helpers rather than select inputs.
		foreach (['audioarchive_' . $name, 'jform_audioarchive_' . $name] as $fieldId)
		{
			HTMLHelper::register('users.' . $fieldId, static function ($value) use ($options): string
			{
				$text = $options !== [] ? ($options[(string) $value] ?? '') : (string) $value;
				return htmlspecialchars(Text::_($text), ENT_QUOTES, 'UTF-8');
			});
		}
	}

	/** @brief Add controls for an existing, accessible account; hide choices fixed by site policy. */
	public function prepareForm(PrepareFormEvent $event): void
	{
		$form = $event->getForm();
		if (!$this->supported($form->getName())) return;
		$data = $event->getData();
		$id = (int) (is_array($data) ? ($data['id'] ?? 0) : ($data->id ?? 0));
		$id = $id ?: (int) $form->getValue('id', null, 0);
		$service = $this->service();
		if (!$service->canEdit($id)) return;
		$choices = $service->choices($id);
		$xml = new \SimpleXMLElement('<form><fields name="audioarchive"><fieldset name="audioarchive" label="PLG_USER_AUDIOARCHIVE_SECTION"/></fields></form>');
		$fieldset = $xml->fields->fieldset;
		if ($choices['categories'] !== [] && $choices['levels'] !== [])
		{
			$this->field($fieldset, 'default_visibility', 'list', 'PLG_USER_AUDIOARCHIVE_VISIBILITY', $choices['visibility']);
			foreach (['default_category_id' => 'categories', 'default_access_id' => 'levels'] as $field => $list)
			{
				$options = [0 => 'PLG_USER_AUDIOARCHIVE_SITE_DEFAULT'];
				foreach ($choices[$list] as $row) $options[(int) $row->id] = $row->title;
				$this->field($fieldset, $field, 'list', 'PLG_USER_AUDIOARCHIVE_' . strtoupper($list), $options);
			}
		}
		if ($choices['boards'] !== [])
		{
			$options = [0 => 'PLG_USER_AUDIOARCHIVE_FIRST_BOARD'];
			foreach ($choices['boards'] as $board) $options[(int) $board->id] = $board->title;
			$this->field($fieldset, 'default_soundboard_id', 'list', 'PLG_USER_AUDIOARCHIVE_DEFAULT_BOARD', $options);
		}
		foreach (['owned_clips', 'private_clips', 'storage_usage', 'clip_usage', 'playlist_count', 'soundboard_count', 'quota_status'] as $name)
		{
			$this->field($fieldset, $name, 'text', 'PLG_USER_AUDIOARCHIVE_' . strtoupper($name), [], ['readonly' => 'true', 'filter' => 'unset']);
		}
		if ($service->canOverride())
		{
			$this->field($fieldset, 'quota_source', 'text', 'PLG_USER_AUDIOARCHIVE_QUOTA_SOURCE', [], ['readonly' => 'true', 'filter' => 'unset']);
			foreach (['storage' => 'storage_mb', 'clips' => 'clips_limit'] as $dimension => $valueField)
			{
				$this->field($fieldset, $dimension . '_mode', 'list', 'PLG_USER_AUDIOARCHIVE_' . strtoupper($dimension) . '_OVERRIDE', ['inherit' => 'PLG_USER_AUDIOARCHIVE_INHERIT', 'custom' => 'PLG_USER_AUDIOARCHIVE_CUSTOM', 'unlimited' => 'PLG_USER_AUDIOARCHIVE_UNLIMITED']);
				$this->field($fieldset, $valueField, 'number', 'PLG_USER_AUDIOARCHIVE_' . strtoupper($valueField), [], ['min' => '0', 'max' => '2147483647', 'step' => '1', 'showon' => $dimension . '_mode:custom']);
			}
		}
		$form->load($xml, false);
	}

	/** @brief Populate private account data and read-only usage for display/edit forms. */
	public function prepareData(PrepareDataEvent $event): void
	{
		if (!$this->supported($event->getContext())) return;
		$data = $event->getData();
		if (!is_object($data) || empty($data->id)) return;
		$service = $this->service();
		$id = (int) $data->id;
		if (!$service->canEdit($id)) return;
		$stored = $service->read($id);
		$values = array_intersect_key($stored, array_flip(['default_visibility', 'default_category_id', 'default_access_id', 'default_soundboard_id']));
		$values += ['default_visibility' => '', 'default_category_id' => 0, 'default_access_id' => 0, 'default_soundboard_id' => 0];
		if ($service->canOverride())
		{
			foreach (['storage' => ['storage_quota_override_bytes', 'storage_mb', 1048576], 'clips' => ['clip_quota_override', 'clips_limit', 1]] as $dimension => [$column, $field, $factor])
			{
				$value = $stored[$column] ?? null;
				$values[$dimension . '_mode'] = $value === null ? 'inherit' : ((int) $value < 0 ? 'unlimited' : 'custom');
				$values[$field] = $value !== null && (int) $value >= 0 ? (int) ceil((int) $value / $factor) : 0;
			}
		}
		// Preserve validation-error submissions, but never trust posted usage or effective limits.
		$data->audioarchive = array_replace($values, (array) ($data->audioarchive ?? []), $service->summary($id));
		$event->updateData($data);
	}

	/** @brief Reject forged quota data before Joomla commits its account row. */
	public function beforeSave(BeforeSaveEvent $event): void
	{
		if (!ComponentHelper::isEnabled('com_audioarchive')) return;
		$data = $event->getData();
		if (!array_key_exists('audioarchive', $data)) return;
		$id = (int) ($event->getUser()['id'] ?? 0);
		if ($event->getIsNew() || !is_array($data['audioarchive']))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
		$this->service()->validate($id, $data['audioarchive']);
		$this->pending[$id] = $data['audioarchive'];
	}

	/** @brief Save only the submission validated for this successfully saved existing account. */
	public function afterSave(AfterSaveEvent $event): void
	{
		$id = (int) ($event->getUser()['id'] ?? 0);
		$data = $this->pending[$id] ?? null;
		unset($this->pending[$id]);
		if ($event->getSavingResult() && is_array($data)) $this->service()->save($id, $data);
	}

	/** @brief Revoke links after deletion while preserving all archival data for recovery. */
	public function afterDelete(AfterDeleteEvent $event): void
	{
		if (ComponentHelper::isEnabled('com_audioarchive') && $event->getArgument('deletingResult', false))
		{
			$this->service()->deleted((int) ($event->getUser()['id'] ?? 0));
		}
	}
}
