<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2012
 * @license LGPLv3, http://www.arcavias.com/en/license
 * @package MShop
 * @subpackage Coupon
 */


/**
 * Abstract model for coupons.
 *
 * @package MShop
 * @subpackage Coupon
 */
abstract class MShop_Coupon_Provider_Abstract
{
	private $_context;
	private $_object;
	private $_item;
	private $_code = '';

	/**
	 * Initializes the coupon model.
	 *
	 * @param MShop_Context_Item_Interface $context Context object
	 * @param MShop_Coupon_Item_Interface $item Coupon item to set
	 * @param string $code Coupon code entered by the customer
	 */
	public function __construct( MShop_Context_Item_Interface $context, MShop_Coupon_Item_Interface $item, $code )
	{
		$this->_context = $context;
		$this->_item = $item;
		$this->_code = $code;
	}


	/**
	 * Updates the result of a coupon to the order base instance.
	 *
	 * @param MShop_Order_Item_Base_Interface $base Basic order of the customer
	 */
	public function updateCoupon( MShop_Order_Item_Base_Interface $base )
	{
		if( $this->_getObject()->isAvailable( $base ) !== true )
		{
			$base->deleteCoupon( $this->_code );
			return;
		}

		$this->deleteCoupon( $base );
		$this->addCoupon( $base );
	}


	/**
	 * Removes the result of a coupon from the order base instance.
	 *
	 * @param MShop_Order_Item_Base_Interface $base Basic order of the customer
	 */
	public function deleteCoupon( MShop_Order_Item_Base_Interface $base )
	{
		$base->deleteCoupon( $this->_code, true );
	}


	/**
	 * Tests if a coupon should be granted
	 *
	 * @param MShop_Order_Item_Base_Interface $base
	 */
	public function isAvailable( MShop_Order_Item_Base_Interface $base )
	{
		return true;
	}


	/**
	 * Sets the reference of the outside object.
	 *
	 * @param MShop_Coupon_Provider_Interface $object Reference to the outside provider or decorator
	 */
	public function setObject( MShop_Coupon_Provider_Interface $object )
	{
		$this->_object = $object;
	}


	/**
	 * Returns the stored context object.
	 *
	 * @return MShop_Context_Item_Interface Context object
	 */
	protected function _getContext()
	{
		return $this->_context;
	}


	/**
	 * Returns the coupon code the provider is responsible for.
	 *
	 * @return string Coupon code
	 */
	protected function _getCode()
	{
		return $this->_code;
	}


	/**
	 * Returns the configuration value from the service item specified by its key.
	 *
	 * @param string $key Configuration key
	 * @param mixed $default Default value if configuration key isn't available
	 * @return mixed Value from service item configuration
	 */
	protected function _getConfigValue( $key, $default = null )
	{
		$config = $this->_item->getConfig();

		if( isset( $config[$key] ) ) {
			return $config[$key];
		}

		return $default;
	}


	/**
	 * Returns the stored coupon item.
	 *
	 * @return MShop_Coupon_Item_Interface Coupon item
	 */
	protected function _getItem()
	{
		return $this->_item;
	}


	/**
	 * Returns the outmost decorator or a reference to the provider itself.
	 *
	 * @return MShop_Coupon_Provider_Interface Outmost object
	 */
	protected function _getObject()
	{
		if( isset( $this->_object ) ) {
			return $this->_object;
		}

		return $this;
	}


	/**
	 * Creates an order product from the product item.
	 *
	 * @param string $productCode Unique product code
	 * @param integer $quantity Number of products in basket
	 * @param string $warehouse Unique code of the warehouse the product is from
	 * @return MShop_Order_Item_Base_Product_Interface Ordered product
	 */
	protected function _createProduct( $productCode, $quantity = 1, $warehouse = 'default' )
	{
		$productManager = MShop_Factory::createManager( $this->_context, 'product' );
		$search = $productManager->createSearch( true );
		$search->setConditions( $search->compare( '==', 'product.code', $productCode ) );
		$products = $productManager->searchItems( $search, array( 'text', 'media', 'price' ) );

		if( ( $product = reset( $products ) ) === false ) {
			throw new MShop_Coupon_Exception( sprintf( 'No product with code "%1$s" found', $productCode ) );
		}

		$priceManager = MShop_Factory::createManager( $this->_context, 'price' );
		$prices = $product->getRefItems( 'price', 'default', 'default' );

		if( empty( $prices ) ) {
			$price = $priceManager->createItem();
		} else {
			$price = $priceManager->getLowestPrice( $prices, $quantity );
		}

		$orderBaseProductManager = MShop_Factory::createManager( $this->_context, 'order/base/product' );
		$orderProduct = $orderBaseProductManager->createItem();

		$orderProduct->copyFrom( $product );
		$orderProduct->setQuantity( $quantity );
		$orderProduct->setWarehouseCode( $warehouse );
		$orderProduct->setPrice( $price );
		$orderProduct->setFlags( MShop_Order_Item_Base_Product_Abstract::FLAG_IMMUTABLE );

		return $orderProduct;
	}


	/**
	 * Creates the order products for monetary rebates.
	 *
	 * @param MShop_Order_Item_Base_Interface Basket object
	 * @param string $productCode Unique product code
	 * @param float $rebate Rebate amount that should be granted
	 * @param integer $quantity Number of products in basket
	 * @param string $warehouse Unique code of the warehouse the product is from
	 * @return MShop_Order_Item_Base_Product_Interface[] Order products with monetary rebates
	 */
	protected function _createMonetaryRebateProducts( MShop_Order_Item_Base_Interface $base,
		$productCode, $rebate, $quantity = 1, $warehouse = 'default' )
	{
		$orderProducts = array();
		$prices = $this->_getPriceByTaxRate( $base );

		krsort( $prices );

		if( empty( $prices ) ) {
			$prices = array( '0.00' => MShop_Factory::createManager( $this->_getContext(), 'price' )->createItem() );
		}

		foreach( $prices as $taxrate => $price )
		{
			if( abs( $rebate ) < 0.01 ) {
				break;
			}

			$amount = $price->getValue() + $price->getCosts();

			if( $amount > 0 && $amount < $rebate )
			{
				$value = $price->getValue() + $price->getCosts();
				$rebate -= $value;
			}
			else
			{
				$value = $rebate;
				$rebate = '0.00';
			}

			$orderProduct = $this->_createProduct( $productCode, $quantity, $warehouse );

			$price = $orderProduct->getPrice();
			$price->setValue( -$value );
			$price->setRebate( $value );
			$price->setTaxRate( $taxrate );

			$orderProduct->setPrice( $price );

			$orderProducts[] = $orderProduct;
		}

		return $orderProducts;
	}


	/**
	 * Returns a list of tax rates and their price items for the given basket.
	 *
	 * @param MShop_Order_Item_Base_Interface $basket Basket containing the products, services, etc.
	 * @return array Associative list of tax rates as key and corresponding price items as value
	 */
	protected function _getPriceByTaxRate( MShop_Order_Item_Base_Interface $basket )
	{
		$taxrates = array();
		$manager = MShop_Factory::createManager( $this->_getContext(), 'price' );

		foreach( $basket->getProducts() as $product )
		{
			$price = $product->getPrice();
			$taxrate = $price->getTaxRate();

			if( !isset( $taxrates[$taxrate] ) ) {
				$taxrates[$taxrate] = $manager->createItem();
			}

			$taxrates[$taxrate]->addItem( $price, $product->getQuantity() );
		}

		try
		{
			$price = $basket->getService( 'delivery' )->getPrice();
			$taxrate = $price->getTaxRate();

			if( !isset( $taxrates[$taxrate] ) ) {
				$taxrates[$taxrate] = $manager->createItem();
			}

			$taxrates[$taxrate]->addItem( $price );
		}
		catch( Exception $e ) { ; } // if delivery service isn't available

		try
		{
			$price = $basket->getService( 'payment' )->getPrice();
			$taxrate = $price->getTaxRate();

			if( !isset( $taxrates[$taxrate] ) ) {
				$taxrates[$taxrate] = $manager->createItem();
			}

			$taxrates[$taxrate]->addItem( $price );
		}
		catch( Exception $e ) { ; } // if payment service isn't available

		return $taxrates;
	}
}
