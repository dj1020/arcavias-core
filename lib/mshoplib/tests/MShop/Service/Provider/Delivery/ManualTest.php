<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2011
 * @license LGPLv3, http://www.arcavias.com/en/license
 */


/**
 * Test class for MShop_Service_Provider_Delivery_Manual.
 */
class MShop_Service_Provider_Delivery_ManualTest extends PHPUnit_Framework_TestCase
{
	private $_object;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$context = TestHelper::getContext();

		$serviceManager = MShop_Service_Manager_Factory::createManager( $context );

		$serviceItem = $serviceManager->createItem();

		$this->_object = new MShop_Service_Provider_Delivery_Manual( $context, $serviceItem );
	}


	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @access protected
	 */
	protected function tearDown()
	{
		unset( $this->_object );
	}


	public function testGetConfigBE()
	{
		$this->assertEquals( array(), $this->_object->getConfigBE() );
	}


	public function testGetConfigFE()
	{
		$orderManager = MShop_Order_Manager_Factory::createManager( TestHelper::getContext() );
		$basket = $orderManager->getSubManager( 'base' )->createItem();

		$this->assertEquals( array(), $this->_object->getConfigFE( $basket ) );
	}


	public function testProcess()
	{
		$manager = MShop_Order_Manager_Factory::createManager( TestHelper::getContext() );
		$order = $manager->createItem();
		$this->_object->process( $order );

		$this->assertEquals( MShop_Order_Item_Abstract::STAT_PROGRESS, $order->getDeliveryStatus() );
	}


	public function testSetConfigFE()
	{
		$item = MShop_Factory::createManager( TestHelper::getContext(), 'order/base/service' )->createItem();
		$this->_object->setConfigFE( $item, array( 'test.code' => 'abc', 'test.number' => 123 ) );

		$this->assertEquals( 2, count( $item->getAttributes() ) );
		$this->assertEquals( 'abc', $item->getAttribute( 'test.code', 'delivery' ) );
		$this->assertEquals( 123, $item->getAttribute( 'test.number', 'delivery' ) );
		$this->assertEquals( 'delivery', $item->getAttributeItem( 'test.code', 'delivery' )->getType() );
		$this->assertEquals( 'delivery', $item->getAttributeItem( 'test.number', 'delivery' )->getType() );
	}

}
