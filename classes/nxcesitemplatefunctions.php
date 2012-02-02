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
	
	/** Construct an instance of this class, using the given name for the
	 * template function handled by this object.
	 * @param string $esIncludeName The name to use for the es-include function.
	 */
	public function __construct( $esIncludeName = 'es-include' )
	{
		$this->ESIncludeName = $esIncludeName;
	}
	
	/** Get a list of the function names this object handles.
	 * Required for eZTemplate::registerFunctions.
	 * @return array List of function names handled by this object.
	 */
	public function functionList()
	{
		return array( $this->ESIncludeName );
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
		if ( $functionName != $this->ESIncludeName )
		{
			eZDebug::writeError(
				'Cannot process unknown template function: '.$functionName,
				__METHOD__
			);
			return false;
		}
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
