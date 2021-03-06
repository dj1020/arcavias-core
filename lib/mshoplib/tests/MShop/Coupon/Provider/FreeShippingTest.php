<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2012
 * @license LGPLv3, http://www.arcavias.com/en/license
 */


/**
 * Test class for MShop_Coupon_Provider_FreeShipping.
 * Generated by PHPUnit on 2008-08-06 at 13:07:41.
 */
class MShop_Coupon_Provider_FreeShippingTest extends MW_Unittest_Testcase
{
	private $_object;
	private $_orderBase;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$context = TestHelper::getContext();


		$couponItem = MShop_Coupon_Manager_Factory::createManager( $context )->createItem();
		$couponItem->setConfig( array( 'freeshipping.productcode' => 'U:SD' ) );

		$this->_object = new MShop_Coupon_Provider_FreeShipping( $context, $couponItem, 'zyxw' );


		$delPrice = MShop_Price_Manager_Factory::createManager( $context )->createItem();
		$delPrice->setCosts( '5.00' );
		$delPrice->setCurrencyId( 'EUR' );

		$priceManager = MShop_Price_Manager_Factory::createManager( $context );
		$manager = MShop_Order_Manager_Factory::createManager( $context )
			->getSubManager( 'base' )->getSubManager('service');

		$delivery = $manager->createItem();
		$delivery->setCode( 'test' );
		$delivery->setType( 'delivery' );
		$delivery->setPrice( $delPrice );

		// Don't create order base item by createItem() as this would already register the plugins
		$this->_orderBase = new MShop_Order_Item_Base_Default( $priceManager->createItem(), $context->getLocale() );
		$this->_orderBase->setService( $delivery, 'delivery' );
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


	public function testAddCoupon()
	{
		$this->_object->addCoupon( $this->_orderBase );
		$coupons = $this->_orderBase->getCoupons();

		if( ( $product = reset( $coupons['zyxw'] ) ) === false ) {
			throw new Exception( 'No coupon available' );
		}

		// Test if service delivery item is available
		$this->_orderBase->getService( 'delivery' );

		$this->assertEquals( 1, count( $this->_orderBase->getProducts() ) );
		$this->assertEquals( '-5.00', $product->getPrice()->getCosts() );
		$this->assertEquals( '5.00', $product->getPrice()->getRebate() );
		$this->assertEquals( 'unitSupplier', $product->getSupplierCode() );
		$this->assertEquals( 'U:SD', $product->getProductCode() );
		$this->assertNotEquals( '', $product->getProductId() );
		$this->assertEquals( '', $product->getMediaUrl() );
		$this->assertEquals( 'Versandkosten Nachlass', $product->getName() );
	}


	public function testDeleteCoupon()
	{
		$this->_object->addCoupon( $this->_orderBase );
		$this->_object->deleteCoupon($this->_orderBase);

		$products = $this->_orderBase->getProducts();
		$coupons = $this->_orderBase->getCoupons();

		$this->assertEquals( 0, count( $products ) );
		$this->assertArrayNotHasKey( 'zyxw', $coupons );
	}


	public function testAddCouponInvalidConfig()
	{
		$context = TestHelper::getContext();

		$couponItem = MShop_Coupon_Manager_Factory::createManager( TestHelper::getContext() )->createItem();
		$object = new MShop_Coupon_Provider_FreeShipping( $context, $couponItem, 'zyxw' );

		$this->setExpectedException( 'MShop_Coupon_Exception' );
		$object->addCoupon( $this->_orderBase );
	}


	public function testIsAvailable()
	{
		$this->assertTrue( $this->_object->isAvailable( $this->_orderBase ) );
	}

}
