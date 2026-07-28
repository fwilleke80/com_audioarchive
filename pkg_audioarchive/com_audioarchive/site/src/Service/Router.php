<?php

namespace Willeke\Component\Audioarchive\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\PreprocessRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * @brief Site router for Audio Archive archive, tag-directory, soundboard, and clip views.
 */
class Router extends RouterView
{
	/** @var DatabaseInterface */
	private DatabaseInterface $database;

	/**
	 * @brief Construct the component router.
	 *
	 * @param SiteApplication $application Site application.
	 * @param AbstractMenu $menu Site menu.
	 * @param CategoryFactoryInterface $categoryFactory Category factory supplied by Joomla.
	 * @param DatabaseInterface $database Joomla database connection.
	 */
	public function __construct(
		SiteApplication $application,
		AbstractMenu $menu,
		CategoryFactoryInterface $categoryFactory,
		DatabaseInterface $database
	)
	{
		$this->database = $database;

		$archive = new RouterViewConfiguration('archive');
		$this->registerView($archive);

		$tagDirectory = new RouterViewConfiguration('tagdirectory');
		$this->registerView($tagDirectory);

		$soundboard = new RouterViewConfiguration('soundboard');
		$this->registerView($soundboard);

		$clip = new RouterViewConfiguration('clip');
		$clip->setKey('id')->setParent($archive);
		$this->registerView($clip);

		$edit = new RouterViewConfiguration('edit');
		$edit->setParent($clip);
		$this->registerView($edit);

		parent::__construct($application, $menu);

		$preprocess = new PreprocessRules($clip, '#__audioarchive_clips', 'id');
		$preprocess->setDatabase($this->database);
		$this->attachRule($preprocess);
		$this->attachRule(new MenuRules($this));
		$this->attachRule(new TagFilterRules($this->database));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

	/**
	 * @brief Build the globally unique alias segment for one clip.
	 *
	 * @param string|int $id Clip identifier.
	 * @param array<string, mixed> $query Current router query.
	 *
	 * @return array<int, string>
	 */
	public function getClipSegment($id, $query): array
	{
		$id = (int) $id;

		if ($id <= 0)
		{
			return [];
		}

		$queryObject = $this->database->getQuery(true)
			->select($this->database->quoteName('alias'))
			->from($this->database->quoteName('#__audioarchive_clips'))
			->where($this->database->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);
		$alias = trim((string) $this->database->setQuery($queryObject)->loadResult());

		return $alias !== '' ? [$id => $alias] : [];
	}

	/**
	 * @brief Parse an alias-only segment and retain compatibility with old ID routes.
	 *
	 * @param string $segment URL segment.
	 * @param array<string, mixed> $query Current router query.
	 *
	 * @return int|false Clip identifier or false.
	 */
	public function getClipId($segment, $query)
	{
		$segment = trim(rawurldecode($segment));

		if ($segment === '')
		{
			return false;
		}

		$queryObject = $this->database->getQuery(true)
			->select($this->database->quoteName('id'))
			->from($this->database->quoteName('#__audioarchive_clips'))
			->where($this->database->quoteName('alias') . ' = :alias')
			->bind(':alias', $segment, ParameterType::STRING);
		$id = (int) $this->database->setQuery($queryObject, 0, 1)->loadResult();

		if ($id > 0)
		{
			return $id;
		}

		if (!preg_match('/^(\d+)(?:-(.+))?$/', $segment, $matches))
		{
			return false;
		}

		$legacyId = (int) $matches[1];

		if ($legacyId <= 0)
		{
			return false;
		}

		$queryObject = $this->database->getQuery(true)
			->select($this->database->quoteName('alias'))
			->from($this->database->quoteName('#__audioarchive_clips'))
			->where($this->database->quoteName('id') . ' = :id')
			->bind(':id', $legacyId, ParameterType::INTEGER);
		$alias = trim((string) $this->database->setQuery($queryObject, 0, 1)->loadResult());

		if ($alias === '')
		{
			return false;
		}

		$this->app->getRouter()->setTainted();

		return $legacyId;
	}
}
