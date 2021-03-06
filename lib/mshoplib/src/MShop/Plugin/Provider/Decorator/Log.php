<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 * @package MShop
 * @subpackage Plugin
 */


/**
 * Logging and tracing for plugins.
 *
 * @package MShop
 * @subpackage Plugin
 */
class MShop_Plugin_Provider_Decorator_Log extends MShop_Plugin_Provider_Decorator_Abstract
{
	/**
	 * Subscribes itself to a publisher
	 *
	 * @param MW_Observer_Publisher_Interface $p Object implementing publisher interface
	 */
	public function register( MW_Observer_Publisher_Interface $p )
	{
		$this->_getContext()->getLogger()->log( 'Plugin: ' . __METHOD__, MW_Logger_Abstract::DEBUG );

		$this->_getProvider()->register( $p );
	}


	/**
	 * Receives a notification from a publisher object
	 *
	 * @param MW_Observer_Publisher_Interface $order Shop basket instance implementing publisher interface
	 * @param string $action Name of the action to listen for
	 * @param mixed $value Object or value changed in publisher
	 */
	public function update( MW_Observer_Publisher_Interface $order, $action, $value = null )
	{
		$msg = 'Plugin: ' . __METHOD__ . ', action: ' . $action . ( is_scalar( $value ) ? ', value: ' . $value : '' );
		$this->_getContext()->getLogger()->log( $msg, MW_Logger_Abstract::DEBUG );

		return $this->_getProvider()->update( $order, $action, $value );
	}
}
