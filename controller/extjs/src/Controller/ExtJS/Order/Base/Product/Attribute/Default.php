<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2011
 * @license LGPLv3, http://www.arcavias.com/en/license
 * @package Controller
 * @subpackage ExtJS
 */


/**
 * ExtJS order base product attribute controller for admin interfaces.
 *
 * @package Controller
 * @subpackage ExtJS
 */
class Controller_ExtJS_Order_Base_Product_Attribute_Default
	extends Controller_ExtJS_Abstract
	implements Controller_ExtJS_Common_Interface
{
	private $_manager = null;


	/**
	 * Initializes the order base service attribute controller.
	 *
	 * @param MShop_Context_Item_Interface $context MShop context object
	 */
	public function __construct( MShop_Context_Item_Interface $context )
	{
		parent::__construct( $context, 'Order_Base_Product_Attribute' );

		$manager = MShop_Order_Manager_Factory::createManager( $context );
		$baseManager = $manager->getSubManager( 'base' );
		$productManager = $baseManager->getSubManager( 'product' );
		$this->_manager = $productManager->getSubManager( 'attribute' );
	}


	/**
	 * Creates a new order base product attribute item or updates an existing one or a list thereof.
	 *
	 * @param stdClass $params Associative array containing the order base service attribute properties
	 */
	public function saveItems( stdClass $params )
	{
		$this->_checkParams( $params, array( 'site', 'items' ) );
		$this->_setLocale( $params->site );

		$ids = array();
		$items = ( !is_array( $params->items ) ? array( $params->items ) : $params->items );

		foreach( $items as $entry )
		{
			$item = $this->_createItem( (array) $entry );
			$this->_manager->saveItem( $item );
			$ids[] = $item->getId();
		}

		$search = $this->_manager->createSearch();
		$search->setConditions( $search->compare( '==', 'order.base.product.attribute.id', $ids ) );
		$search->setSlice( 0, count( $ids ) );
		$items = $this->_toArray( $this->_manager->searchItems( $search ) );

		return array(
			'items' => ( !is_array( $params->items ) ? reset( $items ) : $items ),
			'success' => true,
		);
	}


	/**
	 * Creates a new order base product attribute item and sets the properties from the given array.
	 *
	 * @param array $entry Associative list of name and value properties using the "order.base.product.attribute" prefix
	 * @return MShop_Order_Item_Base_Product_Attribute_Interface Order product attribute item
	 */
	protected function _createItem( array $entry )
	{
		$item = $this->_manager->createItem();

		foreach( $entry as $name => $value )
		{
			switch( $name )
			{
				case 'order.base.product.attribute.id': $item->setId( $value ); break;
				case 'order.base.product.attribute.name': $item->setName( $value ); break;
				case 'order.base.product.attribute.code': $item->setCode( $value ); break;
				case 'order.base.product.attribute.value': $item->setValue( $value ); break;
				case 'order.base.product.attribute.ordprodid': $item->setProductId( $value ); break;
			}
		}

		return $item;
	}


	/**
	 * Returns the manager the controller is using.
	 *
	 * @return MShop_Common_Manager_Interface Manager object
	 */
	protected function _getManager()
	{
		return $this->_manager;
	}
}
