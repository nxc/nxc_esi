<?php
/** Definition of the nxcESI class.
 * @author FA
 * @since 2012-01-31 19:24
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 */

/** This class implements some static helper functions.
 * @package nxcESI
 */
class nxcESI
{
	/** Cached instance of the ESI type handler, to avoid re-instantiating it
	 * for every es-include invocation.
	 * @var object Usually one of the nxcESIType* classes.
	 */
	private static $esiTypeHandler = null;
	
	/** Check that the given name is a valid key name.
	 * @param string $keyName The name to check if is valid for a key name.
	 * @return bool Whether or not the key name is valid.
	 */
	public static function validateKeyName( $keyName )
	{
		return !!preg_match( '/^[a-zA-Z0-9_-]+$/', $keyName );
	}
	
	/** Get the text content to make the edge include the given template.
	 * @param string $template The template that should be included.
	 * @param array $keys The cache keys (and template variables) for the
	 * included template.
	 * @return string|null The text content to put in the output to make the
	 * edge processor include the given template with the given keys,
	 * or null on error.
	 */
	public static function getIncludeForTemplate( $template, $keys )
	{
		if ( !self::isTemplateAllowed( $template ) )
		{
			eZDebug::writeWarning(
				'Tried to include a template that is not allowed.',
				__METHOD__
			);
			return null;
		}
		$handler = self::getESITypeHandler();
		$call = array( $handler, 'getIncludeForTemplate' );
		if ( !is_callable( $call ) )
		{
			eZDebug::writeError(
				'The ESI type handler cannot handle template includes.',
				__METHOD__
			);
			return null;
		}
		$content = call_user_func( $call, $template, $keys );
		if ( !is_string( $content ) && $content !== null )
		{
			eZDebug::writeError(
				'The ESI type handler returned an invalid value.',
				__METHOD__
			);
			return null;
		}
		return $content;
	}
	
	/** Get the text content to make the edge include the given method call.
	 * @param array $methodInfo Information on the method call to be included.
	 * This includes the keys 'class', 'method' and 'static'.
	 * @param array $keys The cache keys for the included method call.
	 * These will be provided to the method call as a parameter.
	 * @return string|null The text content to put in the output to make the
	 * edge processor include the given method call with the given keys,
	 * or null on error.
	 */
	public static function getIncludeForMethodCall( $methodInfo, $keys )
	{
		if (
			!self::isMethodAllowed(
				$methodInfo['class'], $methodInfo['method']
			)
		) {
			eZDebug::writeWarning(
				'Tried to include a method call that is not allowed.',
				__METHOD__
			);
			return null;
		}
		$handler = self::getESITypeHandler();
		$call = array( $handler, 'getIncludeForMethodCall' );
		if ( !is_callable( $call ) )
		{
			eZDebug::writeError(
				'The ESI type handler cannot handle method call includes.',
				__METHOD__
			);
			return null;
		}
		$content = call_user_func( $call, $methodInfo, $keys );
		if ( !is_string( $content ) && $content !== null )
		{
			eZDebug::writeError(
				'The ESI type handler returned an invalid value.',
				__METHOD__
			);
			return null;
		}
		return $content;
	}
	
	/** Get an instance of the configured ESI type handler class.
	 * @return object An instance of the configured ESI type handler.
	 */
	protected static function getESITypeHandler()
	{
		if ( self::$esiTypeHandler !== null )
		{
			return self::$esiTypeHandler;
		}
		$ini = eZINI::instance( 'nxc_esi.ini' );
		$handler = null;
		$ini->assign( 'ESIType', 'ESITypeHandler', $handler );
		if ( !is_string( $handler ) || $handler == '' )
		{
			eZDebug::writeError(
				'No ESI type handler is configured, falling back to default.',
				__METHOD__
			);
			$handler = 'nxcESITypeNone';
		}
		if ( !class_exists( $handler ) )
		{
			eZDebug::writeError(
				'Invalid ESI type handler in configuration, no such class: '
				.$handler,
				__METHOD__
			);
			$handler = 'nxcESITypeNone';
		}
		try
		{
			$handler = new $handler();
		}
		catch ( Exception $exception )
		{
			eZDebug::writeError(
				'Invalid ESI type handler in configuration,'
				.' the class could not be instantiated: '.$handler
				."\n".$exception->getMessage(),
				__METHOD__
			);
			$handler = new nxcESITypeNone();
		}
		self::$esiTypeHandler = $handler;
		return self::$esiTypeHandler;
	}
	
	/** Check if the given template is on the list of allowed templates.
	 * @param string $template The template to check if is allowed.
	 * @return bool Whether or not that template is allowed to be fetched.
	 */
	public static function isTemplateAllowed( $template )
	{
		$allowedTemplates = array();
		$ini = eZINI::instance( 'nxc_esi.ini' );
		$ini->assign( 'Permissions', 'AllowedTemplates', $allowedTemplates );
		if ( !is_array( $allowedTemplates ) )
		{
			eZDebug::writeError(
				'The list of allowed templates is invalid.',
				__METHOD__
			);
			return false;
		}
		return in_array( $template, $allowedTemplates );
	}
	
	/** Check if the given method is on the list of allowed methods.
	 * @param string $class The class of the method to check if is allowed.
	 * @param string $method The method to check if calls are allowed to.
	 * @return bool Whether or not it is allowed to call the given method.
	 */
	public static function isMethodAllowed( $class, $method )
	{
		$allowedMethods = array();
		$ini = eZINI::instance( 'nxc_esi.ini' );
		$ini->assign( 'Permissions', 'AllowedMethods', $allowedMethods );
		if ( !is_array( $allowedMethods ) )
		{
			eZDebug::writeError(
				'The list of allowed methods is invalid.',
				__METHOD__
			);
			return false;
		}
		return (
			in_array( $class, $allowedMethods ) ||
			in_array( $class.'::'.$method, $allowedMethods )
		);
	}
}

?>
