<?php
/** Module definition for the nxc_esi module.
 * @author FA
 * @since 2012-01-31 16:00
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 */

$Module = array( 'name' => 'NXC ESI' );

$ViewList = array();
$ViewList['include'] = array(
	'script' => 'include.php',
	'ui_context' => 'include',
	'functions' => array( 'include' ),
	'params' => array( 'IncludeType' ),
);

$FunctionList = array();
$FunctionList['include'] = array();

?>
