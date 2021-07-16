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

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\PluginAdapter;

/**
 * ClassExtender script file.
 *
 * @package   Obix Class Extender System Plugin
 * @since     1.0.0
 */
class plgSystemClassExtenderInstallerScript
{
	/**
	 * Called after any type of action.
	 *
	 * @param   string         $route    Which action is happening (install|uninstall|discover_install|update)
	 * @param   PluginAdapter  $adapter  The object responsible for running this script
	 *
	 * @return  void
	 */
	public function postflight($route, PluginAdapter $adapter): void
	{
		// Enable plugin on first installation only.
		if ($route === 'install')
		{
			$db    = Factory::getDbo();
			$query = sprintf('UPDATE %s SET %s = 1 WHERE %s = %s AND %s = %s',
				$db->quoteName('#__extensions'),
				$db->quoteName('enabled'),
				$db->quoteName('type'), $db->quote('plugin'),
				$db->quoteName('name'), $db->quote('PLG_SYSTEM_CLASS_EXTENDER')
			);
			$db->setQuery($query);
			$db->execute();
		}
	}
}
