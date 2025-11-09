<?php
/**
 * Class Singleton
 *
 * @package wpct-plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

namespace WPCT_PLUGIN;

use Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Singleton abstract class.
 */
abstract class Singleton {


	/**
	 * Handle singleton instances map.
	 *
	 * @var object[]
	 */
	private static $instances = array();

	/**
	 * Controlled class contructor.
	 *
	 * @param boolean &$singleton Pointer to a boolean handler to be set as true by the constructor.
	 */
	public function __construct( &$singleton ) {
		$singleton = true;
	}

	/**
	 * Prevent class clonning.
	 */
	final public function __clone() {
	}

	/**
	 * Prevent class serialization.
	 *
	 * @throws Error Each time the method is called.
	 */
	final public function __wakeup() {
		throw new Error( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Abstract singleton class constructor.
	 *
	 * @param mixed[] ...$args Class constructor arguments.
	 */
	abstract protected function construct( ...$args );

	/**
	 * Get class instance.
	 *
	 * @return object $instance class instance
	 *
	 * @throws Error If no instance is found.
	 */
	final public static function get_instance() {
		$args = func_get_args();
		$cls  = static::class;

		if ( ! isset( self::$instances[ $cls ] ) ) {
			// Pass $singleton reference to prevent singleton classes constructor overwrites.
			self::$instances[ $cls ] = new static( $singleton );

			if ( ! $singleton ) {
				throw new Error( 'Cannot create uncontrolled instances from a singleton.' );
			}

			self::$instances[ $cls ]->construct( ...$args );
		}

		return self::$instances[ $cls ];
	}
}
