<?php
/**
 * @package     Obix\ClassExtender
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Obix\ClassExtender;

use Joomla\CMS\Factory;
use Throwable;

/**
 * Class extender exception.
 *
 * @package   Obix Class Extender System Plugin
 *
 * @since       1.2.0
 */
class ClassExtenderException extends \RuntimeException
{
	/**
	 * Message types.
	 */
	const TYPE_MESSAGE = 0;
	const TYPE_NOTICE = 1;
	const TYPE_WARNING = 2;
	const TYPE_ERROR = 3;

	/**
	 * @return string
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