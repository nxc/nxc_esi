<?php
/** Definition of the nxcESITypePHP class.
 * @author FA
 * @since 2012-02-01 14:43
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 * @subpackage ESI-types
 */

/** This class implements an ESI type handler that does the includes in PHP
 * before returning, returning the included content directly instead of any
 * directive for some other include processor.
 *
 * This can be useful for development or other cases where no ESI or SSI
 * processor is available, but the content should still be included.
 *
 * Note that this handler may exhibit somewhat different behaviour than the
 * other handlers, as it uses the same PHP runtime as the original request,
 * and thus the same globals and static information - which can affect the
 * result quite a bit (due to such things as shared instances of objects).
 *
 * While I have tried to minimize these effects where I could, I cannot really
 * eliminate them for this handler.
 * @package nxcESI
 * @subpackage ESI-types
 */
class nxcESITypePHP
{
	/** Get the text content to make the edge include the given template.
	 * @see nxcESI::getIncludeForTemplate()
	 */
	public function getIncludeForTemplate( $template, $keys )
	{
		// Save the current EAS instance to restore it afterwards, so that the
		// {es-cache} settings of the included template doesn't affect the
		// header for the current response, which is for the full page.
		$easInstance = nxcESIEAS::restoreInstance( null );
		
		$tpl = eZTemplate::factory();
		
		do {
			$namespace = uniqid( 'nxcESI-', true );
		} while ( isset( $tpl->Variables[$namespace] ) );
		
		$oldValues = array();
		foreach ( $keys as $key => $value )
		{
			if ( $tpl->hasVariable( $key, $namespace ) )
			{
				$oldValues[$key] = $tpl->variable( $key, $namespace );
			}
			$tpl->setVariable( $key, $value, $namespace );
		}
		
		$content = array();
		$tpl->processURI(
			$template, true, $extraParameters, $content, $namespace, $namespace
		);
		if ( is_array( $content ) )
		{
			$content = implode( '', $content );
		}
		
		if ( !is_string( $content ) )
		{
			$content = '';
		}
		
		foreach ( $keys as $key => $value )
		{
			if ( array_key_exists( $key, $oldValues ) )
			{
				$tpl->setVariable( $key, $oldValues[$key], $namespace );
			}
			else
			{
				$tpl->unsetVariable( $key, $namespace );
			}
		}
		
		unset( $tpl->Variables[$namespace] );
		
		nxcESIEAS::restoreInstance( $easInstance );
		
		return $content;
	}
	
	/** Get the text content to make the edge include the given method call.
	 * @see nxcESI::getIncludeForMethodCall()
	 */
	public function getIncludeForMethodCall( $methodInfo, $keys )
	{
		// Save the current EAS instance to restore it afterwards, so that the
		// called method can use nxcESIEAS without affecting the header for the
		// current response, which is for the full page.
		$easInstance = nxcESIEAS::restoreInstance( null );
		
		if ( $methodInfo['static'] )
		{
			$call = array( $methodInfo['class'], $methodInfo['method'] );
		}
		else
		{
			$class = $methodInfo['class'];
			$call = array( new $class(), $methodInfo['method'] );
		}
		$content = call_user_func( $call, $keys );
		if ( !is_string( $content ) )
		{
			$content = '';
		}
		
		nxcESIEAS::restoreInstance( $easInstance );
		
		return $content;
	}
}

?>
