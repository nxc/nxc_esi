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
	
	/** The capability string for a surrogate supporting EAS that we can use.
	 */
	const CAPABILITY_SURROGATE = 'Surrogate/1.0';
	
	/** This holds the list of surrogates and their capabilities, as parsed
	 * from the Surrogate-Capability request header.
	 * @see getSurrogates()
	 * @var array
	 */
	private static $surrogates = null;
	
	/** This holds the current instance of this class, once instantiated.
	 * @see instance()
	 * @var nxcESIEAS|null
	 */
	private static $instance = null;
	
	/** Whether or not to use ESI for this request.
	 * @see setUseESI()
	 * @var bool
	 */
	private $useESI = false;
	
	/** The maximum age (aka TTL) of this request's response, in seconds.
	 *
	 * If null, the max age will not be set, so the HTTP headers are used.
	 * @see setMaxAge(), $freshnessExtension
	 * @var null|int Positive integer or zero, or null to not set it.
	 */
	private $maxAge = null;
	
	/** The freshness extension of this request's response, in seconds.
	 *
	 * This is only used if {@link $maxAge} is set.
	 * @see setMaxAge(), $maxAge
	 * @var int Positive integer or zero.
	 */
	private $freshnessExtension = 0;
	
	/** Whether or not to allow the surrogate to store this request's response.
	 * @see setNoStore()
	 * @var bool
	 */
	private $noStore = false;
	
	/** Whether or not to allow remote surrogates to store this response.
	 * @see setNoStoreRemote()
	 * @var bool
	 */
	private $noStoreRemote = false;
	
	/** Constructor.
	 * @see instance()
	 */
	private function __construct()
	{
	}
	
	/** Get the current instance of this class.
	 * @return nxcESIEAS
	 */
	public static function instance()
	{
		if ( !( self::$instance instanceof self ) )
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/** Restore an instance of this class to be the current instance.
	 *
	 * This should only be used if you <em>really</em> know what you're doing,
	 * as it messes with the internals of this class, and is meant for internal
	 * use only, to implement some functionality needed in the absence of ESI.
	 * @param nxcESIEAS|null $instance The instance to restore as the current
	 * instance, or null to reset the class so a new instance is created.
	 * @return nxcESIEAS|null|false The previous instance of this class, or
	 * null if no previous instance existed, or false on error.
	 * A returned instance should be treated as an opaque handle, not used for
	 * anything except later calls to this method, for internal state reasons.
	 * @see instance()
	 */
	public static function restoreInstance( $instance )
	{
		if ( $instance instanceof self )
		{
			$previousInstance = self::$instance;
			self::$instance = $instance;
			$instance->updateSurrogateControlHeader();
			if ( $previousInstance instanceof self )
			{
				return $previousInstance;
			}
			return null;
		}
		return false;
	}
	
	
	/** Set the flag that says whether this response should be ESI processed.
	 * @param bool $useESI Whether or not this response should use ESI.
	 */
	public static function setUseESI( $useESI = true )
	{
		$instance = self::instance();
		$instance->useESI = ( is_bool( $useESI ) ? $useESI : true );
		$instance->updateSurrogateControlHeader();
	}
	
	/** Set the maximum age and freshness extension for this response.
	 * @param int $maxAge The maximum age for this response, in seconds.
	 * @param int $freshnessExtension The freshness extension, in seconds.
	 */
	public static function setMaxAge( $maxAge, $freshnessExtension = 0 )
	{
		if ( !is_numeric( $maxAge ) || !is_numeric( $freshnessExtension ) )
		{
			eZDebug::writeWarning(
				'Non-numeric value given, not setting the max age.',
				__METHOD__
			);
			return;
		}
		if ( $maxAge < 0 || $freshnessExtension < 0 )
		{
			eZDebug::writeWarning(
				'Negative value given, not setting the max age.',
				__METHOD__
			);
			return;
		}
		$instance = self::instance();
		$instance->maxAge = intval( $maxAge );
		$instance->freshnessExtension = intval( $freshnessExtension );
		$instance->updateSurrogateControlHeader();
	}
	
	/** Set whether or not this response should <em>NOT</em> be stored in a
	 * surrogate's cache.
	 * @param bool $noStore If true, the response is not stored in the cache
	 * of a surrogate, while false means that it can be cached.
	 * @see setNoStoreRemote()
	 */
	public static function setNoStore( $noStore = true )
	{
		$instance = self::instance();
		$instance->noStore = ( is_bool( $noStore ) ? $noStore : true );
		$instance->updateSurrogateControlHeader();
	}
	
	/** Set whether or not this response should <em>NOT</em> be stored in a
	 * <em>remote</em> surrogate's cache.
	 * @param bool $noStore If true, the response is not stored in the cache
	 * of a remote surrogate, while false means that it can be cached.
	 * @see setNoStore()
	 */
	public static function setNoStoreRemote( $noStore = true )
	{
		$instance = self::instance();
		$instance->noStoreRemote = ( is_bool( $noStore ) ? $noStore : true );
		$instance->updateSurrogateControlHeader();
	}
	
	/** Update the Surrogate-Control header with the current settings.
	 */
	private function updateSurrogateControlHeader()
	{
		if ( !self::hasCapability( self::CAPABILITY_SURROGATE ) )
		{
			// There's no supported surrogate for this request, so don't set
			// the Surrogate-Control header to anything.
			return;
		}
		$directives = array();
		if ( $this->noStore )
		{
			$directives[] = 'no-store';
			$directives[] = 'max-age=0';
		}
		elseif ( is_numeric( $this->maxAge ) && $this->maxAge >= 0 )
		{
			if ( $this->freshnessExtension > 0 )
			{
				$directives[] =
					'max-age='.intval( $this->maxAge )
					.'+'.intval( $this->freshnessExtension )
				;
			}
			else
			{
				$directives[] = 'max-age='.intval( $this->maxAge );
			}
		}
		if ( $this->noStoreRemote )
		{
			$directives[] = 'no-store-remote';
		}
		if ( $this->useESI )
		{
			$directives[] = 'content="ESI/1.0"';
		}
		header( 'Surrogate-Control: '.implode( ', ', $directives ) );
	}
	
	
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
