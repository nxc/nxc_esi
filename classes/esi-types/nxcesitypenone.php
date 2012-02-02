<?php
/** Definition of the nxcESITypeNone class.
 * @author FA
 * @since 2012-02-01 14:25
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 * @subpackage ESI-types
 */

/** This class implements an ESI type handler that doesn't include anything at
 * all, and instead just discards the requested includes.
 *
 * This is mainly useful for testing, and is used as a default fallback when
 * the configured handler cannot be used, to make it obvious that something is
 * wrong.
 * @package nxcESI
 * @subpackage ESI-types
 */
class nxcESITypeNone
{
	/** Get the text content to make the edge include the given template.
	 * @see nxcESI::getIncludeForTemplate()
	 */
	public function getIncludeForTemplate( $template, $keys )
	{
		return '';
	}
	
	/** Get the text content to make the edge include the given method call.
	 * @see nxcESI::getIncludeForMethodCall()
	 */
	public function getIncludeForMethodCall( $methodInfo, $keys )
	{
		return '';
	}
}

?>
