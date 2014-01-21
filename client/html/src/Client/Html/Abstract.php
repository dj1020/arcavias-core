<?php

/**
 * @copyright Copyright (c) Metaways Infosystems GmbH, 2012
 * @license LGPLv3, http://www.arcavias.com/en/license
 * @package Client
 * @subpackage Html
 */


/**
 * Common abstract class for all HTML client classes.
 *
 * @package Client
 * @subpackage Html
 */
abstract class Client_Html_Abstract
	implements Client_Html_Common_Client_Factory_Interface
{
	const CACHE_BODY = 1;
	const CACHE_HEADER = 2;

	private $_view;
	private $_context;
	private $_templatePaths;
	private $_cache = array();


	/**
	 * Initializes the class instance.
	 *
	 * @param MShop_Context_Item_Interface $context Context object
	 * @param array $templatePaths Associative list of the file system paths to the core or the extensions as key
	 * 	and a list of relative paths inside the core or the extension as values
	 */
	public function __construct( MShop_Context_Item_Interface $context, array $templatePaths )
	{
		$this->_context = $context;
		$this->_templatePaths = $templatePaths;
	}


	/**
	 * Tests if the output of is cachable.
	 *
	 * @param integer $what Header or body constant from Client_HTML_Abstract
	 * @return boolean True if the output can be cached, false if not
	 */
	public function isCachable( $what )
	{
		return false;
	}


	/**
	 * Returns the view object that will generate the HTML output.
	 *
	 * @return MW_View_Interface $view The view object which generates the HTML output
	 */
	public function getView()
	{
		if( !isset( $this->_view ) ) {
			throw new Client_Html_Exception( sprintf( 'No view available' ) );
		}

		return $this->_view;
	}


	/**
	 * Sets the view object that will generate the HTML output.
	 *
	 * @param MW_View_Interface $view The view object which generates the HTML output
	 * @return Client_Html_Interface Reference to this object for fluent calls
	 */
	public function setView( MW_View_Interface $view )
	{
		$this->_view = $view;
		return $this;
	}


	/**
	 * Transforms the client path to the appropriate class names.
	 *
	 * @param string $client Path of client names, e.g. "catalog/navigation"
	 * @return string Class names, e.g. "Catalog_Navigation"
	 */
	protected function _createSubNames( $client )
	{
		$names = explode( '/', $client );

		foreach( $names as $key => $subname )
		{
			if( empty( $subname ) || ctype_alnum( $subname ) === false ) {
				throw new Client_Html_Exception( sprintf( 'Invalid characters in client name "%1$s"', $client ) );
			}

			$names[$key] = ucfirst( $subname );
		}

		return implode( '_', $names );
	}


	/**
	 * Returns the context object.
	 *
	 * @return MShop_Context_Item_Interface Context object
	 */
	protected function _getContext()
	{
		return $this->_context;
	}


	/**
	 * Returns the sub-client given by its name.
	 *
	 * @param string $client Name of the sub-part in lower case (can contain a path like catalog/navigation)
	 * @param string|null $name Name of the implementation, will be from configuration (or Default) if null
	 * @return Client_Html_Interface Sub-part object
	 */
	protected function _createSubClient( $client, $name )
	{
		$client = strtolower( $client );

		if( $name === null )
		{
			$path = 'client/html/' . $client . '/name';
			$name = $this->_context->getConfig()->get( $path, 'Default' );
		}

		if( empty( $name ) || ctype_alnum( $name ) === false ) {
			throw new Client_Html_Exception( sprintf( 'Invalid characters in client name "%1$s"', $name ) );
		}

		$subnames = $this->_createSubNames( $client );

		$classname = 'Client_Html_'. $subnames . '_' . $name;
		$interface = 'Client_Html_Interface';

		if( class_exists( $classname ) === false ) {
			throw new Client_Html_Exception( sprintf( 'Class "%1$s" not available', $classname ) );
		}

		$subClient = new $classname( $this->_context, $this->_templatePaths );

		if( ( $subClient instanceof $interface ) === false ) {
			throw new Client_Html_Exception( sprintf( 'Class "%1$s" does not implement interface "%2$s"', $classname, $interface ) );
		}

		return $subClient;
	}


	/**
	 * Returns the parameters used by the html client.
	 *
	 * @param array $params Associative list of all parameters
	 * @return array Associative list of parameters used by the html client
	 */
	protected function _getClientParams( array $params )
	{
		$list = array();

		foreach( $params as $key => $value )
		{
			if( ( $key[0] === 'f' || $key[0] === 'l' || $key[0] === 'd' || $key[0] === 'a' ) && $key[1] === '-' ) {
				$list[$key] = $value;
			}
		}

		return $list;
	}


	/**
	 * Returns the configured sub-clients or the ones named in the default parameter if none are configured.
	 *
	 * @param string $confpath Path to the configuration that contains the configured sub-clients
	 * @param array $default List of sub-client names that should be used if no other configuration is available
	 * @return array List of sub-clients implementing Client_Html_Interface	ordered in the same way as the names
	 */
	protected function _getSubClients( $confpath, array $default )
	{
		$subclients = array();

		foreach( $this->_context->getConfig()->get( $confpath, $default ) as $name )
		{
			if( !isset( $this->_cache[$name] ) ) {
				$this->_cache[$name] = $this->getSubClient( $name );
			}

			$subclients[] = $this->_cache[$name];
		}

		return $subclients;
	}


	/**
	 * Returns the absolute path to the given template file.
	 * It uses the first one found from the configured paths in the manifest files, but in reverse order.
	 *
	 * @param string $file Relative file path segments and its name separated by slashes
	 * @param string|array $default Relative file name or list of file names to use when nothing else is configured
	 * @return Absolute path the to the template file
	 * @throws Client_Html_Exception If no template file was found
	 */
	protected function _getTemplate( $confpath, $default )
	{
		$ds = DIRECTORY_SEPARATOR;

		foreach( (array) $default as $fname )
		{
			$file = $this->_context->getConfig()->get( $confpath, $fname );

			foreach( array_reverse( $this->_templatePaths ) as $path => $relPaths )
			{
				foreach( $relPaths as $relPath )
				{
					$absPath = $path . $ds . $relPath . $ds . $file;
					if( $ds !== '/' ) {
						$absPath = str_replace( '/', $ds, $absPath );
					}

					if( is_file( $absPath ) ) {
						return $absPath;
					}
				}
			}
		}

		throw new Client_Html_Exception( sprintf( 'Template "%1$s" not available', $file ) );
	}


	/**
	 * Tests if the output of the sub-clients is cachable.
	 *
	 * @param integer $what Header or body constant from Client_HTML_Abstract
	 * @param string $confpath Path to the configuration that contains the configured sub-clients
	 * @param array $default List of sub-client names that should be used if no other configuration is available
	 * @return boolean True if the output can be cached, false if not
	 */
	protected function _isCachable( $what, $confpath, array $default )
	{
		foreach( $this->_getSubClients( $confpath, $default ) as $subclient )
		{
			if( $subclient->isCachable( $what ) === false ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Sets the necessary parameter values in the view.
	 *
	 * @param MW_View_Interface $view The view object which generates the HTML output
	 * @return MW_View_Interface Modified view object
	 */
	protected function _setViewParams( MW_View_Interface $view )
	{
		return $view;
	}


	/**
	 * Processes the input, e.g. store given values.
	 * A view must be available and this method doesn't generate any output
	 * besides setting view variables.
	 *
	 * @param string $confpath Path to the configuration that contains the configured sub-clients
	 * @param array $default List of sub-client names that should be used if no other configuration is available
	 */
	protected function _process( $confpath, array $default )
	{
		$view = $this->getView();

		foreach( $this->_getSubClients( $confpath, $default ) as $subclient )
		{
			$subclient->setView( $view );
			$subclient->process();
		}
	}


	/**
	 * Translates the plugin error codes to human readable error strings.
	 *
	 * @param array $codes Associative list of scope and object as key and error code as value
	 * @return array List of translated error messages
	 */
	protected function _translatePluginErrorCodes( array $codes )
	{
		$errors = array();
		$i18n = $this->_context->getI18n();

		foreach( $codes as $scope => $list )
		{
			foreach( $list as $object => $errcode )
			{
				$key = $scope . ( $scope !== 'product' ? '.' . $object : '' ) . '.' . $errcode;
				$errors[] = $i18n->dt( 'mshop/code', $key );
			}
		}

		return $errors;
	}
}
