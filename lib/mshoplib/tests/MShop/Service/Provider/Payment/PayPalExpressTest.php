<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2012
 * @license LGPLv3, http://www.arcavias.com/en/license
 */


/**
 * Test class for MShop_Service_Provider_Payment_PostPay.
 */
class MShop_Service_Provider_Payment_PayPalExpressTest extends MW_Unittest_Testcase
{
	private $_context;
	private $_object;
	private $_serviceItem;
	private $_order;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$this->_context = TestHelper::getContext();
		$serviceManager = MShop_Service_Manager_Factory::createManager( $this->_context );

		$search = $serviceManager->createSearch();
		$search->setConditions( $search->compare('==', 'service.code', 'paypalexpress') );

		$serviceItems = $serviceManager->searchItems( $search );

		if( ( $this->_serviceItem = reset( $serviceItems ) ) === false ) {
			throw new Exception( 'No paypalexpress service item available' );
		}

		$this->_object = new MShop_Service_Provider_Payment_PayPalExpress( $this->_context, $this->_serviceItem );


		$orderManager = MShop_Order_Manager_Factory::createManager( $this->_context );

		$search = $orderManager->createSearch();
		$expr = array(
			$search->compare( '==', 'order.type', MShop_Order_Item_Abstract::TYPE_WEB ),
			$search->compare( '==', 'order.statuspayment', MShop_Order_Item_Abstract::PAY_AUTHORIZED )
		);
		$search->setConditions( $search->combine( '&&', $expr ) );
		$orderItems = $orderManager->searchItems( $search );

		if( ( $this->_order = reset( $orderItems ) ) === false ) {
			throw new Exception( sprintf('No Order found with statuspayment "%1$s" and type "%2$s"', MShop_Order_Item_Abstract::PAY_AUTHORIZED, MShop_Order_Item_Abstract::TYPE_WEB ) );
		}


		$this->_context->getConfig()->set( 'classes/order/manager/name', 'MockPayPal' );
		$orderMock = $this->getMock( 'MShop_Order_Manager_Default', array( 'saveItem' ), array( $this->_context ) );
		MShop_Order_Manager_Factory::injectManager( 'MShop_Order_Manager_MockPayPal', $orderMock );
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
		unset( $this->_serviceItem );
		unset( $this->_order );
	}


	public function testCheckConfigBE()
	{
		$attributes = array(
			'paypalexpress.ApiUsername' => 'user',
			'paypalexpress.AccountEmail' => 'user@test.de',
			'paypalexpress.ApiPassword' => 'pw',
			'paypalexpress.ApiSignature' => '1df23eh67',
			'payment.url-cancel' => 'http://cancelUrl',
			'payment.url-success' => 'http://returnUrl'
		);

		$result = $this->_object->checkConfigBE( $attributes );

		$this->assertEquals( 12, count( $result ) );
		$this->assertEquals( null, $result['paypalexpress.ApiUsername'] );
		$this->assertEquals( null, $result['paypalexpress.AccountEmail'] );
		$this->assertEquals( null, $result['paypalexpress.ApiPassword'] );
		$this->assertEquals( null, $result['paypalexpress.ApiSignature'] );
		$this->assertEquals( null, $result['payment.url-cancel'] );
		$this->assertEquals( null, $result['payment.url-success'] );
	}

	public function testIsImplemented()
	{
		$this->assertTrue( $this->_object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_CANCEL ) );
		$this->assertTrue( $this->_object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_CAPTURE ) );
		$this->assertTrue( $this->_object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_QUERY ) );
		$this->assertTrue( $this->_object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_REFUND ) );
	}


	public function testProcess()
	{
		$what = array( 'PAYMENTREQUEST_0_AMT' => 18.50 );
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=process method error';
		$success = '&ACK=Success&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&TOKEN=UT-99999999';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$helperForm = $this->_object->process( $this->_order );

		$orderManager = MShop_Order_Manager_Factory::createManager( $this->_context );
		$orderBaseManager = $orderManager->getSubManager( 'base' );

		$refOrderBase = $orderBaseManager->load( $this->_order->getBaseId() );

		$attributes = $refOrderBase->getService( 'payment' )->getAttributes();

		$attributeList = array();
		foreach( $attributes as $attribute ) {
			$attributeList[ $attribute->getCode() ] = $attribute;
		}

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $helperForm );
		$this->assertEquals( 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&useraction=commit&token=UT-99999999', $helperForm->getUrl() );
		$this->assertEquals( 'POST', $helperForm->getMethod() );
		$this->assertEquals( array(), $helperForm->getValues() );

		$testData = array(
			'TOKEN' => 'UT-99999999'
		);

		foreach( $testData AS $key => $value ) {
			$this->assertEquals( $attributeList[ $key ]->getValue(), $testData[ $key ] );
		}
	}


	public function testUpdateSync()
	{
		//DoExpressCheckout

		$what = array( 'TOKEN' => 'UT-99999999' );

		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=updatesync method error';
		$success = '&TOKEN=UT-99999999&CORRELATIONID=1234567890&ACK=Success&VERSION=87.0&BUILD=3136725&PAYERID=PaypalUnitTestBuyer&TRANSACTIONID=111111110&PAYMENTSTATUS=Pending&PENDINGREASON=authorization&INVNUM='.$this->_order->getId();

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$orderManager = MShop_Order_Manager_Factory::createManager( $this->_context );
		$orderBaseManager = $orderManager->getSubManager( 'base' );

		$response = array(
			'token' => 'UT-99999999',
			'PayerID' => 'PaypalUnitTestBuyer',
			'orderid' => $this->_order->getId()
		);

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $this->_object->updateSync( $response ) );

		//IPN Call
		$price = $orderBaseManager->getItem( $this->_order->getBaseId() )->getPrice();
		$amount = $price->getValue() + $price->getCosts();
		$what = array(
			'residence_country' => 'US',
			'address_city' => 'San+Jose',
			'first_name' => 'John',
			'payment_status' => 'Completed',
			'invoice' => $this->_order->getId(),
			'txn_id' => '111111111',
			'payment_amount' => $amount,
			'receiver_email' => 'selling2@metaways.de',
		);
		$error = 'INVALID';
		$success = 'VERIFIED';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );


		$response = array (
			'residence_country' => 'US',
			'receiver_email' => 'selling2@metaways.de',
			'address_city' => 'San+Jose',
			'first_name' => 'John',
			'payment_status' => 'Completed',
			'invoice' => $this->_order->getId(),
			'txn_id' => '111111111',
			'payment_amount' => $amount
		);
		$testData = array(
			'TRANSACTIONID' => '111111111',
			'PAYERID' => 'PaypalUnitTestBuyer',
			'111111110' => 'Pending',
			'111111111' => 'Completed'
		);

		$orderItem = $this->_object->updateSync( $response );
		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $orderItem );

		$refOrderBase = $orderBaseManager->load( $this->_order->getBaseId(), MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );
		$attributes = $refOrderBase->getService( 'payment' )->getAttributes();
		$attrManager = $orderBaseManager->getSubManager('service')->getSubManager('attribute');

		$attributeList = array();
		foreach( $attributes as $attribute ){
			//remove attr where txn ids as keys, because next test with same txn id would fail
			if( $attribute->getCode() === '111111110' || $attribute->getCode() === '111111111' ) {
				$attrManager->deleteItem( $attribute->getId() );
			}

			$attributeList[ $attribute->getCode() ] = $attribute;
		}

		foreach( $testData AS $key => $value ) {
			$this->assertEquals( $attributeList[ $key ]->getValue(), $testData[ $key ] );
		}

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_RECEIVED, $orderItem->getPaymentStatus() );
	}


	public function testRefund()
	{
		$what = array(
			'METHOD' => 'RefundTransaction',
			'REFUNDSOURCE' => 'instant',
			'REFUNDTYPE' => 'Full',
			'TRANSACTIONID' => '111111111',
			'INVOICEID' => $this->_order->getId()
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=refund method error';
		$success = 'REFUNDTRANSACTIONID=88888888&FEEREFUNDAMT=2.00&TOTALREFUNDAMT=24.00&CURRENCYCODE=EUR&REFUNDSTATUS=delayed&PENDINGREASON=echeck&CORRELATIONID=1234567890&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->_object->refund( $this->_order );

		$testData = array(
			'TOKEN' => 'UT-99999999',
			'TRANSACTIONID' => '111111111',
			'REFUNDTRANSACTIONID' => '88888888'
		);

		$orderManager = MShop_Order_Manager_Factory::createManager( $this->_context );
		$orderBaseManager = $orderManager->getSubManager( 'base' );

		$refOrderBase = $orderBaseManager->load( $this->_order->getBaseId() );
		$attributes = $refOrderBase->getService( 'payment' )->getAttributes();

		$attributeList = array();
		foreach( $attributes as $attribute ){
			$attributeList[ $attribute->getCode() ] = $attribute;
		}

		foreach( $testData AS $key => $value ) {
			$this->assertEquals( $attributeList[ $key ]->getValue(), $testData[ $key ] );
		}

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_REFUND, $this->_order->getPaymentStatus() );
	}


	public function testCapture()
	{
		$orderManager = MShop_Order_Manager_Factory::createManager( $this->_context );
		$orderBaseManager = $orderManager->getSubManager('base');
		$baseItem = $orderBaseManager->getItem( $this->_order->getBaseId() );

		$what = array(
			'METHOD' => 'DoCapture',
			'COMPLETETYPE' => 'Complete',
			'AUTHORIZATIONID' => '111111111',
			'INVNUM' => $this->_order->getId(),
			'CURRENCYCODE' => $baseItem->getPrice()->getCurrencyId(),
			'AMT' => ( $baseItem->getPrice()->getValue() + $baseItem->getPrice()->getCosts() )
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=capture method error';
		$success = 'AUTHORIZATIONID=112233&TRANSACTIONID=111111111&PARENTTRANSACTIONID=12212AD&TRANSACTIONTYPE=express-checkout&AMT=22.30&FEEAMT=3.33&PAYMENTSTATUS=Completed&PENDINGREASON=None&CORRELATIONID=1234567890&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->_object->capture( $this->_order );

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_RECEIVED, $this->_order->getPaymentStatus() );
	}

	public function testQueryPaymentReceived()
	{
		$what = array(
			'METHOD' => 'GetTransactionDetails',
			'TRANSACTIONID' => '111111111'
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=query payment received test method error';
		$success = 'SHIPPINGCALCULATIONMODE=Callback&INSURANCEOPTIONSELECTED=false&RECEIVERID=unit_1340199666_biz_api1.yahoo.de&PAYERID=unittest&PAYERSTATUS=verified&COUNTRYCODE=DE&FIRSTNAME=Unit&LASTNAME=Test&SHIPTOSTREET=Unitteststr. 11&TRANSACTIONID=111111111&PARENTTRANSACTIONID=111111111&TRANSACTIONTYPE=express-checkout&AMT=22.50CURRENCYCODE=EUR&FEEAMT=4.44&PAYMENTSTATUS=Completed&PENDINGREASON=None&INVNUM=34&CORRELATIONID=1f4b8e2c86ead&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->_object->query( $this->_order );

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_RECEIVED, $this->_order->getPaymentStatus() );
	}


	public function testQueryPaymentRefused()
	{
		$what = array(
			'METHOD' => 'GetTransactionDetails',
			'TRANSACTIONID' => '111111111',
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=query payment refused test method error';
		$success = 'SHIPPINGCALCULATIONMODE=Callback&INSURANCEOPTIONSELECTED=false&RECEIVERID=unit_1340199666_biz_api1.yahoo.de&PAYERID=unittest&PAYERSTATUS=verified&COUNTRYCODE=DE&FIRSTNAME=Unit&LASTNAME=Test&SHIPTOSTREET=Unitteststr. 11&TRANSACTIONID=111111111&PARENTTRANSACTIONID=111111111&TRANSACTIONTYPE=express-checkout&AMT=22.50CURRENCYCODE=EUR&FEEAMT=4.44&PAYMENTSTATUS=Expired&PENDINGREASON=None&INVNUM=34&CORRELATIONID=1f4b8e2c86ead&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->_object->query( $this->_order );

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_REFUSED, $this->_order->getPaymentStatus() );
	}


	public function testCancel()
	{
		$what = array(
			'METHOD' => 'DoVoid',
			'AUTHORIZATIONID' => '111111111',
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=cancel test method error';
		$success = 'CORRELATIONID=1234567890&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->_object->cancel( $this->_order );

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_CANCELED, $this->_order->getPaymentStatus() );
	}


	public function testQueryPaymentAuthorized()
	{
		$what = array(
			'METHOD' => 'GetTransactionDetails',
			'TRANSACTIONID' => '111111111',
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=query payment authorized test method error';
		$success = 'SHIPPINGCALCULATIONMODE=Callback&INSURANCEOPTIONSELECTED=false&RECEIVERID=unit_1340199666_biz_api1.yahoo.de&PAYERID=unittest&PAYERSTATUS=verified&COUNTRYCODE=DE&FIRSTNAME=Unit&LASTNAME=Test&SHIPTOSTREET=Unitteststr. 11&TRANSACTIONID=111111111&PARENTTRANSACTIONID=111111111&TRANSACTIONTYPE=express-checkout&AMT=22.50CURRENCYCODE=EUR&FEEAMT=4.44&PAYMENTSTATUS=Pending&PENDINGREASON=authorization&INVNUM=34&CORRELATIONID=1234567890&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->_object->query( $this->_order );

		$this->assertEquals( MShop_Order_Item_Abstract::PAY_AUTHORIZED, $this->_order->getPaymentStatus() );
	}


	public function testWrongAuthorization()
	{
		$what = array(
			'VERSION' => '87.0',
			'SIGNATURE' => 'signature',
			'USER' => 'name',
			'PWD' => 'pw',
		);
		$error = '&ACK=Error&VERSION=87.0&BUILD=3136725&CORRELATIONID=1234567890&L_ERRORCODE0=0000&L_SHORTMESSAGE0=wrong authorization test method error';
		$success = 'SHIPPINGCALCULATIONMODE=Callback&INSURANCEOPTIONSELECTED=false&RECEIVERID=unit_1340199666_biz_api1.yahoo.de&PAYERID=unittest&PAYERSTATUS=verified&COUNTRYCODE=DE&FIRSTNAME=Unit&LASTNAME=Test&SHIPTOSTREET=Unitteststr. 11&TRANSACTIONID=111111111&PARENTTRANSACTIONID=111111111&TRANSACTIONTYPE=express-checkout&AMT=22.50CURRENCYCODE=EUR&FEEAMT=4.44&PAYMENTSTATUS=Pending&PENDINGREASON=authorization&INVNUM=34&CORRELATIONID=1234567890&ACK=Success&VERSION=87.0&BUILD=3136725';

		$com = new MW_Communication_TestPayPalExpress();
		$com->addRule( $what, $error, $success );
		$this->_object->setCommunication( $com );

		$this->setExpectedException( 'MShop_Service_Exception' );
		$this->_object->process( $this->_order );
	}
}