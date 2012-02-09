<?php
/** Definition of the nxcESITypeESI class.
 * @author FA
 * @since 2012-02-01 14:31
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 * @subpackage ESI-types
 */

/** This class implements an ESI type handler that outputs basic ESI includes
 * according to {@link http://www.w3.org/TR/2001/NOTE-esi-lang-20010804 the
 * standard}, using this extension's module view as the address of the content.
 *
 * This type is typically used when you have an edge proxy supporting the ESI
 * standard, e.g. Varnish or Akamai.
 * @link http://www.w3.org/TR/2001/NOTE-esi-lang-20010804 ESI Language
 * Specification 1.0
 * @package nxcESI
 * @subpackage ESI-types
 */
class nxcESITypeESI
{
	/** This is the header that will be sent if {@link $setDoESIHeader} is true.
	 */
	const DO_ESI_HEADER = 'X-Do-ESI: true';
	
	/** Whether or not we should send the Do-ESI header when something has been
	 * included.
	 * @var bool
	 */
	private $sendDoESIHeader = false;
	
	/** Whether or not we should add onerror="continue" to the ESI include tag.
	 * @var bool
	 */
	private $continueOnError = false;
	
	/** Constructor, initializes the class.
	 */
	public function __construct()
	{
		$sendDoESIHeader = 'false';
		$continueOnError = 'false';
		$ini = eZINI::instance( 'nxc_esi.ini' );
		$ini->assign( 'ESITypeESI', 'SendDoESIHeader', $sendDoESIHeader );
		$ini->assign( 'ESITypeESI', 'ContinueOnError', $continueOnError );
		$this->sendDoESIHeader = ( $sendDoESIHeader == 'true' );
		$this->continueOnError = ( $continueOnError == 'true' );
	}
	
	/** Get the text content to make the edge include the given template.
	 * @see nxcESI::getIncludeForTemplate()
	 */
	public function getIncludeForTemplate( $template, $keys )
	{
		$url = '/nxc_esi/include/template?template='.rawurlencode( $template );
		return $this->getIncludeTag( $url, $keys );
	}
	
	/** Get the text content to make the edge include the given method call.
	 * @see nxcESI::getIncludeForMethodCall()
	 */
	public function getIncludeForMethodCall( $methodInfo, $keys )
	{
		$url =
			'/nxc_esi/include/method'
			.( $methodInfo['static'] ? '-static' : '' )
			.'?class='.rawurlencode( $methodInfo['class'] )
			.'&method='.rawurlencode( $methodInfo['method'] )
		;
		return $this->getIncludeTag( $url, $keys );
	}
	
	/** Get an include tag for the given base URL and parameters.
	 *
	 * This performs the processing common to both types of includes.
	 * @param string $url The base of the URL that should be included.
	 * @param array $keys An associative array of GET parameters to add to the
	 * URL before including it.
	 * @return string The generated esi:include tag for the given URL.
	 */
	private function getIncludeTag( $url, $params )
	{
		if ( $this->sendDoESIHeader )
		{
			header( self::DO_ESI_HEADER );
		}
		nxcESIEAS::setUseESI( true );
		foreach ( $params as $key => $value )
		{
			$url .= '&'.rawurlencode( $key ).'='.rawurlencode( $value );
		}
		eZURI::transformURI( $url );
		$include = '<esi:include src="'.$url;
		if ( $this->continueOnError )
		{
			$include .= '" onerror="continue';
		}
		$include .= '"/>';
		return $include;
	}
}

?>
