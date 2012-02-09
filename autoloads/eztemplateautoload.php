<?php
/** Definition of the template functions for this extension.
 * @author FA
 * @since 2012-01-31 17:50
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 */

$eZTemplateFunctionArray = array();
$eZTemplateFunctionArray[] = array(
	'class' => 'nxcESITemplateFunctions',
	'function_names' => array( 'es-include', 'es-cache' ),
);

?>
