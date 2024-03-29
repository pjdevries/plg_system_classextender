<?php
/**
 * @package    Obix Class Extender System Plugin
 *
 * @author     Pieter-Jan de Vries/Obix webtechniek <pieter@obix.nl>
 * @copyright  Copyright © 2020 Obix webtechniek. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.obix.nl
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Obix\ClassExtender\ClassExtenderException;

if (Version::MAJOR_VERSION <= 3)
{
	\JLoader::registerNamespace(
		'Obix', JPATH_PLUGINS . '/system/classextender/libraries'
	);
}
else
{
	\JLoader::registerNamespace(
		'Obix', JPATH_PLUGINS . '/system/classextender/libraries/Obix'
	);
}

/**
 * ClassExtender plugin.
 *
 * @package   Obix Class Extender System Plugin
 * @since     1.0.0
 */
class plgSystemClassExtender extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  1.0.0
	 */
	protected $app;

	/**
	 * Database object
	 *
	 * @var    JDatabaseDriver
	 * @since  1.0.0
	 */
	protected $db;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Base path for class etension files.
	 *
	 * @var string
	 */
	private $extenderRootPath = '';

	/**
	 * Set if we are called for the site or administrator client
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private $client = '';

	const EXTENSION = 'ExtensionBase';

	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		// Get extensions folder path from plugin configuration.
		$this->extenderRootPath = trim($this->params->get('extenderRootPath', ''));

		// Set backward compatible default, if folder path not configured.
		if ($this->extenderRootPath === '')
		{
			$this->extenderRootPath = '/templates/' . $this->app->getTemplate() . '/class_extensions';
		}

		// Remove amy leading and trailing slashes and prefix with website root.
		$this->extenderRootPath = JPATH_ROOT . '/' . trim($this->extenderRootPath, '\\/');

		$this->client = $this->app->getName();
	}

	/**
	 * Listener for the 'onAfterInitialise' event
	 *
	 * @return  void
	 */
	public function onAfterInitialise(): void
	{
		// Initialise non routed extended (core) classes.
		$this->extendClasses(false);
	}

	/**
	 * Listener for the 'onAfterRoute' event
	 *
	 * @return  void
	 */
	public function onAfterRoute(): void
	{
		// Initialise routed extended (core) classes.
		$this->extendClasses(true);
	}

	/**
	 * Initialise extended core classes.
	 */
	private function extendClasses(bool $routed): void
	{
		try
		{
			// File path of the class extension specifications.
			$classExtenderSepecificationFile = $this->extenderRootPath . '/class_extensions.json';

			// If no specification file exists, we're done already.
			if (!file_exists($classExtenderSepecificationFile))
			{
				return;
			}

			// Read json encoded file and decode into array of \stdClass objects.
			// The file contains an array of objects with the following attributes:
			// - "file": the path of the file, relative to the website root,
			//           containing the original class definition to be extended.
			// - "class": the name of the original class to be extended.
			try
			{
				$classExtensions = json_decode(
					file_get_contents($classExtenderSepecificationFile),
					null, 512, JSON_THROW_ON_ERROR
				);
			}
			catch (JsonException $exception)
			{
				throw new ClassExtenderException(
					Text::_('PLG_SYSTEM_CLASS_EXTENDER_INVALID_JSON_FILE'),
					ClassExtenderException::TYPE_ERROR
				);
			}

			$classExtensions = array_filter($classExtensions, function (stdClass $extensionSpecs) use ($routed) {
				return (($routed && isset($extensionSpecs->route)) || (!$routed && !isset($extensionSpecs->route)));
			});

			foreach ($classExtensions as $extensionSpecs)
			{
				$this->extend($extensionSpecs);
			}
		}
		catch (ClassExtenderException $exception)
		{
			$this->app->enqueueMessage($exception->getMessage(), $exception->getMessageType());
		}
		catch (Exception $exception)
		{
			$this->app->enqueueMessage(
				Text::sprintf('PLG_SYSTEM_CLASS_EXTENDER_UNFORESEEN_EXCEPTION', $exception->getMessage()), 'error'
			);
		}
	}

	private function extend(stdClass $extensionSpecs): void
	{
		// Check if we need to verify the correct application client
		if (isset($extensionSpecs->client)
			&& !empty($extensionSpecs->client)
			&& $extensionSpecs->client !== $this->client)
		{
			return;
		}

		if (empty($extensionSpecs->file ?? '') || empty($extensionSpecs->class ?? ''))
		{
			throw new ClassExtenderException(
				Text::_('PLG_SYSTEM_CLASS_EXTENDER_INVALID_CLASS_DESCRIPTION'),
				ClassExtenderException::TYPE_WARNING
			);
		}

		$className = $extensionSpecs->class;

		// Remove root path ...
		$originalClassFile = preg_replace(
			'#^' . preg_quote(JPATH_ROOT) . '#',
			'', $extensionSpecs->file
		);
		// ... and leading/trailing slashes from original file path.
		$originalClassFile = trim($originalClassFile, '\\/');

		// If the extension specifications only applies to a specific route,
		// we expect a name to be used as part of the path name (see below).
		// This implies that the route specs name must be filename safe.
		$hasRoute = isset($extensionSpecs->route);

		if ($hasRoute && empty($extensionSpecs->route->name ?? ''))
		{
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_CLASS_EXTENDER_MISSING_ROUTE_NAME'), 'warning');
		}

		if ($hasRoute)
		{
			$routeSpecifiers = array_filter(get_object_vars($extensionSpecs->route), function (string $value, string $name) {
				return $name !== 'name' && !empty($value);
			}, ARRAY_FILTER_USE_BOTH);
		}

		if ($hasRoute && !count($routeSpecifiers ?? []))
		{
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_CLASS_EXTENDER_MISSING_ROUTE_SPECS'), 'warning');
		}

		// If the extension specifications only applies to a specific route,
		// we check if the current route matches the route specs.
		if ($hasRoute && !$this->isEnRoute($extensionSpecs->route))
		{
			return;
		}

		// Extract path without filename from original file path.
		// We use this for the path of the copied original containing
		// the class that will be extended and for the file that
		// contains the extended class itself.
		$classBaseDir = dirname($originalClassFile);

		// Both the name of the extended class and its filename are
		// the same as the name of the original class. For route specific
		// extensions we append the route name to the base dir.
		$extendedClassFile = $hasRoute
			? sprintf(
				"%s/%s/%s/%s.php",
				$this->extenderRootPath, $classBaseDir, $extensionSpecs->route->name, $className
			)
			: sprintf(
				"%s/%s/%s.php",
				$this->extenderRootPath, $classBaseDir, $className
			);

		// If no extended class file exists, we're done already.
		if (!file_exists($extendedClassFile))
		{
			throw new ClassExtenderException(
				Text::sprintf('PLG_SYSTEM_CLASS_EXTENDER_EXTENDED_CLASS_FILE_MISSING', basename($extendedClassFile)),
				ClassExtenderException::TYPE_ERROR
			);
		}

		// Make original file path absolute.
		$originalClassFile = JPATH_ROOT . '/' . $originalClassFile;

		// If file with class to be extended does not exist, we're done already.
		if (!file_exists($originalClassFile))
		{
			throw new ClassExtenderException(
				Text::sprintf(
					'PLG_SYSTEM_CLASS_EXTENDER_TO_BE_EXTENDED_CLASS_FILE_MISSING',
					str_ireplace(JPATH_SITE, '', $originalClassFile)
				),
				ClassExtenderException::TYPE_ERROR
			);
		}

		// The original class to be extended is copied to a file named after
		// the original class, but with 'ExtensionBase' appended. The copy
		// is located in the same directory as the original.
		$toBeExtendedClassFile = sprintf(
			"%s/%s/%s%s.php",
			JPATH_ROOT, $classBaseDir, $className, self::EXTENSION
		);

		// If no copy of the original class file exists or if it has changed since
		// the copy was made, we make a fresh copy.
		if (!file_exists($toBeExtendedClassFile) || filemtime($originalClassFile) > filemtime($toBeExtendedClassFile))
		{
			$orgFileContents = file_get_contents($originalClassFile);

			// The following pattern is supposed to match a class declaration,
			// returning the class name in first and only capture group.
			// class\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff-]+)(?:(?:\s+(?:extends|implements))|(?:\s*(?:\{|$)))
			//
			// Because we know which class name to look for,
			// we replace the capture group pattern with the literal class name.
			$pattern = '/class\s+(' . $className . ')(?:(?:[\s\r\n]+(?:extends|implements))|(?:[\s\n\r]*(?:\{|$)))/';
			$replacement = $className . self::EXTENSION;

			$ExtensionBaseContents = preg_replace_callback(
				$pattern, function (array $matches) use ($className, $replacement) {
				return ($matches[1] ?? null)
					? substr($matches[0][0], 0, $matches[1][1] - $matches[0][1])
					. $replacement
					. substr($matches[0][0], $matches[1][1] - $matches[0][1] + strlen($className))
					: $matches[0][0];
			}, $orgFileContents, -1, $replacementCount, PREG_OFFSET_CAPTURE
			);

			file_put_contents($toBeExtendedClassFile, $ExtensionBaseContents);
		}

		// First check if there are any files the overridden or overriding
		// class depend on and load them if there are.
		if (isset($extensionSpecs->dependencies))
		{
			foreach ((array) $extensionSpecs->dependencies as $dependency)
			{
				if (!empty($dependency))
				{
					include_once JPATH_ROOT . '/' . trim($dependency, '\\/');
				}
			}
		}

		// Then include the copy of the original class file, making the original
		// class available with a derived name (i.e. with 'ExtensionBase' appended).
		include_once $toBeExtendedClassFile;

		// Next we include overriding class, which has the name of the original class
		// and extends the original class by referring to the derived name of the copy.
		// The overriding class is now loaded and readily available.
		include_once $extendedClassFile;
	}

	private function isEnRoute(stdClass $extensionSpecsRoute): bool
	{
		static $routeElements = [
			'option',
			'view',
			'layout',
			'task',
			'format',
			'Itemid',
		];

		foreach ($routeElements as $element)
		{
			if (!isset($extensionSpecsRoute->{$element}))
			{
				continue;
			}

			if ($this->app->input->getString($element, '') !== $extensionSpecsRoute->{$element})
			{
				return false;
			}
		}

		return true;
	}
}