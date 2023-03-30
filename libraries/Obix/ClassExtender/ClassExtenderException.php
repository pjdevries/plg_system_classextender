<?php
/**
 * @package    Obix Class Extender System Plugin
 *
 * @author     Pieter-Jan de Vries/Obix webtechniek <pieter@obix.nl>
 * @copyright  Copyright © 2020 Obix webtechniek. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.obix.nl
 */

namespace Obix\ClassExtender;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use RuntimeException;

/**
 * Class extender exception.
 *
 * @package   Obix Class Extender System Plugin
 *
 * @since     1.2.0
 */
class ClassExtenderException extends RuntimeException
{
	/**
	 * Message types.
	 */
	const TYPE_MESSAGE = 0;
	const TYPE_NOTICE = 1;
	const TYPE_WARNING = 2;
	const TYPE_ERROR = 3;

	/**
	 * Get the name of a specific error code.
	 *
	 * @return  string  The type of message.
	 *
	 * @since   1.2.0
	 */
	public function getMessageType(): string
	{
		/**
		 * Joomla! message types, indexed by code.
		 *
		 * @var string[]
		 */
		static $messageTypes = [
			'message',
			'notice',
			'warning',
			'error'
		];

		return $messageTypes[$this->getCode()];
	}
}
