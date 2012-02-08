<?php
/** Definition of the nxcESIEAS class.
 * @author FA
 * @since 2012-02-08 20:20
 * @copyright Copyright (C) 2012 NXC International
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package nxcESI
 */

/** This class implements some functionality for the server side of the
 * {@link http://www.w3.org/TR/2001/NOTE-edge-arch-20010804 Edge Architecture
 * Specification 1.0}.
 * @package nxcESI
 */
class nxcESIEAS
{
	/** The capability string for the ESI version we support (currently 1.0).
	 */
	const CAPABILITY_ESI = 'ESI/1.0';
	
	/** This holds the list of surrogates and their capabilities, as parsed
	 * from the Surrogate-Capability request header.
	 * @see getSurrogates()
	 * @var array
	 */
	private static $surrogates = null;
	
	/** Check if the request identified a surrogate with ESI capability.
	 * @return bool Whether or not a surrogate with ESI capability was found.
	 */
	public static function hasESICapability()
	{
		return self::hasCapability( self::CAPABILITY_ESI );
	}
	
	/** Check if the request identified a surrogate with a given capability.
	 * @param string $capability The capability string to check for.
	 * @return bool Whether or not a surrogate with that capability was found.
	 */
	private static function hasCapability( $capability )
	{
		$surrogates = self::getSurrogates();
		foreach ( $surrogates as $surrogate )
		{
			if ( in_array( $capability, $surrogate['capabilities'] ) )
			{
				return true;
			}
		}
		return false;
	}
	
	/** Get the list of identified surrogates and their capabilities.
	 *
	 * This is based on the Surrogate-Capability header, but only includes the
	 * surrogates that followed the specification, ignoring any others.
	 * @return array The array of identified surrogates. Each element of this
	 * array is an associative array with the keys 'device-token' and
	 * 'capabilities', where 'capabilities' is an array of capability tokens
	 * for that device.
	 */
	private static function getSurrogates()
	{
		if ( is_array( self::$surrogates ) )
		{
			return self::$surrogates;
		}
		self::$surrogates = array();
		$header = self::getSurrogateCapabilityHeader();
		if ( is_string( $header ) && $header != '' )
		{
			$token = '[^\0-\037\0177()<>@,;:\\"\/[\]?={} \t]+';
			$matches = array();
			if (
				preg_match_all(
					'/(?:^|,)\s*('.$token.')="([^"]*)"\s*(?=,|$)/',
					$header, $matches, PREG_SET_ORDER
				) > 0
			) {
				foreach ( $matches as $match )
				{
					self::$surrogates[] = array(
						'device-token' => $match[1],
						'capabilities' => explode( ' ', $match[2] ),
					);
				}
			}
		}
		return self::$surrogates;
	}
	
	/** Find and return the Surrogate-Capability header, if it was provided.
	 * @return string|false The value of the header, or false if the header was
	 * not found.
	 */
	private static function getSurrogateCapabilityHeader()
	{
		if ( isset( $_SERVER['HTTP_SURROGATE_CAPABILITY'] ) )
		{
			return trim( $_SERVER['HTTP_SURROGATE_CAPABILITY'] );
		}
		elseif ( function_exists( 'apache_request_headers' ) )
		{
			$headers = @apache_request_headers();
			if ( is_array( $headers ) )
			{
				if ( isset( $headers['Surrogate-Capability'] ) )
				{
					return trim( $headers['Surrogate-Capability'] );
				}
				else
				{
					foreach ( $headers as $header => $value )
					{
						if ( strtolower( $header ) == 'surrogate-capability' )
						{
							return trim( $value );
						}
					}
				}
			}
		}
		return false;
	}
	
}

?>
