<?php
/** Module view that outputs specified HTML fragments for inclusion using ESI.
 * @author FA
 * @since 2012-01-31 16:00
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 */

$module = $Params['Module'];

$includeType = $Params['IncludeType'];

$http = eZHTTPTool::instance();

$keys = array();
$keysHeader = '';
foreach ( $http->attribute( 'get' ) as $key => $value )
{
	if ( nxcESI::validateKeyName( $key ) )
	{
		if ( is_string( $value ) || is_numeric( $value ) )
		{
			$keys[$key] = strval( $value );
			$keysHeader .= ' '.rawurlencode( $key ).'='.rawurlencode( $value );
		}
	}
}
header( 'X-NXC-ESI-Keys:'.$keysHeader );

switch ( $includeType )
{
	case 'empty':
	{
		eZExecution::cleanExit();
	} break;
	
	case 'template':
	case 'template-in-pagelayout':
	{
		if ( !isset( $keys['template'] ) || trim( $keys['template'] ) == '' )
		{
			eZDebug::writeError(
				'Tried to include a template without specifying which.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		$template = $keys['template'];
		unset( $keys['template'] );
		
		if ( !nxcESI::isTemplateAllowed( $template ) )
		{
			eZDebug::writeError(
				'Tried to include a template that is not allowed.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		
		header( 'X-NXC-ESI-Template: '.rawurlencode( $template ) );
		
		$tpl = eZTemplate::factory();
		foreach ( $keys as $key => $value )
		{
			$tpl->setVariable( $key, $value );
		}
		$content = $tpl->fetch( $template );
		
		if ( is_string( $content ) )
		{
			if ( $includeType == 'template-in-pagelayout' )
			{
				return array( 'content' => $content );
			}
			echo $content;
		}
		
		eZExecution::cleanExit();
	} break;
	
	case 'method':
	case 'method-static':
	{
		if ( !isset( $keys['class'] ) || trim( $keys['class'] ) == '' )
		{
			eZDebug::writeError(
				'Tried to include a method call without specifying the class.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		if ( !isset( $keys['method'] ) || trim( $keys['method'] ) == '' )
		{
			eZDebug::writeError(
				'Tried to include a method call without specifying the method.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		$class = $keys['class'];
		$method = $keys['method'];
		unset( $keys['class'], $keys['method'] );
		
		if ( !nxcESI::isMethodAllowed( $class, $method ) )
		{
			eZDebug::writeError(
				'Tried to include a method call that is not allowed.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		
		if ( !class_exists( $class ) )
		{
			eZDebug::writeError(
				'Tried to include a method call on a non-existing class.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		
		if ( $includeType == 'method-static' )
		{
			$call = array( $class, $method );
		}
		else
		{
			$call = array( new $class(), $method );
		}
		if ( !is_callable( $call ) )
		{
			eZDebug::writeError(
				'Tried to include a method call on a non-callable method.',
				'nxc_esi/include/'.$includeType
			);
			return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
		}
		
		header( 'X-NXC-ESI-Class: '.rawurlencode( $class ) );
		header( 'X-NXC-ESI-Method: '.rawurlencode( $method ) );
		
		$return = call_user_func( $call, $keys, $module );
		if ( is_array( $return ) )
		{
			$Result = $return;
			return;
		}
		if ( is_string( $return ) )
		{
			echo $return;
		}
		
		eZExecution::cleanExit();
	} break;
}

eZDebug::writeError(
	'Unknown include type: '.$includeType,
	'nxc_esi/include'
);
return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );

?>
