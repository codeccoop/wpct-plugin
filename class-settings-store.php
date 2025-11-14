<?php
/**
 * Class Settings_Store
 *
 * @package wpct-plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

namespace WPCT_PLUGIN;

use ArgumentCountError;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

require_once 'class-singleton.php';
require_once 'class-setting.php';
require_once 'class-rest-settings-controller.php';
require_once 'class-undefined.php';
require_once 'json-schema-utils.php';

/**
 * Settings store base class.
 */
class Settings_Store extends Singleton {


	/**
	 * Handle plugin settings rest controller class name.
	 *
	 * @var string
	 */
	const REST_CONTROLLER = '\WPCT_PLUGIN\REST_Settings_Controller';

	/**
	 * Handle settings' group name.
	 *
	 * @var string
	 */
	private $group;

	/**
	 * Handle settings instanes store.
	 *
	 * @var array
	 */
	private $store = array();

	/**
	 * Add a new setting to the store.
	 *
	 * @param string  $setting_name Setting name.
	 * @param Setting $setting Setting instance.
	 */
	private static function store_setting( $setting_name, $setting ) {
		static::get_instance()->store[ $setting_name ] = $setting;
	}

	/**
	 * Proxy to the store settings `use_setter` hooks.
	 *
	 * @param string   $name Setting name.
	 * @param callable $setter Callback function.
	 * @param integer  $priority Priority of the callabck in the setters chain.
	 */
	final public static function use_setter( $name, $setter, $priority = 10 ) {
		$setting = static::setting( $name );
		if ( $setting ) {
			$setting->use_setter( $setter, $priority );
		}
	}

	/**
	 * Proxy to the store settings `use_getter` hooks.
	 *
	 * @param string   $name Setting name.
	 * @param callable $getter Callback function.
	 * @param integer  $priority Priority of the callback in the getters chain.
	 */
	final public static function use_getter( $name, $getter, $priority = 10 ) {
		$setting = static::setting( $name );
		if ( $setting ) {
			$setting->use_getter( $getter, $priority );
		}
	}

	/**
	 * Proxy to the store settings `use_cleaner` hooks.
	 *
	 * @param string   $name Setting name.
	 * @param callable $cleaner Callback function.
	 * @param integer  $priority Priority of the cleaner in the cleaners chain.
	 */
	final public static function use_cleaner(
		$name,
		$cleaner,
		$priority = 10
	) {
		$setting = static::setting( $name );
		if ( $setting ) {
			$setting->use_cleaner( $cleaner, $priority );
		}
	}

	/**
	 * Register a setting in the store.
	 *
	 * @param array|callable $setting Setting's schema or a filter function. The function will be
	 *                                called with the array of registered settings and should return
	 *                                a new array of settings as its output.
	 */
	final public static function register_setting( $setting ) {
		add_filter(
			'wpct_plugin_register_settings',
			static function ( $settings, $group ) use ( $setting ) {
				if ( static::group() === $group ) {
					if ( is_array( $setting ) ) {
						$settings[] = $setting;
					} elseif ( is_callable( $setting ) ) {
						$filter   = $setting;
						$settings = $filter( $settings );
					}
				}

				return $settings;
			},
			10,
			2
		);
	}

	/**
	 * Hook to execute callbacks once the settings are registered
	 * and the store is ready.
	 *
	 * @param callable $callback Callback to be executed.
	 */
	final public static function ready( $callback ) {
		if ( ! is_callable( $callback ) ) {
			return;
		}

		add_filter(
			'wpct_plugin_registered_settings',
			static function ( $settings, $group, $store ) use ( $callback ) {
				if ( static::group() === $group ) {
					$callback( $store, $group );
				}
			},
			10,
			3
		);
	}

	/**
	 * Store the store group and set up init hook callbacks.
	 *
	 * @param aryay{0: string} ...$args Array with setting's group name in its first position.
	 *
	 * @throws ArgumentCountError If no group argument.
	 */
	protected function construct( ...$args ) {
		if ( empty( $args ) ) {
			throw new ArgumentCountError( 'Too few arguments to Settigs Store constructor' );
		}

		$this->group = $args[0];

		$rest_controller_class = static::REST_CONTROLLER;
		$rest_controller_class::setup( $this->group );

		add_action(
			'init',
			function () {
				$settings = static::register_settings();

				do_action(
					'wpct_plugin_registered_settings',
					$settings,
					$this->group,
					$this
				);
			},
			10,
			5
		);
	}

	/**
	 * Group name getter.
	 *
	 * @return string $group_name settings group name
	 */
	final public static function group() {
		return static::get_instance()->group;
	}

	/**
	 * Store data getter.
	 *
	 * @return array<string, Setting> Array with store's settings instances.
	 */
	final public static function store() {
		return static::get_instance()->store ?: array();
	}

	/**
	 * Instance's store settings collection getter.
	 *
	 * @return Setting[]
	 */
	final public static function settings() {
		return array_values( static::store() );
	}

	/**
	 * Store settings getter.
	 *
	 * @param string $name Setting name.
	 *
	 * @return Setting|null
	 */
	final public static function setting( $name ) {
		$store = static::store();

		if ( empty( $store ) ) {
			return;
		}

		return $store[ $name ] ?? null;
	}

	/**
	 * Registers a setting and its fields.
	 *
	 * @return array list with setting instances
	 */
	private static function register_settings() {
		$group = static::group();

		$schemas = apply_filters(
			'wpct_plugin_register_settings',
			array(),
			$group
		);

		$settings = array();

		foreach ( $schemas as $schema ) {
			if ( ! is_array( $schema ) || ! is_string( $schema['name'] ?? null ) ) {
				continue;
			}

			$name = $schema['name'];

			$setting = static::setting( $name );
			if ( $setting ) {
				$settings[] = $setting;
				continue;
			}

			if (
				isset( $schema['properties'] ) &&
				is_array( $schema['properties'] )
			) {
				$default_required = array_keys( $schema['properties'] );
			}

			$schema = array_merge(
				array(
					'$id'                  => $group . '_' . $name,
					'$schema'              => 'http://json-schema.org/draft-04/schema#',
					'title'                => "Setting {$name} of {$group}",
					'type'                 => 'object',
					'properties'           => array(),
					'required'             => $default_required ?? array(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				$schema
			);

			$default = is_array( $schema['default'] )
				? $schema['default']
				: array();

			foreach ( $default as $prop => $value ) {
				if ( isset( $schema['properties'][ $prop ] ) ) {
					$schema['properties'][ $prop ]['default'] = $value;
				}
			}

			$setting = new Setting( $group, $name, $default, $schema );
			static::store_setting( $name, $setting );

			$settings[] = $setting;
		}

		return $settings;
	}
}
