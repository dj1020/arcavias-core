<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */

class Perf_Product_Import_Csv_DefaultTest extends MW_Unittest_Testcase
{
	private $_context;


	protected function setUp()
	{
		$this->_context = TestHelper::getContext('unitperf');

		$config = $this->_context->getConfig();
		$config->set( 'controller/jobs/product/import/csv/location', 'tmp/product-import.zip' );
		$config->set( 'controller/jobs/product/import/csv/container/type', 'Zip' );
		$config->set( 'controller/jobs/product/import/csv/container/content', 'CSV' );
		$config->set( 'controller/jobs/product/import/csv/mapping', $this->_getMapping() );

		$container = MW_Container_Factory::getContainer( 'tmp/product-import.zip', 'Zip', 'CSV', array() );

		$content = $container->create( 'product.csv' );

		for( $i = 0; $i < 1000; $i++ )
		{
			$data = array();

			$data = $this->_addProduct( $data, $i );
			$data = $this->_addText( $data, $i );
			$data = $this->_addMedia( $data, $i );
			$data = $this->_addPrice( $data, $i );
			$data = $this->_addAttribute( $data, $i );
			$data = $this->_addProductRef( $data, $i );
			$data = $this->_addProperty( $data, $i );

			$content->add( $data );
		}

		$container->add( $content );

		$container->close();
	}


	protected function tearDown()
	{
		$this->_cleanupAttribute();
		$this->_cleanupMedia();
		$this->_cleanupText();
		$this->_cleanupPrice();
		$this->_cleanupProduct();

		unlink( 'tmp/product-import.zip' );
	}


	public function testImport()
	{
		$arcavias = TestHelper::getArcavias();
		$cntl = Controller_Jobs_Product_Import_Csv_Factory::createController( $this->_context, $arcavias, 'Default' );

		$start = microtime( true );

		$cntl->run();

		$stop = microtime( true );
		echo "\n    product import CSV: " . ( ( $stop - $start ) ) . " sec\n";
	}


	protected function _addAttribute( array $data, $cnt )
	{
		$data[] = 'length'; // type
		$data[] = 'import-' . ($cnt % 30); // code
		$data[] = 'width'; // type
		$data[] = 'import-' . ($cnt % 30); // code
		$data[] = 'size'; // type
		$data[] = 'import-' . ($cnt % 5); // code

		return $data;
	}


	protected function _addMedia( array $data, $cnt )
	{
		$data[] = "/path/to/image-$cnt.jpg"; // url

		return $data;
	}


	protected function _addPrice( array $data, $cnt )
	{
		$data[] = 1; // quantity
		$data[] = 'import-' . $cnt; // label
		$data[] = "$cnt.00"; // value
		$data[] = '20.00'; //tax rate

		return $data;
	}


	protected function _addProduct( array $data, $cnt )
	{
		$data[] = 'import-' . $cnt; // code
		$data[] = 'import-' . $cnt; // label
		$data[] = 'default'; // type
		$data[] = 0; //status

		return $data;
	}


	protected function _addProductRef( array $data, $cnt )
	{
		$data[] = 'import-' . ($cnt % 100); // code
		$data[] = 'suggestion'; // type

		return $data;
	}


	protected function _addProperty( array $data, $cnt )
	{
		$data[] = 'package-weight'; // type
		$data[] = '0.5'; // value

		return $data;
	}


	protected function _addText( array $data, $cnt )
	{
		$data[] = 'name'; // type
		$data[] = 'import-name-' . $cnt; //content
		$data[] = 'short'; // type
		$data[] = 'import-short-' . $cnt; //content
		$data[] = 'long'; // type
		$data[] = 'import-long-' . $cnt; //content

		return $data;
	}


	protected function _cleanupAttribute()
	{
		$manager = MShop_Attribute_Manager_Factory::createManager( $this->_context );

		$search = $manager->createSearch();
		$expr = array(
			$search->compare( '==', 'attribute.domain', 'product' ),
			$search->compare( '==', 'attribute.label', 'import-%' ),
		);
		$search->setConditions( $search->combine( '&&', $expr ) );
		$search->setSortations( array( $search->sort( '+', 'attribute.id' ) ) );

		$start = 0;

		do
		{
			$result = $manager->searchItems( $search );
			$manager->deleteItems( array_keys( $result ) );

			$count = count( $result );
			$start += $count;
			$search->setSlice( $start );
		}
		while( $count == $search->getSliceSize() );
	}


	protected function _cleanupMedia()
	{
		$manager = MShop_Media_Manager_Factory::createManager( $this->_context );

		$search = $manager->createSearch();
		$expr = array(
			$search->compare( '==', 'media.domain', 'product' ),
			$search->compare( '==', 'media.label', 'import-%' ),
		);
		$search->setConditions( $search->combine( '&&', $expr ) );
		$search->setSortations( array( $search->sort( '+', 'media.id' ) ) );

		$start = 0;

		do
		{
			$result = $manager->searchItems( $search );
			$manager->deleteItems( array_keys( $result ) );

			$count = count( $result );
			$start += $count;
			$search->setSlice( $start );
		}
		while( $count == $search->getSliceSize() );
	}


	protected function _cleanupPrice()
	{
		$manager = MShop_Price_Manager_Factory::createManager( $this->_context );

		$search = $manager->createSearch();
		$expr = array(
				$search->compare( '==', 'price.domain', 'product' ),
				$search->compare( '==', 'price.label', 'import-%' ),
		);
		$search->setConditions( $search->combine( '&&', $expr ) );
		$search->setSortations( array( $search->sort( '+', 'price.id' ) ) );

		$start = 0;

		do
		{
			$result = $manager->searchItems( $search );
			$manager->deleteItems( array_keys( $result ) );

			$count = count( $result );
			$start += $count;
			$search->setSlice( $start );
		}
		while( $count == $search->getSliceSize() );
	}


	protected function _cleanupProduct()
	{
		$manager = MShop_Product_Manager_Factory::createManager( $this->_context );

		$search = $manager->createSearch();
		$search->setConditions( $search->compare( '==', 'product.code', 'import-%' ) );
		$search->setSortations( array( $search->sort( '+', 'product.id' ) ) );

		$start = 0;

		do
		{
			$result = $manager->searchItems( $search );
			$manager->deleteItems( array_keys( $result ) );

			$count = count( $result );
			$start += $count;
			$search->setSlice( $start );
		}
		while( $count == $search->getSliceSize() );
	}


	protected function _cleanupText()
	{
		$manager = MShop_Text_Manager_Factory::createManager( $this->_context );

		$search = $manager->createSearch();
		$expr = array(
			$search->compare( '==', 'text.domain', 'product' ),
			$search->compare( '==', 'text.label', 'import-%' ),
		);
		$search->setConditions( $search->combine( '&&', $expr ) );
		$search->setSortations( array( $search->sort( '+', 'text.id' ) ) );

		$start = 0;

		do
		{
			$result = $manager->searchItems( $search );
			$manager->deleteItems( array_keys( $result ) );

			$count = count( $result );
			$start += $count;
			$search->setSlice( $start );
		}
		while( $count == $search->getSliceSize() );
	}


	protected function _getMapping()
	{
		return array(
			'item' => array(
				0 => 'product.code',
				1 => 'product.label',
				2 => 'product.type',
				3 => 'product.status',
			),
			'text' => array(
				4 => 'text.type',
				5 => 'text.content',
				6 => 'text.type',
				7 => 'text.content',
				8 => 'text.type',
				9 => 'text.content',
			),
			'media' => array(
				10 => 'media.url',
			),
			'price' => array(
				11 => 'price.quantity',
				12 => 'price.label',
				13 => 'price.value',
				14 => 'price.taxrate',
			),
			'attribute' => array(
				15 => 'attribute.type',
				16 => 'attribute.code',
				17 => 'attribute.type',
				18 => 'attribute.code',
				19 => 'attribute.type',
				20 => 'attribute.code',
			),
			'product' => array(
				21 => 'product.code',
				22 => 'product.list.type',
			),
			'property' => array(
				23 => 'product.property.type',
				24 => 'product.property.value',
			),
		);
	}
}
