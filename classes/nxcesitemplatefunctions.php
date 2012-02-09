<?php
/** Definition of the nxcESITemplateFunctions class.
 * @author FA
 * @since 2012-01-31 17:54
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 */

/** This class implements the template functions this extension provides.
 * @package nxcESI
 */
class nxcESITemplateFunctions
{
	/** The tag name this object should use for the es-include function.
	 * @var string
	 */
	private $ESIncludeName;
	
	/** The tag name this object should use for the es-cache function.
	 * @var string
	 */
	private $ESCacheName;
	
	/** Construct an instance of this class, using the given name for the
	 * template functions handled by this object.
	 * @param string $esIncludeName The name to use for the es-include function.
	 * @param string $esCacheName The name to use for the es-cache function.
	 */
	public function __construct(
		$esIncludeName = 'es-include', $esCacheName = 'es-cache'
	) {
		$this->ESIncludeName = $esIncludeName;
		$this->ESCacheName = $esCacheName;
	}
	
	/** Get a list of the function names this object handles.
	 * Required for eZTemplate::registerFunctions.
	 * @return array List of function names handled by this object.
	 */
	public function functionList()
	{
		return array( $this->ESIncludeName, $this->ESCacheName );
	}
	
	/** Get whether or not the tag for this function has children,
	 * as opposed to being an empty tag with no end tag.
	 * This is used by the template parser.
	 * @return false
	 */
	public function hasChildren()
	{
		return false;
	}
	
	/** Handle the processing of one of our template functions.
	 */
	public function process(
		$tpl, &$textElements, $functionName,
		$functionChildren, $functionParameters, $functionPlacement,
		$rootNamespace, $currentNamespace
	) {
		if ( $functionName == $this->ESIncludeName )
		{
			return $this->processESIncludeFunction(
				$tpl, $textElements, $functionName,
				$functionChildren, $functionParameters, $functionPlacement,
				$rootNamespace, $currentNamespace
			);
		}
		if ( $functionName == $this->ESCacheName )
		{
			return $this->processESCacheFunction(
				$tpl, $textElements, $functionName,
				$functionChildren, $functionParameters, $functionPlacement,
				$rootNamespace, $currentNamespace
			);
		}
		eZDebug::writeError(
			'Cannot process unknown template function: '.$functionName,
			__METHOD__
		);
		return false;
	}
	
	/** Handle the processing of the es-cache template function.
	 * @see process()
	 */
	private function processESCacheFunction(
		$tpl, &$textElements, $functionName,
		$functionChildren, $functionParameters, $functionPlacement,
		$rootNamespace, $currentNamespace
	) {
		if ( isset( $functionParameters['ttl'] ) ) {
			$ttl = $tpl->elementValue(
				$functionParameters['ttl'], $rootNamespace, $currentNamespace,
				$functionPlacement
			);
			$parts = array();
			if (
				!preg_match( '/^(\d+\.?\d*|\.\d+)\s*([a-z]?)$/', $ttl, $parts )
			) {
				$tpl->warning(
					$this->ESCacheName,
					'Invalid TTL: '.$ttl,
					$functionPlacement
				);
			}
			else
			{
				$ttl = $parts[1];
				// NOTE: This switch uses fall-through on purpose.
				switch ( $parts[2] )
				{
					case 'w': $ttl *= 7; // weeks
					case 'd': $ttl *= 24; // days
					case 'h': $ttl *= 60; // hours
					case 'm': $ttl *= 60; // minutes
					case '': // Empty unit is defaulted to seconds.
					case 's': // $ttl *= 1; // seconds
						break;
					
					default:{
						$tpl->warning(
							$this->ESCacheName,
							'Unknown unit on TTL, assuming seconds: '.$parts[2],
							$functionPlacement
						);
					} break;
				}
				// Ensure we have an integer number of seconds.
				$ttl = round( $ttl );
				nxcESIEAS::setMaxAge( $ttl );
			}
		}
		if ( isset( $functionParameters['no-store'] ) ) {
			$noStore = $tpl->elementValue(
				$functionParameters['no-store'],
				$rootNamespace, $currentNamespace, $functionPlacement
			);
			if ( !is_bool( $noStore ) )
			{
				$tpl->warning(
					$this->ESCacheName,
					'Non-boolean value given to no-store',
					$functionPlacement
				);
			}
			else
			{
				nxcESIEAS::setNoStore( $noStore );
			}
		}
		if ( isset( $functionParameters['no-store-remote'] ) ) {
			$noStore = $tpl->elementValue(
				$functionParameters['no-store-remote'],
				$rootNamespace, $currentNamespace, $functionPlacement
			);
			if ( !is_bool( $noStore ) )
			{
				$tpl->warning(
					$this->ESCacheName,
					'Non-boolean value given to no-store-remote',
					$functionPlacement
				);
			}
			else
			{
				nxcESIEAS::setNoStoreRemote( $noStore );
			}
		}
		return true;
	}
	
	/** Handle the processing of the es-include template function.
	 * @see process()
	 */
	private function processESIncludeFunction(
		$tpl, &$textElements, $functionName,
		$functionChildren, $functionParameters, $functionPlacement,
		$rootNamespace, $currentNamespace
	) {
		if (
			!isset( $functionParameters['template'] ) &&
			!isset( $functionParameters['method'] )
		) {
			$tpl->warning(
				$this->ESIncludeName,
				'Missing parameter template or method',
				$functionPlacement
			);
			return false;
		}
		$keys = $this->extractKeys(
			$tpl, $functionParameters, $rootNamespace, $currentNamespace,
			$functionPlacement
		);
		if ( isset( $keys['template'] ) && trim( $keys['template'] ) != '' )
		{
			$template = $keys['template'];
			unset( $keys['template'] );
			
			if ( !nxcESI::isTemplateAllowed( $template ) )
			{
				$tpl->warning(
					$this->ESIncludeName,
					'Tried to include a template that is not allowed.',
					$functionPlacement
				);
				return false;
			}
			
			$content = nxcESI::getIncludeForTemplate( $template, $keys );
			if ( is_string( $content ) && $content != '' )
			{
				$textElements[] = $content;
				return true;
			}
		}
		elseif ( isset( $keys['method'] ) && trim( $keys['method'] ) != '' )
		{
			$method = $keys['method'];
			unset( $keys['method'] );
			
			$info = $this->parseMethod( $tpl, $method, $functionPlacement );
			if ( !is_array( $info ) )
			{
				return false;
			}
			
			if ( !nxcESI::isMethodAllowed( $info['class'], $info['method'] ) )
			{
				$tpl->warning(
					$this->ESIncludeName,
					'Tried to include a method call that is not allowed.',
					$functionPlacement
				);
				return false;
			}
			
			$content = nxcESI::getIncludeForMethodCall( $info, $keys );
			if ( is_string( $content ) && $content != '' )
			{
				$textElements[] = $content;
				return true;
			}
		}
		else
		{
			$tpl->warning(
				$this->ESIncludeName,
				'Empty parameter template or method',
				$functionPlacement
			);
		}
		return false;
	}
	
	/** Extract the cache keys, with values, from the function invocation.
	 * @return array Associative array of key name to value.
	 * @see process()
	 */
	private function extractKeys(
		$tpl, $functionParameters, $rootNamespace, $currentNamespace,
		$functionPlacement
	) {
		$keys = array();
		foreach ( $functionParameters as $paramName => $paramValue )
		{
			if ( !nxcESI::validateKeyName( $paramName ) )
			{
				$tpl->warning(
					$this->ESIncludeName,
					'Invalid key name: '.$paramName,
					$functionPlacement
				);
			}
			else
			{
				$value = $tpl->elementValue(
					$paramValue, $rootNamespace, $currentNamespace,
					$functionPlacement
				);
				if ( is_null( $value ) )
				{
					// Skip this key
				}
				elseif ( is_bool( $value ) )
				{
					$keys[$paramName] = ( $value ? 'true' : 'false' );
				}
				elseif ( is_string( $value ) || is_numeric( $value ) )
				{
					$keys[$paramName] = strval( $value );
				}
				else
				{
					$tpl->warning(
						$this->ESIncludeName,
						'Invalid value for key '.$paramName.': '.$value,
						$functionPlacement
					);
				}
			}
		}
		return $keys;
	}
	
	/** Parse the method string to get the class and method names, and check
	 * that they can be called.
	 * @param eZTemplate $tpl The template the parse is being done for.
	 * @param string $method The class::method string to parse into components.
	 * @return array|null An associative array with the components of the
	 * parsed string, or null on error.
	 */
	private function parseMethod( $tpl, $method, $functionPlacement = false )
	{
		$static = false;
		$parts = explode( '->', $method );
		if ( count( $parts ) < 2 )
		{
			$static = true;
			$parts = explode( '::', $method );
			if ( count( $parts ) < 2 )
			{
				$tpl->warning(
					$this->ESIncludeName,
					'Invalid method, missing class/method separator.',
					$functionPlacement
				);
				return null;
			}
		}
		if ( count( $parts ) > 2 )
		{
			$tpl->warning(
				$this->ESIncludeName,
				'Invalid method, extra class/method separators.',
				$functionPlacement
			);
			return null;
		}
		$class = $parts[0];
		$method = $parts[1];
		
		if ( !class_exists( $class ) )
		{
			$tpl->warning(
				$this->ESIncludeName,
				'Invalid method, no such class: '.$class,
				$functionPlacement
			);
			return null;
		}
		
		if ( !method_exists( $class, $method ) )
		{
			$tpl->warning(
				$this->ESIncludeName,
				'Invalid method, no such method in class '.$class.': '.$method,
				$functionPlacement
			);
			return null;
		}
		
		if ( $static )
		{
			$callable = is_callable( array( $class, $method ) );
		}
		else
		{
			$callable = is_callable( array( new $class(), $method ) );
		}
		if ( !$callable )
		{
			$tpl->warning(
				$this->ESIncludeName,
				'Invalid method, it is not callable: '
				.$class.( $static ? '::' : '->' ).$method,
				$functionPlacement
			);
			return null;
		}
		
		return array(
			'class' => $class,
			'method' => $method,
			'static' => $static,
		);
	}
}

?>
